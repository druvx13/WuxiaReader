<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Novel
 *
 * Represents a novel in the library.
 */
class Novel
{
    /**
     * Retrieves all novels from the database.
     *
     * Includes the count of chapters for each novel.
     * Ordered by creation date descending.
     *
     * @return array List of novels with chapter counts.
     */
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

    /**
     * Finds a novel by its ID.
     *
     * @param int $id The ID of the novel.
     * @return array|false The novel data or false if not found.
     */
    public static function find($id)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM novels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Creates a new novel.
     *
     * @param string $title       The title of the novel.
     * @param string $cover_url   The URL of the cover image.
     * @param string $description The description/summary of the novel.
     * @param string $author      The author of the novel.
     * @param string $tags        Comma-separated tags for the novel.
     * @return string|false The ID of the created novel or false on failure.
     */
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
