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
        // Une adresse externe ou un protocole (tel:, mailto:, https:) sort du
        // site : ni préfixe de langue, ni base à lui ajouter. Sans ce garde-fou,
        // un bouton « Nous appeler » réglé sur tel:… deviendrait /tel:….
        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $chemin) === 1) {
            return $chemin;
        }

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

if (!function_exists('jetons')) {
    /**
     * Remplace les jetons d'un texte saisi dans le back-office.
     *
     * `[year]` (ou `[annee]`) devient l'année courante. Écrire l'année en dur
     * dans la mention de copyright oblige à y repenser chaque 1er janvier —
     * et personne n'y repense : c'est ainsi qu'un site affiche 2026 en 2029.
     *
     * Les deux graphies sont acceptées : le champ est rempli en français,
     * mais `[year]` est la forme que l'on croise partout ailleurs.
     */
    function jetons(string $texte): string
    {
        return str_ireplace(['[year]', '[annee]', '[année]'], date('Y'), $texte);
    }
}

if (!function_exists('date_fr')) {
    /**
     * Date en toutes lettres, en français.
     *
     * strftime() est supprimée depuis PHP 8.1 et IntlDateFormatter suppose
     * l'extension intl, absente de bien des mutualisés : la table de mois
     * tient en trois lignes et fonctionne partout.
     */
    function date_fr(int $horodatage, bool $avecJour = false): string
    {
        static $mois = [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ];

        $jour = (int) date('j', $horodatage);
        $libelle = ($avecJour ? $jour . ($jour === 1 ? 'er ' : ' ') : '')
            . $mois[(int) date('n', $horodatage)] . ' ' . date('Y', $horodatage);

        return $libelle;
    }
}

if (!function_exists('tel_lien')) {
    /**
     * Numéro de téléphone en lien composable.
     *
     * Le format international est déduit du numéro saisi : un numéro français
     * commençant par 0 devient +33…, un numéro déjà international est gardé
     * tel quel. Sans cette conversion, un appel depuis l'étranger échoue.
     */
    function tel_lien(string $numero): string
    {
        $chiffres = preg_replace('/(?!^\+)\D+/', '', trim($numero)) ?? '';

        if ($chiffres === '') {
            return '';
        }
        if (str_starts_with($chiffres, '+')) {
            return 'tel:' . $chiffres;
        }
        if (str_starts_with($chiffres, '00')) {
            return 'tel:+' . substr($chiffres, 2);
        }
        if (str_starts_with($chiffres, '0') && strlen($chiffres) === 10) {
            return 'tel:+33' . substr($chiffres, 1);
        }

        return 'tel:' . $chiffres;
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

if (!function_exists('date_texte')) {
    /**
     * Date ISO (« 2023-01-28 ») écrite en toutes lettres.
     *
     * Le contenu range ses dates en ISO parce que c'est la seule forme qui se
     * trie correctement par une simple comparaison de chaînes — c'est ce qui
     * fait remonter la dernière actualité en tête sans code de tri des dates.
     * Elle ne se lit pas pour autant : la conversion se fait à l'affichage.
     *
     * Une date incomplète (« 2023-01 », « 2023 ») est fréquente sur un
     * bulletin trimestriel : elle est rendue telle quelle, sans inventer un
     * jour qui n'a pas été saisi.
     */
    function date_texte(string $iso, bool $avecJourSemaine = false): string
    {
        $iso = trim($iso);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) !== 1) {
            if (preg_match('/^(\d{4})-(\d{2})$/', $iso, $m) === 1) {
                return date_fr((int) mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1]));
            }
            return $iso;
        }

        $horodatage = (int) mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
        $texte = date_fr($horodatage, true);

        if ($avecJourSemaine) {
            static $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
            $texte = $jours[(int) date('w', $horodatage)] . ' ' . $texte;
        }

        return $texte;
    }
}

if (!function_exists('poids')) {
    /**
     * Poids d'un fichier, en unités lisibles.
     *
     * Affiché à côté de chaque téléchargement : un bulletin municipal pèse
     * quatre méga-octets, et l'administré qui consulte depuis un téléphone en
     * fond de vallée a le droit de le savoir avant de lancer le transfert.
     */
    function poids(int $octets): string
    {
        if ($octets >= 1048576) {
            return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
        }
        return max(1, (int) round($octets / 1024)) . ' Ko';
    }
}

if (!function_exists('nom_du_site')) {
    /**
     * Le nom de la collectivité, tel qu'il signe un courriel ou une requête.
     *
     * Il était recopié en dur dans quatre fichiers de code : les deux valeurs
     * de repli du nom d'expéditeur, celle du contrôleur qui l'enregistre, et
     * l'agent annoncé aux services de traduction. Produire le site d'une
     * autre commune demandait donc de les retrouver un par un — et celui
     * qu'on oubliait signait les courriels au nom de la commune précédente.
     *
     * Le nom vit désormais à un seul endroit du code, config/config.php ; le
     * reste passe par ici. Le nom affiché par le site lui-même reste celui de
     * site.json, que la mairie modifie sans toucher au code.
     */
    function nom_du_site(): string
    {
        $nom = trim((string) ($GLOBALS['config']['app']['name'] ?? ''));

        return $nom !== '' ? $nom : 'Mairie';
    }
}

if (!function_exists('riche')) {
    /**
     * Sortie d'un texte mis en forme par la mairie.
     *
     * La seule sortie de ce dépôt qui ne passe pas par `e()`, et elle est
     * volontairement nommée : voir un `riche()` dans un gabarit doit sauter
     * aux yeux autant qu'un `e()` manquant. Le filtrage a lieu à l'affichage
     * et non seulement à l'enregistrement, parce que le contenu peut aussi
     * arriver par l'éditeur JSON avancé ou par un fichier repris à la main —
     * il n'existe donc aucun chemin qui contourne la liste blanche.
     *
     * @param mixed $valeur HTML de l'éditeur, ou ancien tableau de paragraphes
     */
    function riche(mixed $valeur): string
    {
        return App\Core\TexteRiche::nettoyer($valeur);
    }
}
