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

    private static ?array $memo = null;

    /** @return array<int,array{by:string,uid:?string,at:string}> */
    public static function all(): array
    {
        return self::$memo ??= (JsonStore::read(self::FILE, []) ?: []);
    }

    /**
     * Fiches validées et à valider par liste du back-office (matchs, personnes, articles & pages,
     * objets), corbeille exclue. @return array<string,array{ok:int,todo:int}>
     */
    public static function counts(): array
    {
        $valid = self::all();
        $byType = [];
        $out = [];
        foreach (\App\Admin\Fiches::LISTS as $slug => $conf) {
            $out[$slug] = ['ok' => 0, 'todo' => 0];
            foreach ($conf['types'] as $t) {
                $byType[$t] = $slug;
            }
        }
        foreach (\App\Data\Index::all() as $s) {
            $slug = $byType[$s['type']] ?? null;
            if ($slug === null || $s['status'] === 'corbeille') {
                continue;
            }
            $out[$slug][isset($valid[(int) $s['id']]) ? 'ok' : 'todo']++;
        }
        return $out;
    }

    public static function get(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function set(int $id, array $user): void
    {
        self::$memo = JsonStore::update(self::FILE, function ($d) use ($id, $user) {
            $d = is_array($d) ? $d : [];
            $d[$id] = ['by' => (string) ($user['name'] ?? 'équipe'), 'uid' => isset($user['id']) ? (string) $user['id'] : null, 'at' => date('c')];
            return $d;
        }, []);
    }

    /** Retire la validation (fiche modifiée ou supprimée) ; rien n'est écrit si elle n'était pas validée. */
    public static function clear(int $id): void
    {
        if (!isset(self::all()[$id])) {
            return;
        }
        self::$memo = JsonStore::update(self::FILE, function ($d) use ($id) {
            $d = is_array($d) ? $d : [];
            unset($d[$id]);
            return $d;
        }, []);
    }
}
