<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session PHP démarrée uniquement quand c'est nécessaire (connexion, espace pro, admin) :
 * les visiteurs anonymes ne reçoivent aucun cookie.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            self::$started = true;
            if (PHP_SAPI === 'cli' && !isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }
        $dir = STORAGE_PATH . '/sessions';
        Fs::ensureDir($dir, 0700);
        session_save_path($dir);
        session_name('apvs_sid');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '86400');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Request::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
        // Protection contre le vol de session : empreinte du navigateur.
        $fp = substr(hash('sha256', Request::userAgent()), 0, 16);
        if (isset($_SESSION['_fp']) && $_SESSION['_fp'] !== $fp) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_fp'] = $fp;
        // Messages flash : visibles une seule fois.
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public static function active(): bool
    {
        return self::$started || isset($_COOKIE['apvs_sid']);
    }

    /** Démarre la session seulement si le navigateur en possède déjà une. */
    public static function resume(): void
    {
        if (!self::$started && isset($_COOKIE['apvs_sid'])) {
            self::start();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::resume();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::resume();
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        self::resume();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
            session_destroy();
        }
        self::$started = false;
    }

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash_new'][] = ['type' => $type, 'msg' => $message];
    }

    /** @return array<int,array{type:string,msg:string}> */
    public static function flashes(): array
    {
        self::resume();
        $all = array_merge($_SESSION['_flash_old'] ?? [], $_SESSION['_flash_new'] ?? []);
        $_SESSION['_flash_old'] = [];
        $_SESSION['_flash_new'] = [];
        return $all;
    }

    /** Conserve les anciennes valeurs d'un formulaire pour le réafficher après erreur. */
    public static function withInput(array $input, array $errors = []): void
    {
        self::start();
        unset($input['password'], $input['password2'], $input['_csrf']);
        $_SESSION['_flash_new_input'] = $input;
        $_SESSION['_flash_new_errors'] = $errors;
    }

    public static function old(): array
    {
        self::resume();
        static $old = null;
        if ($old === null) {
            $old = ['input' => $_SESSION['_flash_new_input'] ?? [], 'errors' => $_SESSION['_flash_new_errors'] ?? []];
            unset($_SESSION['_flash_new_input'], $_SESSION['_flash_new_errors']);
        }
        return $old;
    }
}
