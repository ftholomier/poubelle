<?php
declare(strict_types=1);

/** Routeur minimaliste avec paramètres nommés : /actualites/{slug} */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:callable}> */
    private array $routes = [];
    /** @var callable|null */
    private $fallback = null;

    public function get(string $pattern, callable $handler): void { $this->add('GET', $pattern, $handler); }
    public function post(string $pattern, callable $handler): void { $this->add('POST', $pattern, $handler); }
    public function any(string $pattern, callable $handler): void { $this->add('*', $pattern, $handler); }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
    }

    public function fallback(callable $handler): void { $this->fallback = $handler; }

    public function dispatch(string $method, string $uri): void
    {
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        $base = rtrim((string) settings('site.base_path', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = '/' . ltrim(substr($path, strlen($base)), '/');
        }
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== '*' && $route['method'] !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#i', '(?P<$1>[^/]+)', str_replace('#', '\#', $route['pattern'])) . '$#u';
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                ($route['handler'])($params);
                return;
            }
        }
        if ($this->fallback) {
            ($this->fallback)(['path' => $path]);
        }
    }
}
