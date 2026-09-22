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
        return Net::clientIp();
    }

    /**
     * Robot d'indexation ou d'aspiration : on ne déclenche pour lui ni appel
     * d'API facturé, ni requête vers un partenaire.
     */
    public function isBot(): bool
    {
        $agent = strtolower($this->userAgent());
        if ($agent === '') {
            return true;
        }
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|embedly|quora|pinterest|bingpreview|'
            . 'yandex|baidu|duckduck|semrush|ahrefs|mj12|dotbot|petal|applebot|gptbot|'
            . 'claudebot|ccbot|perplexity|headlesschrome|python-requests|curl|wget|okhttp/',
            $agent,
        );
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
