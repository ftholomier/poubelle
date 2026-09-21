<?php
declare(strict_types=1);

namespace App\Core;

/** Requête HTTP entrante, normalisée. */
final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly array $query;
    public readonly array $post;
    public readonly array $files;

    private function __construct(string $method, string $path, array $query, array $post, array $files)
    {
        $this->method = $method;
        $this->path   = $path;
        $this->query  = $query;
        $this->post   = $post;
        $this->files  = $files;
    }

    public static function capture(): self
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path === '//' ? '/' : $path,
            $_GET,
            $_POST,
            $_FILES,
        );
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Valeurs multiples d'un même champ (cases à cocher des filtres). */
    public function all(string $key): array
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? [];
        if (is_string($value)) {
            $value = $value === '' ? [] : explode(',', $value);
        }
        return is_array($value) ? array_values(array_filter(array_map('trim', $value), 'strlen')) : [];
    }

    public function intval(string $key, int $default = 1): int
    {
        $raw = $this->query[$key] ?? $this->post[$key] ?? null;
        return is_numeric($raw) ? (int) $raw : $default;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
