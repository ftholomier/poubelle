<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Store;

/**
 * Authentification des administrateurs et des pros (adhérents).
 * Verrouillage après échecs répétés, régénération de session, délai d'inactivité.
 */
final class Auth
{
    private static ?array $admin = null;
    private static bool $adminLoaded = false;
    private static ?array $pro = null;
    private static bool $proLoaded = false;

    // ------------------------------------------------------------------ admin

    public static function admin(): ?array
    {
        if (self::$adminLoaded) {
            return self::$admin;
        }
        self::$adminLoaded = true;
        if (PHP_SAPI === 'cli' || !Session::active()) {
            return null;
        }
        $id = (int) Session::get('admin_id', 0);
        if ($id <= 0 || !Session::get('admin_2fa_ok', false)) {
            return null;
        }
        $timeout = max(10, Env::int('SESSION_LIFETIME_ADMIN', 120)) * 60;
        $last = (int) Session::get('admin_seen', 0);
        if ($last && time() - $last > $timeout) {
            self::logoutAdmin();
            Session::flash('warning', 'Session expirée, reconnectez-vous.');
            return null;
        }
        $user = Store::admins()->get($id);
        if (!$user || ($user['status'] ?? 'active') !== 'active' || (int) ($user['session_version'] ?? 0) !== (int) Session::get('admin_sv', 0)) {
            self::logoutAdmin();
            return null;
        }
        Session::set('admin_seen', time());
        return self::$admin = $user;
    }

    public static function adminCan(string $permission): bool
    {
        $u = self::admin();
        if (!$u) {
            return false;
        }
        $role = $u['role'] ?? 'moderator';
        $matrix = [
            'superadmin' => ['*'],
            'admin' => ['*', '!users', '!env'],
            'moderator' => ['dashboard', 'pros', 'requests', 'messages', 'reviews', 'notifications'],
            'editor' => ['dashboard', 'content', 'seo', 'notifications'],
        ];
        $perms = $matrix[$role] ?? [];
        if (in_array('!' . $permission, $perms, true)) {
            return false;
        }
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    /**
     * Étape 1 : identifiants. Renvoie 'ok', '2fa', 'locked' ou 'invalid'.
     * Blocage par adresse IP et par couple (compte, IP) : un attaquant ne peut pas bloquer
     * le compte légitime depuis une autre adresse, et le message ne révèle pas si le compte existe.
     */
    public static function attemptAdmin(string $email, string $password): string
    {
        $email = Str::email($email);
        $ip = Request::ip();
        $ipKey = 'login-admin-ip:' . $ip;
        $pairKey = 'login-admin:' . sha1($email . '|' . $ip);
        $max = max(3, Env::int('LOGIN_MAX_ATTEMPTS', 5));
        if (RateLimiter::tooMany($ipKey, 20) || RateLimiter::tooMany($pairKey, $max)) {
            Logger::security('Connexion admin bloquée (trop de tentatives)', ['email' => $email]);
            return 'locked';
        }
        $users = Store::admins()->find(static fn ($u) => strtolower($u['email'] ?? '') === $email, null, 1)['items'];
        $light = $users[0] ?? null;
        $user = $light ? Store::admins()->get((int) $light['id']) : null;
        if (!$user || ($user['status'] ?? 'active') !== 'active' || !Crypto::verifyPassword($password, (string) ($user['password_hash'] ?? ''))) {
            RateLimiter::hit($ipKey, 3600);
            RateLimiter::hit($pairKey, max(60, Env::int('LOGIN_LOCK_MINUTES', 15) * 60));
            if ($user) {
                $after = Store::admins()->update((int) $user['id'], static function (array $u): array {
                    $u['failed_logins'] = (int) ($u['failed_logins'] ?? 0) + 1;
                    return $u;
                }, false);
                if ((int) ($after['failed_logins'] ?? 0) % 10 === 0) {
                    \App\Services\Notify::admin('security', 'Tentatives de connexion répétées', ((int) $after['failed_logins']) . ' échecs de connexion pour ' . $email . ' (dernière IP : ' . $ip . ')', Url::admin('journal?canal=security'), 'danger');
                }
            }
            Logger::security('Échec de connexion admin', ['email' => $email]);
            return 'invalid';
        }
        RateLimiter::clear($pairKey);
        if (Crypto::needsRehash((string) $user['password_hash'])) {
            Store::admins()->update((int) $user['id'], ['password_hash' => Crypto::hashPassword($password)], false);
        }
        Session::regenerate();
        Session::set('admin_id', (int) $user['id']);
        Session::set('admin_sv', (int) ($user['session_version'] ?? 0));
        if (!empty($user['totp_enabled'])) {
            Session::set('admin_2fa_ok', false);
            return '2fa';
        }
        self::completeAdminLogin($user);
        return 'ok';
    }

    public static function pendingAdmin(): ?array
    {
        $id = (int) Session::get('admin_id', 0);
        return $id > 0 ? Store::admins()->get($id) : null;
    }

    public static function verifyAdmin2fa(string $code): bool
    {
        $user = self::pendingAdmin();
        if (!$user || empty($user['totp_secret'])) {
            return false;
        }
        if (!RateLimiter::attempt('2fa:' . $user['id'], 8, 900)) {
            return false;
        }
        $secret = Crypto::decrypt((string) $user['totp_secret']);
        $ok = $secret !== null && Totp::verify($secret, $code, (int) ($user['totp_last_step'] ?? 0));
        if (!$ok) {
            // codes de secours (usage unique)
            $codes = $user['recovery_codes'] ?? [];
            foreach ($codes as $i => $hash) {
                if (password_verify(strtoupper((string) preg_replace('/[\s\-]+/', '', $code)), $hash)) {
                    unset($codes[$i]);
                    Store::admins()->update((int) $user['id'], ['recovery_codes' => array_values($codes)], false);
                    Logger::security('Code de secours 2FA utilisé', ['admin' => $user['email']]);
                    $ok = true;
                    break;
                }
            }
        } else {
            Store::admins()->update((int) $user['id'], ['totp_last_step' => Totp::lastStep()], false);
        }
        if (!$ok) {
            Logger::security('Code 2FA invalide', ['admin' => $user['email']]);
            return false;
        }
        self::completeAdminLogin($user);
        return true;
    }

    private static function completeAdminLogin(array $user): void
    {
        Session::regenerate();
        Session::set('admin_2fa_ok', true);
        Session::set('admin_seen', time());
        Store::admins()->update((int) $user['id'], [
            'last_login_at' => date('c'),
            'last_login_ip' => Request::ip(),
            'failed_logins' => 0,
            'locked_until' => null,
        ], false);
        self::$adminLoaded = false;
        Logger::security('Connexion admin réussie', ['admin' => $user['email']], 'info');
    }

    public static function logoutAdmin(): void
    {
        Session::forget('admin_id');
        Session::forget('admin_2fa_ok');
        Session::forget('admin_seen');
        Session::forget('admin_sv');
        Session::forget('impersonate_from');
        self::$admin = null;
        self::$adminLoaded = true;
    }

    // -------------------------------------------------------------------- pro

    public static function pro(): ?array
    {
        if (self::$proLoaded) {
            return self::$pro;
        }
        self::$proLoaded = true;
        if (PHP_SAPI === 'cli' || !Session::active()) {
            return null;
        }
        $id = (int) Session::get('pro_id', 0);
        if ($id <= 0) {
            return null;
        }
        $pro = Store::pros()->get($id);
        if (!$pro || in_array($pro['status'] ?? '', ['deleted', 'rejected'], true) || (int) ($pro['session_version'] ?? 0) !== (int) Session::get('pro_sv', 0)) {
            self::logoutPro();
            return null;
        }
        return self::$pro = $pro;
    }

    /** Connexion pro par identifiant (ancien login) ou email. Blocage par IP et par couple (compte, IP). */
    public static function attemptPro(string $identifier, string $password): string
    {
        $identifier = trim($identifier);
        $ip = Request::ip();
        $ipKey = 'login-pro-ip:' . $ip;
        $pairKey = 'login-pro:' . sha1(mb_strtolower($identifier) . '|' . $ip);
        $max = max(3, Env::int('LOGIN_MAX_ATTEMPTS', 5) + 3);
        if (RateLimiter::tooMany($ipKey, 30) || RateLimiter::tooMany($pairKey, $max)) {
            return 'locked';
        }
        $pro = \App\Services\Pros::findByLogin($identifier);
        if (!$pro || in_array($pro['status'] ?? '', ['deleted', 'rejected'], true) || !Crypto::verifyPassword($password, (string) ($pro['password_hash'] ?? ''))) {
            RateLimiter::hit($ipKey, 3600);
            RateLimiter::hit($pairKey, max(60, Env::int('LOGIN_LOCK_MINUTES', 15) * 60));
            if ($pro) {
                Store::pros()->update((int) $pro['id'], static function (array $p): array {
                    $p['failed_logins'] = (int) ($p['failed_logins'] ?? 0) + 1;
                    return $p;
                }, false);
            }
            Logger::security('Échec de connexion pro', ['login' => $identifier], 'notice');
            return 'invalid';
        }
        RateLimiter::clear($pairKey);
        $patch = ['last_login_at' => date('c'), 'failed_logins' => 0, 'locked_until' => null];
        if (Crypto::needsRehash((string) $pro['password_hash'])) {
            $patch['password_hash'] = Crypto::hashPassword($password);
        }
        Store::pros()->update((int) $pro['id'], $patch, false);
        self::loginPro($pro);
        return 'ok';
    }

    public static function loginPro(array $pro): void
    {
        Session::regenerate();
        Session::set('pro_id', (int) $pro['id']);
        Session::set('pro_sv', (int) ($pro['session_version'] ?? 0));
        self::$proLoaded = false;
    }

    public static function logoutPro(): void
    {
        Session::forget('pro_id');
        Session::forget('pro_sv');
        self::$pro = null;
        self::$proLoaded = true;
    }

    /** Règles de mot de passe communes (pros et administrateurs). Renvoie un message d'erreur ou null. */
    public static function passwordError(string $password, array $context = []): ?string
    {
        $min = max(8, Env::int('PASSWORD_MIN_LENGTH', 10));
        if (mb_strlen($password) < $min) {
            return "Le mot de passe doit contenir au moins $min caractères.";
        }
        if (mb_strlen($password) > 200) {
            return 'Mot de passe trop long.';
        }
        $low = mb_strtolower($password);
        foreach ($context as $c) {
            $c = mb_strtolower(trim((string) $c));
            if ($c !== '' && mb_strlen($c) >= 4 && str_contains($low, explode('@', $c)[0])) {
                return 'Le mot de passe ne doit pas contenir votre identifiant ou votre email.';
            }
        }
        $common = ['password', 'motdepasse', 'azerty', 'qwerty', '123456', 'soleil', 'animateur', 'dj', 'bonjour', 'admin', 'loulou'];
        $letters = preg_replace('/[^a-z]/', '', Str::ascii($low)) ?? '';
        if (in_array($letters, $common, true) || preg_match('/^(.)\1+$/', $password) || preg_match('/^(0123456789|1234567890|abcdefgh)/', $low)) {
            return 'Ce mot de passe est trop facile à deviner.';
        }
        if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[^a-zA-Z]/', $password)) {
            return 'Mélangez lettres et chiffres (ou caractères spéciaux).';
        }
        return null;
    }

    public static function reset(): void
    {
        self::$adminLoaded = self::$proLoaded = false;
        self::$admin = self::$pro = null;
    }
}
