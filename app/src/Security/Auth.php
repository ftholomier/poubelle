<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\JsonStore;
use App\Core\Logger;

/**
 * Authentification back-office : email + mot de passe, sans base de données.
 * Les comptes vivent dans data/users.json (hors racine web).
 */
final class Auth
{
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_EDITOR = 'editor';

    private const SCHEMA = ['users' => [], 'version' => 1];

    public static function file(): string
    {
        return DATA_PATH . '/users.json';
    }

    /* ------------------------------------------------------------------ */
    /* Connexion                                                           */
    /* ------------------------------------------------------------------ */

    /** @return array{ok:bool,error?:string,user?:array<string,mixed>} */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = self::normalizeEmail($email);
        $max    = Config::int('security.login_max_attempts', 5);
        $window = Config::int('security.login_lockout', 900);

        // Double compteur : par email ET par IP.
        $okEmail = RateLimiter::hit('login', 'email:' . $email, $max, $window);
        $okIp    = RateLimiter::hit('login', 'ip:' . $ip, $max * 3, $window);
        if (!$okEmail || !$okIp) {
            Logger::warning('Blocage brute-force', ['email' => $email, 'ip' => $ip]);
            return ['ok' => false, 'error' => 'too_many'];
        }

        $user = self::findByEmail($email);

        // Comparaison à temps constant même si l'utilisateur n'existe pas
        // (évite l'énumération de comptes par mesure du temps de réponse).
        $hash = $user['password'] ?? '$argon2id$v=19$m=65536,t=4,p=1$aW52YWxpZHNhbHQ$aW52YWxpZGhhc2hpbnZhbGlkaGFzaGludmFsaWQ';
        $valid = password_verify($password, (string) $hash);

        if (!$user || !$valid || ($user['active'] ?? true) !== true) {
            Logger::warning('Échec de connexion', ['email' => $email, 'ip' => $ip]);
            return ['ok' => false, 'error' => 'invalid'];
        }

        // Ré-hachage si l'algorithme a évolué.
        if (password_needs_rehash((string) $user['password'], self::algo())) {
            self::updateUser((string) $user['id'], ['password' => password_hash($password, self::algo())]);
        }

        RateLimiter::clear('login', 'email:' . $email);
        RateLimiter::clear('login', 'ip:' . $ip);

        Session::start();
        Session::regenerate();
        Session::set('auth', [
            'id'    => $user['id'],
            'email' => $user['email'],
            'name'  => $user['name'],
            'role'  => $user['role'],
            'ip'    => $ip,
            'ua'    => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
            'at'    => time(),
        ]);

        self::updateUser((string) $user['id'], ['last_login' => date('c')]);
        Logger::audit('auth.login', ['email' => $email, 'ip' => $ip]);

        return ['ok' => true, 'user' => $user];
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user) {
            Logger::audit('auth.logout', ['email' => $user['email'] ?? '']);
        }
        Session::destroy();
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        Session::start();
        $auth = Session::get('auth');
        if (!is_array($auth) || empty($auth['id'])) {
            return null;
        }
        // Détournement de session : l'agent doit rester stable.
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120);
        if (($auth['ua'] ?? '') !== $ua) {
            Session::destroy();
            return null;
        }
        $user = self::findById((string) $auth['id']);
        if (!$user || ($user['active'] ?? true) !== true) {
            Session::destroy();
            return null;
        }
        return $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] ?? '') === self::ROLE_ADMIN;
    }

    /* ------------------------------------------------------------------ */
    /* Réinitialisation de mot de passe                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Crée un jeton à usage unique. Retourne toujours un résultat neutre
     * pour ne pas révéler l'existence d'un compte.
     */
    public static function createResetToken(string $email): ?array
    {
        $email = self::normalizeEmail($email);
        $user  = self::findByEmail($email);
        if (!$user) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        self::updateUser((string) $user['id'], [
            'reset_hash'    => hash('sha256', $token),
            'reset_expires' => time() + Config::int('security.reset_token_ttl', 3600),
        ]);
        Logger::audit('auth.reset_requested', ['email' => $email]);
        return ['token' => $token, 'user' => $user];
    }

    /** @return array{ok:bool,error?:string} */
    public static function resetPassword(string $token, string $password): array
    {
        $error = self::validatePassword($password);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        $hash = hash('sha256', $token);
        $target = null;
        foreach (self::all() as $user) {
            if (!empty($user['reset_hash']) && hash_equals((string) $user['reset_hash'], $hash)) {
                $target = $user;
                break;
            }
        }
        if (!$target) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }
        if ((int) ($target['reset_expires'] ?? 0) < time()) {
            return ['ok' => false, 'error' => 'expired_token'];
        }
        self::updateUser((string) $target['id'], [
            'password'      => password_hash($password, self::algo()),
            'reset_hash'    => '',
            'reset_expires' => 0,
        ]);
        Logger::audit('auth.reset_done', ['email' => $target['email'] ?? '']);
        return ['ok' => true];
    }

    public static function validatePassword(string $password): ?string
    {
        $min = Config::int('security.password_min_length', 10);
        if (mb_strlen($password) < $min) {
            return 'too_short';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'too_weak';
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* CRUD utilisateurs                                                   */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        $data = JsonStore::read(self::file(), self::SCHEMA, true);
        return array_values(array_filter($data['users'], 'is_array'));
    }

    public static function findById(string $id): ?array
    {
        foreach (self::all() as $user) {
            if ((string) ($user['id'] ?? '') === $id) {
                return $user;
            }
        }
        return null;
    }

    public static function findByEmail(string $email): ?array
    {
        $email = self::normalizeEmail($email);
        foreach (self::all() as $user) {
            if (self::normalizeEmail((string) ($user['email'] ?? '')) === $email) {
                return $user;
            }
        }
        return null;
    }

    /** @return array{ok:bool,error?:string,id?:string} */
    public static function createUser(string $name, string $email, string $password, string $role = self::ROLE_EDITOR): array
    {
        $email = self::normalizeEmail($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email'];
        }
        if (self::findByEmail($email)) {
            return ['ok' => false, 'error' => 'email_taken'];
        }
        $error = self::validatePassword($password);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }

        $id = bin2hex(random_bytes(8));
        JsonStore::mutate(self::file(), static function (array $data) use ($id, $name, $email, $password, $role): array {
            $data['users'][] = [
                'id'            => $id,
                'name'          => $name !== '' ? $name : 'Administrateur',
                'email'         => $email,
                'password'      => password_hash($password, self::algo()),
                'role'          => in_array($role, [self::ROLE_ADMIN, self::ROLE_EDITOR], true) ? $role : self::ROLE_EDITOR,
                'active'        => true,
                'created_at'    => date('c'),
                'last_login'    => '',
                'reset_hash'    => '',
                'reset_expires' => 0,
            ];
            return $data;
        }, self::SCHEMA);

        Logger::audit('user.create', ['email' => $email, 'role' => $role]);
        return ['ok' => true, 'id' => $id];
    }

    /** @param array<string,mixed> $changes */
    public static function updateUser(string $id, array $changes): bool
    {
        $found = false;
        JsonStore::mutate(self::file(), static function (array $data) use ($id, $changes, &$found): array {
            foreach ($data['users'] as $index => $user) {
                if (is_array($user) && (string) ($user['id'] ?? '') === $id) {
                    $data['users'][$index] = array_merge($user, $changes);
                    $found = true;
                    break;
                }
            }
            return $data;
        }, self::SCHEMA);
        return $found;
    }

    public static function deleteUser(string $id): bool
    {
        if (count(self::all()) <= 1) {
            return false; // On ne supprime jamais le dernier compte.
        }
        $removed = false;
        JsonStore::mutate(self::file(), static function (array $data) use ($id, &$removed): array {
            $data['users'] = array_values(array_filter(
                $data['users'],
                static function ($user) use ($id, &$removed) {
                    if (is_array($user) && (string) ($user['id'] ?? '') === $id) {
                        $removed = true;
                        return false;
                    }
                    return true;
                }
            ));
            return $data;
        }, self::SCHEMA);
        if ($removed) {
            Logger::audit('user.delete', ['id' => $id]);
        }
        return $removed;
    }

    public static function changePassword(string $id, string $current, string $new): array
    {
        $user = self::findById($id);
        if (!$user || !password_verify($current, (string) $user['password'])) {
            return ['ok' => false, 'error' => 'invalid_current'];
        }
        $error = self::validatePassword($new);
        if ($error !== null) {
            return ['ok' => false, 'error' => $error];
        }
        self::updateUser($id, ['password' => password_hash($new, self::algo())]);
        Logger::audit('user.password_changed', ['id' => $id]);
        return ['ok' => true];
    }

    private static function algo(): string
    {
        $algo = Config::get('security.hash_algo', PASSWORD_ARGON2ID);
        if ($algo === PASSWORD_ARGON2ID && !defined('PASSWORD_ARGON2ID')) {
            return PASSWORD_BCRYPT;
        }
        return (string) $algo;
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
