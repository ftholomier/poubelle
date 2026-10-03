<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Data\Categories;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches as Store;
use App\Data\Index;
use App\Data\Names;
use App\Services\Search;

/**
 * Référentiels : adversaires (regroupement des variantes de noms, ville, logo,
 * coordonnées), stades (coordonnées corrigeables à la main pour la carte), lieux
 * de naissance géolocalisés, compétitions (renommage dans toutes les fiches) et
 * saisons (vue d'ensemble).
 */
final class Referentials extends Base
{
    public const TABS = ['adversaires' => 'Adversaires', 'stades' => 'Stades', 'lieux' => 'Lieux de naissance', 'competitions' => 'Compétitions', 'saisons' => 'Saisons'];
    private const PER_PAGE = 50;

    public static function index(Request $req): Response
    {
        $tab = isset(self::TABS[$req->str('onglet')]) ? $req->str('onglet') : 'adversaires';
        $q = Search::norm($req->str('q'));
        $filter = $req->str('filtre');
        $d = Derived::get();
        $vars = ['tab' => $tab, 'q' => $req->str('q'), 'filter' => $filter];
        switch ($tab) {
            case 'adversaires':
            case 'stades':
                $isClub = $tab === 'adversaires';
                $list = Collections::get($isClub ? 'clubs' : 'stades', []);
                $stats = $d[$isClub ? 'clubs' : 'stades'] ?? [];
                $rows = [];
                foreach ($list as $it) {
                    $it['count'] = (int) ($stats[$it['id']]['count'] ?? 0);
                    if ($q !== '' && !str_contains(Search::norm($it['name'] . ' ' . implode(' ', $it['aliases'] ?? []) . ' ' . ($it['city'] ?? '')), $q)) {
                        continue;
                    }
                    if ($filter === 'sans-coordonnees' && !empty($it['lat'])) {
                        continue;
                    }
                    if ($filter === 'sans-ville' && trim((string) ($it['city'] ?? '')) !== '') {
                        continue;
                    }
                    $rows[] = $it;
                }
                usort($rows, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strcoll((string) $a['name'], (string) $b['name']));
                $vars += self::paginate($rows, $req);
                $vars['missing'] = count(array_filter($list, fn ($x) => empty($x['lat'])));
                $vars['all'] = count($list);
                break;

            case 'lieux':
                $geo = Collections::get('geo', []);
                $people = [];
                foreach (Index::all() as $s) {
                    if ($s['type'] === 'personne' && !empty($s['p']['birth_place'])) {
                        $people[Search::norm((string) $s['p']['birth_place'])] = ($people[Search::norm((string) $s['p']['birth_place'])] ?? 0) + 1;
                    }
                }
                $rows = [];
                foreach ($geo as $key => $g) {
                    if (!is_array($g) || str_starts_with((string) $key, '_')) {
                        continue;
                    }
                    $label = (string) ($g['city'] ?? explode('|', (string) $key)[0]);
                    if ($q !== '' && !str_contains(Search::norm($key . ' ' . $label), $q)) {
                        continue;
                    }
                    if ($filter === 'sans-coordonnees' && !empty($g['lat'])) {
                        continue;
                    }
                    $rows[] = ['key' => (string) $key, 'city' => $label, 'country' => (string) ($g['country'] ?? (explode('|', (string) $key)[1] ?? '')), 'lat' => $g['lat'] ?? null, 'lng' => $g['lng'] ?? null, 'source' => (string) ($g['source'] ?? ''), 'manual' => !empty($g['manual']), 'people' => $people[Search::norm($label)] ?? 0];
                }
                usort($rows, fn ($a, $b) => $b['people'] <=> $a['people'] ?: strcoll($a['city'], $b['city']));
                $vars += self::paginate($rows, $req);
                break;

            case 'competitions':
                $rows = [];
                $labels = [];
                foreach ($d['matches'] ?? [] as $m) {
                    if ($m['label'] !== '') {
                        $labels[$m['comp']][$m['label']] = ($labels[$m['comp']][$m['label']] ?? 0) + 1;
                    }
                }
                foreach ($d['comps'] ?? [] as $name => $c) {
                    if ($q !== '' && !str_contains(Search::norm((string) $name), $q)) {
                        continue;
                    }
                    $l = $labels[$name] ?? [];
                    arsort($l);
                    $first = $c['matches'][0] ?? null;
                    $last = $c['matches'][count($c['matches']) - 1] ?? null;
                    $rows[] = ['name' => (string) $name, 'count' => $c['count'], 'v' => $c['v'], 'n' => $c['n'], 'd' => $c['d'], 'labels' => array_slice($l, 0, 6, true),
                        'from' => $first ? substr((string) ($d['matches'][$first]['date'] ?? ''), 0, 4) : '', 'to' => $last ? substr((string) ($d['matches'][$last]['date'] ?? ''), 0, 4) : ''];
                }
                usort($rows, fn ($a, $b) => $b['count'] <=> $a['count']);
                $vars['rows'] = $rows;
                break;

            case 'saisons':
                $rows = [];
                $cats = [];
                foreach (Categories::all() as $slug => $c) {
                    if (!empty($c['season'])) {
                        $cats[$c['season']] = $c + ['slug' => $slug];
                    }
                }
                $keys = array_unique(array_merge(array_keys($d['seasons'] ?? []), array_keys($cats)));
                rsort($keys);
                foreach ($keys as $k) {
                    $s = $d['seasons'][$k] ?? [];
                    $rows[] = ['season' => (string) $k, 'count' => count($s['matches'] ?? []), 'res' => $s['res'] ?? ['V' => 0, 'N' => 0, 'D' => 0], 'division' => $s['division'] ?? null,
                        'cat' => $cats[$k] ?? null, 'bilan' => self::bilanOf((string) $k, $d)];
                }
                $vars['rows'] = $rows;
                break;
        }
        return self::html('admin/referentiels', $vars, [
            'title' => 'Référentiels', 'crumb' => 'Contenus', 'nav' => 'referentiels',
            'tabs' => array_map(fn ($k, $l) => [$l, '/admin/referentiels?onglet=' . $k, $k === $tab], array_keys(self::TABS), self::TABS),
        ]);
    }

    private static function bilanOf(string $season, array $d): ?array
    {
        foreach ($d['bilans'] ?? [] as $b) {
            if (($b['season'] ?? null) === $season) {
                return $b;
            }
        }
        return null;
    }

    private static function paginate(array $rows, Request $req): array
    {
        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($pages, max(1, (int) $req->str('page', '1')));
        return ['rows' => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Enregistrement (JSON) des lignes modifiées d'un onglet, ou renommage d'une compétition. */
    public static function save(Request $req): Response
    {
        $isJson = (bool) $req->json();
        $in = $isJson ? $req->json() : $req->post;
        $kind = (string) ($in['kind'] ?? '');
        $user = self::actor();
        $num = function ($v, float $min, float $max): ?float {
            if ($v === null || $v === '') {
                return null;
            }
            $f = (float) str_replace(',', '.', (string) $v);
            return $f >= $min && $f <= $max ? round($f, 6) : null;
        };
        if ($kind === 'adversaires' || $kind === 'stades') {
            $name = $kind === 'adversaires' ? 'clubs' : 'stades';
            $list = Collections::get($name, []);
            $byId = [];
            foreach ($list as $i => $it) {
                $byId[$it['id']] = $i;
            }
            $changed = 0;
            foreach ((array) ($in['rows'] ?? []) as $row) {
                $id = (string) ($row['id'] ?? '');
                if (!isset($byId[$id])) {
                    continue;
                }
                $it = $list[$byId[$id]];
                $new = $it;
                $new['name'] = Html::line($row['name'] ?? $it['name'], 120) ?: $it['name'];
                $new['aliases'] = array_values(array_unique(array_filter(array_map(fn ($a) => Html::line($a, 120), preg_split('/\s*[;|]\s*|\R/u', (string) ($row['aliases'] ?? '')) ?: []))));
                $new['city'] = Html::line($row['city'] ?? '', 120);
                $new['country'] = Html::line($row['country'] ?? '', 60);
                if ($kind === 'stades') {
                    $new['department'] = Html::line($row['department'] ?? ($it['department'] ?? ''), 80);
                } else {
                    $new['logo'] = Html::line($row['logo'] ?? '', 300) ?: null;
                }
                $new['lat'] = $num($row['lat'] ?? null, -90, 90);
                $new['lng'] = $num($row['lng'] ?? null, -180, 180);
                if ($new['lat'] === null || $new['lng'] === null) {
                    $new['lat'] = $new['lng'] = null;
                }
                if ($new != $it) {
                    $new['auto'] = false; // saisi à la main : les recalculs ne l'écrasent plus
                    if (($new['lat'] ?? null) !== ($it['lat'] ?? null)) {
                        $new['geo_manual'] = $new['lat'] !== null;
                    }
                    $list[$byId[$id]] = $new;
                    $changed++;
                }
            }
            // Regroupement : le nom d'une autre entrée saisi comme variante la fusionne dans celle-ci.
            $merged = 0;
            $keyFn = $kind === 'adversaires' ? [Names::class, 'clubKey'] : [Names::class, 'stadiumKey'];
            foreach ($list as $i => $it) {
                if (!isset($list[$i])) {
                    continue;
                }
                foreach ($it['aliases'] as $alias) {
                    $ak = $keyFn($alias);
                    foreach ($list as $j => $other) {
                        if ($j === $i || !isset($list[$j]) || $keyFn($other['name']) !== $ak) {
                            continue;
                        }
                        $list[$i]['aliases'] = array_values(array_unique(array_merge($list[$i]['aliases'], [$other['name']], $other['aliases'] ?? [])));
                        foreach (['city', 'country', 'logo', 'lat', 'lng', 'department'] as $k) {
                            if (empty($list[$i][$k]) && !empty($other[$k])) {
                                $list[$i][$k] = $other[$k];
                            }
                        }
                        $list[$i]['auto'] = false;
                        unset($list[$j]);
                        $merged++;
                    }
                }
            }
            if (!$changed && !$merged) {
                return self::json(['ok' => true, 'message' => 'Aucune modification.']);
            }
            Collections::save($name, array_values($list), $user, ($changed ? "$changed fiche(s) modifiée(s)" : '') . ($merged ? " · $merged regroupement(s)" : ''));
            Derived::scheduleRebuild();
            \App\Services\Geo::forget();
            return self::json(['ok' => true, 'message' => "$changed ligne(s) enregistrée(s)" . ($merged ? ", $merged regroupement(s) effectué(s)" : '') . '. Les statistiques sont recalculées.', 'reload' => $merged > 0]);
        }
        if ($kind === 'lieux') {
            $geo = Collections::get('geo', []);
            $changed = 0;
            foreach ((array) ($in['rows'] ?? []) as $row) {
                $key = (string) ($row['key'] ?? '');
                if (!isset($geo[$key])) {
                    continue;
                }
                $lat = $num($row['lat'] ?? null, -90, 90);
                $lng = $num($row['lng'] ?? null, -180, 180);
                if ($lat === ($geo[$key]['lat'] ?? null) && $lng === ($geo[$key]['lng'] ?? null)) {
                    continue;
                }
                $geo[$key]['lat'] = $lat !== null && $lng !== null ? $lat : null;
                $geo[$key]['lng'] = $lat !== null && $lng !== null ? $lng : null;
                $geo[$key]['manual'] = true;
                $geo[$key]['source'] = 'saisie';
                $geo[$key]['at'] = date('c');
                $changed++;
            }
            if ($changed) {
                Collections::save('geo', $geo, $user, "$changed lieu(x) corrigé(s)");
                \App\Services\Geo::forget();
            }
            return self::json(['ok' => true, 'message' => $changed ? "$changed lieu(x) corrigé(s)." : 'Aucune modification.']);
        }
        if ($kind === 'competition') {
            $from = (string) ($in['from'] ?? '');
            $to = Html::line($in['to'] ?? '', 120);
            if ($from === '' || $to === '' || $from === $to) {
                return $isJson ? self::json(['error' => 'Indiquez le nouveau nom de la compétition.', 'field' => 'to'], 422) : self::back('/admin/referentiels?onglet=competitions', null, 'Indiquez le nouveau nom de la compétition.');
            }
            $n = 0;
            @set_time_limit(300);
            Store::batch(function () use ($from, $to, $user, &$n) {
                foreach (Index::all() as $s) {
                    if ($s['type'] !== 'match' || ($s['m']['competition'] ?? null) !== $from) {
                        continue;
                    }
                    $doc = Store::get((int) $s['id']);
                    if (!$doc || ($doc['match']['competition'] ?? null) !== $from) {
                        continue;
                    }
                    $doc['match']['competition'] = $to;
                    Store::save($doc, $user, "Compétition renommée : $from → $to");
                    $n++;
                }
            });
            Activity::log($user, "a renommé la compétition « $from » en « $to »", ['title' => "$n match(s)"]);
            Derived::scheduleRebuild();
            if (!$isJson) {
                return self::back('/admin/referentiels?onglet=competitions', "« $from » renommée en « $to » dans $n fiche(s) match. Les statistiques sont recalculées.");
            }
            return self::json(['ok' => true, 'message' => "$n fiche(s) match mise(s) à jour.", 'reload' => true]);
        }
        return self::json(['error' => 'Demande inconnue.'], 400);
    }
}
