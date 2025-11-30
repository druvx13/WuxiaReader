<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    public static function find($id)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUsername($username)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public static function create($username, $hash)
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'reader')");
        $stmt->execute([$username, $hash]);
        return $pdo->lastInsertId();
    }
}
