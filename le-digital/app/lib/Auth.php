<?php
declare(strict_types=1);

namespace LD;

/**
 * Authentification du back-office (session PHP isolée dans storage/sessions).
 */
final class Auth
{
    private const USERS = 'data/users.json';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_save_path(Store::path('sessions'));
        session_name('ld_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => Http::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_start();
        if (!empty($_SESSION['uid']) && ($_SESSION['last'] ?? 0) < time() - 28800) {
            self::logout();
            session_start();
        }
        $_SESSION['last'] = time();
    }

    public static function hasUsers(): bool
    {
        return count(Store::read(self::USERS, [])) > 0;
    }

    public static function user(): ?array
    {
        self::start();
        if (empty($_SESSION['uid'])) {
            return null;
        }
        foreach (Store::read(self::USERS, []) as $u) {
            if ($u['id'] === $_SESSION['uid']) {
                return $u;
            }
        }
        return null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Vérifie la session admin sans démarrer de session si aucun cookie n'est présent (pages publiques). */
    public static function peek(): bool
    {
        if (empty($_COOKIE['ld_admin'])) {
            return false;
        }
        return self::check();
    }

    public static function createUser(string $email, string $password, string $name = ''): array
    {
        $user = [
            'id'         => bin2hex(random_bytes(8)),
            'email'      => mb_strtolower(trim($email)),
            'name'       => $name,
            'hash'       => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
        ];
        Store::update(self::USERS, static function (array &$users) use ($user): void {
            $users[] = $user;
        });
        return $user;
    }

    public static function attempt(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));
        foreach (Store::read(self::USERS, []) as $u) {
            if (hash_equals($u['email'], $email) && password_verify($password, $u['hash'])) {
                self::start();
                session_regenerate_id(true);
                $_SESSION['uid'] = $u['id'];
                $_SESSION['csrf'] = bin2hex(random_bytes(16));
                if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
                    self::updateUser($u['id'], ['password' => $password]);
                }
                return true;
            }
        }
        // Temps constant approximatif face à l'énumération.
        password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
        return false;
    }

    public static function updateUser(string $id, array $changes): void
    {
        Store::update(self::USERS, static function (array &$users) use ($id, $changes): void {
            foreach ($users as &$u) {
                if ($u['id'] !== $id) {
                    continue;
                }
                if (!empty($changes['email'])) {
                    $u['email'] = mb_strtolower(trim($changes['email']));
                }
                if (isset($changes['name'])) {
                    $u['name'] = $changes['name'];
                }
                if (!empty($changes['password'])) {
                    $u['hash'] = password_hash($changes['password'], PASSWORD_DEFAULT);
                }
            }
        });
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(): void
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
        if (!is_string($sent) || !hash_equals(self::csrf(), $sent)) {
            http_response_code(419);
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || !empty($_SERVER['HTTP_X_CSRF'])) {
                Http::fail('Jeton de sécurité expiré, recharge la page.', 419);
            }
            exit('Jeton de sécurité expiré. Reviens en arrière et recharge la page.');
        }
    }

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        self::start();
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }
}
