<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Config;

final class Request
{
    public readonly string $method;
    public readonly string $path;
    /** @var array<string,mixed> */
    public readonly array $query;
    /** @var array<string,mixed> */
    public readonly array $post;
    /** @var array<string,mixed> */
    public readonly array $json;

    public function __construct()
    {
        $this->method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        // Neutralise toute tentative de traversée dans le chemin de route.
        $path = preg_replace('#/+#', '/', str_replace('\\', '/', $path)) ?? '/';
        $this->path = '/' . trim($path, '/');

        $this->query = is_array($_GET) ? $_GET : [];
        $this->post  = is_array($_POST) ? $_POST : [];

        $json = [];
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '' && strlen($raw) < 512_000) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $json = $decoded;
                }
            }
        }
        $this->json = $json;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    /** @return array<int|string,mixed> */
    public function arr(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return str_contains($accept, 'application/json')
            || str_starts_with($this->path, '/api/')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function ip(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = Config::arr('security.trusted_proxies', []);
        if ($trusted !== [] && in_array($remote, $trusted, true)) {
            $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            $first = trim(explode(',', $forwarded)[0] ?? '');
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function baseUrl(): string
    {
        $configured = Config::str('app.url', '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        $scheme = \App\Security\Session::isHttps() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        // Anti Host-header injection : on ne garde qu'un hôte plausible.
        if (!preg_match('/^[A-Za-z0-9\.\-:]+$/', $host)) {
            $host = 'localhost';
        }
        return $scheme . '://' . $host;
    }

    /** Origine identique ? (défense supplémentaire pour les endpoints d'API) */
    public function isSameOrigin(): bool
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            return true; // navigation classique / requête sans origine
        }
        $host = parse_url($origin, PHP_URL_HOST);
        return is_string($host) && $host === parse_url($this->baseUrl(), PHP_URL_HOST);
    }
}
