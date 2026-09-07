<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $secure = self::isHttps();
        session_name(Config::str('security.session_name', 'lcal_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) Config::int('security.session_lifetime', 14400));

        session_start();
        self::$started = true;

        // Expiration par inactivité.
        $idle = Config::int('security.idle_timeout', 2700);
        $last = self::get('_last_activity');
        if (is_int($last) && (time() - $last) > $idle) {
            self::destroy();
            session_start();
        }
        self::set('_last_activity', time());

        // Rotation périodique de l'identifiant de session.
        $born = self::get('_born');
        if (!is_int($born)) {
            self::set('_born', time());
        } elseif (time() - $born > 1800) {
            session_regenerate_id(true);
            self::set('_born', time());
        }
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

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        self::set('_born', time());
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$started = false;
    }

    /* Messages flash (bandeaux back-office). */
    public static function flash(string $type, string $message): void
    {
        $bag = self::get('_flash', []);
        $bag[] = ['type' => $type, 'message' => $message];
        self::set('_flash', $bag);
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function pullFlash(): array
    {
        $bag = self::get('_flash', []);
        self::forget('_flash');
        return is_array($bag) ? $bag : [];
    }

    public static function isHttps(): bool
    {
        if (Config::bool('security.force_https', false)) {
            return true;
        }
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
