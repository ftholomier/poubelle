<?php
declare(strict_types=1);

namespace App\Core;

/** Session PHP durcie : cookie strict, HttpOnly, rotation d'identifiant. */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        session_name((string) Config::get('security.session_name', 'imtt'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        self::$started = true;
    }

    public static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
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

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /** Message affiché une seule fois (bandeau de confirmation après POST). */
    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $out = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $out;
    }

    /** À appeler après une connexion réussie : évite la fixation de session. */
    public static function regenerate(): void
    {
        self::start();
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli') {
            session_destroy();
        }
        self::$started = false;
    }
}
