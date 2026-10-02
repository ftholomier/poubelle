<?php
declare(strict_types=1);

namespace App\Core;

/** Accès à la requête HTTP courante. */
final class Request
{
    private static ?array $json = null;
    private static ?string $ip = null;

    public static function method(): string
    {
        $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($m === 'POST' && isset($_POST['_method']) && in_array(strtoupper((string) $_POST['_method']), ['PUT', 'PATCH', 'DELETE'], true)) {
            return strtoupper((string) $_POST['_method']);
        }
        return $m;
    }

    public static function isPost(): bool
    {
        return self::method() !== 'GET' && self::method() !== 'HEAD';
    }

    public static function uri(): string
    {
        return (string) ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public static function path(): string
    {
        $path = parse_url(self::uri(), PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $path = '/' . ltrim(preg_replace('#/{2,}#', '/', $path), '/');
        return $path;
    }

    public static function queryString(): string
    {
        return (string) ($_SERVER['QUERY_STRING'] ?? '');
    }

    public static function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    public static function input(?string $key = null, mixed $default = null): mixed
    {
        $src = $_POST;
        if (!$src && self::isJson()) {
            $src = self::json();
        }
        if ($key === null) {
            return $src;
        }
        $v = $src[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** Valeur brute (sans trim), utile pour les mots de passe. */
    public static function raw(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? (self::isJson() ? (self::json()[$key] ?? $default) : $default);
    }

    public static function str(string $key, int $max = 5000, string $default = ''): string
    {
        $v = self::input($key);
        if ($v === null) {
            $v = self::query($key);
        }
        if (!is_string($v) && !is_numeric($v)) {
            return $default;
        }
        $v = Str::clean((string) $v);
        return mb_substr($v, 0, $max);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::input($key) ?? self::query($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function bool(string $key): bool
    {
        $v = self::input($key) ?? self::query($key);
        return in_array($v, ['1', 1, true, 'on', 'true', 'oui'], true);
    }

    public static function arr(string $key): array
    {
        $v = self::input($key) ?? self::query($key);
        return is_array($v) ? $v : [];
    }

    public static function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        if (!is_array($f) || !isset($f['error'])) {
            return null;
        }
        return $f;
    }

    /** Liste normalisée de fichiers envoyés via name="x[]". */
    public static function files(string $key): array
    {
        $f = $_FILES[$key] ?? null;
        if (!is_array($f) || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }

    public static function isJson(): bool
    {
        return str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json');
    }

    public static function json(): array
    {
        if (self::$json === null) {
            $raw = file_get_contents('php://input', false, null, 0, 2_000_000);
            $data = json_decode((string) $raw, true);
            self::$json = is_array($data) ? $data : [];
        }
        return self::$json;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v = $_SERVER[$key] ?? null;
        return is_string($v) ? $v : null;
    }

    public static function isAjax(): bool
    {
        return strtolower((string) self::header('X-Requested-With')) === 'xmlhttprequest' || self::isJson() || str_contains((string) self::header('Accept'), 'application/json');
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
    }

    public static function isBot(): bool
    {
        return (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora|pinterest|semrush|ahrefs|mj12|dotbot|petalbot|bytespider|gptbot|headless|lighthouse|python|curl|wget|httpclient|java\//i', self::userAgent());
    }

    public static function referer(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
    }

    public static function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return self::fromTrustedProxy() && strtolower((string) self::header('X-Forwarded-Proto')) === 'https';
    }

    public static function host(): string
    {
        return strtolower((string) ($_SERVER['HTTP_HOST'] ?? parse_url((string) Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST)));
    }

    /** IP du client (en tenant compte des proxys de confiance déclarés dans TRUSTED_PROXIES). */
    public static function ip(): string
    {
        if (self::$ip !== null) {
            return self::$ip;
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (self::fromTrustedProxy()) {
            $cf = self::header('CF-Connecting-IP');
            $xff = self::header('X-Forwarded-For');
            $candidate = $cf ?: ($xff ? trim(explode(',', $xff)[0]) : null);
            if ($candidate && filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
        return self::$ip = $ip;
    }

    /** Empreinte anonymisée de l'IP (RGPD) pour les statistiques et l'anti-spam. */
    public static function ipHash(): string
    {
        return substr(hash_hmac('sha256', self::ip(), Crypto::key()), 0, 20);
    }

    private static function fromTrustedProxy(): bool
    {
        $trusted = trim((string) Env::get('TRUSTED_PROXIES', ''));
        if ($trusted === '') {
            return false;
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($trusted === '*' || strtolower($trusted) === 'cloudflare') {
            return true;
        }
        foreach (array_map('trim', explode(',', $trusted)) as $range) {
            if ($range !== '' && Net::ipInRange($remote, $range)) {
                return true;
            }
        }
        return false;
    }

    public static function baseUrl(): string
    {
        $app = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($app !== '') {
            return $app;
        }
        return (self::isSecure() ? 'https' : 'http') . '://' . self::host();
    }

    public static function fullUrl(): string
    {
        return self::baseUrl() . self::uri();
    }
}
