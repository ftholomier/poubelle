<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Une adresse saisie au back-office, ramenée à ce qu'un `href` peut porter.
 *
 * **Pourquoi cette classe existe.** Le contrôle existait — dans
 * `EditionController`, pour le menu seul — et nulle part ailleurs. Les fiches
 * de démarche, les blocs de page et les coordonnées d'hébergeur enregistraient
 * l'adresse telle quelle, et les gabarits la rendaient telle quelle. Un
 * `javascript:…` posé dans un champ d'édition devenait donc un lien exécutable
 * sur le site public : une faille stockée, ouverte à quiconque a — ou obtient
 * — le compte d'administration.
 *
 * Le principe retenu tient en une phrase : **une seule fonction décide, et
 * elle décide deux fois** — à l'écriture, pour que le fichier de contenu ne
 * porte jamais rien d'exécutable, et au rendu, pour que le contenu écrit avant
 * cette classe (ou modifié à la main dans l'Éditeur avancé) soit rattrapé.
 * Filtrer à un seul des deux endroits laisse toujours une porte : filtrer à
 * l'écriture seule ne nettoie pas l'existant, filtrer au rendu seul laisse le
 * fichier contaminé et l'assistant le lit.
 *
 * Ce qui passe :
 *   · une adresse interne — `/demarches`, `contact`, `#ancre` ;
 *   · `http://` et `https://`, si l'adresse est bien formée ;
 *   · `mailto:` et `tel:`, mais seulement là où le champ les attend.
 *
 * Tout le reste devient la chaîne vide, jamais `#` ni `/` : un lien qui
 * disparaît se remarque et se corrige ; un lien qui reste et ne fait rien
 * s'oublie.
 */
final class Adresse
{
    /**
     * @param bool $contact autorise « mailto: » et « tel: » — vrai pour un
     *                      bouton d'appel, faux pour un lien de menu ou de bloc
     */
    public static function nettoyer(string $brut, bool $contact = false): string
    {
        $brut = trim($brut);
        if ($brut === '') {
            return '';
        }

        /* Tabulation, saut de ligne et NUL d'abord, où qu'ils soient :
           « java\tscript:alert(1) » n'a pas l'air d'un schéma pour une
           expression régulière, mais le navigateur retire ces caractères de
           l'adresse avant de la suivre — et exécute. Les enlever AVANT de
           regarder le schéma fait la différence entre un filtre et un filtre
           contournable. L'espace, lui, reste : le navigateur ne l'enlève pas,
           et « https://pas valide » doit être refusé comme adresse invalide,
           non recollé en une adresse plausible qui mènerait ailleurs. */
        $brut = trim(str_replace(["\0", "\t", "\n", "\r", "\v", "\f"], '', $brut));
        if ($brut === '') {
            return '';
        }

        // Une ancre ou une adresse relative : rien à décider, rien à exécuter.
        if (str_starts_with($brut, '#')) {
            return $brut;
        }

        if (preg_match('~^([a-z][a-z0-9+.-]*):~i', $brut, $trouve) === 1) {
            $schema = strtolower($trouve[1]);

            if ($schema === 'http' || $schema === 'https') {
                return filter_var($brut, FILTER_VALIDATE_URL) !== false ? $brut : '';
            }
            if ($contact && $schema === 'mailto') {
                $adresse = substr($brut, 7);
                return filter_var($adresse, FILTER_VALIDATE_EMAIL) !== false ? 'mailto:' . $adresse : '';
            }
            if ($contact && $schema === 'tel') {
                $numero = preg_replace('/(?!^\+)[^0-9]/', '', substr($brut, 4)) ?? '';
                return $numero === '' ? '' : 'tel:' . $numero;
            }

            /* javascript:, data:, vbscript:, file:… et tout schéma inventé
               demain. La liste blanche ci-dessus est la seule qui tienne : une
               liste noire se contourne par le schéma qu'on n'a pas prévu. */
            return '';
        }

        /* « //exemple.fr » est une adresse absolue déguisée : le navigateur y
           voit un autre domaine, la relecture humaine y voit un chemin. */
        if (str_starts_with($brut, '//')) {
            return '';
        }

        return '/' . ltrim($brut, '/');
    }

    /**
     * Comme nettoyer(), mais une adresse refusée devient la racine du site.
     *
     * Réservé au menu, où une entrée sans adresse casserait la navigation :
     * mieux vaut un lien qui ramène à l'accueil qu'un `<a href="">` qui
     * recharge la page courante sans que personne comprenne pourquoi.
     */
    public static function interne(string $brut): string
    {
        return self::nettoyer($brut) ?: '/';
    }
}
