<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Comptes du back-office (storage/users.json, hors /public et hors dépôt).
 *
 * Deux niveaux :
 *  - admin : tout ;
 *  - user  : tout sauf utilisateurs, réglages, suppression définitive, restauration de versions.
 */
final class Auth
{
    private const FILE = STORAGE_PATH . '/users.json';
    public const ROLES = ['admin' => 'Administrateur', 'user' => 'Utilisateur'];

    /** Droits refusés au niveau « utilisateur ». */
    private const ADMIN_ONLY = ['users', 'settings', 'destroy', 'restore', 'backup_restore'];

    private static ?array $current = null;
    private static bool $loaded = false;

    /** @return array<string,array> par id */
    public static function users(): array
    {
        return JsonStore::read(self::FILE, []) ?? [];
    }

    private static function saveUsers(array $users): void
    {
        JsonStore::write(self::FILE, $users);
        @chmod(self::FILE, 0640);
    }

    public static function find(string $id): ?array
    {
        return self::users()[$id] ?? null;
    }

    public static function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        foreach (self::users() as $u) {
            if ($u['email'] === $email) {
                return $u;
            }
        }
        return null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$current;
        }
        self::$loaded = true;
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE['sr_session'])) {
            return null; // pas de session ouverte : visiteur anonyme
        }
        $id = Session::get('uid');
        $fp = Session::get('ufp');
        if (!$id) {
            return null;
        }
        $u = self::find((string) $id);
        if (!$u || ($u['status'] ?? '') !== 'active' || $fp !== self::fingerprint($u)) {
            Session::forget('uid');
            return null;
        }
        return self::$current = $u;
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return $u['role'] === 'admin' || !in_array($perm, self::ADMIN_ONLY, true);
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    /** Version publique (sans secrets) pour les journaux. */
    public static function actor(): ?array
    {
        $u = self::user();
        return $u ? ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email']] : null;
    }

    private static function fingerprint(array $u): string
    {
        // Change si le mot de passe change : toutes les sessions ouvertes sont alors fermées.
        return substr(hash('sha256', $u['id'] . '|' . ($u['password'] ?? '')), 0, 24);
    }

    public static function attempt(string $email, string $password, string $ip): array
    {
        $key = mb_strtolower(trim($email)) . '|' . $ip;
        if (!RateLimiter::hit('login', $key, 8, 900) || !RateLimiter::hit('login-ip', $ip, 30, 900)) {
            return ['ok' => false, 'error' => 'Trop de tentatives. Réessayez dans 15 minutes.'];
        }
        $u = self::findByEmail($email);
        // Temps constant : on vérifie un hash même si le compte n'existe pas.
        $hash = $u['password'] ?? password_hash(bin2hex(random_bytes(8)), PASSWORD_ARGON2ID);
        $ok = password_verify($password, $hash);
        if (!$u || !$ok || ($u['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'E-mail ou mot de passe incorrect.'];
        }
        if (password_needs_rehash($u['password'], PASSWORD_ARGON2ID)) {
            self::update($u['id'], ['password' => password_hash($password, PASSWORD_ARGON2ID)]);
            $u = self::find($u['id']);
        }
        // Seuls les échecs comptent : une connexion réussie remet le compteur du compte à zéro.
        RateLimiter::clear('login', $key);
        self::login($u);
        return ['ok' => true];
    }

    public static function login(array $u): void
    {
        Session::start();
        session_regenerate_id(true);
        Session::set('uid', $u['id']);
        Session::set('ufp', self::fingerprint($u));
        self::update($u['id'], ['last_login' => date('c')]);
        self::$current = self::find($u['id']);
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::start();
        $_SESSION = [];
        session_regenerate_id(true);
        self::$current = null;
    }

    public static function createUser(string $email, string $name, string $role, ?string $password = null): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresse e-mail invalide.');
        }
        if (self::findByEmail($email)) {
            throw new RuntimeException('Un compte existe déjà avec cette adresse.');
        }
        if ($password !== null && mb_strlen($password) < 10) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 10 caractères.');
        }
        $u = [
            'id' => bin2hex(random_bytes(8)),
            'email' => $email,
            'name' => trim($name) ?: $email,
            'role' => isset(self::ROLES[$role]) ? $role : 'user',
            'password' => $password !== null ? password_hash($password, PASSWORD_ARGON2ID) : null,
            'status' => $password !== null ? 'active' : 'invited',
            'invite' => null,
            'created_at' => date('c'),
            'last_login' => null,
        ];
        JsonStore::update(self::FILE, function ($all) use ($u) {
            $all = $all ?: [];
            $all[$u['id']] = $u;
            return $all;
        }, []);
        return $u;
    }

    public static function update(string $id, array $changes): void
    {
        JsonStore::update(self::FILE, function ($all) use ($id, $changes) {
            if (isset($all[$id])) {
                $all[$id] = array_merge($all[$id], $changes);
            }
            return $all;
        }, []);
    }

    public static function delete(string $id): void
    {
        JsonStore::update(self::FILE, function ($all) use ($id) {
            unset($all[$id]);
            return $all;
        }, []);
    }

    /** Invitation : renvoie le jeton à envoyer par e-mail (valable 7 jours). Seul son hash est stocké. */
    public static function invite(string $id): string
    {
        $token = bin2hex(random_bytes(24));
        self::update($id, ['invite' => ['hash' => hash('sha256', $token), 'expires' => time() + 7 * 86400], 'status' => self::find($id)['status'] === 'active' ? 'active' : 'invited']);
        return $token;
    }

    public static function findByInvite(string $token): ?array
    {
        $h = hash('sha256', $token);
        foreach (self::users() as $u) {
            if (!empty($u['invite']['hash']) && hash_equals($u['invite']['hash'], $h) && $u['invite']['expires'] > time()) {
                return $u;
            }
        }
        return null;
    }

    public static function setPassword(string $id, string $password): void
    {
        if (mb_strlen($password) < 10) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 10 caractères.');
        }
        self::update($id, ['password' => password_hash($password, PASSWORD_ARGON2ID), 'status' => 'active', 'invite' => null]);
    }

    public static function countAdmins(): int
    {
        return count(array_filter(self::users(), fn ($u) => $u['role'] === 'admin' && $u['status'] === 'active'));
    }
}
