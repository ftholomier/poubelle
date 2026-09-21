<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Session;
use App\Domain\UserRepository;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Authentification du back-office.
 *
 * Les mots de passe sont stockés en Argon2id. Les empreintes WordPress reprises
 * sont acceptées une dernière fois : à la première connexion réussie, le mot de
 * passe est réécrit en Argon2id et l'ancienne empreinte effacée. Personne n'a
 * donc à réinitialiser son mot de passe, et rien ne reste en MD5 itéré.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user';

    /* ------------------------------------------------------------ connexion */

    /**
     * @return array{ok:bool, user:array|null, error:string, wait:int}
     */
    public static function login(string $email, string $password, string $ip): array
    {
        $max = (int) Config::get('security.login_max_tries', 5);
        $lock = (int) Config::get('security.login_lock_secs', 900);

        $wait = RateLimit::hit('login', $ip, $max, $lock);
        if ($wait > 0) {
            Audit::log('auth.rate_limited', ['email' => self::mask($email)]);
            return ['ok' => false, 'user' => null, 'error' => I18n::t('admin.err_rate'), 'wait' => $wait];
        }

        $user = UserRepository::findByEmail($email);

        // Comparaison factice : le temps de réponse ne doit pas révéler
        // si l'adresse existe.
        if ($user === null) {
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=2$' . base64_encode(random_bytes(16))
                . '$' . base64_encode(random_bytes(32)));
            Audit::log('auth.unknown_email', ['email' => self::mask($email)]);
            return ['ok' => false, 'user' => null, 'error' => I18n::t('admin.err_credentials'), 'wait' => 0];
        }

        if (!($user['active'] ?? true)) {
            return ['ok' => false, 'user' => null, 'error' => I18n::t('admin.err_disabled'), 'wait' => 0];
        }

        $current = (string) ($user['password'] ?? '');
        $legacy = (string) ($user['password_legacy'] ?? '');
        $verified = false;
        $needsRehash = false;

        if ($current !== '' && password_verify($password, $current)) {
            $verified = true;
            $needsRehash = password_needs_rehash($current, PASSWORD_ARGON2ID, self::argonOptions());
        } elseif ($legacy !== '' && LegacyPassword::verify($password, $legacy)) {
            $verified = true;
            $needsRehash = true;   // conversion vers Argon2id
        }

        if (!$verified) {
            Audit::log('auth.bad_password', ['user' => $user['id'], 'email' => self::mask($email)]);
            return ['ok' => false, 'user' => null, 'error' => I18n::t('admin.err_credentials'), 'wait' => 0];
        }

        if ($needsRehash) {
            $user['password'] = self::hash($password);
            $user['password_legacy'] = '';
            Audit::log('auth.password_upgraded', ['user' => $user['id']], (int) $user['id']);
        }

        $user['last_login_at'] = date('c');
        UserRepository::save($user);
        RateLimit::clear('login', $ip);

        Session::regenerate();
        Session::set(self::SESSION_KEY, [
            'id'    => $user['id'],
            'email' => $user['email'],
            'name'  => $user['display_name'],
            'role'  => $user['role'],
            'since' => time(),
        ]);
        Audit::log('auth.login', ['user' => $user['id'], 'role' => $user['role']], (int) $user['id']);

        return ['ok' => true, 'user' => $user, 'error' => '', 'wait' => 0];
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user !== null) {
            Audit::log('auth.logout', ['user' => $user['id']], (int) $user['id']);
        }
        Regie::forget();
        Session::destroy();
    }

    /** @return array{id:mixed,email:string,name:string,role:string,since:int}|null */
    public static function user(): ?array
    {
        $user = Session::get(self::SESSION_KEY);
        return is_array($user) ? $user : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::argonOptions());
    }

    private static function argonOptions(): array
    {
        return [
            'memory_cost' => (int) Config::get('security.argon.memory_cost', 65536),
            'time_cost'   => (int) Config::get('security.argon.time_cost', 4),
            'threads'     => (int) Config::get('security.argon.threads', 2),
        ];
    }

    /* ------------------------------------------- récupération de mot de passe */

    private static function tokenDir(): string
    {
        $dir = Config::path('data') . '/private/auth';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * Crée un lien de récupération à usage unique, valable 30 minutes.
     * Seule l'empreinte du jeton est écrite sur disque : le jeton lui-même
     * ne vit que dans l'e-mail envoyé.
     *
     * @return string le jeton en clair, ou '' si l'adresse est inconnue
     */
    public static function createResetToken(string $email, string $ip): string
    {
        if (RateLimit::hit('reset', $ip, 5, 3600) > 0) {
            return '';
        }

        $user = UserRepository::findByEmail($email);
        if ($user === null || !($user['active'] ?? true)) {
            // On ne révèle pas si l'adresse existe : l'appelant affiche
            // toujours le même message.
            Audit::log('auth.reset_unknown', ['email' => self::mask($email)]);
            return '';
        }

        $token = bin2hex(random_bytes(32));
        $ttl = (int) Config::get('security.reset_ttl', 1800);

        Json::write(self::tokenDir() . '/' . hash('sha256', $token) . '.json', [
            'user_id'    => $user['id'],
            'created_at' => time(),
            'expires_at' => time() + $ttl,
            'used'       => false,
        ]);

        self::pruneTokens();
        Audit::log('auth.reset_requested', ['user' => $user['id']], (int) $user['id']);

        return $token;
    }

    /** @return array|null l'utilisateur si le jeton est valide */
    public static function checkResetToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $file = self::tokenDir() . '/' . hash('sha256', $token) . '.json';
        $state = Json::read($file);

        if ($state === [] || !empty($state['used']) || (int) $state['expires_at'] < time()) {
            return null;
        }
        return UserRepository::find((string) $state['user_id']);
    }

    /** Consomme le jeton et écrit le nouveau mot de passe. */
    public static function consumeResetToken(string $token, string $password): bool
    {
        $user = self::checkResetToken($token);
        if ($user === null || mb_strlen($password) < 10) {
            return false;
        }

        $file = self::tokenDir() . '/' . hash('sha256', $token) . '.json';
        $state = Json::read($file);
        $state['used'] = true;
        $state['used_at'] = time();
        Json::write($file, $state);

        $user['password'] = self::hash($password);
        $user['password_legacy'] = '';
        $user['must_reset'] = false;
        UserRepository::save($user);

        Audit::log('auth.password_reset', ['user' => $user['id']], (int) $user['id']);
        return true;
    }

    /** Supprime les jetons expirés : le dossier ne s'accumule pas. */
    private static function pruneTokens(): void
    {
        foreach (glob(self::tokenDir() . '/*.json') ?: [] as $file) {
            $state = Json::read($file);
            if ($state === [] || (int) ($state['expires_at'] ?? 0) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    /** « f****@example.com » — le journal ne contient jamais l'adresse entière. */
    private static function mask(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) {
            return '***';
        }
        return substr($email, 0, 1) . str_repeat('*', max(1, $at - 1)) . substr($email, $at);
    }
}
