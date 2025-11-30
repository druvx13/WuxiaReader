<?php

namespace App\Core;

/**
 * Config
 *
 * Manages configuration settings loaded from a .env file.
 */
class Config
{
    /**
     * @var array Holds the configuration key-value pairs.
     */
    private static $config = [];

    /**
     * Loads configuration from a file.
     *
     * Parses the file line by line, ignoring comments and empty lines.
     * Populates the internal config array.
     *
     * @param string $path The path to the configuration file (e.g., .env).
     * @return void
     */
    public static function load($path)
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            self::$config[$name] = $value;
        }
    }

    /**
     * Retrieves a configuration value.
     *
     * @param string $key     The configuration key.
     * @param mixed  $default The default value to return if the key is not found.
     * @return mixed The configuration value or the default.
     */
    public static function get($key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }
}
