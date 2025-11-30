<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Chapter
 *
 * Represents a chapter of a novel. Provides methods for retrieving, creating,
 * and navigating chapters.
 */
class Chapter
{
    /**
     * Finds all chapters for a specific novel.
     *
     * @param int $novelId The ID of the novel.
     * @return array List of chapters (id, title, order_index) ordered by index.
     */
    public static function findByNovelId($novelId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, title, order_index FROM chapters WHERE novel_id = ? ORDER BY order_index ASC");
        $stmt->execute([$novelId]);
        return $stmt->fetchAll();
    }

    /**
     * Finds a chapter by its ID.
     *
     * Also retrieves the associated novel title and ID.
     *
     * @param int $id The ID of the chapter.
     * @return array|false The chapter data or false if not found.
     */
    public static function find($id)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT ch.*, n.title AS novel_title, n.id AS novel_id
            FROM chapters ch
            JOIN novels n ON ch.novel_id = n.id
            WHERE ch.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Calculates the next available order index for a novel's chapters.
     *
     * @param int $novelId The ID of the novel.
     * @return int The next order index (max index + 1).
     */
    public static function getNextOrderIndex($novelId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 AS next_idx FROM chapters WHERE novel_id = ?");
        $stmt->execute([$novelId]);
        return (int)$stmt->fetch()['next_idx'];
    }

    /**
     * Creates a new chapter.
     *
     * @param int    $novelId    The ID of the novel.
     * @param string $title      The title of the chapter.
     * @param string $content    The content of the chapter.
     * @param int    $orderIndex The order index of the chapter.
     * @return string|false The ID of the created chapter or false on failure.
     */
    public static function create($novelId, $title, $content, $orderIndex)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO chapters (novel_id, title, content, order_index)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$novelId, $title, $content, $orderIndex]);
        return $pdo->lastInsertId();
    }

    /**
     * Finds the previous chapter in the sequence.
     *
     * @param int $novelId           The ID of the novel.
     * @param int $currentOrderIndex The order index of the current chapter.
     * @return array|false The previous chapter data (id, title, order_index) or false if none exists.
     */
    public static function findPrevious($novelId, $currentOrderIndex)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, title, order_index
            FROM chapters
            WHERE novel_id = ? AND order_index < ?
            ORDER BY order_index DESC
            LIMIT 1
        ");
        $stmt->execute([$novelId, $currentOrderIndex]);
        return $stmt->fetch();
    }

    /**
     * Finds the next chapter in the sequence.
     *
     * @param int $novelId           The ID of the novel.
     * @param int $currentOrderIndex The order index of the current chapter.
     * @return array|false The next chapter data (id, title, order_index) or false if none exists.
     */
    public static function findNext($novelId, $currentOrderIndex)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT id, title, order_index
            FROM chapters
            WHERE novel_id = ? AND order_index > ?
            ORDER BY order_index ASC
            LIMIT 1
        ");
        $stmt->execute([$novelId, $currentOrderIndex]);
        return $stmt->fetch();
    }
}
