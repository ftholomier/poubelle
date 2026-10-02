<?php
declare(strict_types=1);

namespace App\Core;

/** Routeur minimaliste : motifs {param} ou {param:regex}, groupes, middlewares. */
final class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $middleware = [];
    /** @var array<string,callable> */
    private array $namedMiddleware = [];

    public function middleware(string $name, callable $fn): void
    {
        $this->namedMiddleware[$name] = $fn;
    }

    public function get(string $pattern, callable|array $handler, array $mw = []): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler, $mw);
    }

    public function post(string $pattern, callable|array $handler, array $mw = []): void
    {
        $this->add(['POST'], $pattern, $handler, $mw);
    }

    public function any(string $pattern, callable|array $handler, array $mw = []): void
    {
        $this->add(['GET', 'HEAD', 'POST'], $pattern, $handler, $mw);
    }

    public function add(array $methods, string $pattern, callable|array $handler, array $mw = []): void
    {
        $full = $this->prefix . $pattern;
        $this->routes[] = [
            'methods' => $methods,
            'pattern' => $full,
            'regex' => $this->compile($full),
            'handler' => $handler,
            'mw' => array_merge($this->middleware, $mw),
        ];
    }

    public function group(string $prefix, array $mw, callable $fn): void
    {
        $prevP = $this->prefix;
        $prevM = $this->middleware;
        $this->prefix .= $prefix;
        $this->middleware = array_merge($this->middleware, $mw);
        $fn($this);
        $this->prefix = $prevP;
        $this->middleware = $prevM;
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback('/\{([a-z_][a-z0-9_]*)(?::([^}]+))?\}/i', static function ($m): string {
            return '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
        }, $pattern);
        return '#^' . $regex . '$#u';
    }

    public function dispatch(string $method, string $path): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if (!in_array($method, $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['mw'] as $mw) {
                $fn = is_string($mw) ? ($this->namedMiddleware[$mw] ?? null) : $mw;
                if ($fn === null) {
                    throw new \RuntimeException('Middleware inconnu : ' . (string) $mw);
                }
                $res = $fn($params);
                if ($res instanceof Response) {
                    return $res;
                }
            }
            return $this->call($route['handler'], $params);
        }
        // Ajout automatique du slash final (URL canoniques).
        if (in_array($method, ['GET', 'HEAD'], true) && !str_ends_with($path, '/') && !preg_match('/\.[a-z0-9]{2,5}$/i', $path)) {
            foreach ($this->routes as $route) {
                if (in_array('GET', $route['methods'], true) && preg_match($route['regex'], $path . '/')) {
                    $qs = Request::queryString();
                    return Response::redirect($path . '/' . ($qs !== '' ? '?' . $qs : ''), 301);
                }
            }
        }
        if ($allowed) {
            return Response::text('Méthode non autorisée', 405)->header('Allow', implode(', ', array_unique($allowed)));
        }
        throw new HttpException(404);
    }

    private function call(callable|array $handler, array $params): Response
    {
        if (is_array($handler) && is_string($handler[0])) {
            $obj = new $handler[0]();
            $handler = [$obj, $handler[1]];
        }
        $ref = is_array($handler) ? new \ReflectionMethod($handler[0], $handler[1]) : new \ReflectionFunction(\Closure::fromCallable($handler));
        $args = [];
        foreach ($ref->getParameters() as $p) {
            $name = $p->getName();
            if (array_key_exists($name, $params)) {
                $type = $p->getType();
                $args[] = ($type instanceof \ReflectionNamedType && $type->getName() === 'int') ? (int) $params[$name] : $params[$name];
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } else {
                $args[] = null;
            }
        }
        $res = $handler(...$args);
        if ($res instanceof Response) {
            return $res;
        }
        if (is_array($res)) {
            return Response::json($res);
        }
        return Response::html((string) $res);
    }
}
