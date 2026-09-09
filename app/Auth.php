<?php
declare(strict_types=1);

namespace App;

/**
 * Comptes du back-office : connexion, sessions, « rester connecté »,
 * réinitialisation de mot de passe et limitation des tentatives.
 * Stockage : content/users.json, mots de passe hachés en Argon2id.
 */
final class Auth
{
    public const FILE = 'users.json';
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;
    private const SESSION_TTL = 28_800;        // 8 h
    private const REMEMBER_TTL = 604_800;      // 7 jours
    private const REMEMBER_COOKIE = 'ioio_remember';
    private const RESET_TTL = 1_800;           // 30 min

    /** Hash factice valide, comparé quand le compte n'existe pas (temps constant). */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$eDI2VUN0N01HdTNYbHoyVA$GPmUELRZ84hly3eEwLhW5Cxb5aGUcOOTn6CI2KY8FW4';

    public static function isInstalled(): bool
    {
        $users = self::users();
        return $users !== [];
    }

    public static function users(): array
    {
        $data = Store::read(self::FILE);
        $users = \is_array($data['users'] ?? null) ? $data['users'] : [];
        return array_values(array_filter($users, static fn ($u): bool => \is_array($u) && !empty($u['email'])));
    }

    public static function findUser(string $email): ?array
    {
        $email = self::normalize($email);
        foreach (self::users() as $user) {
            if (self::normalize((string) $user['email']) === $email) {
                return $user;
            }
        }
        return null;
    }

    /** Crée le premier compte (écran d'installation) ou un compte supplémentaire. */
    public static function createUser(string $email, string $password, string $role = 'admin', string $name = ''): array
    {
        $email = self::normalize($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Adresse email invalide.'];
        }
        if (mb_strlen($password) < 10) {
            return ['ok' => false, 'error' => 'Le mot de passe doit faire au moins 10 caractères.'];
        }
        if (self::findUser($email) !== null) {
            return ['ok' => false, 'error' => 'Ce compte existe déjà.'];
        }
        $users = self::users();
        $users[] = [
            'email' => $email,
            'name' => $name !== '' ? $name : explode('@', $email)[0],
            'hash' => self::hash($password),
            'role' => \in_array($role, ['admin', 'editor'], true) ? $role : 'editor',
            'createdAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'resetHash' => null,
            'resetExpires' => null,
            'failedAttempts' => 0,
            'lockedUntil' => null,
            'rememberHash' => null,
            'rememberExpires' => null,
        ];
        Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'users' => $users], $email);
        Log::write('auth', 'Compte créé : ' . $email);
        return ['ok' => true];
    }

    public static function updatePassword(string $email, string $password): bool
    {
        if (mb_strlen($password) < 10) {
            return false;
        }
        return self::mutate($email, static function (array $user) use ($password): array {
            $user['hash'] = self::hash($password);
            $user['resetHash'] = null;
            $user['resetExpires'] = null;
            $user['failedAttempts'] = 0;
            $user['lockedUntil'] = null;
            $user['rememberHash'] = null;
            return $user;
        });
    }

    public static function deleteUser(string $email): bool
    {
        $email = self::normalize($email);
        $users = array_values(array_filter(self::users(), static fn (array $u): bool => self::normalize((string) $u['email']) !== $email));
        if ($users === []) {
            return false; // On ne supprime jamais le dernier compte.
        }
        return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'users' => $users], $email);
    }

    /**
     * Tentative de connexion.
     * @return array{ok:bool,error?:string}
     */
    public static function attempt(string $email, string $password, bool $remember = false): array
    {
        $email = self::normalize($email);
        $genericError = 'Identifiants incorrects.';

        if (!RateLimit::allow('login', 15, 900)) {
            Log::write('auth', 'Trop de tentatives depuis ' . RateLimit::ip());
            return ['ok' => false, 'error' => 'Trop de tentatives. Réessayez dans quelques minutes.'];
        }

        $user = self::findUser($email);
        if ($user === null) {
            // Coût constant : on vérifie quand même contre un hash valide,
            // sinon le temps de réponse révélerait qu'aucun compte n'existe.
            password_verify($password, self::DUMMY_HASH);
            Log::write('auth', 'Échec de connexion (compte inconnu) : ' . $email);
            return ['ok' => false, 'error' => $genericError];
        }

        $lockedUntil = strtotime((string) ($user['lockedUntil'] ?? '')) ?: 0;
        if ($lockedUntil > time()) {
            return ['ok' => false, 'error' => 'Compte temporairement bloqué. Réessayez dans ' . (int) ceil(($lockedUntil - time()) / 60) . ' min.'];
        }

        if (!password_verify($password, (string) ($user['hash'] ?? ''))) {
            self::mutate($email, static function (array $u): array {
                $u['failedAttempts'] = (int) ($u['failedAttempts'] ?? 0) + 1;
                if ($u['failedAttempts'] >= self::MAX_ATTEMPTS) {
                    $u['lockedUntil'] = (new \DateTimeImmutable('+' . self::LOCK_MINUTES . ' minutes'))->format(\DATE_ATOM);
                    $u['failedAttempts'] = 0;
                }
                return $u;
            });
            Log::write('auth', 'Échec de connexion : ' . $email);
            return ['ok' => false, 'error' => $genericError];
        }

        if (password_needs_rehash((string) $user['hash'], self::algo(), self::options())) {
            self::mutate($email, static fn (array $u): array => array_replace($u, ['hash' => self::hash($password)]));
        }

        self::mutate($email, static fn (array $u): array => array_replace($u, [
            'failedAttempts' => 0,
            'lockedUntil' => null,
            'lastLoginAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]));

        self::startSession($email);
        if ($remember) {
            self::issueRememberToken($email);
        }
        Log::write('auth', 'Connexion réussie : ' . $email);
        return ['ok' => true];
    }

    private static function startSession(string $email): void
    {
        Session::start();
        session_regenerate_id(true);
        Session::set('user', $email);
        Session::set('user_since', time());
        Session::set('user_ua', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120));
    }

    /** Utilisateur connecté (session ou cookie « rester connecté »), null sinon. */
    public static function user(): ?array
    {
        Session::start();
        $email = Session::get('user');
        $since = (int) Session::get('user_since', 0);

        if (\is_string($email) && $email !== '') {
            if ($since + self::SESSION_TTL < time()) {
                self::logout();
            } elseif (Session::get('user_ua') !== substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120)) {
                self::logout();
            } else {
                return self::findUser($email);
            }
        }
        return self::userFromRememberCookie();
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            header('Location: ' . Router::adminUrl('', ['next' => (string) ($_SERVER['REQUEST_URI'] ?? '')]));
            exit;
        }
        return $user;
    }

    public static function logout(): void
    {
        $email = Session::get('user');
        if (\is_string($email) && $email !== '') {
            self::mutate($email, static fn (array $u): array => array_replace($u, ['rememberHash' => null, 'rememberExpires' => null]));
        }
        self::clearRememberCookie();
        Session::destroy();
    }

    // --------------------------------------------------------- rester connecté

    private static function issueRememberToken(string $email): void
    {
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        self::mutate($email, static fn (array $u): array => array_replace($u, [
            'rememberHash' => hash('sha256', $validator),
            'rememberSelector' => $selector,
            'rememberExpires' => (new \DateTimeImmutable('+7 days'))->format(\DATE_ATOM),
        ]));
        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires' => time() + self::REMEMBER_TTL,
            'path' => Config::basePath() . '/admin/',
            'secure' => Config::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private static function userFromRememberCookie(): ?array
    {
        $cookie = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($cookie === '' || !str_contains($cookie, ':')) {
            return null;
        }
        [$selector, $validator] = explode(':', $cookie, 2);
        foreach (self::users() as $user) {
            if ((string) ($user['rememberSelector'] ?? '') !== $selector || empty($user['rememberHash'])) {
                continue;
            }
            $expires = strtotime((string) ($user['rememberExpires'] ?? '')) ?: 0;
            if ($expires < time() || !hash_equals((string) $user['rememberHash'], hash('sha256', $validator))) {
                self::clearRememberCookie();
                return null;
            }
            self::startSession((string) $user['email']);
            return $user;
        }
        self::clearRememberCookie();
        return null;
    }

    private static function clearRememberCookie(): void
    {
        if (!headers_sent()) {
            setcookie(self::REMEMBER_COOKIE, '', [
                'expires' => time() - 3600,
                'path' => Config::basePath() . '/admin/',
                'secure' => Config::isHttps(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
    }

    // ------------------------------------------------- mot de passe oublié

    /**
     * Génère un lien de réinitialisation. La réponse au visiteur est toujours
     * identique, que le compte existe ou non.
     */
    public static function startPasswordReset(string $email): void
    {
        $user = self::findUser($email);
        if ($user === null) {
            Log::write('auth', 'Demande de réinitialisation pour un compte inconnu : ' . $email);
            return;
        }
        $token = bin2hex(random_bytes(32));
        self::mutate((string) $user['email'], static fn (array $u): array => array_replace($u, [
            'resetHash' => hash('sha256', $token),
            'resetExpires' => (new \DateTimeImmutable('+' . (self::RESET_TTL / 60) . ' minutes'))->format(\DATE_ATOM),
        ]));

        $link = Config::baseUrl() . Router::adminUrl('reset', ['token' => $token, 'email' => (string) $user['email']]);
        Mailer::sendPasswordReset((string) $user['email'], $link);
    }

    public static function checkResetToken(string $email, string $token): bool
    {
        $user = self::findUser($email);
        if ($user === null || empty($user['resetHash'])) {
            return false;
        }
        $expires = strtotime((string) ($user['resetExpires'] ?? '')) ?: 0;
        return $expires > time() && hash_equals((string) $user['resetHash'], hash('sha256', $token));
    }

    public static function finishPasswordReset(string $email, string $token, string $password): bool
    {
        if (!self::checkResetToken($email, $token)) {
            return false;
        }
        $ok = self::updatePassword($email, $password);
        if ($ok) {
            Log::write('auth', 'Mot de passe réinitialisé : ' . $email);
        }
        return $ok;
    }

    // ------------------------------------------------------------------ divers

    private static function mutate(string $email, callable $fn): bool
    {
        $email = self::normalize($email);
        $users = self::users();
        $changed = false;
        foreach ($users as $i => $user) {
            if (self::normalize((string) $user['email']) === $email) {
                $users[$i] = $fn($user);
                $changed = true;
            }
        }
        if (!$changed) {
            return false;
        }
        return Store::write(self::FILE, ['_schema' => Config::SCHEMA, 'users' => $users], $email);
    }

    private static function hash(string $password): string
    {
        return password_hash($password, self::algo(), self::options());
    }

    private static function algo(): string
    {
        return \defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    private static function options(): array
    {
        return \defined('PASSWORD_ARGON2ID')
            ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
            : [];
    }

    private static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
