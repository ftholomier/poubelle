<?php
declare(strict_types=1);

/**
 * Fonctions utilitaires disponibles dans toute l'application et les gabarits.
 */

if (!function_exists('e')) {
    /**
     * Échappement HTML. À utiliser sur TOUTE valeur affichée dans un gabarit.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * URL absolue à partir d'un chemin racine.
     */
    function url(string $path = '/'): string
    {
        static $base;
        $base ??= rtrim($GLOBALS['config']['app']['base_url'] ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * URL d'un asset, avec empreinte de cache basée sur la date du fichier.
     */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = ($GLOBALS['config']['paths']['public'] ?? '') . '/' . $path;
        $version = is_file($file) ? '?v=' . filemtime($file) : '';
        return url($path) . $version;
    }
}

if (!function_exists('origine')) {
    /**
     * Protocole et domaine seuls (https://exemple.fr), sans le chemin de base.
     */
    function origine(): string
    {
        $config = (string) ($GLOBALS['config']['app']['base_url'] ?? '');

        if (str_starts_with($config, 'http')) {
            $parties = parse_url($config) ?: [];
            return ($parties['scheme'] ?? 'https') . '://' . ($parties['host'] ?? 'localhost')
                . (isset($parties['port']) ? ':' . $parties['port'] : '');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return ($https ? 'https://' : 'http://') . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}

if (!function_exists('base_absolue')) {
    /**
     * Racine absolue du site, sous-répertoire d'installation compris : le plan
     * du site et la balise canonical exigent des adresses complètes.
     */
    function base_absolue(): string
    {
        $config = rtrim((string) ($GLOBALS['config']['app']['base_url'] ?? ''), '/');

        return str_starts_with($config, 'http') ? $config : origine() . $config;
    }
}

if (!function_exists('absolu')) {
    /**
     * Rend absolue une adresse déjà produite par url(), asset() ou image().
     */
    function absolu(string $adresse): string
    {
        if ($adresse === '' || str_starts_with($adresse, 'http')) {
            return $adresse;
        }

        return origine() . '/' . ltrim($adresse, '/');
    }
}

if (!function_exists('route')) {
    /**
     * Adresse d'une page du site, slug personnalisé compris.
     *
     * route('contact') plutôt que url('/contact') : le lien suit
     * automatiquement un slug modifié dans le back-office.
     */
    function route(string $cle, ?string $item = null): string
    {
        $seo = $GLOBALS['seo'] ?? null;
        if (!$seo instanceof \App\Core\Seo) {
            return url('/' . trim($cle, '/'));
        }
        return url($seo->chemin($cle, $item));
    }
}

if (!function_exists('lien')) {
    /**
     * Adresse interne enregistrée dans le contenu (menu, boutons), remise
     * dans la langue servie : /hebergements devient /en/hebergements.
     */
    function lien(string $chemin): string
    {
        $seo = $GLOBALS['seo'] ?? null;
        $prefixe = $seo instanceof \App\Core\Seo ? $seo->prefixe() : '';
        $chemin = '/' . ltrim($chemin, '/');

        return url($prefixe === '' ? $chemin : $prefixe . ($chemin === '/' ? '' : $chemin));
    }
}

if (!function_exists('t')) {
    /**
     * Texte d'interface traduit — les mots des gabarits, par opposition au
     * contenu éditorial qui vient de /data.
     *
     * Le français passé en argument est à la fois la clé et la valeur par
     * défaut : une phrase non traduite s'affiche en français plutôt que de
     * disparaître, et rien ne casse quand on en ajoute une.
     */
    function t(string $texte): string
    {
        $traducteur = $GLOBALS['traducteur'] ?? null;
        $langue = $GLOBALS['langue'] ?? \App\Core\Langues::SOURCE;

        if ($langue === \App\Core\Langues::SOURCE || !$traducteur instanceof \App\Core\Traducteur) {
            return $texte;
        }

        return $traducteur->texte($langue, 'interface.' . $texte, $texte);
    }
}

if (!function_exists('image')) {
    /**
     * URL d'une image de contenu, avec repli sur un visuel d'attente.
     *
     * Une fiche créée mais pas encore illustrée, ou une photo retirée de la
     * médiathèque, laisserait sinon une image cassée sur le site comme dans
     * le back-office.
     *
     * @param string|null $chemin  chemin relatif à /public
     * @param bool $vignette       utiliser la version -mini si elle existe
     */
    function image(?string $chemin, bool $vignette = false): string
    {
        $chemin = trim((string) $chemin);
        $racine = $GLOBALS['config']['paths']['public'] ?? '';

        if ($chemin !== '' && $vignette) {
            $mini = preg_replace('/\.(jpe?g|png|webp)$/i', '-mini.jpg', $chemin) ?? $chemin;
            if ($mini !== $chemin && is_file($racine . '/' . $mini)) {
                $chemin = $mini;
            }
        }

        if ($chemin === '' || !is_file($racine . '/' . $chemin)) {
            return asset('assets/img/ui/photo-a-venir.svg');
        }

        return asset($chemin);
    }
}

if (!function_exists('json_response')) {
    /**
     * Réponse JSON pour les points d'entrée d'API.
     */
    function json_response(mixed $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
