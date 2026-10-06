<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/** Redirections 301 : anciennes adresses WordPress et adresses modifiées dans le back-office. */
final class Redirects
{
    public const FILE = DATA_PATH . '/redirects.json';

    /** Toutes les redirections (494 Ko de JSON) : gardées en cache PHP, relues seulement quand le fichier change. */
    public static function all(): array
    {
        return \App\Core\Memo::get('redirections', [self::FILE], '', fn () => self::read());
    }

    private static function read(): array
    {
        try {
            $all = JsonStore::read(self::FILE, []);
        } catch (\RuntimeException $e) {
            // Fichier abîmé (envoi par FTP interrompu) : le site continue sans redirections, l'écran
            // Qualité le signale ; il n'est réécrit qu'une fois remplacé (JsonStore::update refuse).
            error_log($e->getMessage());
            return [];
        }
        return is_array($all) ? $all : [];
    }

    /** Anciennes adresses WordPress par paramètre : /?p=123 (lien court) et /?s=… (recherche). */
    public static function legacyQuery(array $query): ?string
    {
        if (isset($query['p']) && is_string($query['p']) && ctype_digit($query['p'])) {
            return self::all()['/?p=' . $query['p']] ?? null;
        }
        if (isset($query['s']) && is_string($query['s'])) {
            return '/recherche/?q=' . rawurlencode(mb_substr($query['s'], 0, 200));
        }
        return null;
    }

    public static function find(string $path, array $query = []): ?string
    {
        $all = self::all();
        if ($path === '/' && ($to = self::legacyQuery($query))) {
            return $to;
        }
        $candidates = [$path, rtrim($path, '/') . '/', rawurldecode($path)];
        foreach ($candidates as $c) {
            if (isset($all[$c]) && $all[$c] !== $path) {
                return $all[$c];
            }
        }
        // Anciennes pages paginées des rubriques : /category/x/page/2/
        if (preg_match('#^(/category/.+?/)page/\d+/?$#', $path, $m) && isset($all[$m[1]])) {
            return $all[$m[1]];
        }
        return null;
    }

    public const LOG404 = STORAGE_PATH . '/404.json';

    /** Journal des adresses introuvables (pour créer les redirections manquantes). */
    public static function log404(string $path, string $referer = ''): void
    {
        if (strlen($path) > 300 || preg_match('#(\.(php\d?|env|asp|aspx|jsp|cgi|ini|sql|bak|git|ya?ml|log|zip|tar|gz)$)|^/(wp-admin|wp-login|xmlrpc|wp-includes|\.well-known|cgi-bin|vendor|admin/)#i', $path)) {
            return;
        }
        $ref = (string) parse_url($referer, PHP_URL_HOST) === (string) parse_url(base_url(), PHP_URL_HOST) ? (string) parse_url($referer, PHP_URL_PATH) : (string) parse_url($referer, PHP_URL_HOST);
        // Une ligne ajoutée au journal du jour (rapide, sans réécrire 404.json sous verrou) ; regroupée
        // dans 404.json par la tâche planifiée et à l'ouverture de l'écran Redirections.
        $dir = self::DIR404;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . date('Y-m-d') . '.log';
        if ((int) @filesize($file) < 20000000) {
            @file_put_contents($file, json_encode([$path, mb_substr($ref, 0, 200), date('c')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    public const DIR404 = STORAGE_PATH . '/404';

    /** Regroupe les journaux en attente dans 404.json. @return int adresses lues */
    public static function merge404(): int
    {
        $lines = [];
        foreach (glob(self::DIR404 . '/*.log') ?: [] as $f) {
            // Renommé d'abord : les visites suivantes écrivent dans un nouveau fichier, rien n'est perdu.
            $work = $f . '.' . getmypid() . '.work';
            if (!@rename($f, $work)) {
                continue;
            }
            foreach (file($work, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
                $e = json_decode($l, true);
                if (is_array($e) && isset($e[0], $e[2])) {
                    $lines[] = $e;
                }
            }
            @unlink($work);
        }
        if (!$lines) {
            return 0;
        }
        JsonStore::update(self::LOG404, function ($all) use ($lines) {
            $all = $all ?: [];
            foreach ($lines as [$path, $ref, $at]) {
                $e = $all[$path] ?? ['n' => 0, 'first' => $at];
                $e['n']++;
                $e['last'] = $at;
                if ($ref !== '') {
                    $e['ref'] = $ref;
                }
                $all[$path] = $e;
            }
            if (count($all) > 3000) {
                uasort($all, fn ($a, $b) => strcmp((string) $b['last'], (string) $a['last']));
                $all = array_slice($all, 0, 2500, true);
            }
            return $all;
        }, []);
        return count($lines);
    }

    public static function remove(string $from): void
    {
        JsonStore::update(self::FILE, function ($all) use ($from) {
            unset($all[$from]);
            return $all ?: [];
        }, []);
    }

    public static function add(string $from, string $to): void
    {
        if ($from === $to || $from === '') {
            return;
        }
        JsonStore::update(self::FILE, function ($all) use ($from, $to) {
            $all = $all ?: [];
            // Les redirections qui pointaient vers l'ancienne adresse suivent.
            foreach ($all as $k => $v) {
                if ($v === $from) {
                    $all[$k] = $to;
                }
            }
            unset($all[$to]);
            $all[$from] = $to;
            ksort($all);
            return $all;
        }, []);
    }
}
