<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Like
{
    public static function count($targetType, $targetId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM likes WHERE target_type = ? AND target_id = ?");
        $stmt->execute([$targetType, $targetId]);
        return (int)$stmt->fetch()['c'];
    }

    public static function isLikedByUser($targetType, $targetId, $userId)
    {
        if (!$userId) return false;
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id FROM likes WHERE target_type=? AND target_id=? AND user_id=?");
        $stmt->execute([$targetType, $targetId, $userId]);
        return (bool)$stmt->fetch();
    }

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
