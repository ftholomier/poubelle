<?php

declare(strict_types=1);

/**
 * Mises à jour en un clic depuis GitHub (back-office › Mises à jour), sur le modèle d'Intensiti.
 *
 * Le site vit dans le dossier front/ du dépôt. La dernière version de la branche réglée est
 * comparée à la version installée ; « Installer » télécharge l'archive de cette version et ne
 * remplace que le code qui a changé : src/, views/, public/ (sauf public/uploads/), config/,
 * content/ et .htaccess. Jamais storage/ (contenus modifiés, demandes, comptes, secrets),
 * public/uploads/ (fichiers téléversés) ni config/config.local.php. config/config.php et les
 * .htaccess modifiés sur le serveur sont gardés.
 *
 * Avant d'écrire, les fichiers remplacés ou supprimés sont sauvegardés (storage/update/sauvegardes/) :
 * « Revenir à cette version » les remet en place. Pendant la copie, le site répond « Mise à jour en
 * cours » (storage/update/maintenance). Un échec en cours de route remet aussitôt la sauvegarde.
 *
 * Synchronisation : à chaque vérification, le code du serveur est comparé fichier par fichier à la
 * dernière version (empreintes Git, sans rien télécharger). Un site mis en ligne par FTP dont le
 * code est identique voit sa version reconnue ; un fichier oublié ou retouché est signalé.
 *
 * État : storage/update/etat.json (version installée, dernière vérification, historique) ;
 * storage/update/manifeste.json (empreinte CRC32 de chaque fichier installé).
 */
final class Updater
{
    /** Dossier du site dans le dépôt. */
    public const PREFIX = 'front/';
    /** Code remplacé par une mise à jour (chemins du dépôt, dossiers « …/ » ou fichiers). */
    public const MANAGED = ['front/src/', 'front/views/', 'front/public/', 'front/config/', 'front/content/', 'front/.htaccess'];
    /** Jamais touchés. */
    public const EXCLUDED = ['front/public/uploads/', 'front/storage/', 'front/config/config.local.php'];
    /** Gardés s'ils ont été modifiés sur le serveur (réglage fait à la main). */
    public const KEEP_IF_EDITED = ['front/config/config.php', 'front/.htaccess', 'front/public/.htaccess'];
    public const REPO = 'ftholomier/poubelle';
    public const BRANCH = 'claude/laughing-lamport-g5ikf8';
    private const MAX_ZIP = 400 * 1024 * 1024;
    private const KEEP_BACKUPS = 5;
    private const CHECK_TTL = 3600;

    public static string $root = APP_ROOT;
    public static string $dir = APP_ROOT . '/storage/update';
    /** Essais : réponses HTTP simulées (url, fichier) => [code, corps]. */
    public static ?Closure $http = null;

    // ------------------------------------------------------------------ réglages

    public static function repo(): string
    {
        $r = trim((string) Settings::get('update_repo', ''));
        return preg_match('#^[\w.-]+/[\w.-]+$#', $r) ? $r : self::REPO;
    }

    public static function branch(): string
    {
        $b = trim((string) Settings::get('update_branch', ''));
        return preg_match('#^[\w.-]+(/[\w.-]+)*$#', $b) && !str_contains($b, '..') ? $b : self::BRANCH;
    }

    private static function token(): string
    {
        return Settings::secret('github_token');
    }

    public static function hasToken(): bool
    {
        return self::token() !== '';
    }

    // ------------------------------------------------------------------ état

    public static function state(): array
    {
        return self::readJson(self::$dir . '/etat.json');
    }

    private static function saveState(array $changes): void
    {
        self::writeJson(self::$dir . '/etat.json', array_merge(self::state(), $changes));
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

    /** Mise à jour proposée d'après la dernière vérification (sans appel réseau), ou null. */
    public static function available(): ?array
    {
        $c = self::state()['check'] ?? null;
        $latest = $c['latest'] ?? null;
        if (!$latest || ($c['repo'] ?? '') !== self::key()) {
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

    /** Nouvelle version (sinon : fichiers du serveur à remettre d'aplomb). */
    public static function isNewVersion(array $c): bool
    {
        $inst = self::installed();
        return $inst ? $inst['sha'] !== ($c['latest']['sha'] ?? '') : !self::differing($c);
    }

    /** Libellé de version : « 23/10/2026 · a1b2c3d ». */
    public static function label(?array $v): string
    {
        if (!$v || empty($v['sha'])) {
            return 'version inconnue (mise en ligne par FTP)';
        }
        $d = !empty($v['date']) ? date('d/m/Y', (int) strtotime((string) $v['date'])) : '';
        return trim($d . ' · ' . substr((string) $v['sha'], 0, 7), ' ·');
    }

    private static function key(): string
    {
        return self::repo() . '#' . self::branch();
    }

    // ------------------------------------------------------------------ vérification

    /** Vérification par la tâche planifiée : au plus toutes les 3 heures. */
    public static function checkIfDue(): void
    {
        $c = self::state()['check'] ?? null;
        if (is_array($c) && ($c['at'] ?? 0) > time() - 3 * 3600) {
            return;
        }
        try {
            self::check(true);
        } catch (Throwable $e) {
            error_log('[mise à jour] ' . $e->getMessage());
        }
    }

    /**
     * Dernière version de la branche, changements depuis la version installée et comparaison du
     * code du serveur avec cette version (mis en cache une heure, sauf $force).
     */
    public static function check(bool $force = false): array
    {
        $c = self::state()['check'] ?? null;
        if (!$force && is_array($c) && ($c['repo'] ?? '') === self::key() && ($c['at'] ?? 0) > time() - self::CHECK_TTL) {
            return $c;
        }
        $res = ['at' => time(), 'repo' => self::key(), 'latest' => null, 'commits' => [], 'files' => null, 'sync' => null, 'error' => null];
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
                    $res['commits'] = array_slice($ch['commits'], 0, 80);
                    $res['files'] = $ch['files'];
                } else {
                    $res['commits'] = self::recentCommits(15);
                }
            }
        } catch (Throwable $e) {
            $res['error'] = $e->getMessage();
        }
        self::saveState(['check' => $res]);
        return $res;
    }

    /** Dernier commit de la branche. */
    private static function latest(): array
    {
        [$code, $body] = self::get(self::api('commits/' . self::ref(self::branch())));
        if ($code === 200 && is_array($j = json_decode($body, true)) && !empty($j['sha'])) {
            return self::commit($j);
        }
        if ($code === 404 || $code === 422) {
            throw new RuntimeException('Branche « ' . self::branch() . ' » introuvable dans le dépôt ' . self::repo() . (self::hasToken() ? ' (ou jeton sans accès à ce dépôt).' : ' : dépôt privé ? Renseignez un jeton GitHub ci-dessous.'));
        }
        throw new RuntimeException(self::apiError($code));
    }

    private static function recentCommits(int $n): array
    {
        [$code, $body] = self::get(self::api('commits?per_page=' . $n . '&sha=' . rawurlencode(self::branch())));
        $j = $code === 200 ? json_decode($body, true) : null;
        return is_array($j) ? array_map([self::class, 'commit'], $j) : [];
    }

    // ------------------------------------------------------------------ synchronisation

    /**
     * Le code du serveur comparé, fichier par fichier, à une version de GitHub, par les empreintes
     * Git (rien n'est téléchargé). Les fins de ligne ne comptent pas pour un fichier texte.
     */
    public static function sync(string $sha, ?array &$paths = null): array
    {
        $res = ['sha' => $sha, 'at' => time(), 'total' => 0, 'count' => 0, 'differ' => [], 'eol' => 0, 'kept' => [], 'error' => null];
        $paths = [];
        try {
            $tree = self::tree($sha);
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()] + $res;
        }
        if (!isset($tree['front/src/bootstrap.php'], $tree['front/public/index.php'])) {
            $res['error'] = 'Cette version de GitHub ne contient pas le code du site (dossier front/).';
            return $res;
        }
        foreach ($tree as $rel => $blob) {
            if (!self::selected($rel) || !self::safe($rel)) {
                continue;
            }
            $st = self::sameAs(self::dest($rel), $blob);
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
        return $res;
    }

    /** Fichier du serveur comparé à une empreinte Git : 'ok', 'eol', 'absent' ou 'différent'. */
    private static function sameAs(string $file, string $blob): string
    {
        if (!is_file($file)) {
            return 'absent';
        }
        $data = (string) @file_get_contents($file);
        $h = fn(string $s) => sha1('blob ' . strlen($s) . "\0" . $s);
        if ($h($data) === $blob) {
            return 'ok';
        }
        if (!str_contains(substr($data, 0, 8000), "\0")) {
            $lf = str_replace("\r\n", "\n", $data);
            if ($h($lf) === $blob || $h(str_replace("\n", "\r\n", $lf)) === $blob) {
                return 'eol';
            }
        }
        return 'différent';
    }

    /** Fichiers du site dans une version : [chemin du dépôt => empreinte Git]. */
    private static function tree(string $sha): array
    {
        [$code, $body] = self::get(self::api('git/trees/' . $sha));
        $root = $code === 200 ? json_decode($body, true) : null;
        if (!is_array($root) || !is_array($root['tree'] ?? null)) {
            throw new RuntimeException(self::apiError($code));
        }
        $frontSha = null;
        foreach ($root['tree'] as $e) {
            if (($e['path'] ?? '') === rtrim(self::PREFIX, '/') && ($e['type'] ?? '') === 'tree') {
                $frontSha = (string) $e['sha'];
            }
        }
        if (!$frontSha || !preg_match('/^[0-9a-f]{40}$/', $frontSha)) {
            throw new RuntimeException('Dossier front/ absent de cette version du dépôt.');
        }
        @mkdir(self::$dir . '/arbres', 0775, true);
        $cache = self::$dir . '/arbres/' . $frontSha . '.json';
        $list = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
        if (!is_array($list)) {
            [$code, $body] = self::get(self::api('git/trees/' . $frontSha . '?recursive=1'));
            $j = $code === 200 ? json_decode($body, true) : null;
            if (!is_array($j) || !is_array($j['tree'] ?? null) || !empty($j['truncated'])) {
                throw new RuntimeException(!empty($j['truncated']) ? 'Dossier front/ trop grand pour être comparé.' : self::apiError($code));
            }
            $list = [];
            foreach ($j['tree'] as $e) {
                if (($e['type'] ?? '') === 'blob' && ($e['mode'] ?? '') !== '120000' && isset($e['path'], $e['sha'])) {
                    $list[(string) $e['path']] = (string) $e['sha'];
                }
            }
            @file_put_contents($cache, json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        foreach (glob(self::$dir . '/arbres/*.json') ?: [] as $f) {
            if (basename($f, '.json') !== $frontSha) {
                @unlink($f);
            }
        }
        $out = [];
        foreach ($list as $p => $b) {
            $out[self::PREFIX . $p] = (string) $b;
        }
        return $out;
    }

    private static function apiError(int $code): string
    {
        if ($code === 401) {
            return 'Jeton GitHub refusé (expiré ou incorrect) : renouvelez-le dans les réglages ci-dessous.';
        }
        if ($code === 403 || $code === 429) {
            return 'GitHub limite le nombre d\'appels pour l\'instant' . (self::hasToken() ? '' : ' (un jeton GitHub relève cette limite)') . ' : réessayez dans une heure.';
        }
        if ($code === 404 || $code === 422) {
            return 'Version introuvable sur GitHub (ou jeton sans accès au dépôt).';
        }
        return 'GitHub ne répond pas (code ' . $code . ').';
    }

    /** Site mis en ligne par FTP dont le code est identique à la dernière version : version reconnue. */
    private static function adopt(array $latest, array $paths): void
    {
        try {
            $lock = self::lock();
        } catch (RuntimeException) {
            return;
        }
        try {
            if (self::installed()) {
                return;
            }
            $manifest = [];
            foreach ($paths as $rel => $st) {
                if (($st === 'ok' || $st === 'eol') && is_file(self::dest($rel))) {
                    $manifest[$rel] = hash_file('crc32b', self::dest($rel));
                }
            }
            self::writeJson(self::$dir . '/manifeste.json', $manifest);
            $entry = ['at' => date('c'), 'by' => 'Vérification', 'from' => null, 'to' => $latest['sha'], 'message' => 'Version reconnue : le code du serveur est identique à cette version de GitHub',
                'updated' => 0, 'deleted' => 0, 'kept' => [], 'backup' => null];
            self::saveState(['installed' => $latest + ['at' => date('c'), 'by' => 'Vérification', 'via' => 'reconnue'],
                'history' => array_slice(array_merge([$entry], self::history()), 0, 40)]);
        } finally {
            self::unlock($lock);
        }
    }

    /** Commits et fichiers entre deux versions, ou null. */
    private static function compare(string $from, string $to): ?array
    {
        [$code, $body] = self::get(self::api('compare/' . $from . '...' . $to));
        $j = $code === 200 ? json_decode($body, true) : null;
        if (!is_array($j) || !isset($j['commits'])) {
            return null;
        }
        $files = array_values(array_filter(array_map(fn($f) => (string) ($f['filename'] ?? ''), $j['files'] ?? []), fn($f) => self::selected($f)));
        return ['commits' => array_reverse(array_map([self::class, 'commit'], $j['commits'])), 'files' => $files];
    }

    private static function commit(array $j): array
    {
        $msg = mb_scrub((string) ($j['commit']['message'] ?? ''), 'UTF-8');
        $lines = preg_split('/\r\n|\r|\n/', trim($msg)) ?: [];
        $details = array_values(array_filter(array_map('trim', array_slice($lines, 1)),
            fn($l) => $l !== '' && !preg_match('/^(Co-Authored-By|Claude-Session|Signed-off-by):/i', $l)));
        return ['sha' => (string) $j['sha'], 'date' => (string) ($j['commit']['committer']['date'] ?? $j['commit']['author']['date'] ?? ''),
            'message' => trim($lines[0] ?? ''), 'details' => array_slice($details, 0, 12)];
    }

    private static function api(string $path): string
    {
        return 'https://api.github.com/repos/' . self::repo() . '/' . $path;
    }

    private static function ref(string $b): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $b)));
    }

    /** GET HTTPS : [code, corps], ou fichier écrit si $toFile. */
    private static function get(string $url, ?string $toFile = null, int $timeout = 25): array
    {
        if (self::$http) {
            return (self::$http)($url, $toFile);
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extension PHP cURL absente sur le serveur.');
        }
        $headers = ['User-Agent: Renovest-MiseAJour', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if (($t = self::token()) !== '' && str_starts_with($url, 'https://api.github.com/')) {
            $headers[] = 'Authorization: Bearer ' . $t;
        }
        $ch = curl_init($url);
        $opts = [CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS];
        $fp = null;
        if ($toFile !== null) {
            $fp = fopen($toFile, 'wb');
            $opts += [CURLOPT_FILE => $fp, CURLOPT_NOPROGRESS => false, CURLOPT_PROGRESSFUNCTION => fn($c, $total, $done) => $done > self::MAX_ZIP ? 1 : 0];
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
            throw new RuntimeException('GitHub injoignable : ' . $err);
        }
        return [$code, $toFile === null ? (string) $body : ''];
    }

    // ------------------------------------------------------------------ chemins

    /** Le chemin du dépôt fait-il partie de ce qu'une mise à jour remplace ? */
    public static function selected(string $rel): bool
    {
        foreach (self::EXCLUDED as $x) {
            if ($rel === $x || (str_ends_with($x, '/') && str_starts_with($rel, $x))) {
                return false;
            }
        }
        foreach (self::MANAGED as $m) {
            if ($rel === $m || (str_ends_with($m, '/') && str_starts_with($rel, $m))) {
                return true;
            }
        }
        return false;
    }

    /** Emplacement sur le serveur d'un chemin du dépôt (front/… → dossier du site). */
    public static function dest(string $rel): string
    {
        if (!str_starts_with($rel, self::PREFIX)) {
            throw new RuntimeException('Chemin hors du site : ' . $rel);
        }
        return self::$root . '/' . substr($rel, strlen(self::PREFIX));
    }

    /** Chemin relatif sûr : ni « .. », ni chemin absolu, ni caractère de contrôle. */
    public static function safe(string $rel): bool
    {
        return $rel !== '' && !str_contains($rel, '\\') && !str_starts_with($rel, '/')
            && !preg_match('#(^|/)\.\.?(/|$)#', $rel) && !preg_match('/[\x00-\x1F\x7F]/', $rel);
    }

    // ------------------------------------------------------------------ application

    /** Télécharge la dernière version et remplace le code qui a changé. */
    public static function apply(string $by): array
    {
        @set_time_limit(900);
        @ignore_user_abort(true);
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extension PHP Zip absente sur le serveur.');
        }
        $lock = self::lock();
        try {
            $check = self::check(true);
            if (!empty($check['error'])) {
                throw new RuntimeException($check['error']);
            }
            $target = (string) ($check['latest']['sha'] ?? '');
            if (!preg_match('/^[0-9a-f]{40}$/', $target)) {
                throw new RuntimeException('Version à installer inconnue.');
            }
            @mkdir(self::$dir . '/tmp', 0775, true);
            $zipPath = self::$dir . "/tmp/$target.zip";
            try {
                [$code] = self::get(self::api('zipball/' . $target), $zipPath, 600);
                if ($code !== 200) {
                    throw new RuntimeException('Téléchargement de la version refusé par GitHub (code ' . $code . ').');
                }
                $res = self::install($zipPath, $target, $check, $by);
            } finally {
                @unlink($zipPath);
            }
        } finally {
            self::unlock($lock);
        }
        return $res;
    }

    /** Installe une archive GitHub (un dossier racine « dépôt-version/ »). */
    public static function install(string $zipPath, string $target, array $check, string $by): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Archive illisible.');
        }
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
                throw new RuntimeException('Archive refusée : chemin suspect (' . $rel . ').');
            }
            if (self::selected($rel)) {
                $entries[$rel] = ['name' => $name, 'crc' => sprintf('%08x', $st['crc'] & 0xFFFFFFFF), 'size' => (int) $st['size']];
            }
        }
        if (!isset($entries['front/src/bootstrap.php'], $entries['front/public/index.php'])) {
            throw new RuntimeException('Archive refusée : ce n\'est pas le code du site.');
        }
        $old = self::readJson(self::$dir . '/manifeste.json');
        $plan = ['write' => [], 'delete' => [], 'kept' => []];
        foreach ($entries as $rel => $e) {
            $local = self::dest($rel);
            $cur = is_file($local) ? hash_file('crc32b', $local) : null;
            if ($cur === $e['crc']) {
                continue;
            }
            if ($cur !== null && in_array($rel, self::KEEP_IF_EDITED, true) && ($old[$rel] ?? null) !== $cur) {
                $plan['kept'][] = $rel;
                continue;
            }
            $plan['write'][] = $rel;
        }
        foreach (array_keys($old) as $rel) {
            if (!isset($entries[$rel]) && self::selected($rel) && self::safe($rel) && is_file(self::dest($rel))) {
                $plan['delete'][] = $rel;
            }
        }
        $added = array_values(array_filter($plan['write'], fn($rel) => !is_file(self::dest($rel))));

        // Préparation : chaque fichier extrait et vérifié dans un dossier de travail, avant toute
        // écriture dans le site. Archive abîmée ou disque plein : rien n'est touché.
        foreach (glob(self::$dir . '/tmp/prepa-*') ?: [] as $d) {
            self::removeDir($d);
        }
        $stage = self::$dir . '/tmp/prepa-' . substr($target, 0, 7) . '-' . bin2hex(random_bytes(3));
        try {
            foreach ($plan['write'] as $rel) {
                self::writeFrom($zip, $entries[$rel]['name'], $stage . '/' . $rel);
            }
            $zip->close();
            $backup = self::backup($plan, $added, $target, $old, $by);

            @file_put_contents(self::$dir . '/maintenance', (string) time());
            try {
                foreach ($plan['write'] as $rel) {
                    self::place($stage . '/' . $rel, self::dest($rel), $entries[$rel]['size'], $entries[$rel]['crc']);
                }
                foreach ($plan['delete'] as $rel) {
                    @unlink(self::dest($rel));
                }
            } catch (Throwable $e) {
                self::failed($e, $backup, $target, $by);
            } finally {
                @unlink(self::$dir . '/maintenance');
            }
        } finally {
            self::removeDir($stage);
        }
        $manifest = [];
        foreach ($entries as $rel => $e) {
            $manifest[$rel] = $e['crc'];
        }
        self::writeJson(self::$dir . '/manifeste.json', $manifest);
        $latest = ($check['latest']['sha'] ?? '') === $target ? $check['latest'] : ['sha' => $target, 'date' => '', 'message' => ''];
        $from = self::installed();
        $entry = ['at' => date('c'), 'by' => $by, 'from' => $from['sha'] ?? null, 'to' => $target, 'date' => $latest['date'] ?? '', 'message' => $latest['message'] ?? '',
            'commits' => count($check['commits'] ?? []), 'updated' => count($plan['write']), 'deleted' => count($plan['delete']), 'kept' => $plan['kept'], 'backup' => $backup];
        self::saveState(['installed' => $latest + ['at' => date('c'), 'by' => $by], 'history' => array_slice(array_merge([$entry], self::history()), 0, 40)]);
        $s = self::state();
        if (is_array($s['check'] ?? null)) {
            $s['check']['sync'] = ['sha' => $target, 'at' => time(), 'total' => count($entries), 'count' => 0, 'differ' => [], 'eol' => 0, 'kept' => $plan['kept'], 'error' => null];
            self::saveState(['check' => $s['check']]);
        }
        self::invalidateCode(array_merge($plan['write'], $plan['delete']));
        return ['sha' => $target, 'updated' => $plan['write'], 'added' => $added, 'deleted' => $plan['delete'], 'kept' => $plan['kept'],
            'backup' => $backup, 'commits' => $check['commits'] ?? []];
    }

    /** Sauvegarde des fichiers remplacés ou supprimés, avec de quoi revenir en arrière. */
    private static function backup(array $plan, array $added, string $target, array $oldManifest, string $by): string
    {
        @mkdir(self::$dir . '/sauvegardes', 0775, true);
        $from = self::installed();
        $t = microtime(true);
        $name = date('Ymd-His', (int) $t) . sprintf('%06d', (int) (($t - floor($t)) * 1e6)) . '-' . ($from ? substr($from['sha'], 0, 7) : 'ftp') . '.zip';
        $z = new ZipArchive();
        if ($z->open(self::$dir . '/sauvegardes/' . $name, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Sauvegarde impossible : dossier storage/update non inscriptible.');
        }
        foreach (array_merge($plan['write'], $plan['delete']) as $rel) {
            if (is_file(self::dest($rel))) {
                $z->addFile(self::dest($rel), 'fichiers/' . $rel);
            }
        }
        $z->addFromString('meta.json', (string) json_encode(['at' => date('c'), 'by' => $by, 'from' => $from, 'to' => $target, 'added' => $added], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $z->addFromString('manifeste.json', (string) json_encode($oldManifest, JSON_UNESCAPED_SLASHES));
        if (!$z->close()) {
            throw new RuntimeException('Sauvegarde impossible (espace disque ?).');
        }
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
            $meta = null;
            $n = 0;
            if (class_exists(ZipArchive::class)) {
                $z = new ZipArchive();
                if ($z->open($f) === true) {
                    $meta = json_decode((string) $z->getFromName('meta.json'), true);
                    $n = max(0, $z->numFiles - 2);
                    $z->close();
                }
            }
            $out[] = ['name' => basename($f), 'meta' => is_array($meta) ? $meta : [], 'files' => $n, 'size' => (int) @filesize($f)];
        }
        return $out;
    }

    /** Remet les fichiers d'une sauvegarde (annule la mise à jour qui l'a créée). */
    public static function rollback(string $name, string $by): array
    {
        $name = basename($name);
        $path = self::$dir . '/sauvegardes/' . $name;
        if (!preg_match('/^\d{8}-\d{6}(\d{6})?-[0-9a-z]+\.zip$/', $name) || !is_file($path)) {
            throw new RuntimeException('Sauvegarde introuvable.');
        }
        $lock = self::lock();
        try {
            @file_put_contents(self::$dir . '/maintenance', (string) time());
            try {
                [$restored, $meta, $files] = self::restore($path);
            } finally {
                @unlink(self::$dir . '/maintenance');
            }
            $back = is_array($meta['from'] ?? null) ? $meta['from'] : null;
            $entry = ['at' => date('c'), 'by' => $by, 'from' => $meta['to'] ?? null, 'to' => $back['sha'] ?? null, 'date' => $back['date'] ?? '',
                'message' => 'Retour arrière (sauvegarde ' . $name . ')', 'updated' => $restored, 'deleted' => count((array) ($meta['added'] ?? [])), 'kept' => [], 'backup' => null];
            $s = self::state();
            if (is_array($s['check'] ?? null)) {
                $s['check']['sync'] = null;
            }
            self::saveState(['installed' => $back, 'history' => array_slice(array_merge([$entry], self::history()), 0, 40), 'check' => $s['check'] ?? null]);
            self::invalidateCode($files);
            return ['restored' => $restored, 'to' => $back];
        } finally {
            self::unlock($lock);
        }
    }

    private static function restore(string $path): array
    {
        $z = new ZipArchive();
        if ($z->open($path) !== true) {
            throw new RuntimeException('Sauvegarde illisible.');
        }
        $meta = json_decode((string) $z->getFromName('meta.json'), true) ?: [];
        $manifest = json_decode((string) $z->getFromName('manifeste.json'), true);
        $restored = 0;
        $files = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $entry = (string) $z->getNameIndex($i);
            if (!str_starts_with($entry, 'fichiers/') || str_ends_with($entry, '/')) {
                continue;
            }
            $rel = substr($entry, strlen('fichiers/'));
            if (self::safe($rel) && self::selected($rel)) {
                self::writeFrom($z, $entry, self::dest($rel));
                $restored++;
                $files[] = $rel;
            }
        }
        foreach ((array) ($meta['added'] ?? []) as $rel) {
            if (is_string($rel) && self::safe($rel) && self::selected($rel)) {
                @unlink(self::dest($rel));
                $files[] = $rel;
            }
        }
        $z->close();
        if (is_array($manifest)) {
            self::writeJson(self::$dir . '/manifeste.json', $manifest);
        }
        return [$restored, $meta, $files];
    }

    private static function failed(Throwable $e, string $backup, string $target, string $by): void
    {
        $msg = $e->getMessage();
        try {
            [$restored] = self::restore(self::$dir . '/sauvegardes/' . $backup);
            $what = 'version précédente remise en place (' . $restored . ' fichier(s))';
        } catch (Throwable $r) {
            error_log('[mise à jour] retour arrière impossible : ' . $r);
            $what = 'retour arrière automatique impossible (' . $r->getMessage() . ') : utilisez « Revenir à cette version » avec la sauvegarde ' . $backup;
        }
        $from = self::installed();
        $entry = ['at' => date('c'), 'by' => $by, 'from' => $from['sha'] ?? null, 'to' => $target, 'date' => '',
            'message' => 'Échec de la mise à jour : ' . $msg . ' ; ' . $what, 'updated' => 0, 'deleted' => 0, 'kept' => [], 'backup' => $backup, 'error' => true];
        self::saveState(['history' => array_slice(array_merge([$entry], self::history()), 0, 40)]);
        throw new RuntimeException('Mise à jour interrompue (' . $msg . ') : ' . $what . '.', 0, $e);
    }

    // ------------------------------------------------------------------ outils

    private static function writeFrom(ZipArchive $zip, string $entry, string $dest): void
    {
        $st = $zip->statName($entry);
        $in = $st ? $zip->getStream($entry) : false;
        if (!$in) {
            throw new RuntimeException('Fichier illisible dans l\'archive : ' . $entry);
        }
        self::ensureDir(dirname($dest));
        $tmp = $dest . '.maj-' . bin2hex(random_bytes(3));
        $out = @fopen($tmp, 'wb');
        if (!$out) {
            fclose($in);
            throw new RuntimeException('Écriture impossible : ' . self::short($dest) . ' (droits du dossier ?).');
        }
        $n = stream_copy_to_stream($in, $out);
        fclose($in);
        $closed = fclose($out);
        $crc = sprintf('%08x', $st['crc'] & 0xFFFFFFFF);
        if ($n !== (int) $st['size'] || !$closed || (int) @filesize($tmp) !== (int) $st['size'] || hash_file('crc32b', $tmp) !== $crc) {
            @unlink($tmp);
            throw new RuntimeException('Fichier incomplet ou abîmé : ' . self::short($dest) . ' (espace disque ? archive endommagée ?).');
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new RuntimeException('Remplacement impossible : ' . self::short($dest) . '.');
        }
    }

    private static function place(string $src, string $dest, int $size, string $crc): void
    {
        self::ensureDir(dirname($dest));
        $tmp = $dest . '.maj-' . bin2hex(random_bytes(3));
        if (!@copy($src, $tmp) || (int) @filesize($tmp) !== $size || hash_file('crc32b', $tmp) !== $crc) {
            @unlink($tmp);
            throw new RuntimeException('Copie incomplète : ' . self::short($dest) . ' (espace disque ? droits du dossier ?).');
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new RuntimeException('Remplacement impossible : ' . self::short($dest) . '.');
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Dossier impossible à créer : ' . self::short($dir) . '.');
        }
    }

    private static function short(string $path): string
    {
        return ltrim(str_replace(self::$root, '', $path), '/');
    }

    private static function invalidateCode(array $rels): void
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }
        foreach ($rels as $rel) {
            if (str_ends_with($rel, '.php')) {
                try {
                    @opcache_invalidate(self::dest($rel), true);
                } catch (Throwable) {
                }
            }
        }
    }

    private static function readJson(string $file): array
    {
        $j = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
        return is_array($j) ? $j : [];
    }

    private static function writeJson(string $file, array $data): void
    {
        self::ensureDir(dirname($file));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new RuntimeException('Encodage impossible : ' . self::short($file) . ' (' . json_last_error_msg() . ').');
        }
        $tmp = $file . '.' . bin2hex(random_bytes(3));
        if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Écriture impossible : ' . self::short($file) . ' (droits du dossier storage/ ?).');
        }
    }

    /** Une seule mise à jour à la fois. */
    private static function lock()
    {
        @mkdir(self::$dir, 0775, true);
        $file = self::$dir . '/verrou.lock';
        $fp = fopen($file, 'c');
        if ($fp && !flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            $fp = null;
            if ((int) @filemtime($file) < time() - 900 && @unlink($file)) {
                $fp = fopen($file, 'c');
                if ($fp && !flock($fp, LOCK_EX | LOCK_NB)) {
                    fclose($fp);
                    $fp = null;
                }
            }
        }
        if (!$fp) {
            throw new RuntimeException('Une mise à jour est déjà en cours (depuis moins de 15 minutes). Réessayez après ce délai.');
        }
        @touch($file);
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
