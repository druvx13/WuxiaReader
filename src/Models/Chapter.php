<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Chapter
{
    public static function findByNovelId($novelId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, title, order_index FROM chapters WHERE novel_id = ? ORDER BY order_index ASC");
        $stmt->execute([$novelId]);
        return $stmt->fetchAll();
    }

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

    public static function getNextOrderIndex($novelId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 AS next_idx FROM chapters WHERE novel_id = ?");
        $stmt->execute([$novelId]);
        return (int)$stmt->fetch()['next_idx'];
    }

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
