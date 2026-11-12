<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:callable|array, guard:?string}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, ?string $guard = null): void
    {
        $this->add('GET', $pattern, $handler, $guard);
    }

    public function post(string $pattern, callable|array $handler, ?string $guard = null): void
    {
        $this->add('POST', $pattern, $handler, $guard);
    }

    /**
     * @param string|null $guard 'auth' — только для вошедших, 'guest' — только для гостей
     */
    public function add(string $method, string $pattern, callable|array $handler, ?string $guard = null): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'guard' => $guard];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method) === 'HEAD' ? 'GET' : strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            if ($method === 'POST' && !Csrf::verify()) {
                Response::error(419);
                return;
            }

            if ($route['guard'] === 'auth' && !Auth::check()) {
                if (Request::wantsJson()) {
                    Response::json(['ok' => false, 'error' => 'Требуется вход'], 401);
                    return;
                }
                Session::flash('info', 'Войдите, чтобы продолжить.');
                redirect('/login');
            }
            if ($route['guard'] === 'guest' && Auth::check()) {
                redirect('/tasks');
            }

            $params = array_map('intval', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $handler = [new $class(), $action];
            }
            $handler(...$params);
            return;
        }

        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            Response::error(405);
            return;
        }
        Response::error(404);
    }
}
