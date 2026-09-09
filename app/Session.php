<?php
declare(strict_types=1);

namespace App;

/** Session PHP durcie : cookie HttpOnly + SameSite=Strict, Secure si HTTPS. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('ioio_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Config::basePath() . '/',
            'secure' => Config::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $dir = Config::storagePath('sessions');
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        if (is_writable($dir)) {
            session_save_path($dir);
        }
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Message affiché une seule fois (bandeau du back-office). */
    public static function flash(?string $message = null, string $type = 'ok'): ?array
    {
        if ($message !== null) {
            $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
            return null;
        }
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return \is_array($flash) ? $flash : null;
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
