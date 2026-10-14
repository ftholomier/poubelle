<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Fiches validées par un historien : « j'ai tout relu et corrigé, la fiche est bonne ». Évite que
 * deux historiens contrôlent la même fiche. Gardé à part des fiches (pas de nouvelle version) :
 * id => {by, uid, at}.
 */
final class Validation
{
    private const FILE = STORAGE_PATH . '/validations.json';

    /** @return array<int,array{by:string,uid:?string,at:string}> */
    public static function all(): array
    {
        static $memo = null;
        return $memo ??= (JsonStore::read(self::FILE, []) ?: []);
    }

    public static function get(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function set(int $id, array $user): void
    {
        JsonStore::update(self::FILE, function ($d) use ($id, $user) {
            $d = is_array($d) ? $d : [];
            $d[$id] = ['by' => (string) ($user['name'] ?? 'équipe'), 'uid' => isset($user['id']) ? (string) $user['id'] : null, 'at' => date('c')];
            return $d;
        }, []);
    }

    public static function clear(int $id): void
    {
        JsonStore::update(self::FILE, function ($d) use ($id) {
            $d = is_array($d) ? $d : [];
            unset($d[$id]);
            return $d;
        }, []);
    }
}
