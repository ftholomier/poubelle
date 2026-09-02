<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Routeur minimaliste : des motifs avec paramètres nommés entre accolades.
 * Exemple : '/api/shape/{id}' capture « hero » depuis /api/shape/hero.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, keys: list<string>, handler: callable}> */
    private array $routes = [];

    /** @var callable|null */
    private $fallback = null;

    public function get(string $pattern, callable $handler): self
    {
        return $this->map('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->map('POST', $pattern, $handler);
    }

    public function map(string $method, string $pattern, callable $handler): self
    {
        $keys = [];
        $regex = preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '([^/]+)';
            },
            $pattern
        );

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];

        return $this;
    }

    public function fallback(callable $handler): self
    {
        $this->fallback = $handler;

        return $this;
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        $method = strtoupper($method);
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            // HEAD est servi par le gestionnaire GET, le corps est ignoré en amont.
            if ($route['method'] !== $method && !($route['method'] === 'GET' && $method === 'HEAD')) {
                continue;
            }

            array_shift($matches);
            $params = $route['keys'] === [] ? [] : array_combine($route['keys'], $matches);
            ($route['handler'])($params ?: []);

            return;
        }

        if ($pathMatched) {
            if (!headers_sent()) {
                header('Allow: GET');
            }
            Response::error('Méthode non autorisée sur ' . $path, 405);

            return;
        }

        if ($this->fallback !== null) {
            ($this->fallback)(['path' => $path]);

            return;
        }

        Response::error('Route inconnue : ' . $path, 404);
    }
}
