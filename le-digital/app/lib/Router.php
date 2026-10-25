<?php
declare(strict_types=1);

namespace LD;

/**
 * Routeur minimaliste : « /blog/{slug} » → paramètres nommés.
 */
final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add(['POST'], $pattern, $handler);
    }

    public function any(string $pattern, callable $handler): void
    {
        $this->add(['GET', 'HEAD', 'POST'], $pattern, $handler);
    }

    private function add(array $methods, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($pattern, '/') ?: '/') . '$#u';
        $this->routes[] = [$methods, $regex, $handler];
    }

    public function dispatch(string $method, string $path): bool
    {
        foreach ($this->routes as [$methods, $regex, $handler]) {
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            if (!in_array($method, $methods, true)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler(...array_values($params));
            return true;
        }
        return false;
    }
}
