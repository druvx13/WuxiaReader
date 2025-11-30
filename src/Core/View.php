<?php

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
