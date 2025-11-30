<?php

namespace App\Core;

class Router
{
    private $routes = [];

    public function add($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path, // Can be regex
            'handler' => $handler
        ];
    }

    public function dispatch($method, $uri)
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            $pattern = $route['path'];
            // If it's a regex path (starts with # or / and looks like regex)
            if (strpos($pattern, '#') === 0) {
                 if (preg_match($pattern, $uri, $matches)) {
                     array_shift($matches); // Remove full match
                     return $this->callHandler($route['handler'], $matches);
                 }
            } else {
                // Exact match
                if ($pattern === $uri) {
                    return $this->callHandler($route['handler'], []);
                }
            }
        }

        http_response_code(404);
        require_once __DIR__ . '/../Views/404.php';
    }

    private function callHandler($handler, $params)
    {
        if (is_array($handler)) {
            $controllerName = $handler[0];
            $method = $handler[1];
            $controller = new $controllerName();
            return call_user_func_array([$controller, $method], $params);
        }
        return call_user_func_array($handler, $params);
    }
}
