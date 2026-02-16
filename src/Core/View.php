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
 * View
 *
 * Handles the rendering of HTML templates and HTTP redirects.
 */
class View
{
    /**
     * Renders a view template.
     *
     * Extracts the provided data array into variables and includes the template file.
     *
     * @param string $view The name of the view file (relative to templates/, without .php).
     * @param array  $data Associative array of data to make available to the view.
     * @return void
     * @throws \Exception If the view file does not exist.
     */
    public static function render($view, $data = [])
    {
        extract($data);
        $file = __DIR__ . '/../../templates/' . $view . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            throw new \Exception("View $view not found");
        }
    }

    /**
     * Redirects the user to a specific path or URL.
     *
     * If the path is relative, it is prepended with the BASE_URL.
     *
     * @param string $path The destination path or URL.
     * @return void
     */
    public static function redirect($path)
    {
        if (strpos($path, 'http') !== 0) {
            $path = Config::get('BASE_URL') . $path;
        }
        header('Location: ' . $path);
        exit;
    }
}
