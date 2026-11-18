<?php

declare(strict_types=1);

namespace App\Routing;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class Router
{
    private array $routes = [];

    private Closure $controllerResolver;

    private Closure $middlewareResolver;

    public function __construct(callable $controllerResolver, callable $middlewareResolver)
    {
        $this->controllerResolver = Closure::fromCallable($controllerResolver);
        $this->middlewareResolver = Closure::fromCallable($middlewareResolver);
    }

    public function registerController(string $controllerClass): void
    {
        if (!class_exists($controllerClass)) {
            throw new RuntimeException("Controller not found: {$controllerClass}");
        }

        $reflection = new ReflectionClass($controllerClass);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $this->addRoute(
                    $route->method,
                    $route->path,
                    $controllerClass,
                    $method->getName(),
                    $route->middleware
                );
            }
        }
    }

    public function addRoute(
        string $method,
        string $path,
        string $controller,
        string $action,
        array $middleware = []
    ): void {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        if (!in_array($method, $allowedMethods, true)) {
            throw new RuntimeException("Unsupported HTTP method: {$method}");
        }

        foreach ($this->routes as $existingRoute) {
            if ($existingRoute['method'] === $method && $existingRoute['path'] === $path) {
                throw new RuntimeException("Duplicate route: {$method} {$path}");
            }
        }

        foreach ($middleware as $definition) {
            if (
                !is_array($definition)
                || !isset($definition[0])
                || !is_string($definition[0])
            ) {
                throw new RuntimeException('Invalid middleware definition.');
            }
        }

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->compilePath($path),
            'controller' => $controller,
            'action' => $action,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);
        $path = $this->normalizePath(is_string($path) ? $path : '/');
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            $allowedMethods[] = $route['method'];
            if ($route['method'] !== $method) {
                continue;
            }

            $parameters = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[] = $value;
                }
            }

            foreach ($route['middleware'] as $definition) {
                $middlewareClass = $definition[0];
                $middleware = ($this->middlewareResolver)($middlewareClass);

                if (!is_object($middleware) || !is_callable([$middleware, 'handle'])) {
                    throw new RuntimeException(
                        "Middleware must have a handle() method: {$middlewareClass}"
                    );
                }

                $allowed = $middleware->handle(...array_slice($definition, 1));
                if ($allowed !== true) {
                    return;
                }
            }

            $controller = ($this->controllerResolver)($route['controller']);
            if (!is_object($controller) || !is_callable([$controller, $route['action']])) {
                throw new RuntimeException(
                    "Controller action not found: {$route['action']}"
                );
            }

            $controller->{$route['action']}(...$parameters);

            return;
        }

        if ($allowedMethods !== []) {
            header('Allow: '.implode(', ', array_unique($allowedMethods)));
            $this->jsonResponse(405, 'Method not allowed.');

            return;
        }

        $this->jsonResponse(404, 'Route not found.');
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/'.trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    private function compilePath(string $path): string
    {
        if ($path === '/') {
            return '#^/?$#';
        }

        $segments = explode('/', trim($path, '/'));
        foreach ($segments as &$segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $matches) === 1) {
                $segment = '(?P<'.$matches[1].'>[^/]+)';
            } else {
                $segment = preg_quote($segment, '#');
            }
        }
        unset($segment);

        return '#^/'.implode('/', $segments).'/?$#';
    }

    private function jsonResponse(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ], JSON_THROW_ON_ERROR);
    }
}
