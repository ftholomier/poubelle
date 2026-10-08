<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Index;

/**
 * Contrôle qualité remis à zéro par un historien, fiche par fiche : les raisons des alertes
 * présentes à ce moment sont mémorisées et ne sont plus signalées pour cette fiche (bloc de la
 * fiche, écran Qualité, nouvelles anomalies d'un contrôle, compteurs). Une alerte pour une autre
 * raison réapparaît. Une même raison qui ne diffère que par un nombre (« 3 photos sans crédit »
 * → « 4 photos… ») reste la même raison. L'orthographe ne reste écartée que tant que les textes
 * de la fiche n'ont pas changé.
 */
final class QualityAck
{
    public const FILE = STORAGE_PATH . '/qualite-acquittees.json';

    /** Raison d'une alerte : code et message, nombres et exemples retirés. */
    public static function reason(string $code, string $msg): string
    {
        $m = mb_strtolower(trim((string) preg_replace('/\s*·.*$/u', '', $msg)));
        $m = (string) preg_replace('/\d+([.,]\d+)?/', '#', $m);
        return ($code !== '' ? $code : 'fiche') . ':' . $m;
    }

    private static ?array $cache = null;

    private static function all(): array
    {
        return self::$cache ??= (JsonStore::read(self::FILE, []) ?: []);
    }

    public static function get(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function acked(int $id, string $code, string $msg): bool
    {
        $a = self::all()[$id] ?? null;
        if (!$a) {
            return false;
        }
        if ($code === 'orthographe') {
            return !empty($a['ortho']) && $a['ortho'] === substr((string) (Index::get($id)['tsig'] ?? ''), 0, 8);
        }
        return in_array(self::reason($code, $msg), $a['keys'] ?? [], true);
    }

    /**
     * Remise à zéro : $alerts = [[code, message], …] affichées à ce moment ; s'ajoutent aux raisons
     * déjà mises de côté.
     */
    public static function acknowledge(int $id, array $alerts, string $by): int
    {
        $keys = [];
        $ortho = false;
        foreach ($alerts as [$code, $msg]) {
            if ($code === 'orthographe') {
                $ortho = true;
            } else {
                $keys[] = self::reason($code, $msg);
            }
        }
        $n = 0;
        JsonStore::update(self::FILE, function ($all) use ($id, $keys, $ortho, $by, &$n) {
            $all = is_array($all) ? $all : [];
            $old = $all[$id]['keys'] ?? [];
            $new = array_values(array_unique(array_merge($old, $keys)));
            $n = count($new) - count($old) + ($ortho ? 1 : 0);
            $all[$id] = ['at' => date('c'), 'by' => $by, 'keys' => $new, 'ortho' => $ortho ? substr((string) (Index::get($id)['tsig'] ?? ''), 0, 8) : ($all[$id]['ortho'] ?? '')];
            return $all;
        }, []);
        self::forget();
        return $n;
    }

    /** Réaffiche toutes les alertes de la fiche. */
    public static function reopen(int $id): void
    {
        JsonStore::update(self::FILE, function ($all) use ($id) {
            $all = is_array($all) ? $all : [];
            unset($all[$id]);
            return $all;
        }, []);
        self::forget();
    }

    private static function forget(): void
    {
        JsonStore::forget(self::FILE);
        self::$cache = null;
    }
}
