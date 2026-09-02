<?php

declare(strict_types=1);

namespace App\Admin;

/**
 * Authentification du back-office : une adresse électronique et un mot de passe.
 *
 * Sans base de données, l'identifiant et l'empreinte du mot de passe vivent
 * dans var/admin.json, hors de la racine web. Le fichier se crée en ligne de
 * commande :
 *
 *     php tools/admin-password.php frederic@exemple.fr
 *
 * Tant qu'il n'existe pas, le back-office reste fermé : aucune page publique
 * ne permet de créer ce premier compte, sans quoi le premier venu pourrait
 * s'en emparer sur un site fraîchement mis en ligne.
 */
final class Auth
{
    private const SESSION_KEY = 'admin_authenticated';
    private const CSRF_KEY = 'admin_csrf';

    /** Au-delà, l'adresse est bloquée temporairement. */
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900;

    /** Déconnexion automatique après cette durée d'inactivité. */
    private const IDLE_TIMEOUT = 7200;

    public static function credentialsFile(): string
    {
        return APP_ROOT . '/var/admin.json';
    }

    public static function isConfigured(): bool
    {
        $data = self::readCredentials();

        return isset($data['email'], $data['hash'])
            && is_string($data['email']) && $data['email'] !== ''
            && is_string($data['hash']) && $data['hash'] !== '';
    }

    /** Identifiant enregistré, pour l'afficher une fois connecté. */
    public static function email(): string
    {
        return (string) (self::readCredentials()['email'] ?? '');
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Les réglages de cookie ne peuvent plus être posés une fois les en-têtes
        // partis — c'est le cas en ligne de commande, où la session sert
        // uniquement de stockage le temps du script.
        if (!headers_sent()) {
            $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                // Le cookie ne doit voyager en clair que si le site l'est aussi.
                'secure'   => $https,
            ]);
            session_name('particules_admin');
            session_start();
            return;
        }

        // Sans en-têtes disponibles, la session ne peut pas s'appuyer sur un cookie.
        @session_start(['use_cookies' => '0', 'use_only_cookies' => '0']);
    }

    public static function isLoggedIn(): bool
    {
        self::startSession();

        if (($_SESSION[self::SESSION_KEY] ?? false) !== true) {
            return false;
        }

        // Une session oubliée sur un poste partagé finit par expirer d'elle-même.
        $last = (int) ($_SESSION['admin_last_seen'] ?? 0);
        if ($last > 0 && time() - $last > self::IDLE_TIMEOUT) {
            self::logout();
            return false;
        }

        $_SESSION['admin_last_seen'] = time();

        return true;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function attempt(string $email, string $password): array
    {
        self::startSession();

        if (!self::isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Aucun compte défini. Lancez : php tools/admin-password.php',
            ];
        }

        $remaining = self::remainingAttempts();
        if ($remaining <= 0) {
            return ['ok' => false, 'message' => 'Trop de tentatives. Réessayez dans quelques minutes.'];
        }

        $data = self::readCredentials();

        // Les deux vérifications sont menées jusqu'au bout, quel que soit le
        // résultat de la première : sans cela, le temps de réponse trahirait
        // qu'une adresse existe. Et le message reste le même dans les deux cas,
        // pour ne pas indiquer laquelle des deux valeurs était fausse.
        $emailOk = hash_equals(self::normalizeEmail((string) $data['email']), self::normalizeEmail($email));
        $passwordOk = password_verify($password, (string) $data['hash']);

        if (!$emailOk || !$passwordOk) {
            self::recordFailure();
            $left = self::remainingAttempts();

            return [
                'ok' => false,
                'message' => $left > 0
                    ? "Identifiants incorrects. Encore {$left} tentative(s)."
                    : 'Trop de tentatives. Réessayez dans quelques minutes.',
            ];
        }

        self::clearFailures();
        // Nouvel identifiant de session à la connexion : parade classique
        // contre la fixation de session.
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = true;
        $_SESSION['admin_last_seen'] = time();

        // Le coût de hachage recommandé augmente avec le temps.
        if (password_needs_rehash((string) $data['hash'], PASSWORD_DEFAULT)) {
            self::storeCredentials((string) $data['email'], $password);
        }

        return ['ok' => true, 'message' => 'Connecté.'];
    }

    /**
     * Une adresse se compare sans tenir compte de la casse ni des espaces
     * autour : on ne refusera pas une connexion pour une majuscule.
     */
    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function storeCredentials(string $email, string $password): void
    {
        $email = self::normalizeEmail($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Adresse électronique invalide : « {$email} »");
        }
        if (mb_strlen($password) < 10) {
            throw new \InvalidArgumentException('Le mot de passe doit faire au moins 10 caractères.');
        }

        $file = self::credentialsFile();
        $payload = json_encode(
            [
                'email'     => $email,
                'hash'      => password_hash($password, PASSWORD_DEFAULT),
                'updatedAt' => date('c'),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $tmp = $file . '.tmp';
        if (file_put_contents($tmp, $payload) === false) {
            throw new \RuntimeException('Écriture impossible dans var/ : vérifiez les droits.');
        }
        // Lisible par le seul propriétaire : l'empreinte n'a rien à faire ailleurs.
        @chmod($tmp, 0600);
        rename($tmp, $file);
    }

    // ------------------------------------------------------------- Jeton CSRF

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::CSRF_KEY];
    }

    public static function checkCsrf(?string $token): bool
    {
        self::startSession();
        $expected = (string) ($_SESSION[self::CSRF_KEY] ?? '');

        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    // ------------------------------------------------- Limitation des essais

    public static function remainingAttempts(): int
    {
        $log = self::readThrottle();
        $entry = $log[self::clientKey()] ?? null;
        if ($entry === null) {
            return self::MAX_ATTEMPTS;
        }
        // La fenêtre de blocage s'efface d'elle-même avec le temps.
        if (time() - (int) $entry['at'] > self::LOCKOUT_SECONDS) {
            return self::MAX_ATTEMPTS;
        }

        return max(0, self::MAX_ATTEMPTS - (int) $entry['count']);
    }

    private static function recordFailure(): void
    {
        $log = self::readThrottle();
        $key = self::clientKey();
        $entry = $log[$key] ?? ['count' => 0, 'at' => 0];

        if (time() - (int) $entry['at'] > self::LOCKOUT_SECONDS) {
            $entry = ['count' => 0, 'at' => 0];
        }

        $log[$key] = ['count' => (int) $entry['count'] + 1, 'at' => time()];
        self::writeThrottle($log);
    }

    private static function clearFailures(): void
    {
        $log = self::readThrottle();
        unset($log[self::clientKey()]);
        self::writeThrottle($log);
    }

    private static function clientKey(): string
    {
        return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnu'));
    }

    /**
     * @return array<string,array{count:int,at:int}>
     */
    private static function readThrottle(): array
    {
        $file = APP_ROOT . '/var/admin-throttle.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string,array{count:int,at:int}> $log
     */
    private static function writeThrottle(array $log): void
    {
        // Les entrées périmées ne servent plus : le fichier ne grossit pas indéfiniment.
        $log = array_filter($log, static fn(array $e): bool => time() - (int) $e['at'] < self::LOCKOUT_SECONDS);
        @file_put_contents(APP_ROOT . '/var/admin-throttle.json', json_encode($log));
    }

    /**
     * @return array<string,mixed>
     */
    private static function readCredentials(): array
    {
        $file = self::credentialsFile();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }
}
