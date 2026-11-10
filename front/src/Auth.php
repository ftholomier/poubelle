<?php

declare(strict_types=1);

/**
 * Comptes du back-office : sessions sécurisées (cookie limité à /admin, HttpOnly, SameSite=Strict),
 * jeton CSRF, limitation des tentatives de connexion, mots de passe hachés.
 * Rôles : « admin » (tout) et « editeur » (contenus, demandes, conversations du bot :
 * aucun accès aux rubriques Réglages, Avis clients et Conversion, ni dans le menu ni par l'adresse ou l'API).
 */
final class Auth
{
    public const ROLES = ['admin' => 'Administrateur', 'editeur' => 'Éditeur'];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_name('rv_admin');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/admin', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) cfg('session_ttl', 7200));
        $dir = cfg('storage') . '/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_start();
        $ttl = (int) cfg('session_ttl', 7200);
        if (!empty($_SESSION['uid']) && (($_SESSION['seen'] ?? 0) < time() - $ttl)) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['seen'] = time();
    }

    public static function users(): array
    {
        return Store::get('users', []);
    }

    public static function hasUsers(): bool
    {
        return count(self::users()) > 0;
    }

    public static function find(string $id): ?array
    {
        foreach (self::users() as $u) {
            if ($u['id'] === $id) {
                return $u;
            }
        }
        return null;
    }

    public static function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        foreach (self::users() as $u) {
            if (mb_strtolower($u['email']) === $email) {
                return $u;
            }
        }
        return null;
    }

    public static function user(): ?array
    {
        self::start();
        $id = (string) ($_SESSION['uid'] ?? '');
        if ($id === '') {
            return null;
        }
        $u = self::find($id);
        // Mot de passe changé depuis l'ouverture de la session : session close
        if (!$u || ($u['pw_at'] ?? '') !== ($_SESSION['pw_at'] ?? '')) {
            $_SESSION = [];
            return null;
        }
        return $u;
    }

    public static function isAdmin(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && ($u['role'] ?? '') === 'admin';
    }

    public static function login(array $u): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        $_SESSION['pw_at'] = $u['pw_at'] ?? '';
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        self::updateUser($u['id'], ['last_login' => date('c')]);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_regenerate_id(true);
        session_destroy();
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(): void
    {
        $t = (string) ($_SERVER['HTTP_X_CSRF'] ?? ($_POST['_csrf'] ?? ''));
        if ($t === '' || !hash_equals(self::csrf(), $t)) {
            Http::error('Session expirée : rechargez la page.', 419);
        }
    }

    /** $temp : mot de passe provisoire donné par un administrateur, gardé lisible sur la fiche du compte. */
    public static function create(string $email, string $name, string $password, string $role = 'editeur', bool $temp = false): array
    {
        $u = [
            'id' => bin2hex(random_bytes(6)),
            'email' => mb_strtolower(trim($email)),
            'name' => trim($name),
            'role' => isset(self::ROLES[$role]) ? $role : 'editeur',
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'pw_at' => date('c'),
            'created' => date('c'),
        ];
        if ($temp) {
            $u['pw_temp'] = self::sealTemp($password);
            $u['pw_temp_at'] = date('c');
        }
        Store::update('users', function (array $all) use ($u) {
            $all[] = $u;
            return $all;
        });
        return $u;
    }

    public static function updateUser(string $id, array $changes): void
    {
        if (isset($changes['password'])) {
            $changes['hash'] = password_hash((string) $changes['password'], PASSWORD_DEFAULT);
            $changes['pw_at'] = date('c');
            // Donné par un administrateur ('_temp') : gardé lisible (chiffré) sur la fiche du compte.
            // Choisi par la personne elle-même : plus aucune copie lisible n'est gardée.
            $temp = !empty($changes['_temp']);
            $changes['pw_temp'] = $temp ? self::sealTemp((string) $changes['password']) : null;
            $changes['pw_temp_at'] = $temp ? date('c') : null;
            unset($changes['password'], $changes['_temp']);
        }
        Store::update('users', function (array $all) use ($id, $changes) {
            foreach ($all as &$u) {
                if ($u['id'] === $id) {
                    $u = array_replace($u, $changes);
                    foreach (['pw_temp', 'pw_temp_at'] as $k) {
                        if (array_key_exists($k, $u) && $u[$k] === null) {
                            unset($u[$k]);
                        }
                    }
                }
            }
            return $all;
        });
    }

    public static function deleteUser(string $id): void
    {
        Store::update('users', fn(array $all) => array_values(array_filter($all, fn($u) => $u['id'] !== $id)));
    }

    /**
     * Mot de passe provisoire : chiffré (AES-256-GCM, clé dérivée du secret du site) pour que l'administrateur
     * puisse le relire et le transmettre, jusqu'à ce que la personne choisisse le sien (la copie est alors effacée).
     */
    private static function tempKey(): string
    {
        return hash('sha256', 'pw-temp|' . app_secret(), true);
    }

    private static function sealTemp(string $pw): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $c = openssl_encrypt($pw, 'aes-256-gcm', self::tempKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $c);
    }

    /** Mot de passe provisoire en clair ('' s'il n'y en a pas ou s'il a été remplacé par la personne). */
    public static function tempPassword(array $u): string
    {
        $raw = base64_decode((string) ($u['pw_temp'] ?? ''), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::tempKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $p === false ? '' : $p;
    }

    public static function passwordError(string $pw): ?string
    {
        if (mb_strlen($pw) < 10) {
            return 'Le mot de passe doit contenir au moins 10 caractères.';
        }
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            return 'Le mot de passe doit contenir des lettres et des chiffres.';
        }
        return null;
    }

    /** Clé d'installation : config/config.local.php (setup_key) ou fichier généré dans storage/data. */
    public static function setupKey(): string
    {
        $k = trim((string) cfg('setup_key', ''));
        if ($k !== '') {
            return $k;
        }
        $f = cfg('storage') . '/data/setup-key.txt';
        if (!is_file($f)) {
            @file_put_contents($f, bin2hex(random_bytes(12)), LOCK_EX);
            @chmod($f, 0600);
        }
        return trim((string) @file_get_contents($f));
    }

    /** Jeton de réinitialisation du mot de passe (valable 1 heure). */
    /** Lien de réinitialisation : valable $ttl secondes et une seule fois (il expire dès que le mot de passe change). */
    public static function resetToken(array $u, int $ttl = 3600): string
    {
        $exp = time() + $ttl;
        $sig = hash_hmac('sha256', $u['id'] . '|' . $exp . '|' . ($u['pw_at'] ?? ''), app_secret());
        return $u['id'] . '.' . $exp . '.' . substr($sig, 0, 40);
    }

    public static function checkResetToken(string $t): ?array
    {
        if (!preg_match('/^([0-9a-f]{12})\.(\d{10})\.([0-9a-f]{40})$/', $t, $m) || (int) $m[2] < time()) {
            return null;
        }
        $u = self::find($m[1]);
        if (!$u) {
            return null;
        }
        $sig = substr(hash_hmac('sha256', $u['id'] . '|' . $m[2] . '|' . ($u['pw_at'] ?? ''), app_secret()), 0, 40);
        return hash_equals($sig, $m[3]) ? $u : null;
    }
}
