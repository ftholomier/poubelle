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

    /**
     * Le jeton d'un formulaire du back-office.
     *
     * **Le refus n'est plus silencieux.** Une trentaine de contrôleurs
     * répondent `if (!Csrf::verifier()) return $this->rediriger();` : l'écran
     * revenait vide, sans un mot, et la mairie n'avait aucun moyen de savoir
     * si son enregistrement était passé. Le cas courant n'est d'ailleurs pas
     * une attaque, c'est une session qui a expiré pendant la rédaction. Le
     * message est posé ici plutôt que dans chaque contrôleur : un seul
     * endroit, et rien à oublier au prochain écran.
     */
    public static function verifier(): bool
    {
        if (self::verifierJeton($_POST['_csrf'] ?? '')) {
            return true;
        }

        Session::flash('erreur', 'Votre enregistrement n’a pas été pris en compte : la session '
            . 'avait expiré. Reconnectez-vous, puis renvoyez l’écran — le navigateur a gardé '
            . 'votre saisie, elle vous est reproposée à l’ouverture de l’écran.');

        return false;
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
