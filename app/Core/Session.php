<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = STORAGE_PATH . '/sessions';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        session_save_path($dir);
        session_name('sr_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /** Lit une valeur et l'efface (message ou saisie à réafficher une seule fois). */
    public static function pull(string $key, mixed $default = null): mixed
    {
        self::start();
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $v;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        self::start();
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        self::start();
        return is_string($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
