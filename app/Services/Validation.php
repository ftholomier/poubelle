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

    /** Part en pourcentage, au dixième (« 0,2 », « 12,5 », « 100 ») : la première fiche validée se voit. */
    public static function pct(int $n, int $all): string
    {
        if ($all <= 0) {
            return '0';
        }
        $p = round($n * 100 / $all, 1);
        if ($n > 0 && $p == 0.0) {
            $p = 0.1;
        } elseif ($n < $all && $p == 100.0) {
            $p = 99.9;
        }
        return str_replace('.', ',', rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.'));
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

    /**
     * Validations perdues à rétablir : fiches validées dans le journal d'activité (dernière action
     * « a validé la fiche », jamais « a retiré la validation » ensuite) qui ne le sont plus, parce que
     * l'ancienne règle effaçait la validation à chaque modification. @return array<int,array{by:string,uid:?string,at:string}>
     */
    public static function lostFromJournal(): array
    {
        $last = [];
        $files = glob(STORAGE_PATH . '/activity/*.jsonl') ?: [];
        sort($files);
        foreach ($files as $f) {
            foreach (JsonStore::readLines($f) as $e) {
                $id = (int) ($e['id'] ?? 0);
                $act = (string) ($e['action'] ?? '');
                if ($id > 0 && in_array($act, ['a validé la fiche', 'a retiré la validation de la fiche'], true)
                    && (string) ($e['at'] ?? '') > (string) ($last[$id]['at'] ?? '')) {
                    $last[$id] = ['on' => $act === 'a validé la fiche', 'by' => (string) ($e['by'] ?? 'équipe'), 'uid' => isset($e['uid']) ? (string) $e['uid'] : null, 'at' => (string) ($e['at'] ?? date('c'))];
                }
            }
        }
        $valid = self::all();
        $out = [];
        foreach ($last as $id => $v) {
            $doc = \App\Data\Index::get($id);
            if ($v['on'] && !isset($valid[$id]) && $doc && ($doc['status'] ?? '') !== 'corbeille') {
                $out[$id] = ['by' => $v['by'], 'uid' => $v['uid'], 'at' => $v['at']];
            }
        }
        return $out;
    }

    /** Remet des validations telles qu'elles étaient (qui, quand). */
    public static function restore(array $items): void
    {
        if (!$items) {
            return;
        }
        self::$memo = JsonStore::update(self::FILE, function ($d) use ($items) {
            $d = is_array($d) ? $d : [];
            foreach ($items as $id => $v) {
                $d[(int) $id] ??= ['by' => (string) $v['by'], 'uid' => $v['uid'], 'at' => (string) $v['at']];
            }
            return $d;
        }, []);
    }

    /** Retire la validation (« Retirer la validation » ou suppression définitive) ; rien n'est écrit si elle n'était pas validée. */
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
