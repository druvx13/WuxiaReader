<?php

namespace App\Core;

class View
{
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

    public static function redirect($path)
    {
        if (strpos($path, 'http') !== 0) {
            $path = Config::get('BASE_URL') . $path;
        }
        header('Location: ' . $path);
        exit;
    }
}
