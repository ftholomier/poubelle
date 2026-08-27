<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Jetons anti-CSRF pour tous les formulaires du back-office.
 */
final class Csrf
{
    public static function jeton(): string
    {
        $jeton = Session::get('_csrf');
        if (!is_string($jeton) || $jeton === '') {
            $jeton = bin2hex(random_bytes(32));
            Session::set('_csrf', $jeton);
        }
        return $jeton;
    }

    /**
     * Champ caché à insérer dans chaque formulaire.
     */
    public static function champ(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::jeton()) . '">';
    }

    public static function verifier(): bool
    {
        return self::verifierJeton($_POST['_csrf'] ?? '');
    }

    /**
     * Vérifie un jeton reçu autrement que par un formulaire classique.
     *
     * Une requête au format JSON ne remplit pas $_POST : le jeton arrive
     * alors dans le corps ou dans un en-tête, et c'est l'appelant qui sait
     * l'y prendre.
     */
    public static function verifierJeton(mixed $recu): bool
    {
        $attendu = Session::get('_csrf');

        return is_string($recu) && is_string($attendu)
            && $attendu !== '' && hash_equals($attendu, $recu);
    }
}
