<?php
declare(strict_types=1);

/** Jetons CSRF liés à la session. */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(?string $token): bool
    {
        Session::start();
        $ref = $_SESSION['_csrf'] ?? '';
        return $ref !== '' && is_string($token) && hash_equals($ref, $token);
    }

    public static function guard(): void
    {
        $payload = request_payload();
        $token = $payload['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::check(is_string($token) ? $token : null)) {
            json_out(['ok' => false, 'error' => 'Session expirée, merci de recharger la page.'], 419);
        }
    }
}

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_name('si_sess');
        @session_start();
        self::$started = true;
    }

    public static function flash(?string $msg = null, string $type = 'success'): array
    {
        self::start();
        if ($msg !== null) {
            $_SESSION['_flash'][] = ['type' => $type, 'message' => $msg];
            return [];
        }
        $out = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $out;
    }
}

/**
 * Limitation de débit à fenêtre glissante, un fichier par usage.
 *
 * Séparer les compteurs évite qu'un usage très sollicité (la mesure
 * d'audience) ne fasse réécrire le fichier des tentatives de connexion à
 * chaque requête, et réduit d'autant la contention sur le verrou.
 */
final class RateLimit
{
    public static function dir(): string
    {
        $dir = DATA_DIR . '/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * @param string $bucket usage (login, apply, lead, bot…)
     * @param string $key    identifiant de l'appelant dans cet usage
     */
    public static function hit(string $bucket, int $max = 10, int $window = 600, ?string $key = null): bool
    {
        $bucket = preg_replace('/[^a-z0-9_-]/i', '', $bucket) ?: 'divers';
        $key = $key !== null && $key !== '' ? $key : visitor_hash();
        $file = self::dir() . '/' . $bucket . '.json';
        $now = time();
        $allowed = true;

        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return true;   // ne jamais bloquer un visiteur sur un défaut d'écriture
        }
        try {
            flock($fh, LOCK_EX);
            $raw = stream_get_contents($fh) ?: '';
            $rows = json_decode($raw, true);
            if (!is_array($rows)) { $rows = []; }

            // Purge des entrées expirées : le fichier ne grossit pas indéfiniment.
            foreach ($rows as $k => $stamps) {
                $kept = array_values(array_filter((array) $stamps, static fn ($t) => $t > $now - max($window, 3600)));
                if ($kept) { $rows[$k] = $kept; } else { unset($rows[$k]); }
            }

            $stamps = array_values(array_filter($rows[$key] ?? [], static fn ($t) => $t > $now - $window));
            if (count($stamps) >= $max) {
                $allowed = false;
            } else {
                $stamps[] = $now;
            }
            $rows[$key] = $stamps;

            $json = json_encode($rows, JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, $json);
                fflush($fh);
            }
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
        return $allowed;
    }
}

/** Authentification back-office. */
final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        foreach (Store::read('users') as $u) {
            if (strtolower((string) ($u['email'] ?? '')) === $email && ($u['active'] ?? true)) {
                if (password_verify($password, (string) ($u['password_hash'] ?? ''))) {
                    Session::start();
                    session_regenerate_id(true);
                    $_SESSION['admin'] = [
                        'id' => $u['id'] ?? '',
                        'email' => $u['email'] ?? '',
                        'name' => $u['name'] ?? '',
                        'role' => $u['role'] ?? 'admin',
                        'must_change' => !empty($u['must_change_password']),
                        'since' => time(),
                    ];
                    Store::update('users', (string) ($u['id'] ?? ''), ['last_login' => date('c')]);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Le compte doit-il changer son mot de passe avant toute autre action ?
     * Relu depuis le stockage : une session ouverte avant le changement ne
     * doit pas contourner l'écran obligatoire.
     */
    public static function mustChangePassword(): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        $row = Store::find('users', (string) ($u['id'] ?? ''));
        return $row !== null && !empty($row['must_change_password']);
    }

    public static function user(): ?array
    {
        Session::start();
        $u = $_SESSION['admin'] ?? null;
        if (!is_array($u)) { return null; }
        if (time() - (int) ($u['since'] ?? 0) > ADMIN_SESSION_TTL) {
            self::logout();
            return null;
        }
        return $u;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): array
    {
        $u = self::user();
        if ($u === null) {
            redirect(url('admin/login'));
        }
        return $u;
    }

    public static function logout(): void
    {
        Session::start();
        unset($_SESSION['admin']);
        session_regenerate_id(true);
    }
}
