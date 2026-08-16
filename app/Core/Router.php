<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal : motifs avec paramètres nommés {slug}.
 * Les routes sont déclarées dans app/routes.php.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, keys:string[], handler:callable}> */
    private array $routes = [];

    /** @var null|callable(): mixed */
    private $notFound = null;

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function fallback(callable $handler): void
    {
        $this->notFound = $handler;
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $keys = [];
        $regex = preg_replace_callback(
            '#\{([a-z_][a-z0-9_]*)\}#i',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '([^/]+)';
            },
            rtrim($pattern, '/') ?: '/'
        );

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    /**
     * Résout l'URI courante et exécute le handler correspondant.
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            array_shift($m);
            $params = $route['keys'] ? array_combine($route['keys'], $m) : [];

            return ($route['handler'])($params);
        }

        http_response_code(404);
        return $this->notFound ? ($this->notFound)() : '404';
    }
}
