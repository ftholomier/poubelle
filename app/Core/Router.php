<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur maison. Les motifs acceptent `{param}` (un segment) et `{param:.*}` (le reste).
 * Les routes publiques sont montées une fois par langue via addLocalized().
 */
final class Router
{
    /** @var array<int, array{method:string,regex:string,keys:string[],handler:callable|array}> */
    private array $routes = [];

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
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
    }

    /** Monte la route sous `/{lang}/...` pour chacune des langues servies. */
    public function addLocalized(string $method, string $pattern, callable|array $handler): void
    {
        foreach (array_keys((array) Config::get('i18n.languages', [])) as $lang) {
            $suffix = $pattern === '/' ? '' : $pattern;
            $this->add($method, '/' . $lang . $suffix, $handler, ['lang' => $lang]);
        }
    }

    private function add(string $method, string $pattern, callable|array $handler, array $defaults = []): void
    {
        $keys = [];
        $regex = preg_replace_callback(
            '/\{([a-z_]+)(?::([^}]+))?\}/',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern,
        );

        $this->routes[] = [
            'method'   => $method,
            'regex'    => '#^' . $regex . '$#u',
            'keys'     => $keys,
            'handler'  => $handler,
            'defaults' => $defaults,
        ];
    }

    /** @return array{0:callable|array,1:array}|null */
    public function match(string $method, string $path): ?array
    {
        $pathMatchedOtherMethod = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $pathMatchedOtherMethod = true;
                continue;
            }
            $params = $route['defaults'];
            foreach ($route['keys'] as $i => $key) {
                $params[$key] = $m[$i + 1];
            }
            return [$route['handler'], $params];
        }

        // Le chemin existe mais pas pour ce verbe : 405 plutôt que 404.
        if ($pathMatchedOtherMethod) {
            throw new \RuntimeException('method-not-allowed', 405);
        }
        return null;
    }
}
