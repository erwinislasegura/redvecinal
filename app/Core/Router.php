<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
    }

    public function post(string $path, array|callable $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    private function add(string $method, string $path, array|callable $action, array $middleware): void
    {
        $this->routes[$method][] = [$this->normalize($path), $action, $middleware];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');
        $basePath = trim((string) parse_url((string) config('base_url', ''), PHP_URL_PATH), '/');
        if ($basePath && str_starts_with(trim($path, '/'), $basePath)) {
            $path = $this->normalize(substr(trim($path, '/'), strlen($basePath)));
        }

        foreach ($this->routes[$method] ?? [] as [$route, $action, $middleware]) {
            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route);
            if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->runMiddleware($middleware);
            if (is_callable($action)) {
                $action(...array_values($params));
                return;
            }
            [$class, $methodName] = $action;
            (new $class())->{$methodName}(...array_values($params));
            return;
        }

        http_response_code(404);
        require BASE_PATH . '/app/Views/errors/404.php';
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $rule) {
            if ($rule === 'auth') {
                Auth::requireLogin();
            } elseif ($rule === 'guest' && Auth::check()) {
                header('Location: ' . url('panel'));
                exit;
            } elseif (str_starts_with($rule, 'permission:')) {
                Auth::requirePermission(substr($rule, 11));
            }
        }
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}

