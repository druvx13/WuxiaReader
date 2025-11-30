<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Novel
{
    public static function findAll()
    {
        $pdo = Database::connect();
        return $pdo->query("
            SELECT n.*,
                (SELECT COUNT(*) FROM chapters c WHERE c.novel_id = n.id) AS chapter_count
            FROM novels n
            ORDER BY n.created_at DESC
        ")->fetchAll();
    }

    public static function find($id)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM novels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create($title, $cover_url, $description, $author, $tags)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO novels (title, cover_url, description, author, tags)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title,
            $cover_url,
            $description,
            $author,
            $tags
        ]);
        return $pdo->lastInsertId();
    }
}
