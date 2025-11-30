<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Database
 *
 * Singleton class for managing the database connection using PDO.
 */
class Database
{
    /**
     * @var PDO|null The single PDO instance.
     */
    private static $pdo;

    /**
     * Connects to the database.
     *
     * Establishes a PDO connection if one does not already exist.
     * Uses configuration values for host, database name, user, and password.
     *
     * @return PDO The active PDO connection.
     * @throws PDOException If the connection fails.
     */
    public static function connect()
    {
        if (self::$pdo === null) {
            $host = Config::get('DB_HOST');
            $db   = Config::get('DB_NAME');
            $user = Config::get('DB_USER');
            $pass = Config::get('DB_PASS');
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // In production, log this error instead of showing it
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        return self::$pdo;
    }
}
