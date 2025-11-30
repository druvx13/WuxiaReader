<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * User
 *
 * Represents a user in the system.
 */
class User
{
    /**
     * Finds a user by their ID.
     *
     * @param int $id The ID of the user.
     * @return array|null The user data (id, username, role) or null if not found.
     */
    public static function find($id)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Finds a user by their username.
     *
     * @param string $username The username to search for.
     * @return array|false The user data including password hash, or false if not found.
     */
    public static function findByUsername($username)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    /**
     * Creates a new user.
     *
     * @param string $username The username for the new user.
     * @param string $hash     The hashed password.
     * @return string|false The ID of the created user or false on failure.
     */
    public static function create($username, $hash)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'reader')");
        $stmt->execute([$username, $hash]);
        return $pdo->lastInsertId();
    }
}
