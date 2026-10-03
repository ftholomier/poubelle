<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal : motifs du type /category/{path*} ou /api/contenus/{slug}.
 * {nom} capture un segment, {nom*} capture le reste du chemin.
 */
final class Router
{
    /** @var list<array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, callable $handler): self
    {
        return $this->add('ANY', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): self
    {
        $this->routes[] = [$method, $pattern, $handler];
        return $this;
    }

    public function dispatch(Request $req): mixed
    {
        $path = $req->path;
        foreach ($this->routes as [$method, $pattern, $handler]) {
            if ($method !== 'ANY' && $method !== $req->method && !($method === 'GET' && $req->method === 'HEAD')) {
                continue;
            }
            $params = self::match($pattern, $path);
            if ($params !== null) {
                return $handler($req, ...$params);
            }
        }
        return null;
    }

    /** Une page GET répond-elle à cette adresse ? (vérifié sans exécuter la page) */
    public function has(string $path): bool
    {
        return $this->route($path) !== null;
    }

    /**
     * Route GET qui répond à cette adresse, sans exécuter la page : [modèle, paramètres].
     * @return array{0:string,1:array<string,string>}|null
     */
    public function route(string $path): ?array
    {
        foreach ($this->routes as [$method, $pattern]) {
            if ($method !== 'GET' && $method !== 'ANY') {
                continue;
            }
            // Tri rapide sur la partie fixe du modèle (avant le premier paramètre).
            $fixed = strstr($pattern, '{', true);
            if ($fixed !== false ? !str_starts_with($path, $fixed) : rtrim($pattern, '/') !== rtrim($path, '/')) {
                continue;
            }
            if (($params = self::match($pattern, $path)) !== null) {
                return [$pattern, $params];
            }
        }
        return null;
    }

    /** @return array<string,string>|null */
    public static function match(string $pattern, string $path): ?array
    {
        static $regex = [];
        $regex[$pattern] ??= '#^' . preg_replace_callback('#\{(\w+)(\*?)\}#', function ($m) {
            return $m[2] === '*' ? '(?P<' . $m[1] . '>.+?)' : '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern) . '/?$#u';
        if (!preg_match($regex[$pattern], $path, $m)) {
            return null;
        }
        $out = [];
        foreach ($m as $k => $v) {
            if (is_string($k)) {
                $out[$k] = rawurldecode($v);
            }
        }
        return $out;
    }
}
