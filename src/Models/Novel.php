<?php
/*
 * Copyright (C) 2026 Druvx13
 *
 * This Work is licensed under the FFP (Freedom For People) License,
 * Version 1.0.
 *
 * A copy of this License must be included in the LICENSE file distributed
 * with this Work.
 *
 * You may also obtain a copy of the License at:
 * https://github.com/druvx13/FFP/blob/main/LICENSE
 *
 * THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED.
 */

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
     * @param string      $title       The title of the novel.
     * @param string|null $cover_url   The cover image as a data URL or external URL, or null.
     * @param string      $description The description/summary of the novel.
     * @param string      $author      The author of the novel.
     * @param string      $tags        Comma-separated tags for the novel.
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

    /**
     * Updates the cover URL for a novel.
     *
     * @param int         $id       The ID of the novel.
     * @param string|null $coverUrl The new cover value (data URL, external URL, or null).
     * @return bool True if the row was found and updated; false otherwise.
     */
    public static function updateCoverUrl($id, $coverUrl): bool
    {
        $pdo  = Database::connect();
        $stmt = $pdo->prepare("UPDATE novels SET cover_url = ? WHERE id = ?");
        $stmt->execute([$coverUrl, $id]);
        return $stmt->rowCount() > 0;
    }
}
