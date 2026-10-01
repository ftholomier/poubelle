<?php
declare(strict_types=1);

namespace App;

/**
 * Anciennes adresses du site WordPress (le-signal.com) : 301 vers leur
 * successeur, 410 pour la boutique abandonnée.
 *
 * Règles :
 *  - 301 en un seul saut, directement vers l'adresse finale (jamais de
 *    chaîne de redirections, qui dilue le référencement acquis) ;
 *  - 410 Gone pour /panier/, /commander/ et /mon-compte/ : sans successeur,
 *    une redirection vers l'accueil serait traitée en « soft 404 » par
 *    Google et traînerait des mois dans l'index ;
 *  - les fiches produit se redirigent vers la fiche du bureau correspondant,
 *    d'après l'adresse d'origine gardée dans le catalogue (sourceUrl) : si un
 *    bureau change d'identifiant, sa redirection suit ;
 *  - les photos de wp-content/uploads, indexées par Google Images, mènent à
 *    leur version WebP d'après la source enregistrée dans la photothèque.
 *
 * Gérées en PHP plutôt que dans .htaccess : elles marchent sur tout
 * hébergement, y compris sans mod_rewrite, et se testent avec le serveur
 * de développement.
 */
final class Redirects
{
    /** Pages et catégories : ancien chemin → [route, paramètres]. */
    private const PAGES = [
        'location-bureaux-montbeliard-coworking' => ['spaces', []],
        'louer-bureau-coworking-montbeliard-location-bureaux' => ['offices', []],
        'location-bureaux-montbeliard-belfort-aire-urbaine-doubs' => ['contact', []],
        'coworking-montbeliard-belfort-location-bureau-prive-openspace' => ['legal', []],
        'coworking-montbeliard-le-signal' => ['home', []],
        'categorie-produit/location-louer-bureaux-montbeliard' => ['offices', ['facet' => 'private']],
        'categorie-produit/location-louer-bureaux-coworking-montbeliard' => ['offices', ['facet' => 'openspace']],
        'categorie-produit/louer-bureaux-montbeliard-coworking' => ['offices', ['facet' => 'available']],
        // Page et archive de la présentation audio (extension de lecteur MP3).
        'episode' => ['spaces', []],
        'episode/le-signal-en-74-secondes' => ['spaces', []],
        // Adresses courtes annoncées dans la table de redirections du projet.
        'le-signal' => ['spaces', []],
        'nos-bureaux' => ['offices', []],
        'boutique' => ['offices', []],
        'shop' => ['offices', []],
        'produit' => ['offices', []],
        'categorie-produit' => ['offices', []],
    ];

    /** Boutique WooCommerce et flux sans successeur : 410 Gone. */
    private const GONE = ['panier', 'commander', 'mon-compte', 'feed', 'comments/feed', 'wp-login.php', 'wp-admin'];

    /**
     * @return array{status:int,location:string}|null null si l'adresse n'est pas une ancienne URL
     */
    public static function resolve(string $path): ?array
    {
        $path = strtolower(trim(rawurldecode($path), '/'));
        if ($path === '') {
            return null;
        }

        foreach (self::GONE as $gone) {
            if ($path === $gone || str_starts_with($path, $gone . '/')) {
                return ['status' => 410, 'location' => ''];
            }
        }

        if (isset(self::PAGES[$path])) {
            [$route, $params] = self::PAGES[$path];
            return self::to(Router::url($route, Config::DEFAULT_LANG, $params));
        }

        // Ancienne fiche produit : /produit/<slug>/
        if (str_starts_with($path, 'produit/')) {
            foreach (Offices::all() as $office) {
                $source = strtolower(trim((string) parse_url((string) ($office['sourceUrl'] ?? ''), PHP_URL_PATH), '/'));
                if ($source !== '' && $source === $path) {
                    return self::to(Router::url('office', Config::DEFAULT_LANG, ['id' => (string) $office['id']]));
                }
            }
            return self::to(Router::url('offices', Config::DEFAULT_LANG));
        }

        // Ancienne fiche bureau du projet (/nos-bureaux/<id>) : même identifiant.
        if (str_starts_with($path, 'nos-bureaux/')) {
            $id = substr($path, \strlen('nos-bureaux/'));
            return self::to(Offices::find($id) !== null
                ? Router::url('office', Config::DEFAULT_LANG, ['id' => $id])
                : Router::url('offices', Config::DEFAULT_LANG));
        }

        if (str_starts_with($path, 'wp-content/uploads/')) {
            return self::media($path);
        }
        return null;
    }

    /** Photo ou fichier de l'ancienne médiathèque, y compris ses vignettes « -800x768 ». */
    private static function media(string $path): ?array
    {
        $relative = substr($path, \strlen('wp-content/uploads/'));
        $candidates = [$relative, (string) preg_replace('/-\d+x\d+(\.[a-z0-9]+)$/', '$1', $relative)];
        foreach (Media::all() as $item) {
            $source = strtolower((string) ($item['source'] ?? ''));
            $pos = strpos($source, '/wp-content/uploads/');
            if ($pos === false) {
                continue;
            }
            $old = substr($source, $pos + \strlen('/wp-content/uploads/'));
            if (\in_array($old, $candidates, true)) {
                return self::to(Config::basePath() . (string) $item['path']);
            }
        }
        $assets = [
            '2023/12/lesignal.svg' => '/assets/img/lesignal.svg',
            '2023/12/lesignal_b.png' => '/assets/img/lesignal-blanc.png',
            '2023/12/favicon.png' => '/assets/img/favicon.png',
            '2023/12/cropped-favicon.png' => '/assets/img/favicon-512.png',
        ];
        foreach ($candidates as $candidate) {
            if (isset($assets[$candidate])) {
                return self::to(Config::basePath() . $assets[$candidate]);
            }
        }
        if (str_ends_with($relative, '.mp3')) {
            $src = (string) (Content::settings()['audio']['src'] ?? '');
            if ($src !== '') {
                return self::to(Config::basePath() . $src);
            }
        }
        return null;
    }

    /** @return array{status:int,location:string} */
    private static function to(string $location): array
    {
        return ['status' => 301, 'location' => $location];
    }
}
