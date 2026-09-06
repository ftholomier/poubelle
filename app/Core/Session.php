<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session sécurisée du back-office.
 */
final class Session
{
    public static function demarrer(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        /* « efadmin » venait du site commercial dont ce socle est tiré. Le
           nom d'un cookie se lit dans le navigateur de l'administré : autant
           qu'il dise ce qu'il est. Le changer invalide une fois les sessions
           en cours, ce que DEPLOIEMENT.md signale. */
        /* Deux réglages que PHP ne pose pas comme il faut tout seul.
           `use_strict_mode` fait refuser un identifiant de session que le
           serveur n'a jamais émis : sans lui, une adresse contenant un
           `?mairie_session=…` choisi par un tiers fixe la session de qui
           l'ouvre, et l'attaquant n'a plus qu'à attendre la connexion.
           `gc_maxlifetime` fixe la durée au bout de laquelle PHP jette le
           fichier de session ; sur un mutualisé, il vaut souvent 1440 s, soit
           24 minutes — alors qu'Auth promet deux heures d'inactivité. La
           mairie rédigeait une page pendant une heure, enregistrait, et se
           retrouvait déconnectée. Les deux durées doivent être la même. */
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) Auth::INACTIVITE_SEC);

        session_name('mairie_session');
        session_set_cookie_params([
            // 0 : le cookie meurt avec le navigateur. C'est le fichier de
            // session, réglé juste au-dessus, qui décide de la durée réelle.
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function regenerer(): void
    {
        self::demarrer();
        session_regenerate_id(true);
    }

    public static function detruire(): void
    {
        self::demarrer();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
    }

    public static function get(string $cle, mixed $defaut = null): mixed
    {
        self::demarrer();
        return $_SESSION[$cle] ?? $defaut;
    }

    public static function set(string $cle, mixed $valeur): void
    {
        self::demarrer();
        $_SESSION[$cle] = $valeur;
    }

    public static function oublier(string $cle): void
    {
        self::demarrer();
        unset($_SESSION[$cle]);
    }

    /**
     * Message éphémère affiché sur l'écran suivant (confirmation, erreur).
     */
    public static function flash(string $type, ?string $message = null): ?string
    {
        self::demarrer();
        if ($message !== null) {
            $_SESSION['_flash'][$type] = $message;
            return null;
        }
        $m = $_SESSION['_flash'][$type] ?? null;
        unset($_SESSION['_flash'][$type]);
        return is_string($m) ? $m : null;
    }

    /**
     * Même principe, pour un résultat structuré plutôt qu'une phrase — la
     * liste des fiches Google trouvées, par exemple.
     *
     * @param array<mixed>|null $donnees
     * @return array<mixed>|null
     */
    public static function flashDonnees(string $type, ?array $donnees = null): ?array
    {
        self::demarrer();
        if ($donnees !== null) {
            $_SESSION['_flash'][$type] = $donnees;
            return null;
        }
        $d = $_SESSION['_flash'][$type] ?? null;
        unset($_SESSION['_flash'][$type]);
        return is_array($d) ? $d : null;
    }
}
