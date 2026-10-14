<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Ce que le site exige du serveur (docs/DEPLOIEMENT.md, § 1) : version de PHP, extensions,
 * dossiers inscriptibles ; et les dernières erreurs du journal PHP du site. Montré aux seuls
 * administrateurs (tableau de bord, Tâches planifiées, page « Arrêt de jeu »), pour régler
 * l'hébergement sans fouiller cPanel.
 */
final class ServerCheck
{
    public const PHP = '8.3.0';

    /** Extension => ce qui ne marche pas sans elle */
    private const EXTENSIONS = [
        'mbstring' => 'textes accentués',
        'intl' => 'recherche, adresses et classement des fiches',
        'gd' => 'vignettes des photos, images de partage',
        'curl' => 'IA Gemini, paiements en ligne',
        'sodium' => 'clés et mots de passe des réglages',
        'openssl' => 'connexions sécurisées, e-mails',
        'zip' => 'sauvegardes',
        'fileinfo' => 'envoi de fichiers',
        'dom' => 'export PDF, nettoyage des textes',
        'zlib' => 'export PDF',
        'ctype' => 'adresses des pages',
        'iconv' => 'adresses des fiches, export PDF',
    ];

    /** @return list<string> réglages à revoir, en phrases courtes (vide : tout est bon) */
    public static function problems(): array
    {
        $out = [];
        if (version_compare(PHP_VERSION, self::PHP, '<')) {
            $out[] = 'PHP ' . PHP_VERSION . ' : le site demande PHP 8.3 (cPanel › Sélectionner une version de PHP).';
        }
        foreach (self::EXTENSIONS as $ext => $use) {
            if (!extension_loaded($ext)) {
                $out[] = "Extension PHP « $ext » absente ($use) : à cocher dans cPanel › Sélectionner une version de PHP › Extensions.";
            }
        }
        // Sans OPcache, chaque page relit tout le code et les données calculées (plusieurs Mo) : nettement plus lent.
        if (PHP_SAPI !== 'cli' && !(extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN))) {
            $out[] = 'OPcache désactivé : chaque page relit tout le code, le site et le back-office sont nettement plus lents (cPanel › Sélectionner une version de PHP › Extensions : cocher « opcache »).';
        }
        // Vraiment trop petite seulement si elle est pleine maintenant et petite (moins de 256 Mo) ; avec
        // 256 Mo ou plus, les redémarrages viennent de la mémoire « gaspillée » par les fichiers réécrits
        // (mises à jour, données recalculées) : sans gravité, l'OPcache se vide et se remplit seul.
        if (($o = self::opcache()) && $o['full'] && $o['total'] < 256) {
            $out[] = 'Mémoire d’OPcache trop petite (' . $o['used'] . ' Mo utilisés sur ' . $o['total'] . ') : le code est recompilé trop souvent. À augmenter (réglage opcache.memory_consumption, 256 Mo) dans cPanel › Sélectionner une version de PHP › Options, ou auprès de l’hébergeur.';
        }
        // Alerte à 90 % : OPcache (mémoire utile + copies périmées) et Redis.
        if (($o = self::opcache()) && $o['total'] > 0 && $o['used'] / $o['total'] >= 0.9) {
            $out[] = 'OPcache presque plein : ' . $o['used'] . ' Mo sur ' . $o['total'] . ' (' . round(100 * $o['used'] / $o['total']) . ' %). Serveur / tâches planifiées › « Vider OPcache ».';
        }
        if (($rd = self::redis()) && $rd['max'] > 0 && $rd['used'] / $rd['max'] >= 0.9) {
            $out[] = 'Redis presque plein : ' . $rd['used'] . ' Mo sur ' . $rd['max'] . ' (' . round(100 * $rd['used'] / $rd['max']) . ' %). Serveur / tâches planifiées › « Vider le cache Redis ».';
        }
        if (PHP_SAPI !== 'cli' && !self::finishesEarly()) {
            $out[] = 'PHP ne sait pas terminer une page avant la fin du travail qui la suit (mode ' . PHP_SAPI . ') : après un enregistrement, le recalcul des statistiques fait attendre la personne. Un PHP en mode PHP-FPM ou LiteSpeed (cPanel, ou l’hébergeur) l’évite.';
        }
        if (\App\Core\Settings::get('app.push', true) && !WebPush::available()) {
            $out[] = 'Notifications de l’appli du musée impossibles : il faut les extensions « openssl » (courbe P-256, ECDH, AES-GCM) et « curl » (cPanel › Sélectionner une version de PHP › Extensions).';
        }
        if (function_exists('gd_info') && empty(gd_info()['WebP Support'])) {
            $out[] = 'GD sans le format WebP : les vignettes des photos ne peuvent pas être créées.';
        }
        foreach ([STORAGE_PATH, STORAGE_PATH . '/cache', STORAGE_PATH . '/sessions', STORAGE_PATH . '/logs', DATA_PATH, DATA_PATH . '/fiches', MEDIA_PATH] as $dir) {
            if (is_dir($dir) && !is_writable($dir)) {
                $out[] = 'Dossier « ' . self::rel($dir) . ' » non inscriptible par PHP : droits 755 dans le gestionnaire de fichiers de cPanel.';
            }
        }
        return $out;
    }

    /**
     * Mémoire d'OPcache (code et caches compilés gardés en mémoire) : null s'il est absent ou que
     * l'hébergeur en cache l'état.
     * @return array{used:int,total:int,full:bool,oom:int,scripts:int,hits:float}|null
     */
    public static function opcache(): ?array
    {
        $st = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        if (!is_array($st) || empty($st['opcache_enabled'])) {
            return null;
        }
        $m = $st['memory_usage'] ?? [];
        $used = (int) ($m['used_memory'] ?? 0) + (int) ($m['wasted_memory'] ?? 0);
        return [
            'used' => (int) round($used / 1048576),
            'total' => (int) round(($used + (int) ($m['free_memory'] ?? 0)) / 1048576),
            'full' => !empty($st['cache_full']),
            'oom' => (int) ($st['opcache_statistics']['oom_restarts'] ?? 0),
            'wasted' => (int) round((int) ($m['wasted_memory'] ?? 0) / 1048576),
            'scripts' => (int) ($st['opcache_statistics']['num_cached_scripts'] ?? 0),
            'useful' => (int) round((int) ($m['used_memory'] ?? 0) / 1048576),
            'free' => (int) round((int) ($m['free_memory'] ?? 0) / 1048576),
            'start' => (int) ($st['opcache_statistics']['start_time'] ?? 0),
            'restart' => (int) ($st['opcache_statistics']['last_restart_time'] ?? 0),
            'manual' => (int) ($st['opcache_statistics']['manual_restarts'] ?? 0),
            'pending' => !empty($st['restart_pending']),
            'restrict' => (string) ini_get('opcache.restrict_api'),
            'pid' => getmypid(),
            'hits' => round((float) ($st['opcache_statistics']['opcache_hit_rate'] ?? 0), 1),
        ];
    }

    /**
     * Ce qui occupe OPcache, par dossier (deux niveaux sous la racine du site), du plus gros au plus
     * petit : pour repérer des fichiers qui n'ont rien à y faire (gros caches, autre musée, anciennes
     * versions du code après une mise à jour).
     * @return list<array{dir:string,mb:float,n:int}>
     */
    public static function opcacheByDir(int $limit = 15): array
    {
        $st = function_exists('opcache_get_status') ? @opcache_get_status(true) : false;
        if (!is_array($st) || empty($st['scripts'])) {
            return [];
        }
        $root = rtrim(str_replace('\\', '/', dirname(APP_ROOT)), '/') . '/';
        $g = [];
        foreach ($st['scripts'] as $sc) {
            $f = str_replace('\\', '/', (string) ($sc['full_path'] ?? ''));
            $rel = str_starts_with($f, $root) ? substr($f, strlen($root)) : $f;
            $parts = explode('/', $rel);
            array_pop($parts);
            $k = implode('/', array_slice($parts, 0, 4)) ?: '/';
            $g[$k]['mb'] = ($g[$k]['mb'] ?? 0) + (int) ($sc['memory_consumption'] ?? 0);
            $g[$k]['n'] = ($g[$k]['n'] ?? 0) + 1;
        }
        uasort($g, fn ($a, $b) => $b['mb'] <=> $a['mb']);
        $out = [];
        foreach (array_slice($g, 0, $limit, true) as $k => $v) {
            $out[] = ['dir' => $k, 'mb' => round($v['mb'] / 1048576, 1), 'n' => $v['n']];
        }
        return $out;
    }

    /**
     * Mémoire de Redis (tous musées confondus) : null s'il n'est pas connecté.
     * @return array{used:float,max:float,keys:int,where:string}|null
     */
    public static function redis(): ?array
    {
        $r = \App\Core\PhpCache::redis();
        if (!$r) {
            return null;
        }
        try {
            $i = $r->info('memory');
            $max = (int) ($i['maxmemory'] ?? 0);
            if ($max <= 0) {
                $cfg = @$r->config('GET', 'maxmemory');
                $max = (int) (is_array($cfg) ? ($cfg['maxmemory'] ?? 0) : 0);
            }
            return ['used' => round((int) ($i['used_memory'] ?? 0) / 1048576, 1), 'max' => round(($max ?: 128 * 1048576) / 1048576), 'keys' => count($r->keys('*') ?: []), 'where' => (string) \App\Core\PhpCache::$redisWhere];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ce qui occupe Redis pour ce musée, par dossier du cache (même présentation que pour OPcache).
     * @return list<array{dir:string,mb:float,n:int}>
     */
    public static function redisByDir(int $limit = 15): array
    {
        $r = \App\Core\PhpCache::redis();
        if (!$r) {
            return [];
        }
        try {
            $prefix = (string) $r->getOption(\Redis::OPT_PREFIX);
            $root = rtrim(str_replace('\\', '/', dirname(APP_ROOT)), '/') . '/';
            $g = [];
            $known = 0;
            foreach ($r->hGetAll('idx') ?: [] as $rk => $file) {
                $mem = (int) $r->rawCommand('MEMORY', 'USAGE', $prefix . $rk);
                if ($mem <= 0) {
                    $r->hDel('idx', (string) $rk); // entrée expirée ou effacée
                    continue;
                }
                $f = str_replace('\\', '/', (string) $file);
                $rel = str_starts_with($f, $root) ? substr($f, strlen($root)) : $f;
                $k = dirname($rel);
                $g[$k]['mb'] = ($g[$k]['mb'] ?? 0) + $mem;
                $g[$k]['n'] = ($g[$k]['n'] ?? 0) + 1;
                $known++;
            }
            uasort($g, fn ($a, $b) => $b['mb'] <=> $a['mb']);
            $out = [];
            foreach (array_slice($g, 0, $limit, true) as $k => $v) {
                $out[] = ['dir' => $k, 'mb' => round($v['mb'] / 1048576, 1), 'n' => $v['n']];
            }
            $total = count($r->keys('*') ?: []);
            $other = $total - $known * 2 - 1; // chaque cache : la donnée + son pointeur ; plus le répertoire
            if ($other > 0) {
                $out[] = ['dir' => '(entrées plus anciennes, pas encore répertoriées)', 'mb' => 0.0, 'n' => $other];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** La page part avant le travail fait après elle (recalculs, mesure d'audience) : PHP-FPM ou LiteSpeed. */
    public static function finishesEarly(): bool
    {
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }

    /**
     * Dernières erreurs du journal PHP du site (première ligne de chaque erreur, chemins
     * raccourcis), les plus récentes en premier.
     * @return list<string>
     */
    public static function lastErrors(int $n = 8): array
    {
        $files = glob(STORAGE_PATH . '/logs/php-*.log') ?: [];
        rsort($files);
        $out = [];
        foreach ($files as $file) {
            $size = (int) @filesize($file);
            $fp = @fopen($file, 'rb');
            if (!$fp) {
                continue;
            }
            fseek($fp, max(0, $size - 262144));
            $tail = (string) stream_get_contents($fp);
            fclose($fp);
            $lines = array_values(array_filter(explode("\n", $tail), fn ($l) => str_starts_with($l, '[')));
            foreach (array_reverse($lines) as $l) {
                $out[] = mb_strimwidth(str_replace(APP_ROOT . '/', '', $l), 0, 400, '…');
                if (count($out) >= $n) {
                    return $out;
                }
            }
        }
        return $out;
    }

    private static function rel(string $dir): string
    {
        return ltrim(str_replace(APP_ROOT, '', $dir), '/') ?: '.';
    }
}
