<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Index;

/**
 * Statistiques du musée sur une période : chiffres clés (comparés à la période précédente de même
 * durée), courbes par jour, heures et jours de la semaine, appareils, provenances, et les
 * classements (joueurs, matchs, récits, pages, rubriques, décennies, jeux, recherches).
 */
final class StatsReport
{
    public const PRESETS = [
        'aujourdhui' => "Aujourd’hui", 'hier' => 'Hier', '7j' => '7 derniers jours', '30j' => '30 derniers jours',
        'mois' => 'Ce mois-ci', 'mois-prec' => 'Mois dernier', '90j' => '90 derniers jours', 'annee' => 'Cette année', 'tout' => 'Depuis le début',
    ];
    public const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

    /** Période d'après les filtres : [du, au, libellé]. */
    public static function period(string $preset, string $from = '', string $to = ''): array
    {
        $ok = fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
        if ($ok($from) !== '' && $ok($to) !== '') {
            [$from, $to] = $from <= $to ? [$from, $to] : [$to, $from];
            return [$from, min($to, date('Y-m-d')), 'Du ' . date('d/m/Y', strtotime($from)) . ' au ' . date('d/m/Y', strtotime($to))];
        }
        $t = date('Y-m-d');
        $first = array_key_first(Stats::days()) ?? $t;
        [$a, $b] = match ($preset) {
            'aujourdhui' => [$t, $t],
            'hier' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            '7j' => [date('Y-m-d', strtotime('-6 days')), $t],
            'mois' => [date('Y-m-01'), $t],
            'mois-prec' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
            '90j' => [date('Y-m-d', strtotime('-89 days')), $t],
            'annee' => [date('Y-01-01'), $t],
            'tout' => [min($first, $t), $t],
            default => [date('Y-m-d', strtotime('-29 days')), $t],
        };
        return [$a, $b, self::PRESETS[$preset] ?? self::PRESETS['30j']];
    }

    /** Tout le rapport d'une période. */
    public static function build(string $from, string $to): array
    {
        Stats::aggregate();
        $days = Stats::days();
        $len = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
        $pFrom = date('Y-m-d', strtotime($from . " -$len days"));
        $pTo = date('Y-m-d', strtotime($from . ' -1 day'));
        $r = ['from' => $from, 'to' => $to, 'len' => $len, 'series' => [], 'hours' => array_fill(0, 24, 0), 'heat' => array_fill(0, 7, array_fill(0, 24, 0)),
            'dev' => [], 'ref' => [], 'pages' => []];
        $k = ['views' => 0, 'visitors' => 0, 'visits' => 0, 'bounces' => 0, 'en' => 0];
        $prev = $k;
        foreach ($days as $d => $v) {
            if ($d >= $pFrom && $d <= $pTo) {
                foreach (['views' => 'total', 'visitors' => 'visitors', 'visits' => 'visits', 'bounces' => 'bounces', 'en' => 'en'] as $a => $b) {
                    $prev[$a] += (int) ($v[$b] ?? 0);
                }
            }
            if ($d < $from || $d > $to) {
                continue;
            }
            foreach (['views' => 'total', 'visitors' => 'visitors', 'visits' => 'visits', 'bounces' => 'bounces', 'en' => 'en'] as $a => $b) {
                $k[$a] += (int) ($v[$b] ?? 0);
            }
            $wd = (int) date('N', strtotime($d)) - 1;
            foreach ($v['hours'] ?? [] as $h => $c) {
                $r['hours'][$h] += $c;
                $r['heat'][$wd][$h] += $c;
            }
            foreach (['dev', 'ref', 'pages'] as $f) {
                foreach ($v[$f] ?? [] as $key => $c) {
                    $r[$f][$key] = ($r[$f][$key] ?? 0) + $c;
                }
            }
        }
        // Courbe jour par jour (période courte) ou semaine par semaine (période longue).
        $step = $len > 120 ? 7 : 1;
        for ($t = strtotime($from); $t <= strtotime($to); $t += 86400 * $step) {
            $key = date('Y-m-d', $t);
            $row = ['views' => 0, 'visitors' => 0, 'visits' => 0];
            for ($i = 0; $i < $step; $i++) {
                $d = date('Y-m-d', $t + 86400 * $i);
                if ($d > $to) {
                    break;
                }
                $row['views'] += (int) ($days[$d]['total'] ?? 0);
                $row['visitors'] += (int) ($days[$d]['visitors'] ?? 0);
                $row['visits'] += (int) ($days[$d]['visits'] ?? 0);
            }
            $r['series'][$key] = $row;
        }
        $r['step'] = $step;
        $r['kpi'] = self::kpi($k);
        $r['prev'] = self::kpi($prev);
        arsort($r['dev']);
        arsort($r['ref']);
        $r['ref'] = array_slice($r['ref'], 0, 10, true);
        $r += self::rankings($r['pages']);
        // Meilleur jour et meilleure heure.
        $best = ['day' => null, 'n' => 0];
        foreach ($r['series'] as $d => $row) {
            if ($row['views'] > $best['n']) {
                $best = ['day' => $d, 'n' => $row['views']];
            }
        }
        $r['best'] = $best;
        $r['peak'] = array_sum($r['hours']) ? array_search(max($r['hours']), $r['hours'], true) : null;
        return $r;
    }

    private static function kpi(array $k): array
    {
        $k['bounce'] = $k['visits'] ? round($k['bounces'] / $k['visits'] * 100) : 0;
        $k['ppv'] = $k['visits'] ? round($k['views'] / $k['visits'], 1) : 0;
        $k['en_pct'] = $k['views'] ? round($k['en'] / $k['views'] * 100) : 0;
        return $k;
    }

    /** Évolution en % par rapport à la période précédente (null si rien à comparer). */
    public static function delta(int|float $now, int|float $before): ?int
    {
        return $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    }

    /** Classements tirés des pages vues. */
    private static function rankings(array $pages): array
    {
        $out = ['players' => [], 'matches' => [], 'stories' => [], 'top' => [], 'sections' => [], 'decades' => [], 'games' => [], 'searches' => []];
        $label = [];
        foreach ($pages as $path => $n) {
            if (str_contains($path, '?q=')) {
                $q = substr($path, strpos($path, '?q=') + 3);
                $out['searches'][$q] = ($out['searches'][$q] ?? 0) + $n;
                $path = strtok($path, '?');
            }
            $p = (string) preg_replace('#^/en(/|$)#', '/', $path);
            $out['top'][$p] = ($out['top'][$p] ?? 0) + $n;
            $seg = explode('/', trim($p, '/'))[0] ?? '';
            $sec = $seg === '' ? 'Accueil' : self::section($seg);
            $out['sections'][$sec] = ($out['sections'][$sec] ?? 0) + $n;
            if ($seg === 'interactif' && preg_match('#^/interactif/([^/]+)/#', $p, $m)) {
                $g = ucfirst(str_replace('-', ' ', $m[1]));
                $out['games'][$g] = ($out['games'][$g] ?? 0) + $n;
            }
            $e = Index::byPath($p);
            if ($e) {
                $label[$p] = (string) ($e['title'] ?? $p);
                $type = (string) ($e['type'] ?? '');
                if ($type === 'personne' && $seg === 'joueurs') {
                    $out['players'][$p] = ($out['players'][$p] ?? 0) + $n;
                } elseif ($type === 'match') {
                    $out['matches'][$p] = ($out['matches'][$p] ?? 0) + $n;
                } elseif ($type === 'article') {
                    $out['stories'][$p] = ($out['stories'][$p] ?? 0) + $n;
                }
            }
            $dec = self::decade($p);
            if ($dec) {
                $out['decades'][$dec] = ($out['decades'][$dec] ?? 0) + $n;
            }
        }
        foreach ($out as $key => &$list) {
            arsort($list);
            $list = array_slice($list, 0, $key === 'top' ? 15 : 10, true);
        }
        unset($list);
        ksort($out['decades']);
        $out['labels'] = $label;
        return $out;
    }

    /** Décennie d'une page : listes « années-1980 », saisons « 1983-1984 », matchs datés. */
    public static function decade(string $p): ?string
    {
        if (preg_match('#annees-(\d{4})#', $p, $m) || preg_match('#/((?:19|20)\d\d)-(?:19|20)\d\d/#', $p, $m) || preg_match('#-\d\d-\d\d-((?:19|20)\d\d)/$#', $p, $m)) {
            $y = (int) $m[1];
            return $y >= 1920 && $y <= (int) date('Y') ? (string) (intdiv($y, 10) * 10) : null;
        }
        return null;
    }

    private static function section(string $seg): string
    {
        return [
            'matchs' => 'Matchs', 'joueurs' => 'Joueurs', 'personnes' => 'Joueurs', 'entraineurs' => 'Entraîneurs', 'interactif' => 'Interactif',
            'explorer' => 'Explorer', 'saisons' => 'Saisons', 'recits' => 'Récits', 'grands-recits' => 'Grands récits', 'recherche' => 'Recherche',
            'dirigeants' => 'Dirigeants', 'face-a-face' => 'Face-à-face', 'records' => 'Records', 'bilans' => 'Bilans', 'reserves' => 'Réserves',
            'centenaire' => 'Centenaire', 'infrastructures' => 'Infrastructures', 'symboles' => 'Symboles', 'supporters' => 'Supporters', 'articles' => 'Articles',
            'personnages-emblematiques' => 'Personnages emblématiques', 'boutique' => 'Boutique', 'faire-un-don' => 'Dons',
            'carnet' => 'Carnet du supporter', 'appli' => 'L’appli', 'chiffres' => 'Les chiffres', '100-moments' => '100 moments',
        ][$seg] ?? ucfirst(str_replace('-', ' ', $seg));
    }

    /** « À retenir » : quelques phrases tirées des chiffres de la période. */
    public static function tips(array $r): array
    {
        $k = $r['kpi'];
        $p = $r['prev'];
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
        $tips = [];
        if (!$k['views']) {
            return $tips;
        }
        $dv = self::delta($k['visitors'], $p['visitors']);
        if ($dv !== null) {
            $tips[] = ($dv >= 0 ? 'Fréquentation en hausse de ' . $dv : 'Fréquentation en baisse de ' . abs($dv)) . ' % par rapport à la période précédente.';
        }
        if ($r['best']['day']) {
            $tips[] = 'Meilleur' . ($r['step'] > 1 ? 'e semaine : celle du ' : ' jour : le ') . date('d/m/Y', strtotime($r['best']['day'])) . ', avec ' . $fmt($r['best']['n']) . ' pages vues.';
        }
        if ($r['peak'] !== null) {
            $tips[] = 'Heure de pointe : ' . $r['peak'] . ' h – ' . ($r['peak'] + 1) . ' h. C’est le bon moment pour publier ou envoyer une notification.';
        }
        $dt = array_sum($r['dev']);
        if ($dt) {
            $mob = (int) round(($r['dev']['mobile'] ?? 0) / $dt * 100);
            $tips[] = $mob . ' % des visites se font sur mobile' . ($mob >= 50 ? ' : le musée se visite d’abord dans la poche.' : '.');
        }
        if ($r['players']) {
            $tips[] = 'Joueur star de la période : ' . self::label($r, (string) array_key_first($r['players'])) . '.';
        }
        if ($r['decades']) {
            $tips[] = 'Décennie la plus consultée : les années ' . array_search(max($r['decades']), $r['decades'], true) . '.';
        }
        if ($k['bounce'] >= 60) {
            $tips[] = $k['bounce'] . ' % des visites ne voient qu’une page : des liens « à voir aussi » mieux placés aideraient à les retenir.';
        }
        if ($r['searches']) {
            $tips[] = 'Recherche la plus fréquente : « ' . array_key_first($r['searches']) . ' ».';
        }
        return $tips;
    }

    /** Libellé lisible d'une adresse. */
    public static function label(array $r, string $path): string
    {
        $t = $r['labels'][$path] ?? '';
        if ($t !== '') {
            return $t;
        }
        return $path === '/' ? 'Accueil' : ucfirst(str_replace(['-', '/'], [' ', ' › '], trim($path, '/')));
    }
}
