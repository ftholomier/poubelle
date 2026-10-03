<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/** Redirections 301 : anciennes adresses WordPress et adresses modifiées dans le back-office. */
final class Redirects
{
    public const FILE = DATA_PATH . '/redirects.json';

    public static function all(): array
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
        JsonStore::update(self::LOG404, function ($all) use ($path, $ref) {
            $all = $all ?: [];
            $e = $all[$path] ?? ['n' => 0, 'first' => date('c')];
            $e['n']++;
            $e['last'] = date('c');
            if ($ref !== '') {
                $e['ref'] = mb_substr($ref, 0, 200);
            }
            $all[$path] = $e;
            if (count($all) > 3000) {
                uasort($all, fn ($a, $b) => strcmp((string) $b['last'], (string) $a['last']));
                $all = array_slice($all, 0, 2500, true);
            }
            return $all;
        }, []);
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
