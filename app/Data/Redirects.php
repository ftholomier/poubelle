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
        return JsonStore::read(self::FILE, []) ?? [];
    }

    public static function find(string $path, array $query = []): ?string
    {
        $all = self::all();
        if (isset($query['p']) && ctype_digit((string) $query['p'])) {
            return $all['/?p=' . $query['p']] ?? null;
        }
        if (isset($query['s']) && $path === '/') {
            return '/recherche/?q=' . rawurlencode((string) $query['s']);
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
