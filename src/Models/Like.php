<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Like
 *
 * Manages user likes (upvotes) for novels and chapters.
 */
class Like
{
    /**
     * Counts the total number of likes for a target.
     *
     * @param string $targetType The type of the target ('novel' or 'chapter').
     * @param int    $targetId   The ID of the target.
     * @return int The total count of likes.
     */
    public static function count($targetType, $targetId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM likes WHERE target_type = ? AND target_id = ?");
        $stmt->execute([$targetType, $targetId]);
        return (int)$stmt->fetch()['c'];
    }

    /**
     * Checks if a user has liked a specific target.
     *
     * @param string   $targetType The type of the target ('novel' or 'chapter').
     * @param int      $targetId   The ID of the target.
     * @param int|null $userId     The ID of the user (or null if not logged in).
     * @return bool True if the user has liked the target, false otherwise.
     */
    public static function isLikedByUser($targetType, $targetId, $userId)
    {
        if (!$userId) return false;
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE target_type=? AND target_id=? AND user_id=?");
        $stmt->execute([$targetType, $targetId, $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Toggles the like status for a user and target.
     *
     * If the user has already liked the target, the like is removed.
     * If not, the like is added.
     * Operations are performed within a transaction to ensure consistency.
     *
     * @param int    $userId     The ID of the user.
     * @param string $targetType The type of the target ('novel' or 'chapter').
     * @param int    $targetId   The ID of the target.
     * @return array An associative array with 'liked' (bool) and 'count' (int).
     * @throws \Exception If the database operation fails.
     */
    public static function toggle($userId, $targetType, $targetId)
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM likes WHERE target_type = ? AND target_id = ? AND user_id = ?");
            $stmt->execute([$targetType, $targetId, $userId]);
            $like = $stmt->fetch();

            $liked = false;
            if ($like) {
                $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
                $stmt->execute([$like['id']]);
                $liked = false;
            } else {
                $stmt = $pdo->prepare("INSERT INTO likes (target_type, target_id, user_id) VALUES (?, ?, ?)");
                $stmt->execute([$targetType, $targetId, $userId]);
                $liked = true;
            }

            $count = self::count($targetType, $targetId);

            $pdo->commit();
            return ['liked' => $liked, 'count' => $count];
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
