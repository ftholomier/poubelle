<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Les en-têtes de sécurité que le serveur web ne peut pas poser seul.
 *
 * `.htaccess` pose ce qui est constant : nosniff, Referrer-Policy, HSTS.
 * Trois en-têtes lui échappent, et c'est pour cela que cette classe existe.
 *
 * **La CSP porte un nonce**, tiré au sort à chaque requête. Un fichier de
 * configuration Apache ne le connaît pas. Sans nonce, il faudrait
 * `'unsafe-inline'` sur les scripts — ce qui revient à ne pas avoir de CSP,
 * puisque c'est précisément le script injecté dans la page qu'elle doit
 * arrêter.
 *
 * **Les cadres autorisés dépendent de la page.** Le plan d'accès est une
 * `iframe` dont l'adresse est collée par la mairie : elle peut venir de
 * Google, d'OpenStreetMap ou d'ailleurs. Une liste écrite à la main dans un
 * fichier de configuration se périmerait au premier changement, et le plan
 * disparaîtrait sans message ni erreur. Les fragments qui montent une iframe
 * déclarent donc leur hôte par `autoriserCadre()`, et la politique se
 * construit à la fin du rendu, quand on sait ce que la page contient
 * vraiment.
 *
 * **Le back-office ne doit jamais être mis en cache.** Un mutualisé passe
 * souvent derrière un cache partagé, et une page d'administration retenue là
 * s'affiche au visiteur suivant.
 */
final class Entetes
{
    /** Le nonce de la requête, tiré une fois puis rendu à qui le demande. */
    private static string $nonce = '';

    /** @var array<string, true> hôtes des iframes montées par cette page */
    private static array $cadres = [];

    /**
     * Le nonce à poser sur chaque `<script>` et `<style>` écrit dans un
     * gabarit. Seize octets : la recommandation est de 128 bits.
     */
    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    /**
     * Déclare l'hôte d'une iframe que la page va monter.
     *
     * Appelé par le fragment qui rend le cadre, et non par une liste tenue
     * ailleurs : c'est la seule façon que l'autorisation suive le contenu.
     */
    public static function autoriserCadre(string $url): void
    {
        $hote = parse_url(trim($url), PHP_URL_HOST);
        $schema = strtolower((string) parse_url(trim($url), PHP_URL_SCHEME));

        if (is_string($hote) && $hote !== '' && ($schema === 'https' || $schema === 'http')) {
            self::$cadres[$schema . '://' . $hote] = true;
        }
    }

    /**
     * Pose les en-têtes de la réponse. Appelé avant le premier octet écrit :
     * le contrôleur frontal assemble la page en mémoire, puis l'affiche.
     */
    public static function envoyer(string $chemin, Parametres $parametres): void
    {
        if (headers_sent()) {
            return;
        }

        $administration = str_starts_with($chemin, '/admin');

        /* Le back-office et l'API ne sont jamais rejouables : l'un affiche
           des données de la mairie, l'autre répond à une session précise. */
        if ($administration || str_starts_with($chemin, '/api')) {
            header('Cache-Control: no-store, private');
        } else {
            /* Une page publique n'a rien de personnel, mais elle change dès
               qu'une actualité est publiée : « no-cache » autorise le
               navigateur à la garder et l'oblige à la revalider. Sans cette
               ligne, l'absence de toute directive laisserait le navigateur
               deviner une durée — et servir pendant des heures une page que
               la mairie vient de corriger. */
            header('Cache-Control: private, no-cache');
        }

        header('Content-Security-Policy: ' . self::politique($administration, $parametres));
    }

    /**
     * La politique elle-même.
     *
     * Elle n'accorde un hôte tiers que si un réglage l'a réellement allumé :
     * sans identifiant de mesure, Google n'y figure pas ; sans clé Turnstile,
     * Cloudflare non plus. Une politique qui nomme des services éteints
     * apprend à un attaquant ce que le site pourrait charger, et se relâche
     * pour rien.
     */
    private static function politique(bool $administration, Parametres $parametres): string
    {
        $script = ["'self'", "'nonce-" . self::nonce() . "'"];
        $image  = ["'self'", 'data:'];
        $lien   = ["'self'"];
        $cadre  = array_keys(self::$cadres);

        if (!$administration) {
            if (trim((string) $parametres->get('mesure.identifiant', '')) !== '') {
                $script[] = 'https://www.googletagmanager.com';
                $image[]  = 'https://*.google-analytics.com';
                $image[]  = 'https://*.googletagmanager.com';
                $lien[]   = 'https://*.google-analytics.com';
                $lien[]   = 'https://*.analytics.google.com';
                $lien[]   = 'https://*.googletagmanager.com';
            }
            if (trim((string) $parametres->get('antispam.cle_site', '')) !== '') {
                $script[] = 'https://challenges.cloudflare.com';
                $cadre[]  = 'https://challenges.cloudflare.com';
            }
        }

        $directives = [
            "default-src 'self'",
            'script-src ' . implode(' ', $script),
            /* Le nonce et 'unsafe-inline' s'excluent : dès qu'une directive
               porte un nonce, le navigateur ignore 'unsafe-inline'. Or le
               socle a besoin du style en ligne — les jetons de la charte
               posés sur <html>, la photo de bandeau, la hauteur d'une jauge.
               Les styles restent donc en 'unsafe-inline', et c'est le bon
               arbitrage : le danger d'une injection est le script, pas la
               couleur. Toute sortie passe de toute façon par e(). */
            "style-src 'self' 'unsafe-inline'",
            'img-src ' . implode(' ', $image),
            'connect-src ' . implode(' ', $lien),
            "font-src 'self'",
            'frame-src ' . ($cadre === [] ? "'none'" : implode(' ', $cadre)),
            // Un formulaire du site ne poste jamais ailleurs que chez lui :
            // c'est ce qui empêche une injection d'envoyer une saisie dehors.
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            // Doublon volontaire de X-Frame-Options : les navigateurs récents
            // ne lisent plus que celui-ci, les anciens que l'autre.
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }
}
