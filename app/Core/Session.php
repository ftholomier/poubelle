<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session PHP durcie : cookie strict, HttpOnly, rotation d'identifiant.
 *
 * Elle ne démarre qu'à la demande. Ouvrir une session sur chaque requête
 * posait un cookie à tous les visiteurs et faisait répondre « no-store » à
 * toutes les pages : ni le navigateur, ni un cache serveur ne pouvait plus
 * rien garder, alors que l'immense majorité des visites est anonyme.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // PHP pose sinon « Cache-Control: no-store » de lui-même.
        session_cache_limiter('');
        session_name((string) Config::get('security.session_name', 'imtt'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        self::$started = true;
    }

    public static function isHttps(): bool
    {
        return Net::isHttps();
    }

    /** Une session est-elle ouverte, ou le visiteur en porte-t-il le cookie ? */
    public static function exists(): bool
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        return isset($_COOKIE[(string) Config::get('security.session_name', 'imtt')]);
    }

    /**
     * Démarre la session seulement si le visiteur en a déjà une. Sert au noyau :
     * un visiteur anonyme traverse le site sans qu'aucun cookie ne soit posé.
     */
    public static function startIfExists(): void
    {
        if (self::exists()) {
            self::start();
        }
    }

    /** Lecture qui n'ouvre pas de session : renvoie le défaut si aucune n'existe. */
    public static function peek(string $key, mixed $default = null): mixed
    {
        if (!self::exists()) {
            return $default;
        }
        return self::get($key, $default);
    }

    /** Message éphémère, lu sans jamais créer de session. */
    public static function peekFlash(string $key): mixed
    {
        return self::exists() ? self::flash($key) : null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /** Message affiché une seule fois (bandeau de confirmation après POST). */
    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $out = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $out;
    }

    /** À appeler après une connexion réussie : évite la fixation de session. */
    public static function regenerate(): void
    {
        self::start();
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli') {
            session_destroy();
        }
        self::$started = false;
    }
}
