<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Activity;

/**
 * Mises à jour en un clic depuis GitHub (Système › Mises à jour, administrateurs).
 *
 * La dernière version de la branche réglée (Réglages › Mises à jour) est comparée à la version
 * installée ; « Appliquer » télécharge l'archive de cette version et ne remplace que le code :
 * app/, bin/, config/, scripts/, templates/, public/ (sauf public/media/). Jamais data/ (fiches,
 * médiathèque, traductions des fiches…), storage/ (réglages, comptes, journaux, coûts IA),
 * public/media/ (vignettes, voix). Deux exceptions prudentes : data/i18n/en.json reçoit
 * seulement les nouveaux libellés (les traductions du serveur sont gardées) et public/.htaccess
 * modifié sur le serveur est gardé. Seuls les fichiers qui ont changé sont écrits.
 *
 * Avant d'écrire, les fichiers remplacés ou supprimés sont sauvegardés
 * (storage/update/sauvegardes/) : « Revenir en arrière » les remet en place. Pendant la copie,
 * le site répond « Mise à jour en cours » (fichier storage/update/maintenance, lu par le Kernel).
 *
 * Synchronisation : à chaque vérification, le code du serveur est comparé fichier par fichier à
 * la dernière version (empreintes Git, sans rien télécharger). Un site mis en ligne par FTP dont
 * le code est identique voit sa version reconnue ; un fichier oublié ou retouché sur le serveur
 * est signalé et remis d'aplomb en un clic.
 *
 * État : storage/update/etat.json (version installée, dernière vérification, historique) ;
 * storage/update/manifeste.json (empreinte CRC32 de chaque fichier installé : sert à supprimer
 * les fichiers retirés du dépôt et à repérer un .htaccess modifié à la main) ;
 * storage/update/arbres/ (liste des fichiers de chaque dossier du code, par empreinte).
 */
final class Updater
{
    /** Dossiers remplacés par une mise à jour (chemins relatifs à la racine du site). */
    public const MANAGED = ['app/', 'bin/', 'config/', 'scripts/', 'templates/', 'public/'];
    /** Jamais touchés, même sous un dossier remplacé. */
    public const EXCLUDED = ['public/media/'];
    /** Gardés s'ils ont été modifiés sur le serveur (réglage fait à la main). */
    public const KEEP_IF_EDITED = ['public/.htaccess'];
    /** Fusionnés : les nouveaux libellés sont ajoutés, les valeurs du serveur gardées. */
    public const MERGED = ['data/i18n/en.json'];
    public const REPO = 'ftholomier/poubelle';
    public const BRANCH = 'claude/sweet-einstein-hawxrw';
    private const MAX_ZIP = 400 * 1024 * 1024;
    private const KEEP_BACKUPS = 5;
    private const CHECK_TTL = 3600;
    /**
     * Caches de données gardés après une mise à jour (index des fiches, données calculées, index
     * de recherche, médiathèque et usage de ses médias : sans lui, les murs de photos restaient vides) : ils servent encore le temps que la requête suivante les refasse
     * en arrière-plan avec le nouveau code. Les effacer faisait attendre le premier visiteur
     * pendant leur calcul (plusieurs secondes). Chemins relatifs au dossier des caches.
     */
    private const KEEP_CACHES = '#^(?:derived\.php|derived/.+|index-\d+\.php|search\.php|media\.php|media-versions\.php|media-usage\.json|media/.+|[^/]+\.lock)$#';
    /** Marque « caches à refaire » posée par la mise à jour, prise par la requête suivante (public/index.php). */
    private const REFRESH = 'apres-mise-a-jour';

    public static string $root = APP_ROOT;
    public static string $dir = STORAGE_PATH . '/update';
    public static string $cacheDir = STORAGE_PATH . '/cache';
    /** Essais : sans écriture dans le journal d'activité. */
    public static bool $log = true;
    /** Essais : fournit l'archive (chemin d'un .zip local) au lieu de la télécharger. */
    public static ?\Closure $fetch = null;
    /** Essais : réponses HTTP simulées (url => [code, corps]). */
    public static ?\Closure $http = null;

    // ------------------------------------------------------------------ réglages

    public static function repo(): string
    {
        $r = trim((string) Settings::get('update.repo', self::REPO));
        return preg_match('#^[\w.-]+/[\w.-]+$#', $r) ? $r : self::REPO;
    }

    public static function branch(): string
    {
        $b = trim((string) Settings::get('update.branch', self::BRANCH));
        return preg_match('#^[\w.-]+(/[\w.-]+)*$#', $b) && !str_contains($b, '..') ? $b : self::BRANCH;
    }

    private static function token(): string
    {
        return trim((string) Settings::get('update.token', ''));
    }

    // ------------------------------------------------------------------ état

    public static function state(): array
    {
        return JsonStore::read(self::$dir . '/etat.json', []) ?: [];
    }

    private static function saveState(array $changes): void
    {
        JsonStore::update(self::$dir . '/etat.json', fn ($s) => array_merge(is_array($s) ? $s : [], $changes), []);
    }

    /** Comparaison fichier par fichier de la dernière vérification (null : à refaire). */
    private static function setSync(?array $sync): void
    {
        JsonStore::update(self::$dir . '/etat.json', function ($s) use ($sync) {
            $s = is_array($s) ? $s : [];
            if (is_array($s['check'] ?? null)) {
                $same = $sync !== null && ($s['check']['latest']['sha'] ?? '') === $sync['sha'];
                $s['check']['sync'] = $same ? $sync : null;
            }
            return $s;
        }, []);
    }

    /** Version installée par cet écran (null : mise en ligne par FTP, version inconnue). */
    public static function installed(): ?array
    {
        $i = self::state()['installed'] ?? null;
        return is_array($i) && !empty($i['sha']) ? $i : null;
    }

    public static function history(): array
    {
        return self::state()['history'] ?? [];
    }

    /**
     * Mise à jour proposée d'après la dernière vérification (sans appel réseau), ou null : une
     * nouvelle version, ou des fichiers du serveur qui diffèrent de la version installée.
     */
    public static function available(): ?array
    {
        $c = self::state()['check'] ?? null;
        $latest = $c['latest'] ?? null;
        if (!$latest || ($c['repo'] ?? '') !== self::repo() . '#' . self::branch()) {
            return null;
        }
        if (($latest['sha'] ?? '') !== (self::installed()['sha'] ?? '')) {
            return $c;
        }
        return self::differing($c) > 0 ? $c : null;
    }

    /** Nombre de fichiers du serveur qui diffèrent de la dernière version (null : pas comparé). */
    public static function differing(?array $c): ?int
    {
        $s = $c['sync'] ?? null;
        return is_array($s) && empty($s['error']) && ($s['sha'] ?? '') === ($c['latest']['sha'] ?? '') ? (int) ($s['count'] ?? 0) : null;
    }

    /**
     * Nouvelle version (version installée connue et dépassée, ou inconnue sans comparaison) ;
     * sinon, ce sont des fichiers du serveur à remettre d'aplomb (envoi FTP incomplet, retouche).
     */
    public static function isNewVersion(array $c): bool
    {
        $inst = self::installed();
        return $inst ? $inst['sha'] !== ($c['latest']['sha'] ?? '') : !self::differing($c);
    }

    /** Phrase du tableau de bord pour une mise à jour proposée. */
    public static function summary(array $c): string
    {
        if (self::isNewVersion($c)) {
            $k = count($c['commits'] ?? []);
            return 'Nouvelle version du site disponible' . ($k ? ' (' . $k . ' changement' . ($k > 1 ? 's' : '') . ')' : '') . ' : l’appliquer en un clic';
        }
        $n = (int) self::differing($c);
        return 'Le code du serveur diffère de GitHub (' . $n . ' fichier' . ($n > 1 ? 's' : '') . ') : le synchroniser en un clic';
    }

    // ------------------------------------------------------------------ vérification

    /**
     * Dernière version de la branche, changements depuis la version installée et comparaison du
     * code du serveur avec cette version (mis en cache une heure, sauf $force). ['at', 'repo',
     * 'latest' => [sha, date, message], 'commits' => [[sha, date, message]…] du plus récent au plus
     * ancien, 'files' => fichiers du code concernés (ou null si inconnu), 'sync' (voir sync()),
     * 'error'].
     */
    public static function check(bool $force = false): array
    {
        $c = self::state()['check'] ?? null;
        $key = self::repo() . '#' . self::branch();
        if (!$force && is_array($c) && ($c['repo'] ?? '') === $key && ($c['at'] ?? 0) > time() - self::CHECK_TTL) {
            return $c;
        }
        $res = ['at' => time(), 'repo' => $key, 'latest' => null, 'commits' => [], 'files' => null, 'sync' => null, 'error' => null];
        try {
            $res['latest'] = self::latest();
            $paths = [];
            $res['sync'] = self::sync($res['latest']['sha'], $paths);
            if (!self::installed() && $res['sync']['error'] === null && $res['sync']['count'] === 0) {
                self::adopt($res['latest'], $paths);
            }
            $inst = self::installed()['sha'] ?? null;
            if ($inst !== $res['latest']['sha']) {
                $ch = $inst ? self::compare($inst, $res['latest']['sha']) : null;
                if ($ch) {
                    $res['commits'] = array_slice($ch['commits'], 0, 60);
                    $res['files'] = $ch['files'];
                } else {
                    $feed = self::feed();
                    $res['commits'] = $inst ? self::until($feed, $inst) : array_slice($feed, 0, 10);
                }
            }
        } catch (\Throwable $e) {
            $res['error'] = $e->getMessage();
        }
        self::saveState(['check' => $res]);
        return $res;
    }

    /** Dernier commit de la branche (API GitHub ; flux Atom si l'API refuse, limite atteinte). */
    private static function latest(): array
    {
        [$code, $body] = self::get(self::api('commits/' . self::ref(self::branch())));
        if ($code === 200 && is_array($j = json_decode($body, true)) && !empty($j['sha'])) {
            return self::commit($j);
        }
        if ($code === 404 || $code === 422) {
            throw new \RuntimeException('Branche « ' . self::branch() . ' » introuvable dans le dépôt ' . self::repo() . ' (Réglages › Mises à jour).');
        }
        $feed = self::feed();
        if (!$feed) {
            throw new \RuntimeException('GitHub ne répond pas (code ' . $code . ').');
        }
        return $feed[0];
    }

    // ------------------------------------------------------------------ synchronisation

    /**
     * Le code du serveur comparé, fichier par fichier, à une version de GitHub, par les empreintes
     * Git des fichiers (rien n'est téléchargé). Les fins de ligne ne comptent pas pour un fichier
     * texte : un logiciel FTP en mode texte les convertit sans rien changer d'autre.
     * ['sha', 'at', 'total' (fichiers comparés), 'count' (à remplacer), 'differ' => [chemin =>
     * 'absent'|'différent'] (200 au plus), 'eol' (identiques aux fins de ligne près), 'kept'
     * (réglés à la main sur le serveur, gardés par les mises à jour), 'error'].
     * $paths reçoit l'état de chaque fichier : 'ok', 'eol', 'absent', 'différent' ou 'gardé'.
     */
    public static function sync(string $sha, ?array &$paths = null): array
    {
        $res = ['sha' => $sha, 'at' => time(), 'total' => 0, 'count' => 0, 'differ' => [], 'eol' => 0, 'kept' => [], 'error' => null];
        $paths = [];
        try {
            $tree = self::tree($sha);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()] + $res;
        }
        foreach ($tree as $rel => $blob) {
            if (!self::selected($rel) || in_array($rel, self::MERGED, true) || !self::safe($rel)) {
                continue;
            }
            $st = self::sameAs(self::$root . '/' . $rel, $blob);
            if ($st === 'différent' && in_array($rel, self::KEEP_IF_EDITED, true)) {
                $st = 'gardé';
                $res['kept'][] = $rel;
            }
            $paths[$rel] = $st;
            $res['total']++;
            if ($st === 'eol') {
                $res['eol']++;
            } elseif ($st === 'absent' || $st === 'différent') {
                $res['count']++;
                if (count($res['differ']) < 200) {
                    $res['differ'][$rel] = $st;
                }
            }
        }
        if (!isset($tree['app/bootstrap.php'], $tree['public/index.php'])) {
            $res['error'] = 'Cette version de GitHub ne contient pas le code du musée.';
        }
        return $res;
    }

    /** Fichier du serveur comparé à une empreinte Git : 'ok', 'eol' (fins de ligne seulement), 'absent' ou 'différent'. */
    private static function sameAs(string $file, string $blob): string
    {
        if (!is_file($file)) {
            return 'absent';
        }
        $data = (string) @file_get_contents($file);
        $h = fn (string $s) => sha1('blob ' . strlen($s) . "\0" . $s);
        if ($h($data) === $blob) {
            return 'ok';
        }
        // Texte (pas d'octet nul au début, comme Git le décide) : fins de ligne LF ou CRLF.
        if (!str_contains(substr($data, 0, 8000), "\0")) {
            $lf = str_replace("\r\n", "\n", $data);
            if ($h($lf) === $blob || $h(str_replace("\n", "\r\n", $lf)) === $blob) {
                return 'eol';
            }
        }
        return 'différent';
    }

    /**
     * Fichiers du code d'une version : [chemin => empreinte Git]. Un appel pour la racine de la
     * version, puis un par dossier du code ; la liste d'un dossier est gardée dans
     * storage/update/arbres/ sous son empreinte : un dossier inchangé n'est pas redemandé.
     */
    private static function tree(string $sha): array
    {
        [$code, $body] = self::get(self::api('git/trees/' . $sha));
        $root = $code === 200 ? json_decode($body, true) : null;
        if (!is_array($root) || !is_array($root['tree'] ?? null)) {
            throw new \RuntimeException(self::apiError($code, $body));
        }
        $dirs = [];
        foreach ($root['tree'] as $e) {
            $name = (string) ($e['path'] ?? '');
            if (($e['type'] ?? '') === 'tree' && in_array($name . '/', self::MANAGED, true) && preg_match('/^[0-9a-f]{40}$/', (string) ($e['sha'] ?? ''))) {
                $dirs[$name] = (string) $e['sha'];
            }
        }
        @mkdir(self::$dir . '/arbres', 0775, true);
        $out = [];
        foreach ($dirs as $name => $treeSha) {
            $cache = self::$dir . '/arbres/' . $treeSha . '.json';
            $list = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
            if (!is_array($list)) {
                [$code, $body] = self::get(self::api('git/trees/' . $treeSha . '?recursive=1'));
                $j = $code === 200 ? json_decode($body, true) : null;
                if (!is_array($j) || !is_array($j['tree'] ?? null) || !empty($j['truncated'])) {
                    throw new \RuntimeException(!empty($j['truncated']) ? 'Dossier ' . $name . '/ trop grand pour être comparé.' : self::apiError($code, $body));
                }
                $list = [];
                foreach ($j['tree'] as $e) {
                    // Fichiers seulement : ni dossiers, ni liens symboliques, ni sous-modules.
                    if (($e['type'] ?? '') === 'blob' && ($e['mode'] ?? '') !== '120000' && isset($e['path'], $e['sha'])) {
                        $list[(string) $e['path']] = (string) $e['sha'];
                    }
                }
                @file_put_contents($cache, json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
            foreach ($list as $p => $b) {
                $out[$name . '/' . $p] = (string) $b;
            }
        }
        // Les dossiers des versions précédentes ne servent plus.
        foreach (glob(self::$dir . '/arbres/*.json') ?: [] as $f) {
            if (!in_array(basename($f, '.json'), $dirs, true)) {
                @unlink($f);
            }
        }
        return $out;
    }

    private static function apiError(int $code, string $body): string
    {
        if ($code === 403 || $code === 429) {
            return 'GitHub limite le nombre de vérifications (60 par heure sans jeton) : réessayer dans une heure, ou ajouter un jeton dans Réglages › Mises à jour.';
        }
        if ($code === 404 || $code === 422) {
            return 'Version introuvable sur GitHub.';
        }
        return 'GitHub ne répond pas (code ' . $code . ').';
    }

    /**
     * Site mis en ligne par FTP dont le code est identique à la dernière version : cette version
     * devient la version installée (avec son manifeste), sans rien réécrire.
     */
    private static function adopt(array $latest, array $paths): void
    {
        try {
            $lock = self::lock();
        } catch (\RuntimeException) {
            return; // une mise à jour est en cours : c'est elle qui fixera la version installée
        }
        try {
            if (self::installed()) {
                return;
            }
            // Manifeste : empreintes des fichiers identiques. Un .htaccess réglé à la main n'y
            // figure pas : les mises à jour suivantes le garderont.
            $manifest = [];
            foreach ($paths as $rel => $st) {
                if (($st === 'ok' || $st === 'eol') && is_file(self::$root . '/' . $rel)) {
                    $manifest[$rel] = hash_file('crc32b', self::$root . '/' . $rel);
                }
            }
            JsonStore::write(self::$dir . '/manifeste.json', $manifest);
            $entry = ['at' => date('c'), 'by' => 'Vérification', 'from' => null, 'to' => $latest['sha'], 'message' => 'Version reconnue : le code du serveur est identique à cette version de GitHub',
                'updated' => 0, 'deleted' => 0, 'kept' => [], 'merged' => 0, 'backup' => null];
            self::saveState(['installed' => $latest + ['at' => date('c'), 'by' => 'Vérification', 'via' => 'reconnue'],
                'history' => array_slice(array_merge([$entry], self::history()), 0, 30)]);
        } finally {
            self::unlock($lock);
        }
    }

    /** Commits et fichiers entre deux versions (API GitHub), ou null. */
    private static function compare(string $from, string $to): ?array
    {
        [$code, $body] = self::get(self::api('compare/' . $from . '...' . $to));
        $j = $code === 200 ? json_decode($body, true) : null;
        if (!is_array($j) || !isset($j['commits'])) {
            return null;
        }
        $files = array_values(array_filter(array_map(fn ($f) => (string) ($f['filename'] ?? ''), $j['files'] ?? []), fn ($f) => self::selected($f)));
        return ['commits' => array_reverse(array_map([self::class, 'commit'], $j['commits'])), 'files' => $files];
    }

    /** Derniers commits de la branche par son flux Atom (sans limite d'appels). */
    private static function feed(): array
    {
        [$code, $body] = self::get('https://github.com/' . self::repo() . '/commits/' . self::ref(self::branch()) . '.atom');
        if ($code !== 200 || $body === '') {
            return [];
        }
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($body, LIBXML_NONET)) {
            return [];
        }
        $out = [];
        foreach ($dom->getElementsByTagName('entry') as $e) {
            $id = $e->getElementsByTagName('id')->item(0)?->textContent ?? '';
            if (preg_match('#Commit/([0-9a-f]{40})#', $id, $m)) {
                $out[] = ['sha' => $m[1], 'date' => (string) ($e->getElementsByTagName('updated')->item(0)?->textContent ?? ''),
                    'message' => trim((string) ($e->getElementsByTagName('title')->item(0)?->textContent ?? ''))];
            }
        }
        return $out;
    }

    private static function until(array $feed, string $sha): array
    {
        $out = [];
        foreach ($feed as $c) {
            if ($c['sha'] === $sha) {
                break;
            }
            $out[] = $c;
        }
        return $out;
    }

    private static function commit(array $j): array
    {
        $msg = (string) ($j['commit']['message'] ?? '');
        return ['sha' => (string) $j['sha'], 'date' => (string) ($j['commit']['committer']['date'] ?? $j['commit']['author']['date'] ?? ''), 'message' => trim(strtok($msg, "\n") ?: $msg)];
    }

    private static function api(string $path): string
    {
        return 'https://api.github.com/repos/' . self::repo() . '/' . $path;
    }

    /** Nom de branche dans une adresse : chaque segment encodé, les « / » gardés. */
    private static function ref(string $b): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $b)));
    }

    /** GET HTTPS : [code, corps], ou fichier écrit si $toFile. */
    private static function get(string $url, ?string $toFile = null, int $timeout = 20): array
    {
        if (self::$http) {
            return (self::$http)($url, $toFile);
        }
        $headers = ['User-Agent: SochauxRetro-MiseAJour', 'Accept: ' . (str_starts_with($url, 'https://api.github.com/') ? 'application/vnd.github+json' : '*/*')];
        if (($t = self::token()) !== '' && str_starts_with($url, 'https://api.github.com/')) {
            $headers[] = 'Authorization: Bearer ' . $t;
        }
        $ch = curl_init($url);
        $opts = [CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS];
        $fp = null;
        if ($toFile !== null) {
            $fp = fopen($toFile, 'wb');
            $opts += [CURLOPT_FILE => $fp, CURLOPT_NOPROGRESS => false, CURLOPT_PROGRESSFUNCTION => fn ($c, $total, $done) => $done > self::MAX_ZIP ? 1 : 0];
        } else {
            $opts[CURLOPT_RETURNTRANSFER] = true;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($fp) {
            fclose($fp);
        }
        if ($body === false) {
            throw new \RuntimeException('GitHub injoignable : ' . $err);
        }
        return [$code, $toFile === null ? (string) $body : ''];
    }

    // ------------------------------------------------------------------ application

    /** Le chemin fait-il partie de ce qu'une mise à jour remplace ? */
    public static function selected(string $rel): bool
    {
        if (in_array($rel, self::MERGED, true)) {
            return true;
        }
        foreach (self::EXCLUDED as $x) {
            if (str_starts_with($rel, $x)) {
                return false;
            }
        }
        foreach (self::MANAGED as $m) {
            if (str_starts_with($rel, $m)) {
                return true;
            }
        }
        return false;
    }

    /** Chemin relatif sûr : ni « .. », ni chemin absolu, ni caractère de contrôle. */
    public static function safe(string $rel): bool
    {
        return $rel !== '' && !str_contains($rel, '\\') && !str_starts_with($rel, '/')
            && !preg_match('#(^|/)\.\.?(/|$)#', $rel) && !preg_match('/[\x00-\x1F\x7F]/', $rel);
    }

    /**
     * Télécharge la dernière version (ou $sha) et remplace le code qui a changé.
     * ['sha', 'updated' => [chemins], 'added', 'deleted', 'kept' (modifiés sur le serveur, gardés),
     * 'merged' (libellés ajoutés), 'backup' (nom de la sauvegarde), 'commits' (appliqués)].
     */
    public static function apply(?array $user, ?string $sha = null): array
    {
        @set_time_limit(600);
        class_exists(Activity::class); // chargée avant la copie : la suite de la requête ne lit plus de nouveau code
        $lock = self::lock();
        try {
            $check = self::check(true);
            if (!empty($check['error']) && $sha === null) {
                throw new \RuntimeException($check['error']);
            }
            $target = $sha ?? (string) ($check['latest']['sha'] ?? '');
            if (!preg_match('/^[0-9a-f]{40}$/', $target)) {
                throw new \RuntimeException('Version à installer inconnue.');
            }
            @mkdir(self::$dir . '/tmp', 0775, true);
            $zipPath = self::$dir . "/tmp/$target.zip";
            if (self::$fetch) {
                $zipPath = (self::$fetch)($target);
            } else {
                $url = self::token() !== '' ? self::api('zipball/' . $target) : 'https://codeload.github.com/' . self::repo() . '/zip/' . $target;
                [$code] = self::get($url, $zipPath, 300);
                if ($code !== 200) {
                    @unlink($zipPath);
                    throw new \RuntimeException('Téléchargement de la version refusé par GitHub (code ' . $code . ').');
                }
            }
            $report = self::install($zipPath, $target, $check, $user);
            if (!self::$fetch) {
                @unlink($zipPath);
            }
            return $report;
        } finally {
            self::unlock($lock);
        }
    }

    /** Installe une archive (format GitHub : un dossier racine « dépôt-version/ »). */
    public static function install(string $zipPath, string $target, array $check, ?array $user): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Archive illisible.');
        }
        $comment = (string) $zip->getArchiveComment();
        if ($comment !== '' && preg_match('/^[0-9a-f]{40}$/', $comment) && $comment !== $target) {
            throw new \RuntimeException('L’archive reçue ne correspond pas à la version demandée.');
        }
        // Dossier racine de l'archive et fichiers du code qu'elle contient.
        $prefix = null;
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $name = (string) $st['name'];
            $prefix ??= str_contains($name, '/') ? substr($name, 0, strpos($name, '/') + 1) : '';
            if (!str_starts_with($name, $prefix) || str_ends_with($name, '/')) {
                continue;
            }
            $rel = substr($name, strlen($prefix));
            if (!self::safe($rel)) {
                throw new \RuntimeException('Archive refusée : chemin suspect (' . $rel . ').');
            }
            if (self::selected($rel)) {
                $entries[$rel] = ['name' => $name, 'crc' => sprintf('%08x', $st['crc'] & 0xFFFFFFFF), 'size' => (int) $st['size']];
            }
        }
        if (!isset($entries['app/bootstrap.php'], $entries['public/index.php'])) {
            throw new \RuntimeException('Archive refusée : ce n’est pas le code du musée.');
        }
        $old = JsonStore::read(self::$dir . '/manifeste.json', []) ?: [];
        $plan = ['write' => [], 'delete' => [], 'kept' => [], 'merge' => null];
        foreach ($entries as $rel => $e) {
            $local = self::$root . '/' . $rel;
            $cur = is_file($local) ? hash_file('crc32b', $local) : null;
            if (in_array($rel, self::MERGED, true)) {
                $plan['merge'] = $rel;
                continue;
            }
            if ($cur === $e['crc']) {
                continue;
            }
            // .htaccess réglé à la main sur le serveur : gardé.
            if ($cur !== null && in_array($rel, self::KEEP_IF_EDITED, true) && ($old[$rel] ?? null) !== $cur) {
                $plan['kept'][] = $rel;
                continue;
            }
            $plan['write'][] = $rel;
        }
        foreach (array_keys($old) as $rel) {
            if (!isset($entries[$rel]) && self::selected($rel) && self::safe($rel) && !in_array($rel, self::MERGED, true) && is_file(self::$root . '/' . $rel)) {
                $plan['delete'][] = $rel;
            }
        }
        $merged = null;
        if ($plan['merge']) {
            $merged = self::mergeLabels(self::$root . '/' . $plan['merge'], (string) $zip->getFromName($entries[$plan['merge']]['name']));
        }
        $added = array_values(array_filter($plan['write'], fn ($rel) => !is_file(self::$root . '/' . $rel)));
        $backup = self::backup($plan, $merged ? $plan['merge'] : null, $added, $target, $old, $user);

        // Copie : le site répond « Mise à jour en cours » le temps d'écrire les fichiers.
        @file_put_contents(self::$dir . '/maintenance', (string) time());
        try {
            foreach ($plan['write'] as $rel) {
                self::writeFrom($zip, $entries[$rel]['name'], self::$root . '/' . $rel);
            }
            if ($merged) {
                self::writeString(self::$root . '/' . $plan['merge'], $merged['json']);
            }
            foreach ($plan['delete'] as $rel) {
                @unlink(self::$root . '/' . $rel);
            }
        } finally {
            @unlink(self::$dir . '/maintenance');
        }
        $zip->close();
        $manifest = [];
        foreach ($entries as $rel => $e) {
            $manifest[$rel] = in_array($rel, self::MERGED, true) ? null : $e['crc'];
        }
        JsonStore::write(self::$dir . '/manifeste.json', array_filter($manifest, fn ($v) => $v !== null));
        $latest = ($check['latest']['sha'] ?? '') === $target ? $check['latest'] : ['sha' => $target, 'date' => '', 'message' => ''];
        $from = self::installed();
        $entry = ['at' => date('c'), 'by' => (string) ($user['name'] ?? 'Inconnu'), 'from' => $from['sha'] ?? null, 'to' => $target, 'message' => $latest['message'],
            'updated' => count($plan['write']), 'deleted' => count($plan['delete']), 'kept' => $plan['kept'], 'merged' => $merged['added'] ?? 0, 'backup' => $backup];
        $hist = array_slice(array_merge([$entry], self::history()), 0, 30);
        self::saveState(['installed' => $latest + ['at' => date('c'), 'by' => $entry['by']], 'history' => $hist]);
        // Le code du serveur est maintenant celui de l'archive : comparaison à jour sans appel.
        $n = count($entries) - ($plan['merge'] ? 1 : 0);
        self::setSync(['sha' => $target, 'at' => time(), 'total' => $n, 'count' => 0, 'differ' => [], 'eol' => 0, 'kept' => $plan['kept'], 'error' => null]);
        self::afterChange();
        if (self::$log) {
            Activity::log($user, 'a appliqué la mise à jour ' . substr($target, 0, 7) . ($latest['message'] !== '' ? ' (' . $latest['message'] . ')' : ''), null);
        }
        return ['sha' => $target, 'updated' => $plan['write'], 'added' => $added, 'deleted' => $plan['delete'], 'kept' => $plan['kept'],
            'merged' => $merged['added'] ?? 0, 'backup' => $backup, 'commits' => $check['commits'] ?? []];
    }

    /** Libellés anglais : ceux de la nouvelle version qui manquent sont ajoutés, ceux du serveur gardés. */
    public static function mergeLabels(string $localFile, string $incoming): ?array
    {
        $new = json_decode($incoming, true);
        if (!is_array($new)) {
            return null;
        }
        $cur = is_file($localFile) ? json_decode((string) file_get_contents($localFile), true) : [];
        $cur = is_array($cur) ? $cur : [];
        $add = array_diff_key($new, $cur);
        if (!$add && is_file($localFile)) {
            return null;
        }
        $all = $cur + $add;
        ksort($all, SORT_STRING);
        return ['json' => json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", 'added' => count($add)];
    }

    /** Sauvegarde des fichiers remplacés ou supprimés, avec de quoi revenir en arrière. */
    private static function backup(array $plan, ?string $merged, array $added, string $target, array $oldManifest, ?array $user): string
    {
        @mkdir(self::$dir . '/sauvegardes', 0775, true);
        $from = self::installed();
        // Horodatage à la microseconde en tête du nom : le tri par nom donne l'ordre de création.
        $t = microtime(true);
        $name = date('Ymd-His', (int) $t) . sprintf('%06d', (int) (($t - floor($t)) * 1e6)) . '-' . ($from ? substr($from['sha'], 0, 7) : 'ftp') . '.zip';
        $z = new \ZipArchive();
        if ($z->open(self::$dir . '/sauvegardes/' . $name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Sauvegarde impossible : dossier storage/update non inscriptible.');
        }
        $files = array_merge($plan['write'], $plan['delete'], $merged ? [$merged] : []);
        foreach ($files as $rel) {
            if (is_file(self::$root . '/' . $rel)) {
                $z->addFile(self::$root . '/' . $rel, 'fichiers/' . $rel);
            }
        }
        $z->addFromString('meta.json', (string) json_encode(['at' => date('c'), 'by' => (string) ($user['name'] ?? 'Inconnu'), 'from' => $from, 'to' => $target, 'added' => $added], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $z->addFromString('manifeste.json', (string) json_encode($oldManifest, JSON_UNESCAPED_SLASHES));
        if (!$z->close()) {
            throw new \RuntimeException('Sauvegarde impossible (espace disque ?).');
        }
        // Les plus anciennes sauvegardes s'en vont.
        $all = glob(self::$dir . '/sauvegardes/*.zip') ?: [];
        rsort($all);
        foreach (array_slice($all, self::KEEP_BACKUPS) as $f) {
            @unlink($f);
        }
        return $name;
    }

    /** Sauvegardes disponibles, de la plus récente à la plus ancienne. */
    public static function backups(): array
    {
        $out = [];
        $all = glob(self::$dir . '/sauvegardes/*.zip') ?: [];
        rsort($all);
        foreach ($all as $f) {
            $z = new \ZipArchive();
            $meta = null;
            $n = 0;
            if ($z->open($f) === true) {
                $meta = json_decode((string) $z->getFromName('meta.json'), true);
                $n = max(0, $z->numFiles - 2);
                $z->close();
            }
            $out[] = ['name' => basename($f), 'meta' => is_array($meta) ? $meta : [], 'files' => $n, 'size' => (int) @filesize($f)];
        }
        return $out;
    }

    /** Remet les fichiers d'une sauvegarde (annule la mise à jour qui l'a créée). */
    public static function rollback(string $name, ?array $user): array
    {
        $name = basename($name);
        $path = self::$dir . '/sauvegardes/' . $name;
        if (!preg_match('/^\d{8}-\d{6}(\d{6})?-[0-9a-z]+\.zip$/', $name) || !is_file($path)) {
            throw new \RuntimeException('Sauvegarde introuvable.');
        }
        class_exists(Activity::class);
        $lock = self::lock();
        try {
            $z = new \ZipArchive();
            if ($z->open($path) !== true) {
                throw new \RuntimeException('Sauvegarde illisible.');
            }
            $meta = json_decode((string) $z->getFromName('meta.json'), true) ?: [];
            $manifest = json_decode((string) $z->getFromName('manifeste.json'), true);
            @file_put_contents(self::$dir . '/maintenance', (string) time());
            $restored = 0;
            try {
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $entry = (string) $z->getNameIndex($i);
                    if (!str_starts_with($entry, 'fichiers/') || str_ends_with($entry, '/')) {
                        continue;
                    }
                    $rel = substr($entry, strlen('fichiers/'));
                    if (self::safe($rel) && self::selected($rel)) {
                        self::writeFrom($z, $entry, self::$root . '/' . $rel);
                        $restored++;
                    }
                }
                foreach ((array) ($meta['added'] ?? []) as $rel) {
                    if (is_string($rel) && self::safe($rel) && self::selected($rel)) {
                        @unlink(self::$root . '/' . $rel);
                    }
                }
            } finally {
                @unlink(self::$dir . '/maintenance');
            }
            $z->close();
            if (is_array($manifest)) {
                JsonStore::write(self::$dir . '/manifeste.json', $manifest);
            }
            $back = is_array($meta['from'] ?? null) ? $meta['from'] : null;
            $hist = array_slice(array_merge([['at' => date('c'), 'by' => (string) ($user['name'] ?? 'Inconnu'), 'from' => $meta['to'] ?? null, 'to' => $back['sha'] ?? null,
                'message' => 'Retour arrière (sauvegarde ' . $name . ')', 'updated' => $restored, 'deleted' => count((array) ($meta['added'] ?? [])), 'kept' => [], 'merged' => 0, 'backup' => null]], self::history()), 0, 30);
            self::saveState(['installed' => $back, 'history' => $hist]);
            self::setSync(null); // à refaire : le code a changé
            self::afterChange();
            if (self::$log) {
                Activity::log($user, 'a annulé une mise à jour (retour à ' . ($back ? substr($back['sha'], 0, 7) : 'la version mise en ligne par FTP') . ')', null);
            }
            return ['restored' => $restored, 'to' => $back];
        } finally {
            self::unlock($lock);
        }
    }

    // ------------------------------------------------------------------ outils

    private static function writeFrom(\ZipArchive $zip, string $entry, string $dest): void
    {
        $in = $zip->getStream($entry);
        if (!$in) {
            throw new \RuntimeException('Fichier illisible dans l’archive : ' . $entry);
        }
        self::ensureDir(dirname($dest));
        $tmp = $dest . '.maj-' . bin2hex(random_bytes(3));
        $out = fopen($tmp, 'wb');
        if (!$out) {
            fclose($in);
            throw new \RuntimeException('Écriture impossible : ' . self::short($dest) . ' (droits du dossier ?).');
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        @chmod($tmp, 0644);
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new \RuntimeException('Remplacement impossible : ' . self::short($dest) . '.');
        }
    }

    private static function writeString(string $dest, string $content): void
    {
        self::ensureDir(dirname($dest));
        $tmp = $dest . '.maj-' . bin2hex(random_bytes(3));
        if (@file_put_contents($tmp, $content) === false || !@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : ' . self::short($dest) . '.');
        }
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Dossier impossible à créer : ' . self::short($dir) . '.');
        }
    }

    private static function short(string $path): string
    {
        return ltrim(str_replace(self::$root, '', $path), '/');
    }

    /**
     * Après une mise à jour : caches vidés (ils se refont seuls) et code PHP relu. Jamais le
     * dossier correcteur/ : les textes déjà relus par l'IA y sont gardés (sinon, relus et payés
     * une seconde fois). Les gros caches de données restent en place et seront refaits par la
     * requête suivante, après l'envoi de sa page (refresh()).
     */
    private static function afterChange(): void
    {
        $dir = str_replace('\\', '/', self::$cacheDir);
        $it = is_dir($dir) ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) : [];
        foreach ($it as $f) {
            $path = str_replace('\\', '/', $f->getPathname());
            $rel = ltrim(substr($path, strlen($dir)), '/');
            if ($f->isFile() && !str_starts_with($rel, 'correcteur/') && !in_array($f->getFilename(), ['.gitkeep', '.htaccess'], true) && !preg_match(self::KEEP_CACHES, $rel)) {
                @unlink($f->getPathname());
            }
        }
        @file_put_contents($dir . '/' . self::REFRESH, (string) time());
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /**
     * Après une mise à jour, refait l'index des fiches, les données calculées et l'index de
     * recherche gardés par afterChange(), avec le nouveau code ; une seule exécution. Depuis une
     * page, une fois celle-ci envoyée, au moins 5 s après la mise à jour (le temps que PHP relise
     * tout le code) et seulement si l'hébergement sait terminer la page avant (sinon le visiteur
     * attendrait : la tâche planifiée s'en charge). Vrai si les caches ont été refaits.
     */
    public static function refresh(): bool
    {
        $flag = self::$cacheDir . '/' . self::REFRESH;
        $at = @filemtime($flag);
        if ($at === false) {
            // Calcul interrompu (délai dépassé) : repris.
            $busy = @filemtime($flag . '.en-cours');
            if (!$busy || $busy > time() - 900 || !@rename($flag . '.en-cours', $flag)) {
                return false;
            }
            $at = $busy;
        }
        $web = PHP_SAPI !== 'cli';
        if ($web && ($at > time() - 5 || (!function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request')))) {
            return false;
        }
        if (!@rename($flag, $flag . '.en-cours')) {
            return false;
        }
        if ($web) {
            \App\Core\Response::detach();
        }
        @set_time_limit(600);
        // Toutes les fiches relues trois fois : environ 250 Mo.
        $limit = (string) ini_get('memory_limit');
        if ($limit !== '-1' && @ini_parse_quantity($limit) < 512 * 1048576) {
            @ini_set('memory_limit', '512M');
        }
        try {
            \App\Data\Index::rebuild();
            \App\Data\Derived::rebuild();
            \App\Services\Search::rebuild();
        } catch (\Throwable $e) {
            error_log('[après mise à jour] ' . $e->getMessage());
        } finally {
            @unlink($flag . '.en-cours');
        }
        return true;
    }

    /** Une seule mise à jour à la fois. */
    private static function lock()
    {
        @mkdir(self::$dir, 0775, true);
        $fp = fopen(self::$dir . '/verrou.lock', 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Une mise à jour est déjà en cours.');
        }
        return $fp;
    }

    private static function unlock($fp): void
    {
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
