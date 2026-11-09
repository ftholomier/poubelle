<?php

declare(strict_types=1);

/** Réponses JSON, lecture des requêtes, jetons anti-robot et limitation de débit. */
final class Http
{
    public static function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function error(string $message, int $code = 400, array $extra = []): void
    {
        self::json(['ok' => false, 'error' => $message] + $extra, $code);
    }

    /** Corps de la requête : JSON ou formulaire. */
    public static function input(): array
    {
        static $in = null;
        if ($in !== null) {
            return $in;
        }
        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($type, 'application/json') !== false) {
            $raw = (string) file_get_contents('php://input', false, null, 0, 200000);
            $j = json_decode($raw, true);
            return $in = is_array($j) ? $j : [];
        }
        return $in = $_POST;
    }

    public static function str(array $in, string $key, int $max = 500): string
    {
        $v = $in[$key] ?? '';
        if (!is_scalar($v)) {
            return '';
        }
        $v = trim(str_replace("\0", '', (string) $v));
        return mb_substr($v, 0, $max);
    }

    /** IP réelle du visiteur (derrière Cloudflare si l'en-tête vient bien de lui). */
    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $cf = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }
        return $ip;
    }

    /** Jeton signé de formulaire public : horodaté, vérifié côté serveur (délai minimal anti-robot). */
    public static function formToken(): string
    {
        $t = (string) time();
        return $t . '.' . substr(hash_hmac('sha256', 'form|' . $t, app_secret()), 0, 32);
    }

    public static function checkFormToken(string $token, int $minSeconds = 2): bool
    {
        if (!preg_match('/^(\d{10})\.([0-9a-f]{32})$/', $token, $m)) {
            return false;
        }
        $age = time() - (int) $m[1];
        if ($age < $minSeconds || $age > 86400) {
            return false;
        }
        return hash_equals(substr(hash_hmac('sha256', 'form|' . $m[1], app_secret()), 0, 32), $m[2]);
    }

    /**
     * Limitation : au plus $max actions $bucket par IP sur $window secondes.
     * Retourne false si la limite est atteinte (et n'enregistre pas l'essai).
     */
    public static function rateLimit(string $bucket, int $max, int $window): bool
    {
        $key = hash('sha256', $bucket . '|' . self::ip());
        $ok = true;
        Store::update('ratelimit', function (array $d) use ($key, $max, $window, &$ok) {
            $now = time();
            foreach ($d as $k => $hits) {
                $d[$k] = array_values(array_filter((array) $hits, fn($t) => $t > $now - 86400));
                if (!$d[$k]) {
                    unset($d[$k]);
                }
            }
            $recent = array_filter($d[$key] ?? [], fn($t) => $t > $now - $window);
            if (count($recent) >= $max) {
                $ok = false;
                return $d;
            }
            $d[$key][] = $now;
            return $d;
        });
        return $ok;
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public static function redirect(string $to, int $code = 302): void
    {
        header('Location: ' . $to, true, $code);
        exit;
    }

    /** URL absolue du site. */
    public static function abs(string $path = '/'): string
    {
        $base = rtrim((string) cfg('site_url', ''), '/');
        if ($base === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }
        return $base . '/' . ltrim(implode('/', array_map('rawurlencode', explode('/', $path))), '/');
    }
}
