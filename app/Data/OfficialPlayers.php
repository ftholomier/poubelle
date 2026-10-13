<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\Club;
use App\Core\Memo;
use App\Front\Site;

/**
 * Sous-rubrique calculée « Match officiel » (Nos lions › Joueurs) : joueurs ayant disputé au moins
 * un match officiel avec l'équipe première. La liste est livrée avec le code
 * (config/clubs/<club>/match-officiel.php, absente ailleurs : pas de rubrique) et n'écrit rien dans
 * les fiches : chaque ligne est retrouvée par son numéro de fiche, sinon par le nom (titre de la
 * fiche, puis nom + prénom du répertoire, période au club pour départager des homonymes). Les
 * ajouts et retraits faits dans le back-office (Éditorial › Match officiel) sont gardés à part
 * (data/collections/match-officiel.json) et s'appliquent par-dessus.
 */
final class OfficialPlayers
{
    public const SLUG = 'match-officiel';
    public const PATH = '/nos-lions/joueurs/match-officiel/';
    public const CONFIG = 'match-officiel';
    public const ADJUST = 'match-officiel';
    public const SOURCE_URL = 'https://fcsmstory.com/repertoire-de-tous-les-sochaliens/';

    /** Lignes de la liste livrée : [fiche, nom, prénom, titre de la fiche, période, matchs, matchs Coupe Peugeot, motif]. */
    public static function rows(): array
    {
        $rows = Club::content(self::CONFIG, null);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** La rubrique existe-t-elle pour ce musée ? */
    public static function enabled(): bool
    {
        return self::rows() !== [] && Categories::get(Site::C_JOUEURS) !== null;
    }

    /** Ajustements du back-office : {add: list<int>, remove: list<int>}. */
    public static function adjustments(): array
    {
        $a = Collections::get(self::ADJUST, []);
        $a = is_array($a) ? $a : [];
        return [
            'add' => array_values(array_unique(array_map('intval', (array) ($a['add'] ?? [])))),
            'remove' => array_values(array_unique(array_map('intval', (array) ($a['remove'] ?? [])))),
        ];
    }

    public static function saveAdjustments(array $add, array $remove, ?array $user = null): void
    {
        $add = array_values(array_unique(array_filter(array_map('intval', $add))));
        $remove = array_values(array_unique(array_filter(array_map('intval', $remove))));
        Collections::save(self::ADJUST, ['add' => $add, 'remove' => $remove], $user, 'Match officiel : liste ajustée');
    }

    /** Rubrique « virtuelle », présentée comme une sous-rubrique de Joueurs. */
    public static function category(): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        return [
            'slug' => self::SLUG,
            'name' => 'Match officiel',
            'label' => 'Match officiel',
            'parent' => Site::C_JOUEURS,
            'path' => self::PATH,
            'position' => 99,
            'virtual' => true,
            'description' => '',
            'order' => [],
        ];
    }

    /**
     * Résolution de la liste : numéro de fiche => ligne d'origine (ou null pour un ajout du back-office),
     * plus les lignes sans fiche trouvée.
     * @return array{ids: array<int, ?array>, missing: list<array>}
     */
    public static function resolve(): array
    {
        $file = APP_ROOT . '/config/clubs/' . Club::id() . '/' . self::CONFIG . '.php';
        return Memo::get('match-officiel/' . Club::id(), [Index::CACHE, $file, Collections::DIR . '/' . self::ADJUST . '.json', __FILE__], '', function () {
            $persons = array_filter(Index::published('personne'), fn ($s) => isset($s['p']));
            $byKey = [];
            foreach ($persons as $s) {
                foreach (array_unique([Names::personKey((string) $s['title']), Names::personKey((string) ($s['p']['name'] ?? '')), Names::personKey(trim(($s['p']['first'] ?? '') . ' ' . ($s['p']['last'] ?? '')))]) as $k) {
                    if ($k !== '') {
                        $byKey[$k][$s['id']] = true;
                    }
                }
            }
            $ids = [];
            $missing = [];
            foreach (self::rows() as $r) {
                $id = self::match($r, $persons, $byKey);
                if ($id !== null && !isset($ids[$id])) {
                    $ids[$id] = $r;
                } elseif ($id === null) {
                    $missing[] = $r;
                }
            }
            $adj = self::adjustments();
            foreach ($adj['add'] as $id) {
                if (isset($persons[$id]) && !isset($ids[$id])) {
                    $ids[$id] = null;
                }
            }
            foreach ($adj['remove'] as $id) {
                unset($ids[$id]);
            }
            return ['ids' => $ids, 'missing' => $missing];
        });
    }

    /** Fiche d'une ligne : par numéro (si c'est bien cette personne), sinon par le nom. */
    private static function match(array $r, array $persons, array $byKey): ?int
    {
        [$id, $last, $first, $title] = [(int) ($r[0] ?? 0), (string) ($r[1] ?? ''), (string) ($r[2] ?? ''), (string) ($r[3] ?? '')];
        $lastTok = Names::tokens($last);
        if ($id && isset($persons[$id])) {
            $s = $persons[$id];
            $hay = Names::tokens($s['title'] . ' ' . ($s['p']['name'] ?? '') . ' ' . ($s['p']['last'] ?? ''));
            if (Names::personKey((string) $s['title']) === Names::personKey($title) || ($lastTok && !array_diff($lastTok, $hay))) {
                return $id;
            }
        }
        $cands = [];
        foreach ([$title, "$first $last"] as $n) {
            $k = Names::personKey($n);
            if ($k !== '' && isset($byKey[$k])) {
                $cands += $byKey[$k];
                break;
            }
        }
        if (!$cands) {
            return null;
        }
        if (count($cands) === 1) {
            return (int) array_key_first($cands);
        }
        // Homonymes : celui dont le passage au club recouvre la période de la liste.
        [$from, $to] = array_map('intval', explode('-', (string) ($r[4] ?? '0-0')) + [0, 0]);
        foreach (array_keys($cands) as $cid) {
            $p = $persons[$cid]['p'];
            $a = (int) ($p['arrival'] ?? 0);
            $d = (int) ($p['departure'] ?? $a);
            if ($a && $from && $a <= $to && $d >= $from) {
                return (int) $cid;
            }
        }
        return null;
    }

    /** Fiches visibles de la rubrique (résumés de l'index). @return list<array> */
    public static function items(): array
    {
        if (!self::enabled()) {
            return [];
        }
        $all = Index::published('personne');
        $out = [];
        foreach (self::resolve()['ids'] as $id => $_) {
            if (isset($all[$id])) {
                $out[] = $all[$id];
            }
        }
        return $out;
    }

    public static function count(): int
    {
        return count(self::items());
    }

    /** La fiche figure-t-elle dans la liste ? */
    public static function has(int $id): bool
    {
        return self::enabled() && array_key_exists($id, self::resolve()['ids']);
    }
}
