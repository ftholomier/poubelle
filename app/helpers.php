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

if (!function_exists('dimensions_image')) {
    /**
     * Largeur et hauteur d'une image du site, ou null.
     *
     * `getimagesize()` ne lit que l'en-tête du fichier, quelques octets : une
     * page de douze photos coûte douze petites lectures, et rien ne justifie
     * d'y ajouter un cache sur disque avec ses écritures concurrentes et son
     * invalidation à tenir. Le cache tient à la requête, pour les images qui
     * reviennent.
     *
     * @return array{0: int, 1: int}|null
     */
    function dimensions_image(string $url): ?array
    {
        static $connues = [];

        if (array_key_exists($url, $connues)) {
            return $connues[$url];
        }

        $racine = (string) ($GLOBALS['config']['paths']['public'] ?? '');
        // asset() ajoute un ?v= : le disque ne connaît pas ce suffixe.
        $chemin = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $fichier = $racine . '/' . ltrim($chemin, '/');

        $taille = is_file($fichier) ? @getimagesize($fichier) : false;

        return $connues[$url] = $taille === false
            ? null
            : [(int) $taille[0], (int) $taille[1]];
    }
}

if (!function_exists('balise_image')) {
    /**
     * Une photo du contenu, avec ses dimensions et sa variante WebP.
     *
     * Les dix-huit `<img>` des gabarits étaient écrits à la main, et aucun ne
     * portait `width`/`height` : le navigateur ne connaissait la place à
     * réserver qu'une fois la photo arrivée, et la page sautait sous les
     * doigts du lecteur — c'est ce que mesure le « décalage cumulé » des
     * outils de performance. Les dimensions viennent du fichier lui-même :
     * écrites dans le gabarit, elles auraient menti au premier recadrage.
     *
     * Le `<picture>` sert le WebP à qui le comprend et le JPEG aux autres,
     * pour un quart à un tiers d'octets en moins. Il est neutralisé en CSS
     * par `picture { display: contents }` : sans cela il deviendrait
     * lui-même l'élément de flex ou de grille, et l'image cesserait de
     * remplir sa carte.
     *
     * @param array{vignette?: bool, classe?: string, chargement?: string,
     *              priorite?: bool, attributs?: string} $options
     */
    function balise_image(?string $chemin, string $alt, array $options = []): string
    {
        $url = image($chemin, (bool) ($options['vignette'] ?? false));
        $mesure = dimensions_image($url);

        $attributs = ' src="' . e($url) . '" alt="' . e($alt) . '"';
        if ($mesure !== null) {
            $attributs .= ' width="' . $mesure[0] . '" height="' . $mesure[1] . '"';
        }
        if (($options['classe'] ?? '') !== '') {
            $attributs .= ' class="' . e((string) $options['classe']) . '"';
        }
        /* La première image d'une page ne doit PAS être différée : elle est
           celle que le lecteur attend, et `loading="lazy"` la ferait partir
           après le reste. D'où le choix explicite à chaque appel. */
        $attributs .= ' loading="' . (($options['chargement'] ?? 'lazy') === 'eager' ? 'eager' : 'lazy') . '"';
        $attributs .= ' decoding="async"';
        if (($options['priorite'] ?? false) === true) {
            $attributs .= ' fetchpriority="high"';
        }
        $attributs .= (string) ($options['attributs'] ?? '');

        $webp = webp_de($url);
        if ($webp === null) {
            return '<img' . $attributs . '>';
        }

        return '<picture><source type="image/webp" srcset="' . e($webp) . '">'
            . '<img' . $attributs . '></picture>';
    }
}

if (!function_exists('webp_de')) {
    /**
     * L'adresse WebP d'une photo, si elle a été fabriquée.
     *
     * Elle l'est à l'import par la médiathèque, et par outils/images-webp.php
     * pour les photos déjà en place. Absente, on sert le JPEG : le site ne
     * dépend pas de la présence de la variante, ni du support de GD chez
     * l'hébergeur.
     */
    function webp_de(string $url): ?string
    {
        static $connues = [];

        if (array_key_exists($url, $connues)) {
            return $connues[$url];
        }

        $chemin = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $candidat = preg_replace('/\.(jpe?g|png)$/i', '.webp', $chemin);
        if ($candidat === null || $candidat === $chemin) {
            return $connues[$url] = null;
        }

        $racine = (string) ($GLOBALS['config']['paths']['public'] ?? '');

        return $connues[$url] = is_file($racine . '/' . ltrim($candidat, '/'))
            ? asset(ltrim($candidat, '/'))
            : null;
    }
}

if (!function_exists('precharger_image')) {
    /**
     * Demande le préchargement d'une image, et rend son adresse.
     *
     * Réservé à la photo de bandeau, qui est la plus grande de la page et
     * donc celle sur laquelle se mesure le temps d'affichage perçu. Elle est
     * posée en fond CSS : le navigateur ne la découvre qu'après avoir
     * téléchargé et lu la feuille de style, soit deux allers-retours plus
     * tard qu'une balise `<img>`. Le lien de préchargement la lui annonce
     * dès l'en-tête du document.
     *
     * Rien d'autre ne doit passer par ici : précharger dix images revient à
     * n'en précharger aucune, puisque toutes se disputent alors la même
     * bande passante.
     *
     * Le gabarit rend l'adresse et l'écrit dans son style ; le registre sert
     * à l'en-tête, rendu APRÈS la page — App\Core\View assemble le contenu
     * puis la mise en page, dans cet ordre.
     */
    function precharger_image(?string $chemin): string
    {
        $url = image($chemin);
        if (!isset($GLOBALS['precharges'])) {
            $GLOBALS['precharges'] = [];
        }
        $GLOBALS['precharges'][$url] = true;

        return $url;
    }
}

if (!function_exists('liens_de_precharge')) {
    /** @return string[] les adresses annoncées par la page en cours */
    function liens_de_precharge(): array
    {
        return array_keys((array) ($GLOBALS['precharges'] ?? []));
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
