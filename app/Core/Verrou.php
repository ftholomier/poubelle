<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Verrou optimiste sur les fichiers de contenu.
 *
 * Le contenu vit en JSON et s'écrit par fichier temporaire puis rename() :
 * aucun lecteur ne peut donc voir un fichier à moitié écrit. Mais rien
 * n'empêchait, jusqu'ici, la perte silencieuse d'une saisie — la secrétaire
 * ouvre l'éditeur de la page « Déchets », un élu l'ouvre aussi, elle
 * enregistre, il enregistre : son formulaire à lui a été construit avant, il
 * réécrit donc l'état d'avant, et le travail d'elle disparaît sans qu'aucune
 * erreur ne s'affiche. La sauvegarde automatique de Content::save() rend la
 * perte récupérable, encore faut-il s'en apercevoir.
 *
 * D'où ce verrou. Il ne verrouille rien au sens strict : il constate.
 *
 *   · à l'affichage d'un écran d'administration (une requête GET), chaque
 *     contenu lu laisse son empreinte dans la session de celui qui regarde ;
 *   · à l'enregistrement (une requête POST), Content::save() compare
 *     l'empreinte du fichier tel qu'il est maintenant à celle qui avait été
 *     relevée. Si elles diffèrent, quelqu'un d'autre a écrit entre-temps :
 *     l'écriture est refusée et l'administrateur est invité à recharger.
 *
 * Ce découpage GET / POST est ce qui rend le dispositif invisible : aucun
 * formulaire n'a de champ à porter, aucun contrôleur n'a d'appel à passer.
 * Il tient à une seule condition — ne jamais noter d'empreinte pendant un
 * POST, sans quoi la lecture que fait le contrôleur juste avant d'écrire
 * rafraîchirait l'empreinte et le verrou ne verrait plus rien.
 *
 * Un contenu jamais lu pendant l'affichage n'a pas d'empreinte, et son
 * écriture passe : c'est voulu. Renommer un slug ou une photo réécrit une
 * vingtaine de fichiers que l'écran n'a pas montrés, et refuser ces
 * écritures-là bloquerait le back-office sans rien protéger.
 */
final class Verrou
{
    private const CLE = '_verrous';

    /** Relève-t-on les empreintes ? Vrai sur l'affichage d'un écran, faux ailleurs. */
    private static bool $releve = false;

    /** Vérifie-t-on les empreintes ? Vrai à l'enregistrement. */
    private static bool $controle = false;

    /**
     * Arme le verrou pour la requête en cours.
     *
     * Appelé une seule fois, depuis le routage du back-office : le site
     * public n'écrit aucun contenu et n'a rien à armer.
     */
    public static function armer(string $methode): void
    {
        $ecriture = $methode !== 'GET' && $methode !== 'HEAD';
        self::$releve   = !$ecriture;
        self::$controle = $ecriture;
    }

    /**
     * Empreinte d'un fichier de contenu : sa taille et sa date de
     * modification suffisent à détecter une écriture concurrente, et
     * n'obligent pas à relire le fichier entier.
     *
     * La date seule ne suffirait pas : deux écritures dans la même seconde
     * sont improbables à la main, mais gratuites à distinguer par la taille.
     */
    public static function empreinte(string $fichier): string
    {
        if (!is_file($fichier)) {
            return '';
        }
        clearstatcache(true, $fichier);

        return (string) filemtime($fichier) . ':' . (string) filesize($fichier);
    }

    /**
     * Relève l'empreinte d'un contenu affiché, si la requête s'y prête.
     */
    public static function noter(string $nom, string $empreinte): void
    {
        if (!self::$releve) {
            return;
        }
        $verrous = self::verrous();
        $verrous[$nom] = $empreinte;
        Session::set(self::CLE, $verrous);
    }

    /**
     * Le contenu a-t-il changé depuis qu'il a été affiché ?
     *
     * Rend faux quand il n'y a rien à dire : hors enregistrement, ou pour un
     * contenu dont aucune empreinte n'a été relevée.
     */
    public static function perime(string $nom, string $empreinte): bool
    {
        if (!self::$controle) {
            return false;
        }
        $verrous = self::verrous();

        return isset($verrous[$nom]) && $verrous[$nom] !== $empreinte;
    }

    /**
     * Enregistre la nouvelle empreinte après une écriture réussie.
     *
     * Sans cela, un administrateur qui enregistre deux fois de suite sans
     * recharger sa page se ferait refuser la seconde écriture par sa propre
     * première.
     */
    public static function rafraichir(string $nom, string $empreinte): void
    {
        $verrous = self::verrous();
        if (!isset($verrous[$nom])) {
            return;
        }
        $verrous[$nom] = $empreinte;
        Session::set(self::CLE, $verrous);
    }

    /**
     * Oublie les empreintes relevées : appelé quand l'administrateur repart
     * d'un écran neuf après un conflit.
     */
    public static function oublier(): void
    {
        Session::set(self::CLE, []);
    }

    /** @return array<string, string> */
    private static function verrous(): array
    {
        $verrous = Session::get(self::CLE);

        return is_array($verrous) ? $verrous : [];
    }
}
