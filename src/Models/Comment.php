<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Comment
{
    public static function findByNovel($novelId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT c.text, c.created_at, u.username
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.novel_id = ? AND c.chapter_id IS NULL
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$novelId]);
        return $stmt->fetchAll();
    }

    public static function findByChapter($chapterId)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            SELECT c.text, c.created_at, u.username
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.chapter_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$chapterId]);
        return $stmt->fetchAll();
    }

    public static function create($userId, $text, $novelId = null, $chapterId = null)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO comments (novel_id, chapter_id, user_id, text)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $novelId,
            $chapterId,
            $userId,
            $text
        ]);
        return $pdo->lastInsertId();
    }
}
