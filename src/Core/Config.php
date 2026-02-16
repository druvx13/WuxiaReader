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
