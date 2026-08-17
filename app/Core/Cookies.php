<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Catégories de cookies soumises au consentement.
 *
 * Rien n'est déposé tant que le visiteur n'a pas choisi : les scripts de
 * mesure et les contenus externes sont écrits en <script type="text/plain">
 * et ne sont activés qu'après accord, catégorie par catégorie.
 */
final class Cookies
{
    /** Nom du cookie qui mémorise le choix. Première partie, sans traceur. */
    public const COOKIE = 'ef_consentement';

    /**
     * Durée de conservation du choix. La CNIL demande de ne pas dépasser
     * treize mois ; six mois laissent au visiteur l'occasion de se raviser.
     */
    public const DUREE_JOURS = 180;

    /**
     * Incrémenter cette version redemande son accord à tout le monde —
     * à faire quand une nouvelle catégorie apparaît.
     */
    public const VERSION = 1;

    /**
     * @return array<string, array{titre: string, texte: string, obligatoire?: bool}>
     */
    public static function categories(): array
    {
        return [
            'necessaires' => [
                'titre'       => 'Cookies nécessaires',
                'obligatoire' => true,
                'texte'       => 'Indispensables au fonctionnement du site : ils gardent la trace '
                    . 'de votre session, sécurisent les formulaires et mémorisent le choix que vous '
                    . 'faites ici. Ils ne servent à aucun suivi et ne peuvent pas être désactivés.',
            ],
            'mesure' => [
                'titre' => 'Mesure d’audience',
                'texte' => 'Nous aident à comprendre les pages consultées et à améliorer le site. '
                    . 'Les statistiques sont globales : elles ne permettent pas de vous identifier.',
            ],
            'externes' => [
                'titre' => 'Contenus externes',
                'texte' => 'Plan d’accès, vidéos et contenus hébergés par d’autres sites. Les '
                    . 'afficher permet à ces sites de déposer leurs propres cookies. Refusés, '
                    . 'ces contenus sont remplacés par un bouton qui vous laisse décider au cas par cas.',
            ],
        ];
    }

    /**
     * Catégories réglables, hors celles qui sont toujours actives.
     *
     * @return string[]
     */
    public static function reglables(): array
    {
        return array_keys(array_filter(
            self::categories(),
            static fn(array $c): bool => empty($c['obligatoire'])
        ));
    }

    /**
     * Choix déjà exprimé, lu depuis le cookie.
     *
     * @return array<string, bool>|null null si aucun choix valable
     */
    public static function choix(): ?array
    {
        $brut = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($brut) || $brut === '') {
            return null;
        }

        $lu = json_decode($brut, true);
        if (!is_array($lu) || (int) ($lu['v'] ?? 0) !== self::VERSION) {
            return null;
        }

        $choix = [];
        foreach (self::reglables() as $cle) {
            $choix[$cle] = (bool) ($lu[$cle] ?? false);
        }

        return $choix;
    }

    /**
     * Le visiteur a-t-il accepté cette catégorie ?
     */
    public static function accepte(string $categorie): bool
    {
        return (bool) (self::choix()[$categorie] ?? false);
    }
}
