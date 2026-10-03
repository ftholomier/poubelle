<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server,
        public readonly string $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Caractères de contrôle (tabulation, retour à la ligne…) retirés : les navigateurs les ignorent
        // dans une adresse, « /%09/exemple.com » deviendrait sinon une redirection vers un autre site.
        $path = (string) preg_replace('/[\x00-\x1F\x7F]/', '', rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/'));
        // Les « \ » valent « / » pour les navigateurs : sans cela, « /\exemple.com » deviendrait une redirection externe.
        $path = '/' . ltrim(preg_replace('#/+#', '/', str_replace('\\', '/', $path)), '/');
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
            (string) file_get_contents('php://input'),
        );
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $this->json()[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function json(): array
    {
        static $cache = [];
        $k = spl_object_id($this);
        if (!array_key_exists($k, $cache)) {
            $ct = $this->server['CONTENT_TYPE'] ?? '';
            $cache[$k] = str_contains($ct, 'application/json') ? (json_decode($this->body, true) ?: []) : [];
        }
        return $cache[$k];
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/') || str_contains((string) $this->header('Accept'), 'application/json');
    }
}
