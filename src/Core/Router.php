<?php

namespace App\Core;

/**
 * Router
 *
 * Handles URL routing and dispatching requests to the appropriate controllers.
 */
class Router
{
    /**
     * @var array List of registered routes.
     */
    private $routes = [];

    /**
     * Adds a route to the router.
     *
     * @param string          $method  The HTTP method (GET, POST, etc.) or 'ANY'.
     * @param string          $path    The URL path pattern (exact match or regex).
     * @param callable|array  $handler The function or [Controller, Method] to call.
     * @return void
     */
    public function add($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path, // Can be regex
            'handler' => $handler
        ];
    }

    /**
     * Dispatches the request to the matching route handler.
     *
     * Matches the current URI and HTTP method against registered routes.
     * If a match is found, executes the handler. If not, sends a 404 response.
     *
     * @param string $method The HTTP method of the current request.
     * @param string $uri    The URI of the current request.
     * @return mixed The result of the handler execution.
     */
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

    /**
     * Calls the specified handler with parameters.
     *
     * Instantiates the controller if the handler is an array [Controller, Method].
     *
     * @param callable|array $handler The handler to execute.
     * @param array          $params  Parameters to pass to the handler (e.g., regex matches).
     * @return mixed The result of the handler.
     */
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
