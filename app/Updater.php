<?php
declare(strict_types=1);

/**
 * Mise à jour du site depuis GitHub, sans Git ni Composer.
 *
 * Sur un hébergement mutualisé sans accès SSH, mettre le code à jour
 * suppose de transférer des dizaines de fichiers par FTP, en espérant
 * n'en oublier aucun. Cette classe télécharge l'archive de la branche
 * depuis GitHub, vérifie qu'elle ressemble bien au projet, sauvegarde
 * l'existant, puis remplace le code.
 *
 * Ce qui est remplacé : les dossiers de code (`app/`, `public/`,
 * `outils/`) et quelques fichiers à la racine. Le dossier `data/` n'est
 * jamais touché : contenu, réglages, candidatures et CV survivent à
 * toutes les mises à jour.
 */
final class Updater
{
    /**
     * Dossiers et fichiers gérés par la mise à jour.
     *
     * Les deux derniers sont des fichiers de `data/` — et seulement
     * eux : ce ne sont pas des données mais les garde-fous qui refusent
     * l'accès web au dossier. S'ils n'étaient jamais mis à jour, un
     * correctif de protection n'atteindrait jamais les sites installés.
     * Le reste de `data/` n'est ni lu ni écrit par la mise à jour.
     */
    public const GERES = [
        'app', 'public', 'outils',
        'README.md', '.gitignore',
        'data/.htaccess', 'data/index.html',
    ];

    /** Nombre de sauvegardes conservées. */
    public const SAUVEGARDES_GARDEES = 3;

    /** Fichiers dont la présence prouve que l'archive est bien le projet. */
    private const TEMOINS = ['app/bootstrap.php', 'public/index.php', 'app/Controllers/SiteController.php'];

    public static function config(): array
    {
        return [
            'repo' => trim((string) settings('update.repo', '')),
            'branch' => trim((string) settings('update.branch', 'main')) ?: 'main',
            'token' => trim((string) settings('update.token', '')),
        ];
    }

    public static function configure(): bool
    {
        $c = self::config();
        return $c['repo'] !== '' && preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $c['repo']) === 1;
    }

    /** Version installée, telle qu'écrite par la dernière mise à jour. */
    public static function versionLocale(): array
    {
        $f = DATA_DIR . '/version.json';
        if (!is_file($f)) {
            return [];
        }
        $d = json_decode((string) @file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    /**
     * Dernier commit de la branche suivie.
     *
     * @return array{ok:bool,sha?:string,date?:string,message?:string,auteur?:string,error?:string}
     */
    public static function versionDistante(): array
    {
        $c = self::config();
        if (!self::configure()) {
            return ['ok' => false, 'error' => 'Dépôt non renseigné dans les réglages.'];
        }
        $url = 'https://api.github.com/repos/' . $c['repo'] . '/commits/' . rawurlencode($c['branch']);
        $res = self::http($url, $c['token']);
        if (!$res['ok']) {
            return $res;
        }
        $d = $res['data'];
        return [
            'ok' => true,
            'sha' => (string) ($d['sha'] ?? ''),
            'date' => (string) ($d['commit']['author']['date'] ?? ''),
            'message' => trim(explode("\n", (string) ($d['commit']['message'] ?? ''))[0]),
            'auteur' => (string) ($d['commit']['author']['name'] ?? ''),
        ];
    }

    /**
     * Télécharge et applique la dernière version.
     *
     * @return array{ok:bool,error?:string,remplaces?:int,supprimes?:int,sauvegarde?:string,sha?:string}
     */
    public static function appliquer(): array
    {
        $c = self::config();
        if (!self::configure()) {
            return ['ok' => false, 'error' => 'Dépôt non renseigné dans les réglages.'];
        }

        $distante = self::versionDistante();
        if (!$distante['ok']) {
            return $distante;
        }

        $travail = DATA_DIR . '/maj-' . bin2hex(random_bytes(4));
        if (!@mkdir($travail, 0775, true)) {
            return ['ok' => false, 'error' => 'Impossible de créer le dossier de travail ' . $travail . '.'];
        }

        try {
            $archive = $travail . '/source.zip';
            $tele = self::telecharger(
                'https://api.github.com/repos/' . $c['repo'] . '/zipball/' . rawurlencode($c['branch']),
                $c['token'],
                $archive
            );
            if (!$tele['ok']) {
                return $tele;
            }

            if (!class_exists('ZipArchive')) {
                return ['ok' => false, 'error' => 'L’extension PHP zip est absente : impossible d’ouvrir l’archive.'];
            }
            $zip = new ZipArchive();
            if ($zip->open($archive) !== true) {
                return ['ok' => false, 'error' => 'Archive illisible (téléchargement incomplet ?).'];
            }
            $extrait = $travail . '/extrait';
            @mkdir($extrait, 0775, true);
            $zip->extractTo($extrait);
            $zip->close();

            // GitHub enferme tout dans un dossier « depot-sha ».
            $racine = self::racineArchive($extrait);
            if ($racine === null) {
                return ['ok' => false, 'error' => 'Archive inattendue : aucun dossier racine trouvé.'];
            }

            // Garde-fou : on ne remplace pas le site par n'importe quoi.
            foreach (self::TEMOINS as $temoin) {
                if (!is_file($racine . '/' . $temoin)) {
                    return ['ok' => false, 'error' => 'Archive incomplète : ' . $temoin . ' est absent. Rien n’a été modifié.'];
                }
            }

            $sauvegarde = self::sauvegarder();
            $bilan = self::synchroniser($racine);

            @file_put_contents(DATA_DIR . '/version.json', json_encode([
                'sha' => $distante['sha'],
                'branche' => $c['branch'],
                'depot' => $c['repo'],
                'message' => $distante['message'],
                'date_commit' => $distante['date'],
                'installee_le' => date('c'),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            self::journaliser(sprintf(
                'Mise à jour vers %s (%s) — %d fichier(s) écrit(s), %d supprimé(s). Sauvegarde : %s',
                substr($distante['sha'], 0, 7),
                $distante['message'],
                $bilan['remplaces'],
                $bilan['supprimes'],
                basename($sauvegarde)
            ));

            return [
                'ok' => true,
                'sha' => $distante['sha'],
                'remplaces' => $bilan['remplaces'],
                'supprimes' => $bilan['supprimes'],
                'sauvegarde' => basename($sauvegarde),
            ];
        } finally {
            self::effacer($travail);
        }
    }

    /** Sauvegardes disponibles, de la plus récente à la plus ancienne. */
    public static function sauvegardes(): array
    {
        $dir = DATA_DIR . '/sauvegardes';
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            $out[] = ['nom' => basename($d), 'date' => (int) @filemtime($d)];
        }
        usort($out, static fn ($a, $b) => $b['date'] <=> $a['date']);
        return $out;
    }

    /** Remet en place une sauvegarde. */
    public static function restaurer(string $nom): array
    {
        $nom = basename($nom);
        $source = DATA_DIR . '/sauvegardes/' . $nom;
        if ($nom === '' || !is_dir($source)) {
            return ['ok' => false, 'error' => 'Sauvegarde introuvable.'];
        }
        $bilan = self::synchroniser($source);
        self::journaliser('Restauration de la sauvegarde ' . $nom . ' — ' . $bilan['remplaces'] . ' fichier(s) remis.');
        return ['ok' => true, 'remplaces' => $bilan['remplaces'], 'supprimes' => $bilan['supprimes']];
    }

    public static function journal(int $lignes = 20): array
    {
        $f = DATA_DIR . '/logs/maj.log';
        if (!is_file($f)) {
            return [];
        }
        $tout = array_filter(explode("\n", (string) @file_get_contents($f)));
        return array_slice(array_reverse($tout), 0, $lignes);
    }

    // ------------------------------------------------------------- interne

    private static function journaliser(string $message): void
    {
        $dir = DATA_DIR . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/maj.log', date('c') . ' — ' . $message . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Copie la version en place dans data/sauvegardes/<horodatage>.
     *
     * Une mise à jour qui tourne mal doit pouvoir être annulée sans
     * reprendre un transfert FTP complet.
     */
    private static function sauvegarder(): string
    {
        $dir = DATA_DIR . '/sauvegardes/' . date('Y-m-d-His');
        @mkdir($dir, 0775, true);
        foreach (self::GERES as $element) {
            $source = APP_ROOT . '/' . $element;
            if (is_dir($source)) {
                self::copierDossier($source, $dir . '/' . $element);
            } elseif (is_file($source)) {
                @copy($source, $dir . '/' . $element);
            }
        }
        // On ne garde pas l'historique complet : le disque d'un mutualisé
        // est compté.
        $existantes = self::sauvegardes();
        foreach (array_slice($existantes, self::SAUVEGARDES_GARDEES) as $vieille) {
            self::effacer(DATA_DIR . '/sauvegardes/' . $vieille['nom']);
        }
        return $dir;
    }

    /**
     * Aligne les dossiers gérés sur le contenu de `$source`.
     *
     * Les fichiers absents de la source sont supprimés : sans cela, un
     * fichier retiré en amont — un `robots.txt` statique devenu une route,
     * par exemple — continuerait d'être servi et masquerait la nouvelle
     * version.
     *
     * @return array{remplaces:int,supprimes:int}
     */
    private static function synchroniser(string $source): array
    {
        $remplaces = 0;
        $supprimes = 0;

        foreach (self::GERES as $element) {
            $de = $source . '/' . $element;
            $vers = APP_ROOT . '/' . $element;

            if (is_file($de)) {
                if (self::remplacer($de, $vers)) {
                    $remplaces++;
                }
                continue;
            }
            if (!is_dir($de)) {
                continue;   // l'élément n'existe pas dans cette version
            }

            $attendus = self::lister($de);
            foreach ($attendus as $relatif) {
                $cible = $vers . '/' . $relatif;
                $dossier = dirname($cible);
                if (!is_dir($dossier)) {
                    @mkdir($dossier, 0775, true);
                }
                if (self::remplacer($de . '/' . $relatif, $cible)) {
                    $remplaces++;
                }
            }

            if (is_dir($vers)) {
                $presents = self::lister($vers);
                foreach (array_diff($presents, $attendus) as $obsolete) {
                    if (@unlink($vers . '/' . $obsolete)) {
                        $supprimes++;
                    }
                }
                self::nettoyerDossiersVides($vers);
            }
        }

        return ['remplaces' => $remplaces, 'supprimes' => $supprimes];
    }

    /**
     * Remplace un fichier de façon atomique.
     *
     * Le site reste en ligne pendant la mise à jour : une requête peut
     * lire un fichier PHP à l'instant où il est réécrit. Une copie
     * directe l'exposerait à un fichier tronqué ; avec un temporaire
     * suivi d'un rename, elle voit soit l'ancienne version, soit la
     * nouvelle, jamais un entre-deux.
     */
    private static function remplacer(string $de, string $vers): bool
    {
        $tmp = $vers . '.maj-' . bin2hex(random_bytes(3));
        if (!@copy($de, $tmp)) {
            return false;
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $vers)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /** @return array<int,string> chemins relatifs des fichiers d'un dossier */
    private static function lister(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $fichier) {
            if ($fichier->isFile() && !str_contains($fichier->getFilename(), '.maj-')) {
                $out[] = ltrim(str_replace($dir, '', $fichier->getPathname()), '/\\');
            }
        }
        sort($out);
        return $out;
    }

    private static function copierDossier(string $de, string $vers): void
    {
        @mkdir($vers, 0775, true);
        foreach (self::lister($de) as $relatif) {
            $cible = $vers . '/' . $relatif;
            if (!is_dir(dirname($cible))) {
                @mkdir(dirname($cible), 0775, true);
            }
            @copy($de . '/' . $relatif, $cible);
        }
    }

    private static function nettoyerDossiersVides(string $dir): void
    {
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $sous) {
            self::nettoyerDossiersVides($sous);
            if ((glob($sous . '/*') ?: []) === [] && (glob($sous . '/.*') ?: ['.', '..']) === []) {
                @rmdir($sous);
            }
        }
    }

    private static function effacer(string $chemin): void
    {
        if (is_file($chemin)) {
            @unlink($chemin);
            return;
        }
        if (!is_dir($chemin)) {
            return;
        }
        foreach (scandir($chemin) ?: [] as $entree) {
            if ($entree === '.' || $entree === '..') {
                continue;
            }
            self::effacer($chemin . '/' . $entree);
        }
        @rmdir($chemin);
    }

    private static function racineArchive(string $extrait): ?string
    {
        foreach (glob($extrait . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (is_file($d . '/app/bootstrap.php')) {
                return $d;
            }
        }
        return is_file($extrait . '/app/bootstrap.php') ? $extrait : null;
    }

    /** Appel JSON à l'API GitHub. */
    private static function http(string $url, string $token): array
    {
        $entetes = [
            'Accept: application/vnd.github+json',
            'User-Agent: SuisseImmo-Updater',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($token !== '') {
            $entetes[] = 'Authorization: Bearer ' . $token;
        }

        $corps = null;
        $statut = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $entetes,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $corps = curl_exec($ch);
            $statut = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $erreur = curl_error($ch);
            curl_close($ch);
            if ($corps === false) {
                return ['ok' => false, 'error' => 'Connexion à GitHub impossible : ' . $erreur];
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'header' => implode("\r\n", $entetes),
                'timeout' => 30,
                'ignore_errors' => true,
            ]]);
            $corps = @file_get_contents($url, false, $ctx);
            foreach ($http_response_header ?? [] as $ligne) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $ligne, $m)) {
                    $statut = (int) $m[1];
                }
            }
            if ($corps === false) {
                return ['ok' => false, 'error' => 'Connexion à GitHub impossible (allow_url_fopen).'];
            }
        }

        if ($statut === 404) {
            return ['ok' => false, 'error' => 'Dépôt ou branche introuvable. Pour un dépôt privé, renseignez un jeton d’accès.'];
        }
        if ($statut === 401 || $statut === 403) {
            return ['ok' => false, 'error' => 'Accès refusé par GitHub (jeton absent, expiré ou sans les droits).'];
        }
        if ($statut < 200 || $statut >= 300) {
            return ['ok' => false, 'error' => 'GitHub a répondu ' . $statut . '.'];
        }

        $data = json_decode((string) $corps, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Réponse de GitHub illisible.'];
        }
        return ['ok' => true, 'data' => $data];
    }

    /** Télécharge un fichier binaire (l'archive de la branche). */
    private static function telecharger(string $url, string $token, string $destination): array
    {
        $entetes = [
            'Accept: application/vnd.github+json',
            'User-Agent: SuisseImmo-Updater',
        ];
        if ($token !== '') {
            $entetes[] = 'Authorization: Bearer ' . $token;
        }

        $fh = @fopen($destination, 'w');
        if ($fh === false) {
            return ['ok' => false, 'error' => 'Impossible d’écrire dans ' . dirname($destination) . '.'];
        }

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_FILE => $fh,
                    CURLOPT_HTTPHEADER => $entetes,
                    CURLOPT_TIMEOUT => 120,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                $ok = curl_exec($ch);
                $statut = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $erreur = curl_error($ch);
                curl_close($ch);
                if ($ok === false) {
                    return ['ok' => false, 'error' => 'Téléchargement interrompu : ' . $erreur];
                }
            } else {
                $ctx = stream_context_create(['http' => [
                    'header' => implode("\r\n", $entetes),
                    'timeout' => 120,
                    'ignore_errors' => true,
                ]]);
                $flux = @fopen($url, 'r', false, $ctx);
                if ($flux === false) {
                    return ['ok' => false, 'error' => 'Téléchargement impossible (allow_url_fopen).'];
                }
                stream_copy_to_stream($flux, $fh);
                fclose($flux);
                $statut = 200;
                foreach ($http_response_header ?? [] as $ligne) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $ligne, $m)) {
                        $statut = (int) $m[1];
                    }
                }
            }
        } finally {
            fclose($fh);
        }

        if ($statut < 200 || $statut >= 300) {
            return ['ok' => false, 'error' => 'GitHub a répondu ' . $statut . ' au téléchargement.'];
        }
        if ((int) @filesize($destination) < 1024) {
            return ['ok' => false, 'error' => 'Archive vide ou tronquée.'];
        }
        return ['ok' => true];
    }
}
