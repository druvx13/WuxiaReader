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
 * Comment
 *
 * Represents a user comment on a novel or a chapter.
 */
class Comment
{
    /**
     * Finds comments associated with a specific novel (not a specific chapter).
     *
     * @param int $novelId The ID of the novel.
     * @return array List of comments with user information.
     */
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

    /**
     * Finds comments associated with a specific chapter.
     *
     * @param int $chapterId The ID of the chapter.
     * @return array List of comments with user information.
     */
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

    /**
     * Creates a new comment.
     *
     * @param int         $userId    The ID of the user creating the comment.
     * @param string      $text      The content of the comment.
     * @param int|null    $novelId   The ID of the novel being commented on (optional).
     * @param int|null    $chapterId The ID of the chapter being commented on (optional).
     * @return string|false The ID of the created comment or false on failure.
     */
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
