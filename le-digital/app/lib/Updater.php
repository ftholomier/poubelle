<?php
declare(strict_types=1);

namespace LD;

/**
 * Mises à jour du site en un clic depuis GitHub (back-office › Mises à jour).
 *
 * Le site vit dans un dossier du dépôt (réglage « Dossier du site dans le dépôt », par défaut « le-digital »).
 * La dernière version de ce dossier sur la branche réglée est comparée, fichier par fichier, au code du serveur
 * grâce aux empreintes Git (rien n'est téléchargé pour comparer). « Mettre à jour » ne télécharge que les fichiers
 * qui ont changé, vérifie chacun (empreinte Git) puis les met en place :
 *   - remplacés : app/, public/ (sauf public/uploads/), .htaccess, README.md ;
 *   - jamais touchés : storage/ (contenus, demandes, clés…) et public/uploads/ (médias) ;
 *   - gardés s'ils ont été modifiés sur le serveur : .htaccess et public/.htaccess (réglages d'hébergement).
 *
 * Avant d'écrire, les fichiers remplacés ou supprimés sont sauvegardés (storage/update/sauvegardes/) :
 * « Revenir à cette version » les remet en place. Pendant la copie, le site répond « Mise à jour en cours »
 * (storage/update/maintenance) ; une erreur en cours de copie remet aussitôt la sauvegarde en place.
 *
 * Un site mis en ligne par FTP dont le code est identique à la dernière version voit sa version reconnue.
 *
 * État : storage/update/etat.json (version installée, dernière vérification, historique) ;
 * storage/update/manifeste.json (empreinte Git de chaque fichier installé).
 */
final class Updater
{
    /** Code du site remplacé par une mise à jour (dossiers « …/ » ou fichiers), chemins relatifs au dossier du site. */
    public const MANAGED = ['app/', 'public/', '.htaccess', 'README.md'];
    /** Jamais touchés. */
    public const EXCLUDED = ['public/uploads/'];
    /** Gardés s'ils ont été modifiés sur le serveur. */
    public const KEEP_IF_EDITED = ['.htaccess', 'public/.htaccess'];
    /** Fichiers qui prouvent qu'il s'agit bien du code du site. */
    private const MARKERS = ['app/bootstrap.php', 'public/index.php'];
    private const KEEP_BACKUPS = 5;
    private const CHECK_TTL = 3600;
    private const MAX_FILE = 20 * 1024 * 1024;

    public static string $root = LD_ROOT;
    public static string $dir = LD_STORAGE . '/update';
    /** Essais : réponses HTTP simulées fn(url) => [code, corps]. */
    public static ?\Closure $http = null;

    // ------------------------------------------------------------------ réglages

    public static function settings(): array
    {
        return (array) Settings::get('updates', []) + ['repo' => '', 'branch' => 'main', 'path' => '', 'auto_check' => true];
    }

    public static function repo(): string
    {
        $r = trim((string) self::settings()['repo']);
        return preg_match('#^[\w.-]+/[\w.-]+$#', $r) ? $r : '';
    }

    public static function branch(): string
    {
        $b = trim((string) self::settings()['branch']);
        return self::validRef($b) ? $b : 'main';
    }

    /** Dossier du site dans le dépôt (« » : racine du dépôt). */
    public static function prefix(): string
    {
        $p = trim(str_replace('\\', '/', (string) self::settings()['path']), '/');
        return $p === '' || self::validPath($p) ? $p : '';
    }

    public static function validRef(string $b): bool
    {
        return $b !== '' && (bool) preg_match('#^[\w.-]+(/[\w.-]+)*$#', $b) && !str_contains($b, '..');
    }

    public static function validPath(string $p): bool
    {
        return (bool) preg_match('#^[\w.-]+(/[\w.-]+)*$#', $p) && !str_contains($p, '..');
    }

    private static function token(): string
    {
        return trim((string) Secrets::get('github_token'));
    }

    public static function hasToken(): bool
    {
        return self::token() !== '';
    }

    public static function configured(): bool
    {
        return self::repo() !== '';
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
        if (!$latest || ($c['key'] ?? '') !== self::key()) {
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

    /** Libellé de version : « 23/10/2026 14:05 · a1b2c3d ». */
    public static function label(?array $v): string
    {
        if (!$v || empty($v['sha'])) {
            return 'version inconnue (mise en ligne par FTP)';
        }
        $d = !empty($v['date']) ? date('d/m/Y H:i', strtotime((string) $v['date'])) : '';
        return trim($d . ' · ' . substr((string) $v['sha'], 0, 7), ' ·');
    }

    private static function key(): string
    {
        return self::repo() . '#' . self::branch() . '#' . self::prefix();
    }

    // ------------------------------------------------------------------ vérification

    /** Vérification par la tâche planifiée : au plus toutes les 3 heures. Retourne un message si une mise à jour attend. */
    public static function checkIfDue(): ?string
    {
        if (!self::configured() || empty(self::settings()['auto_check'])) {
            return null;
        }
        $c = self::state()['check'] ?? null;
        if (is_array($c) && ($c['key'] ?? '') === self::key() && ($c['at'] ?? 0) > time() - 3 * 3600) {
            return null;
        }
        try {
            $c = self::check(true);
        } catch (\Throwable $e) {
            return 'Mises à jour : ' . $e->getMessage();
        }
        if (!empty($c['error'])) {
            return 'Mises à jour : ' . $c['error'];
        }
        return self::available() ? 'Mise à jour disponible : ' . self::label($c['latest']) : null;
    }

    /**
     * Dernière version du site sur GitHub, changements depuis la version installée et comparaison du code
     * du serveur avec cette version (mis en cache une heure, sauf $force).
     */
    public static function check(bool $force = false): array
    {
        $c = self::state()['check'] ?? null;
        if (!$force && is_array($c) && ($c['key'] ?? '') === self::key() && ($c['at'] ?? 0) > time() - self::CHECK_TTL) {
            return $c;
        }
        $res = ['at' => time(), 'key' => self::key(), 'latest' => null, 'commits' => [], 'sync' => null, 'error' => null];
        try {
            if (!self::configured()) {
                throw new \RuntimeException('Dépôt GitHub non réglé (réglages ci-dessous).');
            }
            $res['latest'] = self::latest();
            $tree = [];
            $res['sync'] = self::sync($res['latest']['sha'], $tree);
            if ($res['sync']['error'] === null && $res['sync']['count'] === 0 && (self::installed()['sha'] ?? '') !== $res['latest']['sha']) {
                self::adopt($res['latest'], $tree);
            }
            $inst = self::installed();
            if (($inst['sha'] ?? '') !== $res['latest']['sha']) {
                $res['commits'] = self::commitsSince($inst);
            }
        } catch (\Throwable $e) {
            $res['error'] = $e->getMessage();
        }
        self::saveState(['check' => $res]);
        return $res;
    }

    /** Dernier commit qui touche le dossier du site sur la branche. */
    private static function latest(): array
    {
        $prefix = self::prefix();
        if ($prefix === '') {
            [$code, $body] = self::get(self::api('commits/' . self::ref(self::branch())));
            $j = $code === 200 ? json_decode($body, true) : null;
        } else {
            [$code, $body] = self::get(self::api('commits?per_page=1&sha=' . rawurlencode(self::branch()) . '&path=' . rawurlencode($prefix)));
            $list = $code === 200 ? json_decode($body, true) : null;
            if (is_array($list) && $list === []) {
                throw new \RuntimeException('Le dossier « ' . $prefix . ' » n’existe pas sur la branche « ' . self::branch() . ' ».');
            }
            $j = is_array($list) ? ($list[0] ?? null) : null;
        }
        if (is_array($j) && !empty($j['sha'])) {
            return self::commit($j);
        }
        if ($code === 404 || $code === 422 || $code === 409) {
            throw new \RuntimeException('Branche « ' . self::branch() . ' » introuvable dans le dépôt ' . self::repo() . (self::hasToken() ? ' (ou jeton sans accès à ce dépôt).' : ' : s’il est privé, ajoute un jeton GitHub.'));
        }
        throw new \RuntimeException(self::apiError($code));
    }

    /** Commits du dossier du site depuis la version installée (les 15 derniers si elle est inconnue). */
    private static function commitsSince(?array $inst): array
    {
        $q = 'commits?per_page=' . ($inst ? 60 : 15) . '&sha=' . rawurlencode(self::branch()) . (self::prefix() !== '' ? '&path=' . rawurlencode(self::prefix()) : '');
        [$code, $body] = self::get(self::api($q));
        $list = $code === 200 ? json_decode($body, true) : null;
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        $since = $inst && !empty($inst['date']) ? strtotime((string) $inst['date']) : null;
        foreach ($list as $j) {
            $c = self::commit($j);
            if ($inst && ($c['sha'] === $inst['sha'] || ($since && strtotime($c['date']) < $since))) {
                break;
            }
            $out[] = $c;
        }
        return $out;
    }

    private static function commit(array $j): array
    {
        $msg = mb_scrub((string) ($j['commit']['message'] ?? ''), 'UTF-8');
        $lines = preg_split('/\r\n|\r|\n/', trim($msg)) ?: [];
        // Détail : points « - … » et paragraphes sous le titre (lignes coupées recollées), sans les mentions de co-auteur.
        $details = [];
        $new = true;
        foreach (array_slice($lines, 1) as $l) {
            $l = trim($l);
            if ($l === '') {
                $new = true;
            } elseif (!preg_match('/^(Co-Authored-By|Claude-Session|Signed-off-by):/i', $l)) {
                if ($new || preg_match('/^[-*•]\s+/u', $l)) {
                    $details[] = (string) preg_replace('/^[-*•]\s+/u', '', $l);
                } else {
                    $details[count($details) - 1] .= ' ' . $l;
                }
                $new = false;
            }
        }
        return [
            'sha'     => (string) $j['sha'],
            'date'    => (string) ($j['commit']['committer']['date'] ?? ($j['commit']['author']['date'] ?? '')),
            'message' => trim($lines[0] ?? ''),
            'details' => array_map(static fn($d) => mb_strimwidth($d, 0, 400, '…'), array_slice($details, 0, 12)),
        ];
    }

    // ------------------------------------------------------------------ comparaison

    /**
     * Le code du serveur comparé, fichier par fichier, à une version de GitHub (empreintes Git).
     * Les fins de ligne ne comptent pas pour un fichier texte. $tree reçoit [chemin => empreinte] de la version.
     */
    public static function sync(string $sha, ?array &$tree = null): array
    {
        $res = ['sha' => $sha, 'at' => time(), 'total' => 0, 'count' => 0, 'differ' => [], 'eol' => 0, 'kept' => [], 'error' => null];
        $tree = [];
        try {
            $tree = self::tree($sha);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()] + $res;
        }
        foreach (self::MARKERS as $m) {
            if (!isset($tree[$m])) {
                $res['error'] = 'Cette version de GitHub ne contient pas le code du site (dossier « ' . self::prefix() . ' » ?).';
                return $res;
            }
        }
        $old = self::readJson(self::$dir . '/manifeste.json');
        foreach ($tree as $rel => $blob) {
            $st = self::sameAs(self::dest($rel), $blob);
            if ($st === 'différent' && self::keepLocal($rel, $old)) {
                $st = 'gardé';
                $res['kept'][] = $rel;
            }
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

    /** Fichier réglé à la main sur le serveur (et différent de ce qu'avait installé la dernière mise à jour) : gardé. */
    private static function keepLocal(string $rel, array $manifest): bool
    {
        return in_array($rel, self::KEEP_IF_EDITED, true) && is_file(self::dest($rel)) && ($manifest[$rel] ?? null) !== self::blobOf((string) @file_get_contents(self::dest($rel)));
    }

    private static function blobOf(string $data): string
    {
        return sha1('blob ' . strlen($data) . "\0" . $data);
    }

    /** Fichier du serveur comparé à une empreinte Git : 'ok', 'eol', 'absent' ou 'différent'. */
    private static function sameAs(string $file, string $blob): string
    {
        if (!is_file($file)) {
            return 'absent';
        }
        $data = (string) @file_get_contents($file);
        if (self::blobOf($data) === $blob) {
            return 'ok';
        }
        if (!str_contains(substr($data, 0, 8000), "\0")) {
            $lf = str_replace("\r\n", "\n", $data);
            if (self::blobOf($lf) === $blob || self::blobOf(str_replace("\n", "\r\n", $lf)) === $blob) {
                return 'eol';
            }
        }
        return 'différent';
    }

    /**
     * Fichiers du site dans une version : [chemin relatif au dossier du site => empreinte Git].
     * Les dossiers app/ et public/ sont listés une fois par empreinte (un dossier inchangé n'est pas redemandé).
     */
    private static function tree(string $sha): array
    {
        $treeSha = $sha;
        $root = self::treeJson($treeSha, false);
        foreach (self::prefix() === '' ? [] : explode('/', self::prefix()) as $segment) {
            $next = null;
            foreach ($root['tree'] as $e) {
                if (($e['type'] ?? '') === 'tree' && ($e['path'] ?? '') === $segment) {
                    $next = (string) $e['sha'];
                }
            }
            if ($next === null) {
                throw new \RuntimeException('Dossier « ' . self::prefix() . ' » introuvable dans cette version.');
            }
            $root = self::treeJson($treeSha = $next, false);
        }
        $tops = [];
        foreach (self::MANAGED as $m) {
            if (str_ends_with($m, '/')) {
                $tops[rtrim($m, '/')] = true;
            }
        }
        $dirs = [];
        $out = [];
        foreach ($root['tree'] as $e) {
            $name = (string) ($e['path'] ?? '');
            if (($e['type'] ?? '') === 'tree' && isset($tops[$name]) && preg_match('/^[0-9a-f]{40}$/', (string) ($e['sha'] ?? ''))) {
                $dirs[$name] = (string) $e['sha'];
            } elseif (($e['type'] ?? '') === 'blob' && self::selected($name) && self::safe($name)) {
                $out[$name] = (string) $e['sha'];
            }
        }
        self::ensureDir(self::$dir . '/arbres');
        foreach ($dirs as $name => $dirSha) {
            $cache = self::$dir . '/arbres/' . $dirSha . '.json';
            $list = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
            if (!is_array($list)) {
                $j = self::treeJson($dirSha, true);
                if (!empty($j['truncated'])) {
                    throw new \RuntimeException('Dossier ' . $name . '/ trop grand pour être comparé.');
                }
                $list = [];
                foreach ($j['tree'] as $e) {
                    if (($e['type'] ?? '') === 'blob' && ($e['mode'] ?? '') !== '120000' && isset($e['path'], $e['sha'])) {
                        $list[(string) $e['path']] = (string) $e['sha'];
                    }
                }
                @file_put_contents($cache, json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
            foreach ($list as $p => $b) {
                $rel = $name . '/' . $p;
                if (self::selected($rel) && self::safe($rel)) {
                    $out[$rel] = (string) $b;
                }
            }
        }
        foreach (glob(self::$dir . '/arbres/*.json') ?: [] as $f) {
            if (!in_array(basename($f, '.json'), $dirs, true)) {
                @unlink($f);
            }
        }
        ksort($out);
        return $out;
    }

    private static function treeJson(string $sha, bool $recursive): array
    {
        [$code, $body] = self::get(self::api('git/trees/' . rawurlencode($sha) . ($recursive ? '?recursive=1' : '')));
        $j = $code === 200 ? json_decode($body, true) : null;
        if (!is_array($j) || !is_array($j['tree'] ?? null)) {
            throw new \RuntimeException(self::apiError($code));
        }
        return $j;
    }

    private static function apiError(int $code): string
    {
        return match (true) {
            $code === 401 => 'Jeton GitHub refusé (expiré ou incorrect) : renouvelle-le dans les réglages ci-dessous.',
            $code === 403, $code === 429 => 'GitHub limite le nombre d’appels pour l’instant' . (self::hasToken() ? '' : ' (sans jeton : 60 par heure)') . ' : réessaie dans une heure.',
            $code === 404, $code === 422 => 'Version introuvable sur GitHub' . (self::hasToken() ? ' (ou jeton sans accès au dépôt).' : ' : si le dépôt est privé, ajoute un jeton GitHub.'),
            default => 'GitHub ne répond pas (code ' . $code . ').',
        };
    }

    /** Code du serveur identique à la dernière version : version reconnue sans rien télécharger. */
    private static function adopt(array $latest, array $tree): void
    {
        try {
            $lock = self::lock();
        } catch (\RuntimeException) {
            return;
        }
        try {
            $from = self::installed();
            self::writeJson(self::$dir . '/manifeste.json', $tree);
            $entry = ['at' => date('c'), 'by' => 'Vérification', 'from' => $from['sha'] ?? null, 'to' => $latest['sha'], 'date' => $latest['date'],
                'message' => 'Version reconnue : le code du serveur est identique à cette version de GitHub', 'updated' => 0, 'deleted' => 0, 'kept' => [], 'backup' => null];
            self::saveState(['installed' => $latest + ['at' => date('c'), 'by' => 'Vérification', 'via' => 'reconnue'], 'history' => array_slice([$entry, ...self::history()], 0, 40)]);
        } finally {
            self::unlock($lock);
        }
    }

    // ------------------------------------------------------------------ chemins

    /** Le chemin (relatif au dossier du site) fait-il partie de ce qu'une mise à jour remplace ? */
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

    public static function dest(string $rel): string
    {
        return self::$root . '/' . $rel;
    }

    /** Chemin relatif sûr : ni « .. », ni chemin absolu, ni caractère de contrôle. */
    public static function safe(string $rel): bool
    {
        return $rel !== '' && !str_contains($rel, '\\') && !str_starts_with($rel, '/')
            && !preg_match('#(^|/)\.\.?(/|$)#', $rel) && !preg_match('/[\x00-\x1F\x7F]/', $rel);
    }

    // ------------------------------------------------------------------ application

    /**
     * Télécharge les fichiers qui ont changé dans la dernière version et les met en place.
     * ['sha', 'updated', 'added', 'deleted', 'kept', 'backup', 'commits'].
     */
    public static function apply(string $by): array
    {
        @set_time_limit(600);
        @ignore_user_abort(true);
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Extension PHP Zip absente sur le serveur (nécessaire aux sauvegardes).');
        }
        $lock = self::lock();
        try {
            $check = self::check(true);
            if (!empty($check['error'])) {
                throw new \RuntimeException($check['error']);
            }
            if (!empty($check['sync']['error'])) {
                throw new \RuntimeException($check['sync']['error']);
            }
            $latest = $check['latest'];
            $target = (string) $latest['sha'];
            if (!preg_match('/^[0-9a-f]{40}$/', $target)) {
                throw new \RuntimeException('Version à installer inconnue.');
            }
            $tree = self::tree($target);
            $res = self::install($tree, $latest, $check, $by);
        } finally {
            self::unlock($lock);
        }
        return $res;
    }

    private static function install(array $tree, array $latest, array $check, string $by): array
    {
        $target = (string) $latest['sha'];
        $old = self::readJson(self::$dir . '/manifeste.json');
        $plan = ['write' => [], 'delete' => [], 'kept' => []];
        foreach ($tree as $rel => $blob) {
            $st = self::sameAs(self::dest($rel), $blob);
            if ($st === 'ok' || $st === 'eol') {
                continue;
            }
            if ($st === 'différent' && self::keepLocal($rel, $old)) {
                $plan['kept'][] = $rel;
                continue;
            }
            $plan['write'][] = $rel;
        }
        foreach (array_keys($old) as $rel) {
            if (!isset($tree[$rel]) && self::selected($rel) && self::safe($rel) && is_file(self::dest($rel))) {
                $plan['delete'][] = $rel;
            }
        }
        $added = array_values(array_filter($plan['write'], static fn($rel) => !is_file(self::dest($rel))));

        // Préparation : chaque fichier téléchargé et vérifié dans un dossier de travail, avant toute écriture
        // dans le site. Réseau coupé, fichier abîmé ou disque plein : rien n'est touché.
        foreach (glob(self::$dir . '/tmp/prepa-*') ?: [] as $d) {
            self::removeDir($d);
        }
        $stage = self::$dir . '/tmp/prepa-' . substr($target, 0, 7) . '-' . bin2hex(random_bytes(3));
        $backup = null;
        try {
            foreach ($plan['write'] as $rel) {
                self::download($tree[$rel], $stage . '/' . $rel);
            }
            if ($plan['write'] || $plan['delete']) {
                $backup = self::backup($plan, $added, $target, $old, $by);
                // Copie : le site répond « Mise à jour en cours » le temps d'écrire. Un échec en cours de route
                // remet aussitôt la sauvegarde en place.
                @file_put_contents(self::$dir . '/maintenance', (string) time());
                try {
                    foreach ($plan['write'] as $rel) {
                        self::place($stage . '/' . $rel, self::dest($rel), $tree[$rel]);
                    }
                    foreach ($plan['delete'] as $rel) {
                        @unlink(self::dest($rel));
                    }
                } catch (\Throwable $e) {
                    self::failed($e, $backup, $target, $by);
                } finally {
                    @unlink(self::$dir . '/maintenance');
                }
            }
        } finally {
            self::removeDir($stage);
        }
        self::writeJson(self::$dir . '/manifeste.json', $tree);
        $from = self::installed();
        $entry = ['at' => date('c'), 'by' => $by, 'from' => $from['sha'] ?? null, 'to' => $target, 'date' => $latest['date'] ?? '', 'message' => $latest['message'] ?? '',
            'commits' => count($check['commits'] ?? []), 'updated' => count($plan['write']), 'deleted' => count($plan['delete']), 'kept' => $plan['kept'], 'backup' => $backup];
        $s = self::state();
        if (is_array($s['check'] ?? null)) {
            $s['check']['sync'] = ['sha' => $target, 'at' => time(), 'total' => count($tree), 'count' => 0, 'differ' => [], 'eol' => 0, 'kept' => $plan['kept'], 'error' => null];
            $s['check']['commits'] = [];
        }
        self::saveState(['installed' => $latest + ['at' => date('c'), 'by' => $by], 'history' => array_slice([$entry, ...self::history()], 0, 40), 'check' => $s['check'] ?? null]);
        self::afterChange(array_merge($plan['write'], $plan['delete']));
        return ['sha' => $target, 'updated' => $plan['write'], 'added' => $added, 'deleted' => $plan['delete'], 'kept' => $plan['kept'],
            'backup' => $backup, 'commits' => $check['commits'] ?? []];
    }

    /** Télécharge un fichier (blob Git) et vérifie son empreinte avant de l'écrire dans $dest. */
    private static function download(string $blob, string $dest): void
    {
        [$code, $body] = self::get(self::api('git/blobs/' . $blob), 120);
        $j = $code === 200 ? json_decode($body, true) : null;
        if (!is_array($j) || ($j['encoding'] ?? '') !== 'base64') {
            throw new \RuntimeException('Téléchargement refusé par GitHub : ' . self::apiError($code));
        }
        $data = base64_decode(str_replace(["\n", "\r"], '', (string) $j['content']), true);
        if ($data === false || strlen($data) > self::MAX_FILE || self::blobOf($data) !== $blob) {
            throw new \RuntimeException('Fichier reçu incomplet ou abîmé (' . substr($blob, 0, 7) . ') : réessaie.');
        }
        self::ensureDir(dirname($dest));
        if (@file_put_contents($dest, $data) !== strlen($data)) {
            throw new \RuntimeException('Écriture impossible dans storage/update (espace disque ?).');
        }
    }

    /** Copie un fichier préparé (et vérifié) à sa place, revérifié avant le renommage. */
    private static function place(string $src, string $dest, string $blob): void
    {
        self::ensureDir(dirname($dest));
        $tmp = $dest . '.maj-' . bin2hex(random_bytes(3));
        if (!@copy($src, $tmp) || self::blobOf((string) @file_get_contents($tmp)) !== $blob) {
            @unlink($tmp);
            throw new \RuntimeException('Copie incomplète : ' . self::short($dest) . ' (espace disque ? droits du dossier ?).');
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new \RuntimeException('Remplacement impossible : ' . self::short($dest) . ' (droits du dossier ?).');
        }
    }

    /** Après un changement de code : OPcache, cache des pages, et nouveaux dossiers / données de départ. */
    private static function afterChange(array $rels): void
    {
        if (function_exists('opcache_invalidate')) {
            foreach ($rels as $rel) {
                if (str_ends_with($rel, '.php')) {
                    @opcache_invalidate(self::dest($rel), true);
                }
            }
        }
        PageCache::clear();
        // Store::boot repasse à la prochaine requête (crée ce qui manque, ne remplace rien).
        @unlink(LD_STORAGE . '/.booted');
        Store::appendLog('update', ['event' => 'files', 'count' => count($rels)]);
    }

    /** Sauvegarde des fichiers remplacés ou supprimés, avec de quoi revenir en arrière. */
    private static function backup(array $plan, array $added, string $target, array $oldManifest, string $by): string
    {
        self::ensureDir(self::$dir . '/sauvegardes');
        $from = self::installed();
        $t = microtime(true);
        $name = date('Ymd-His', (int) $t) . sprintf('%06d', (int) (($t - floor($t)) * 1e6)) . '-' . ($from ? substr($from['sha'], 0, 7) : 'ftp') . '.zip';
        $z = new \ZipArchive();
        if ($z->open(self::$dir . '/sauvegardes/' . $name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Sauvegarde impossible : dossier storage/update non inscriptible.');
        }
        foreach (array_merge($plan['write'], $plan['delete']) as $rel) {
            if (is_file(self::dest($rel))) {
                $z->addFile(self::dest($rel), 'fichiers/' . $rel);
            }
        }
        $z->addFromString('meta.json', (string) json_encode(['at' => date('c'), 'by' => $by, 'from' => $from, 'to' => $target, 'added' => $added], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $z->addFromString('manifeste.json', (string) json_encode($oldManifest, JSON_UNESCAPED_SLASHES));
        if (!$z->close()) {
            throw new \RuntimeException('Sauvegarde impossible (espace disque ?).');
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
            if (class_exists(\ZipArchive::class)) {
                $z = new \ZipArchive();
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
        if (!preg_match('/^\d{8}-\d{12}-[0-9a-z]+\.zip$/', $name) || !is_file($path)) {
            throw new \RuntimeException('Sauvegarde introuvable.');
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
                'message' => 'Retour arrière (sauvegarde du ' . date('d/m/Y H:i', strtotime((string) ($meta['at'] ?? 'now'))) . ')', 'updated' => $restored, 'deleted' => count((array) ($meta['added'] ?? [])), 'kept' => [], 'backup' => null];
            $s = self::state();
            if (is_array($s['check'] ?? null)) {
                $s['check']['sync'] = null; // à refaire : le code a changé
                $s['check']['at'] = 0;
            }
            self::saveState(['installed' => $back, 'history' => array_slice([$entry, ...self::history()], 0, 40), 'check' => $s['check'] ?? null]);
            self::afterChange($files);
            return ['restored' => $restored, 'to' => $back];
        } finally {
            self::unlock($lock);
        }
    }

    /** Remet en place les fichiers d'une sauvegarde. [nombre remis, méta, chemins concernés]. */
    private static function restore(string $path): array
    {
        $z = new \ZipArchive();
        if ($z->open($path) !== true) {
            throw new \RuntimeException('Sauvegarde illisible.');
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

    /** Copie interrompue : la sauvegarde faite juste avant est remise en place, puis l'erreur relancée. */
    private static function failed(\Throwable $e, string $backup, string $target, string $by): never
    {
        $msg = $e->getMessage();
        try {
            [$restored] = self::restore(self::$dir . '/sauvegardes/' . $backup);
            $what = 'version précédente remise en place (' . $restored . ' fichier(s))';
        } catch (\Throwable $r) {
            $what = 'retour arrière automatique impossible (' . $r->getMessage() . ') : utilise « Revenir à cette version » avec la sauvegarde ' . $backup;
        }
        $from = self::installed();
        $entry = ['at' => date('c'), 'by' => $by, 'from' => $from['sha'] ?? null, 'to' => $target, 'date' => '',
            'message' => 'Échec de la mise à jour : ' . $msg . ' ; ' . $what, 'updated' => 0, 'deleted' => 0, 'kept' => [], 'backup' => $backup, 'error' => true];
        self::saveState(['history' => array_slice([$entry, ...self::history()], 0, 40)]);
        throw new \RuntimeException('Mise à jour interrompue (' . $msg . ') : ' . $what . '.', 0, $e);
    }

    // ------------------------------------------------------------------ outils

    private static function api(string $path): string
    {
        return 'https://api.github.com/repos/' . self::repo() . '/' . $path;
    }

    private static function ref(string $b): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $b)));
    }

    /** GET HTTPS vers l'API GitHub : [code, corps]. */
    private static function get(string $url, int $timeout = 25): array
    {
        if (self::$http) {
            return (self::$http)($url);
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Extension PHP cURL absente sur le serveur.');
        }
        $headers = ['User-Agent: LE-DIGITAL-MiseAJour', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if (($t = self::token()) !== '') {
            $headers[] = 'Authorization: Bearer ' . $t;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $timeout, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException('GitHub injoignable : ' . $err);
        }
        return [$code, (string) $body];
    }

    /** Extrait une entrée d'archive vers $dest (fichier temporaire puis renommage). */
    private static function writeFrom(\ZipArchive $zip, string $entry, string $dest): void
    {
        $data = $zip->getFromName($entry);
        if ($data === false) {
            throw new \RuntimeException('Fichier illisible dans la sauvegarde : ' . $entry);
        }
        self::ensureDir(dirname($dest));
        $tmp = $dest . '.maj-' . bin2hex(random_bytes(3));
        if (@file_put_contents($tmp, $data) !== strlen($data)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : ' . self::short($dest) . ' (droits du dossier ? espace disque ?).');
        }
        @chmod($tmp, 0644);
        if (!@rename($tmp, $dest)) {
            @unlink($tmp);
            throw new \RuntimeException('Remplacement impossible : ' . self::short($dest) . '.');
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
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
            throw new \RuntimeException('Encodage impossible : ' . self::short($file) . ' (' . json_last_error_msg() . ').');
        }
        $tmp = $file . '.' . bin2hex(random_bytes(3));
        if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : ' . self::short($file) . ' (droits du dossier storage/ ?).');
        }
    }

    /** Une seule mise à jour à la fois. */
    private static function lock()
    {
        self::ensureDir(self::$dir);
        $file = self::$dir . '/verrou.lock';
        $fp = fopen($file, 'c');
        if ($fp && !flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            $fp = null;
        }
        if (!$fp) {
            throw new \RuntimeException('Une mise à jour est déjà en cours. Réessaie dans quelques minutes.');
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

    /** Site en cours de mise à jour (copie des fichiers) : le front répond « revenez dans un instant ». */
    public static function inMaintenance(): bool
    {
        $f = self::$dir . '/maintenance';
        return is_file($f) && (int) @filemtime($f) > time() - 600;
    }
}
