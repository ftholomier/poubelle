<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Data\Categories;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;

/**
 * « Explorer l'histoire » : pages entièrement calculées depuis les fiches matchs
 * (maquettes « Saison », « Face à face », « Records ») — saisons, face-à-face par
 * adversaire, bilans par compétition ou par stade, livre des records.
 */
final class Explore
{
    // ================================================================== SAISONS

    public static function season(Request $req, string $season): ?Response
    {
        [$a, $b] = array_map('intval', explode('-', $season));
        if ($b !== $a + 1) {
            return null;
        }
        $d = Derived::get();
        $S = $d['seasons'][$season] ?? null;
        $cat = null;
        foreach (Categories::all() as $c) {
            if (($c['season'] ?? null) === $season) {
                $cat = $c;
            }
        }
        if (!$S && !$cat) {
            return null;
        }
        $M = $d['matches'];
        $ids = $S['matches'] ?? [];
        $matches = array_values(array_filter(array_map(fn ($id) => $M[$id] ?? null, $ids), fn ($x) => $x && $x['v']));
        usort($matches, fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));

        $res = ['V' => 0, 'N' => 0, 'D' => 0];
        $gf = $ga = 0;
        $comps = [];
        foreach ($matches as $x) {
            if ($x['result']) {
                $res[$x['result']]++;
            }
            if ($x['us'] !== null) {
                $gf += (int) $x['us'];
                $ga += (int) $x['them'];
            }
            $comps[$x['comp']] = ($comps[$x['comp']] ?? 0) + 1;
        }

        // Buteurs et effectif (compositions des fiches de la saison)
        $scorers = [];
        $squad = [];
        $coaches = [];
        foreach ($matches as $x) {
            $doc = Fiches::get((int) $x['id']);
            $links = [];
            foreach (Derived::lineupLinks((int) $x['id']) as $ap) {
                $links[] = $ap;
            }
            foreach ($doc['match']['lineup']['rows'] ?? [] as $r) {
                $name = trim((string) $r['name']);
                if ($name === '') {
                    continue;
                }
                $pid = $r['person_id'] ?? null;
                if (!$pid) {
                    foreach ($links as $ap) {
                        $s = Index::get($ap[0]);
                        if ($ap[8] === $r['position'] && $s && Names::lineupLastName($name) === implode(' ', Names::tokens($s['p']['last'] ?? ''))) {
                            $pid = $ap[0];
                            break;
                        }
                    }
                }
                $key = $pid ? 'p' . $pid : 'n' . Names::personKey($name);
                if ($r['position'] === 'E') {
                    $coaches[$key] = ($coaches[$key] ?? ['pid' => $pid, 'name' => Names::display($name), 'n' => 0]);
                    $coaches[$key]['n']++;
                    continue;
                }
                $played = $r['position'] !== 'R' || (($r['sub_in'] ?? null) !== null && $r['sub_in'] !== '');
                $squad[$key] ??= ['pid' => $pid, 'name' => Names::display($name), 'mj' => 0, 'goals' => 0, 'pos' => []];
                if ($played) {
                    $squad[$key]['mj']++;
                    if ($r['position'] !== 'R') {
                        $squad[$key]['pos'][$r['position']] = ($squad[$key]['pos'][$r['position']] ?? 0) + 1;
                    }
                }
                $g = count($r['goals'] ?? []);
                if ($g) {
                    $squad[$key]['goals'] += $g;
                    $scorers[$key] = ($scorers[$key] ?? 0) + $g;
                }
            }
        }
        arsort($scorers);
        $topScorers = [];
        $max = $scorers ? max($scorers) : 1;
        foreach (array_slice($scorers, 0, 12, true) as $key => $g) {
            $p = $squad[$key];
            $s = $p['pid'] ? Index::get((int) $p['pid']) : null;
            $topScorers[] = ['name' => $s['p']['name'] ?? $p['name'], 'g' => $g, 'w' => round(100 * $g / $max, 1), 'href' => $s && Index::visible($s) ? url($s['path']) : null];
        }
        $order = ['G' => 0, 'D' => 1, 'M' => 2, 'A' => 3];
        $squadList = [];
        foreach ($squad as $p) {
            if ($p['mj'] === 0) {
                continue;
            }
            arsort($p['pos']);
            $line = array_key_first($p['pos']) ?? 'M';
            $s = $p['pid'] ? Index::get((int) $p['pid']) : null;
            $squadList[] = [
                'name' => $s['p']['name'] ?? $p['name'],
                'short' => $s ? ($s['p']['last'] ?: $s['p']['name']) : $p['name'],
                'line' => $line,
                'mj' => $p['mj'],
                'goals' => $p['goals'],
                'href' => $s && Index::visible($s) ? url($s['path']) : null,
                'image' => $s['image'] ?? null,
            ];
        }
        usort($squadList, fn ($x, $y) => ($order[$x['line']] ?? 9) <=> ($order[$y['line']] ?? 9) ?: $y['mj'] <=> $x['mj']);
        $coachList = array_map(function ($c) {
            $s = $c['pid'] ? Index::get((int) $c['pid']) : null;
            return ['name' => $s['p']['name'] ?? $c['name'], 'n' => $c['n'], 'href' => $s && Index::visible($s) ? url($s['path']) : null, 'image' => $s['image'] ?? null];
        }, array_values($coaches));
        usort($coachList, fn ($x, $y) => $y['n'] <=> $x['n']);

        // Bilan de la saison (article) et photo d'en-tête
        $bilan = null;
        foreach ($d['bilans'] ?? [] as $bl) {
            if (($bl['season'] ?? null) === $season && !empty($bl['_visible'])) {
                $bilan = Index::get((int) $bl['id']);
            }
        }
        $hero = $bilan['image'] ?? null;
        foreach (array_reverse($matches) as $x) {
            $hero ??= $x['image'];
        }

        // Saisons voisines
        $all = self::allSeasons();
        $i = array_search($season, $all, true);
        $prev = $i !== false && $i > 0 ? $all[$i - 1] : null;
        $next = $i !== false && $i < count($all) - 1 ? $all[$i + 1] : null;
        $current = self::currentSeason() === $season;

        $division = $S['division'] ?? null;
        $sums = array_values(array_filter([
            $division ? ['v' => self::shortDivision($division), 'k' => t('Division'), 'yellow' => true, 'title' => $division] : null,
            ['v' => count($matches), 'k' => count($matches) > 1 ? t('matchs fichés') : t('match fiché')],
            ['v' => $res['V'], 'k' => $res['V'] > 1 ? t('victoires') : t('victoire'), 'yellow' => true],
            ['v' => $res['N'], 'k' => $res['N'] > 1 ? t('nuls') : t('nul')],
            ['v' => $res['D'], 'k' => $res['D'] > 1 ? t('défaites') : t('défaite')],
            ['v' => $gf . '-' . $ga, 'k' => t('buts pour / contre')],
        ]));
        $label = substr($season, 0, 4) . '–' . substr($season, 7, 2);

        return Pages::render('season', [
            'season' => $season,
            'label' => $label,
            'matches' => $matches,
            'sums' => $sums,
            'comps' => $comps,
            'scorers' => $topScorers,
            'squad' => $squadList,
            'coaches' => $coachList,
            'bilan' => $bilan,
            'hero' => $hero,
            'prev' => $prev,
            'next' => $next,
            'current' => $current,
            'catPath' => $cat['path'] ?? null,
        ], [
            'title' => t('Saison {s} du FC Sochaux-Montbéliard : résultats, effectif, buteurs', ['s' => $season]),
            'description' => t('La saison {s} du FCSM : {n} matchs fichés, {v} victoires, {nn} nuls, {d} défaites. Résultats, effectif et buteurs.', ['s' => $season, 'n' => count($matches), 'v' => $res['V'], 'nn' => $res['N'], 'd' => $res['D']]),
            'image' => $hero ? img($hero, 1200) : null,
            'active' => 'matchs',
            'body_class' => 'page-season',
            'styles' => ['css/mosaic.css', 'css/explore.css'],
            'scripts' => ['js/explore.js'],
        ]);
    }

    public static function seasons(Request $req): Response
    {
        $d = Derived::get();
        $byDecade = [];
        foreach (self::allSeasons() as $s) {
            $S = $d['seasons'][$s] ?? null;
            $res = $S['res'] ?? ['V' => 0, 'N' => 0, 'D' => 0];
            $n = count($S['matches'] ?? []);
            $decade = intdiv((int) substr($s, 0, 4), 10) * 10;
            $byDecade[$decade][] = ['season' => $s, 'n' => $n, 'res' => $res + ['V' => 0, 'N' => 0, 'D' => 0], 'division' => $S['division'] ?? null, 'href' => url('/matchs/' . $s . '/')];
        }
        krsort($byDecade);
        return Pages::render('seasons', ['byDecade' => $byDecade, 'current' => self::currentSeason()], [
            'title' => t('Toutes les saisons du FC Sochaux-Montbéliard depuis 1928'),
            'description' => t('Saison par saison, les résultats, les effectifs et les buteurs du FCSM, calculés depuis les fiches matchs du musée.'),
            'active' => 'matchs',
            'styles' => ['css/mosaic.css', 'css/explore.css'],
        ]);
    }

    /** @return list<string> toutes les saisons connues (rubriques et matchs), dans l'ordre */
    public static function allSeasons(): array
    {
        $set = [];
        foreach (Categories::all() as $c) {
            if (!empty($c['season'])) {
                $set[$c['season']] = true;
            }
        }
        foreach (array_keys(Derived::get()['seasons'] ?? []) as $s) {
            $set[$s] = true;
        }
        $list = array_keys($set);
        sort($list);
        return $list;
    }

    public static function currentSeason(): string
    {
        $y = (int) date('Y');
        return (int) date('n') >= 7 ? "$y-" . ($y + 1) : ($y - 1) . "-$y";
    }

    public static function shortDivision(string $div): string
    {
        $map = ['Division 1' => 'D1', 'Division 2' => 'D2', 'Division 3' => 'D3', 'Ligue 1' => 'L1', 'Ligue 2' => 'L2', 'National' => 'N', 'National 1' => 'N1', 'National 2' => 'N2'];
        return $map[$div] ?? $div;
    }

    // ================================================================== FACE-À-FACE ET BILANS

    public static function opponents(Request $req): Response
    {
        $d = Derived::get();
        $list = [];
        foreach ($d['clubs'] ?? [] as $club => $c) {
            if (($c['count'] ?? 0) === 0 || $club === 'sochaux') {
                continue;
            }
            $official = array_values(array_filter($c['matches'], fn ($id) => !in_array($d['matches'][$id]['comp'] ?? '', Derived::OFFICIAL_EXCLUDED, true)));
            $list[] = [
                'club' => $club,
                'name' => Fiche::clubName($club),
                'count' => $c['count'],
                'official' => count($official),
                'v' => $c['v'], 'n' => $c['n'], 'd' => $c['d'],
                'gf' => $c['gf'] ?? 0, 'ga' => $c['ga'] ?? 0,
                'href' => url('/face-a-face/' . $club . '/'),
            ];
        }
        usort($list, fn ($x, $y) => $y['count'] <=> $x['count'] ?: strcoll($x['name'], $y['name']));
        return Pages::render('opponents', ['list' => $list], [
            'title' => t('Face-à-face : le bilan du FC Sochaux contre chaque adversaire'),
            'description' => t('Victoires, nuls, défaites, buts : le bilan complet de Sochaux contre ses {n} adversaires, calculé depuis les fiches matchs.', ['n' => count($list)]),
            'active' => 'matchs',
            'styles' => ['css/mosaic.css', 'css/explore.css'],
            'scripts' => ['js/explore.js'],
        ]);
    }

    public static function opponent(Request $req, string $club): ?Response
    {
        $d = Derived::get();
        $c = $d['clubs'][$club] ?? null;
        if (!$c) {
            // Ancienne clé ou nom saisi : on tente le rapprochement
            $key = Names::clubKey(str_replace('-', ' ', $club));
            if ($key !== $club && isset($d['clubs'][$key])) {
                return Response::redirect(url('/face-a-face/' . $key . '/'), 301);
            }
            return null;
        }
        $name = Fiche::clubName($club);
        $top = [];
        $clubs = $d['clubs'];
        uasort($clubs, fn ($x, $y) => $y['count'] <=> $x['count']);
        foreach (array_slice($clubs, 0, 12, true) as $k => $x) {
            if ($k !== 'sochaux') {
                $top[] = ['name' => Fiche::clubName($k), 'href' => url('/face-a-face/' . $k . '/'), 'on' => $k === $club];
            }
        }
        if (!array_filter($top, fn ($t) => $t['on'])) {
            array_unshift($top, ['name' => $name, 'href' => url('/face-a-face/' . $club . '/'), 'on' => true]);
        }
        $data = self::bilanData($c['matches']);
        return Pages::render('h2h', $data + [
            'mode' => 'club',
            'eyebrow' => t('Face-à-face · historique complet'),
            'titleHtml' => 'Sochaux <span class="yellow">×</span> ' . e($name),
            'crumbs' => [['label' => t('Accueil'), 'href' => url('/')], ['label' => t('Matchs'), 'href' => url('/matchs/')], ['label' => t('Face-à-face'), 'href' => url('/face-a-face/')]],
            'here' => $name,
            'chips' => $top,
            'allHref' => url('/face-a-face/'),
            'shareImage' => '/partage/face-a-face/' . $club . '.png',
        ], [
            'title' => t('Sochaux – {club} : le face-à-face complet ({n} matchs)', ['club' => $name, 'n' => $data['t']['count']]),
            'description' => t('Bilan de Sochaux contre {club} : {v} victoires, {nn} nuls, {d} défaites, {gf} buts marqués et {ga} encaissés en {n} matchs.', ['club' => $name, 'v' => $data['t']['V'], 'nn' => $data['t']['N'], 'd' => $data['t']['D'], 'gf' => $data['t']['gf'], 'ga' => $data['t']['ga'], 'n' => $data['t']['count']]),
            'image' => '/partage/face-a-face/' . $club . '.png',
            'active' => 'matchs',
            'styles' => ['css/mosaic.css', 'css/explore.css'],
            'scripts' => ['js/explore.js'],
        ]);
    }

    /** Bilans : une compétition (/bilans/coupe-de-france/) ou un stade (/bilans/stade-auguste-bonal/). */
    public static function bilan(Request $req, string $key): ?Response
    {
        $d = Derived::get();
        if ($key === 'auguste-bonal' || $key === 'bonal') {
            return Response::redirect(url('/bilans/stade-auguste-bonal/'), 301);
        }
        $chips = [];
        foreach (['coupe-de-france', 'coupe-de-la-ligue', 'coupe-d-europe', 'championnat'] as $k) {
            $chips[] = ['name' => t(Mosaic::COMPS[$k][0]), 'href' => url('/bilans/' . $k . '/'), 'on' => $k === $key];
        }
        $chips[] = ['name' => t('Stade Auguste-Bonal'), 'href' => url('/bilans/stade-auguste-bonal/'), 'on' => $key === 'stade-auguste-bonal'];

        if (isset(Mosaic::COMPS[$key])) {
            [$label, , $family] = Mosaic::COMPS[$key];
            $ids = array_keys(array_filter($d['matches'], fn ($x) => $x['v'] && $x['comp'] === $family));
            if (!$ids) {
                return null;
            }
            $data = self::bilanData($ids, true);
            $title = t('Sochaux en {comp}', ['comp' => t($label)]);
            return Pages::render('h2h', $data + [
                'mode' => 'comp',
                'eyebrow' => t('Bilan · calculé depuis les fiches matchs'),
                'titleHtml' => e(t('Sochaux en')) . ' <span class="yellow">' . e(t($label)) . '</span>',
                'crumbs' => [['label' => t('Accueil'), 'href' => url('/')], ['label' => t('Matchs'), 'href' => url('/matchs/')], ['label' => t('Bilans'), 'href' => url('/bilans/coupe-de-france/')]],
                'here' => t($label),
                'chips' => $chips,
                'allHref' => null,
                'shareImage' => null,
            ], [
                'title' => $title . ' : ' . t('le bilan complet'),
                'description' => t('{title} : {n} matchs, {v} victoires, {nn} nuls, {d} défaites. Parcours saison par saison.', ['title' => $title, 'n' => $data['t']['count'], 'v' => $data['t']['V'], 'nn' => $data['t']['N'], 'd' => $data['t']['D']]),
                'active' => 'matchs',
                'styles' => ['css/mosaic.css', 'css/explore.css'],
                'scripts' => ['js/explore.js'],
            ]);
        }
        if (str_starts_with($key, 'stade-')) {
            $stade = substr($key, 6);
            $st = $d['stades'][$stade] ?? null;
            if (!$st) {
                return null;
            }
            $name = self::stadiumName($stade);
            $data = self::bilanData($st['matches'], true, 'decade');
            return Pages::render('h2h', $data + [
                'mode' => 'stade',
                'eyebrow' => t('Bilan · calculé depuis les fiches matchs'),
                'titleHtml' => e(t('Sochaux au')) . ' <span class="yellow">' . e($name) . '</span>',
                'crumbs' => [['label' => t('Accueil'), 'href' => url('/')], ['label' => t('Matchs'), 'href' => url('/matchs/')], ['label' => t('Bilans'), 'href' => url('/bilans/coupe-de-france/')]],
                'here' => $name,
                'chips' => $chips,
                'allHref' => null,
                'shareImage' => null,
            ], [
                'title' => t('Sochaux au {stade} : le bilan complet', ['stade' => $name]),
                'description' => t('Tous les matchs de Sochaux au {stade} : {n} matchs, {v} victoires, {nn} nuls, {d} défaites.', ['stade' => $name, 'n' => $data['t']['count'], 'v' => $data['t']['V'], 'nn' => $data['t']['N'], 'd' => $data['t']['D']]),
                'active' => 'matchs',
                'styles' => ['css/mosaic.css', 'css/explore.css'],
                'scripts' => ['js/explore.js'],
            ]);
        }
        return null;
    }

    public static function stadiumName(string $key): string
    {
        foreach (Collections::get('stades', []) as $s) {
            if (($s['id'] ?? '') === $key) {
                return (string) $s['name'];
            }
        }
        return $key === 'auguste-bonal' ? 'stade Auguste-Bonal' : ucwords(str_replace('-', ' ', $key));
    }

    /**
     * Totaux, faits marquants et liste d'une série de matchs.
     * $group : 'season' (parcours par saison) ou 'decade' (par décennie).
     */
    public static function bilanData(array $ids, bool $withGroups = false, string $group = 'season'): array
    {
        $M = Derived::get()['matches'];
        $list = array_values(array_filter(array_map(fn ($id) => $M[$id] ?? null, $ids), fn ($x) => $x && $x['v']));
        usort($list, fn ($x, $y) => strcmp((string) $y['date'], (string) $x['date']));
        $t = ['V' => 0, 'N' => 0, 'D' => 0, 'count' => count($list), 'gf' => 0, 'ga' => 0];
        $best = $worst = null;
        $first = null;
        $biggestCrowd = null;
        $groups = [];
        foreach ($list as $x) {
            if ($x['result']) {
                $t[$x['result']]++;
            }
            if ($x['us'] !== null) {
                $t['gf'] += (int) $x['us'];
                $t['ga'] += (int) $x['them'];
                $diff = (int) $x['us'] - (int) $x['them'];
                if ($x['result'] === 'V' && (!$best || $diff > $best['_d'] || ($diff === $best['_d'] && $x['us'] > $best['us']))) {
                    $best = $x + ['_d' => $diff];
                }
                if ($x['result'] === 'D' && (!$worst || -$diff > $worst['_d'] || (-$diff === $worst['_d'] && $x['them'] > $worst['them']))) {
                    $worst = $x + ['_d' => -$diff];
                }
            }
            if ($x['date'] && (!$first || strcmp((string) $x['date'], (string) $first['date']) < 0)) {
                $first = $x;
            }
            if (($x['spectators'] ?? 0) > ($biggestCrowd['spectators'] ?? 0)) {
                $biggestCrowd = $x;
            }
            if ($withGroups) {
                $g = $group === 'decade' ? (string) ($x['decade'] ?? '?') : (string) ($x['season'] ?? '?');
                $groups[$g] ??= ['key' => $g, 'n' => 0, 'V' => 0, 'N' => 0, 'D' => 0, 'gf' => 0, 'ga' => 0, 'last' => null];
                $groups[$g]['n']++;
                if ($x['result']) {
                    $groups[$g][$x['result']]++;
                }
                $groups[$g]['gf'] += (int) ($x['us'] ?? 0);
                $groups[$g]['ga'] += (int) ($x['them'] ?? 0);
                // Dernier tour atteint (match le plus tardif de la saison)
                if (!$groups[$g]['last'] || strcmp((string) $x['date'], (string) $groups[$g]['last']['date']) > 0) {
                    $groups[$g]['last'] = $x;
                }
            }
        }
        krsort($groups);
        $score = fn (?array $x) => $x && $x['us'] !== null ? ($x['sh'] ? $x['us'] . '-' . $x['them'] : $x['them'] . '-' . $x['us']) : '–';
        $highlights = array_values(array_filter([
            $list ? ['k' => t('Dernier affrontement'), 'score' => $score($list[0]), 'desc' => Site::matchLabel($list[0]) . ' · ' . date_num($list[0]['date']), 'href' => url($list[0]['path']), 'tone' => 'yellow'] : null,
            $best ? ['k' => t('Plus large victoire'), 'score' => $score($best), 'desc' => Site::matchLabel($best) . ' · ' . date_num($best['date']), 'href' => url($best['path']), 'tone' => 'paper'] : null,
            $worst ? ['k' => t('Plus lourde défaite'), 'score' => $score($worst), 'desc' => Site::matchLabel($worst) . ' · ' . date_num($worst['date']), 'href' => url($worst['path']), 'tone' => 'paper'] : null,
            $first ? ['k' => t('Première rencontre'), 'score' => substr((string) $first['date'], 0, 4), 'desc' => Site::matchLabel($first) . ' · ' . ($first['label'] ?: $first['comp']), 'href' => url($first['path']), 'tone' => 'sand'] : null,
            $biggestCrowd && ($biggestCrowd['spectators'] ?? 0) > 0 ? ['k' => t('Plus grosse affluence'), 'score' => number_format((int) $biggestCrowd['spectators'], 0, ',', ' '), 'desc' => Site::matchLabel($biggestCrowd) . ' · ' . date_num($biggestCrowd['date']), 'href' => url($biggestCrowd['path']), 'tone' => 'paper'] : null,
        ]));
        $n = max(1, $t['V'] + $t['N'] + $t['D']);
        $t['pV'] = round(100 * $t['V'] / $n, 1);
        $t['pN'] = round(100 * $t['N'] / $n, 1);
        $t['pD'] = round(100 - $t['pV'] - $t['pN'], 1);
        $comps = [];
        foreach ($list as $x) {
            $comps[$x['comp']] = ($comps[$x['comp']] ?? 0) + 1;
        }
        arsort($comps);
        return ['t' => $t, 'highlights' => $highlights, 'list' => $list, 'groups' => array_values($groups), 'groupBy' => $group, 'comps' => $comps];
    }

    // ================================================================== RECORDS

    public const RECORDS = [
        'buteurs' => ['Buteurs', 'Meilleurs buteurs', 'buts'],
        'matchs' => ['Matchs joués', 'Joueurs les plus utilisés', 'matchs'],
        'affluences' => ['Affluences', 'Plus grosses affluences', 'spectateurs'],
        'victoires' => ['Larges victoires', 'Plus larges victoires', 'écart'],
        'series' => ['Séries', "Plus longues séries d'invincibilité", 'matchs'],
        'entraineurs' => ['Entraîneurs', 'Entraîneurs les plus fidèles', 'matchs'],
    ];

    public static function records(Request $req): Response
    {
        $cat = isset(self::RECORDS[$req->str('cat')]) ? $req->str('cat') : 'buteurs';
        $decade = preg_match('/^(19|20)\d0$/', $req->str('decennie')) ? (int) $req->str('decennie') : null;
        $comp = isset(Mosaic::COMPS[$req->str('comp')]) ? $req->str('comp') : null;
        $d = Derived::get();
        $M = $d['matches'];
        $okMatch = function (array $x) use ($decade, $comp): bool {
            if (!$x['v']) {
                return false;
            }
            if ($comp) {
                if ($x['comp'] !== Mosaic::COMPS[$comp][2]) {
                    return false;
                }
            } elseif (in_array($x['comp'], Derived::OFFICIAL_EXCLUDED, true)) {
                return false;
            }
            return !$decade || (int) ($x['decade'] ?? 0) === $decade;
        };
        $rows = [];
        switch ($cat) {
            case 'buteurs':
            case 'matchs':
            case 'entraineurs':
                $acc = [];
                foreach ($d['apps'] as $a) {
                    [$pid, $mid, $g, , , , $role] = $a;
                    $x = $M[$mid] ?? null;
                    if (!$x || !$okMatch($x)) {
                        continue;
                    }
                    if ($cat === 'entraineurs' ? $role !== 'coach' : $role !== 'player') {
                        continue;
                    }
                    $acc[$pid] ??= ['v' => 0, 'first' => $x['date'], 'last' => $x['date']];
                    $acc[$pid]['v'] += $cat === 'buteurs' ? $g : 1;
                    $acc[$pid]['first'] = min($acc[$pid]['first'], $x['date']);
                    $acc[$pid]['last'] = max($acc[$pid]['last'], $x['date']);
                }
                uasort($acc, fn ($x, $y) => $y['v'] <=> $x['v']);
                foreach ($acc as $pid => $r) {
                    $s = Index::get((int) $pid);
                    if (!$s || !Index::visible($s) || $r['v'] === 0) {
                        continue;
                    }
                    $years = substr((string) $r['first'], 0, 4) . (substr((string) $r['last'], 0, 4) !== substr((string) $r['first'], 0, 4) ? '–' . substr((string) $r['last'], 0, 4) : '');
                    $rows[] = ['name' => $s['p']['name'], 'meta' => trim(($s['p']['position'] ? ucfirst((string) $s['p']['position']) . ' · ' : '') . $years), 'v' => $r['v'], 'href' => url($s['path']), 'image' => $s['image']];
                    if (count($rows) >= 25) {
                        break;
                    }
                }
                break;
            case 'affluences':
                $list = array_filter($M, fn ($x) => $okMatch($x) && ($x['spectators'] ?? 0) > 0);
                usort($list, fn ($x, $y) => $y['spectators'] <=> $x['spectators']);
                foreach (array_slice($list, 0, 25) as $x) {
                    $rows[] = ['name' => Site::matchLabel($x), 'meta' => date_num($x['date']) . ' · ' . ($x['label'] ?: $x['comp']), 'v' => number_format((int) $x['spectators'], 0, ',', ' '), 'href' => url($x['path']), 'image' => $x['image']];
                }
                break;
            case 'victoires':
                $list = array_filter($M, fn ($x) => $okMatch($x) && $x['result'] === 'V' && $x['us'] !== null);
                usort($list, fn ($x, $y) => (($y['us'] - $y['them']) <=> ($x['us'] - $x['them'])) ?: ($y['us'] <=> $x['us']));
                foreach (array_slice($list, 0, 25) as $x) {
                    $rows[] = ['name' => Site::matchLabel($x), 'meta' => date_num($x['date']) . ' · ' . ($x['label'] ?: $x['comp']), 'v' => '+' . ($x['us'] - $x['them']), 'href' => url($x['path']), 'image' => $x['image']];
                }
                break;
            case 'series':
                $list = array_values(array_filter($M, fn ($x) => $okMatch($x) && $x['result'] && $x['date']));
                usort($list, fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));
                $runs = [];
                $cur = [];
                foreach ($list as $x) {
                    if ($x['result'] !== 'D') {
                        $cur[] = $x;
                    } else {
                        if (count($cur) >= 3) {
                            $runs[] = $cur;
                        }
                        $cur = [];
                    }
                }
                if (count($cur) >= 3) {
                    $runs[] = $cur;
                }
                usort($runs, fn ($x, $y) => count($y) <=> count($x));
                foreach (array_slice($runs, 0, 25) as $run) {
                    $a = $run[0];
                    $b = $run[count($run) - 1];
                    $v = count(array_filter($run, fn ($x) => $x['result'] === 'V'));
                    $rows[] = ['name' => t('Du {a} au {b}', ['a' => date_num($a['date']), 'b' => date_num($b['date'])]), 'meta' => t('{v} victoires, {n} nuls', ['v' => $v, 'n' => count($run) - $v]) . ' · ' . ($a['season'] === $b['season'] ? t('saison') . ' ' . $a['season'] : $a['season'] . ' → ' . $b['season']), 'v' => count($run), 'href' => url($a['path']), 'image' => $a['image']];
                }
                break;
        }
        [$tab, $title, $unit] = self::RECORDS[$cat];
        $qs = fn (array $p) => Mosaic::qs(array_filter(['cat' => $cat === 'buteurs' ? null : $cat, 'decennie' => $decade ? (string) $decade : null, 'comp' => $comp] + $p, fn ($v) => $v !== null));
        $tabs = [];
        foreach (self::RECORDS as $k => [$l]) {
            $tabs[] = ['label' => t($l), 'href' => url('/records/') . $qs(['cat' => $k === 'buteurs' ? null : $k]), 'on' => $k === $cat];
        }
        $decs = [['label' => t('Toutes'), 'href' => url('/records/') . $qs(['decennie' => null]), 'on' => !$decade]];
        for ($y = 1920; $y <= (int) date('Y'); $y += 10) {
            $decs[] = ['label' => $y < 2000 ? "'" . substr((string) $y, 2) : (string) $y, 'href' => url('/records/') . $qs(['decennie' => (string) $y]), 'on' => $decade === $y];
        }
        $compChips = [['label' => t('Toutes'), 'href' => url('/records/') . $qs(['comp' => null]), 'on' => !$comp]];
        foreach (['championnat', 'coupe-de-france', 'coupe-de-la-ligue', 'coupe-d-europe', 'amical'] as $k) {
            $compChips[] = ['label' => t(Mosaic::COMPS[$k][0]), 'href' => url('/records/') . $qs(['comp' => $k]), 'on' => $comp === $k];
        }
        $scope = ($decade ? t('Années') . ' ' . ($decade < 2000 ? substr((string) $decade, 2) : $decade) : t('Toutes époques')) . ' · ' . ($comp ? t(Mosaic::COMPS[$comp][0]) : t('toutes compétitions officielles'));
        return Pages::render('records', [
            'cat' => $cat, 'title' => t($title), 'unit' => t($unit), 'rows' => $rows, 'tabs' => $tabs, 'decs' => $decs, 'compChips' => $compChips, 'scope' => $scope,
        ], [
            'title' => t($title) . ' – ' . t('Le livre des records du FCSM'),
            'description' => t('{title} du FC Sochaux-Montbéliard ({scope}), calculés automatiquement depuis les fiches matchs du musée.', ['title' => t($title), 'scope' => $scope]),
            'active' => 'matchs',
            'noindex' => $decade !== null || $comp !== null,
            'styles' => ['css/mosaic.css', 'css/explore.css'],
        ]);
    }
}
