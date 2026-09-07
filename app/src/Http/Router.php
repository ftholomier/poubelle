<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Routeur minimaliste : motifs avec paramètres {nom}.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:callable|array,params:array<int,string>}> */
    private array $routes = [];
    /** @var callable|array|null */
    private $fallback = null;

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, callable|array $handler): void
    {
        $this->add('ANY', $pattern, $handler);
    }

    public function fallback(callable|array $handler): void
    {
        $this->fallback = $handler;
    }

    private function add(string $method, string $pattern, callable|array $handler): void
    {
        $params = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern
        ) ?? $pattern;

        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'regex'   => '#^' . $regex . '$#u',
            'handler' => $handler,
            'params'  => $params,
        ];
    }

    public function dispatch(Request $request): mixed
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $request->method) {
                continue;
            }
            if (preg_match($route['regex'], $request->path, $matches)) {
                array_shift($matches);
                $args = [];
                foreach ($route['params'] as $index => $name) {
                    $args[$name] = $matches[$index] ?? null;
                }
                return self::call($route['handler'], $request, $args);
            }
        }
        if ($this->fallback !== null) {
            return self::call($this->fallback, $request, []);
        }
        Response::notFound();
        return null;
    }

    /** @param callable|array $handler */
    private static function call($handler, Request $request, array $args): mixed
    {
        if (is_array($handler) && !is_callable($handler)) {
            throw new \RuntimeException('Route non résolue : ' . implode('::', array_map('strval', $handler)));
        }
        return $handler($request, $args);
    }
}
