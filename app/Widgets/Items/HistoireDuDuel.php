<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Derived;
use App\Data\Index;
use App\Data\Names;
use App\Front\Fiche;
use App\Front\Site;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Histoire du duel (avant-match) : le bilan complet contre un adversaire, avec les mêmes données
 * que la page /face-a-face/<club>/ (Derived « clubs » et « matches », matchs publiés seulement) :
 * victoires, nuls, défaites, buts, plus large victoire, meilleur buteur du duel (buts marqués
 * contre cet adversaire, d'après les fiches), dernier match et première rencontre.
 * Adversaire choisi (?adversaire=<slug du club>, celui de l'adresse du face-à-face) ; par défaut
 * l'adversaire du dernier match fiché.
 */
final class HistoireDuDuel extends Widget
{
    public function key(): string
    {
        return 'histoire-du-duel';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Head-to-head history' : 'Histoire du duel';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'The full record against an opponent: wins, draws, defeats, goals, biggest win, top scorer of the fixture and last meeting.'
            : 'Le bilan complet contre un adversaire : victoires, nuls, défaites, buts, plus large victoire, meilleur buteur du duel et dernier match.';
    }

    public function options(): array
    {
        return ['adversaire' => ['label' => 'Adversaire (adresse du face-à-face : « nantes » ; vide : celui du dernier match)', 'label_en' => 'Opponent (head-to-head address: “nantes”; empty: latest match)', 'type' => 'text', 'default' => '', 'pattern' => '/^[a-z0-9-]{2,60}$/']];
    }

    public function height(): int
    {
        return 560;
    }

    /** Clé du club adverse (adresse du face-à-face), ancienne clé ou nom rapproché ; inconnue : l'adversaire par défaut. */
    public static function resolve(string $slug, bool $fallback = true): ?string
    {
        $clubs = Derived::part('clubs');
        $self = Names::clubKey(\App\Core\Club::team());
        if ($slug === '') {
            $last = Site::lastMatchCreated();
            $slug = (string) ($last['club'] ?? '');
            if ($slug === '' || !isset($clubs[$slug])) {
                uasort($clubs, fn ($x, $y) => $y['count'] <=> $x['count']);
                $slug = (string) (array_key_first(array_diff_key($clubs, [$self => 1])) ?? '');
            }
        }
        if (!isset($clubs[$slug])) {
            $slug = Names::clubKey(str_replace('-', ' ', $slug));
        }
        if ($slug !== '' && $slug !== $self && isset($clubs[$slug])) {
            return $slug;
        }
        return $fallback ? self::resolve('', false) : null; // inconnu : l'adversaire par défaut
    }

    /** Adversaires possibles (back-office) : clé => nom, les plus affrontés d'abord. @return array<string,string> */
    public static function choices(int $limit = 300): array
    {
        $clubs = Derived::part('clubs');
        uasort($clubs, fn ($x, $y) => $y['count'] <=> $x['count']);
        $self = Names::clubKey(\App\Core\Club::team());
        $out = [];
        foreach (array_slice($clubs, 0, $limit, true) as $k => $c) {
            if ($k !== $self && (int) $c['count'] > 0) {
                $out[(string) $k] = Fiche::clubName((string) $k) . ' (' . (int) $c['count'] . ')';
            }
        }
        return $out;
    }

    public function data(array $opt, Context $c): ?array
    {
        $club = self::resolve($opt['adversaire']);
        if ($club === null) {
            return null;
        }
        $M = Derived::part('matches');
        $list = [];
        foreach ((array) Derived::part('clubs')[$club]['matches'] as $id) {
            $x = $M[$id] ?? null;
            if ($x && $x['v']) {
                $list[] = $x + ['id' => (int) $id];
            }
        }
        if (!$list) {
            return null;
        }
        usort($list, fn ($x, $y) => strcmp((string) $y['date'], (string) $x['date']));
        $t = ['V' => 0, 'N' => 0, 'D' => 0, 'gf' => 0, 'ga' => 0];
        $best = null;
        $first = null;
        foreach ($list as $x) {
            if (isset($t[$x['result'] ?? ''])) {
                $t[$x['result']]++;
            }
            if ($x['us'] !== null) {
                $t['gf'] += (int) $x['us'];
                $t['ga'] += (int) $x['them'];
                $d = (int) $x['us'] - (int) $x['them'];
                if ($x['result'] === 'V' && (!$best || $d > $best['_d'] || ($d === $best['_d'] && $x['us'] > $best['us']))) {
                    $best = $x + ['_d' => $d];
                }
            }
            if ($x['date'] && (!$first || strcmp((string) $x['date'], (string) $first['date']) < 0)) {
                $first = $x;
            }
        }
        // Meilleur buteur du duel : buts marqués dans ces matchs (fiches liées seulement).
        $goals = [];
        $S = Derived::part('scorers');
        foreach ($list as $x) {
            foreach ((array) ($S[$x['id']] ?? []) as $s) {
                $pid = (int) ($s[0] ?? 0);
                if ($pid > 0) {
                    $goals[$pid] ??= ['n' => 0, 'name' => (string) $s[1]];
                    $goals[$pid]['n'] += max(1, count((array) ($s[2] ?? [])));
                }
            }
        }
        uasort($goals, fn ($a, $b) => $b['n'] <=> $a['n']);
        $scorer = null;
        foreach ($goals as $pid => $g) {
            $p = Index::get($pid);
            if ($p && Index::visible($p)) {
                $scorer = ['name' => $g['name'], 'goals' => $g['n'], 'href' => self::local((string) $p['path'])];
                break;
            }
        }
        $match = fn (array $x) => ['label' => Site::matchLabel($x), 'home' => (string) $x['home'], 'away' => (string) $x['away'],
            'score' => $x['us'] !== null ? ($x['sh'] ? $x['us'] . '-' . $x['them'] : $x['them'] . '-' . $x['us']) : '', 'date' => (string) $x['date'],
            'competition' => (string) (($x['label'] ?? '') ?: ($x['comp'] ?? '')), 'result' => (string) ($x['result'] ?? ''), 'href' => self::local((string) $x['path'])];
        return [
            'club' => $club,
            'opponent' => Fiche::clubName($club),
            'team' => \App\Core\Club::team(),
            'matches' => count($list),
            'wins' => $t['V'],
            'draws' => $t['N'],
            'defeats' => $t['D'],
            'goals_for' => $t['gf'],
            'goals_against' => $t['ga'],
            'biggest_win' => $best ? $match($best) : null,
            'top_scorer' => $scorer,
            'last' => $match($list[0]),
            'first_year' => $first ? (int) substr((string) $first['date'], 0, 4) : null,
            'href' => '/face-a-face/' . $club . '/',
        ];
    }

    public function body(array $d, Context $c): string
    {
        $date = fn (string $iso) => $c->en() ? date('j F Y', strtotime($iso)) : date_fr($iso);
        $h = '<p class="w-eyebrow">' . e($c->tr('L’histoire du duel', 'Head-to-head')) . ($d['first_year'] ? ' · ' . e($c->tr('depuis ', 'since ')) . $d['first_year'] : '') . '</p>';
        $h .= '<p class="w-title"><a href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($d['team']) . ' × ' . e($d['opponent']) . '</a></p>';
        $h .= '<div class="w-stats w-stats--4"><span class="is-key"><b>' . $d['matches'] . '</b>' . e($c->tr('matchs', 'matches')) . '</span><span><b>' . $d['wins'] . '</b>' . e($c->tr('victoires', 'wins')) . '</span><span><b>' . $d['draws'] . '</b>' . e($c->tr('nuls', 'draws')) . '</span><span><b>' . $d['defeats'] . '</b>' . e($c->tr('défaites', 'defeats')) . '</span></div>';
        $total = max(1, $d['wins'] + $d['draws'] + $d['defeats']);
        $h .= '<div class="w-bar" role="img" aria-label="' . e($d['wins'] . ' V, ' . $d['draws'] . ' N, ' . $d['defeats'] . ' D') . '"><i class="w-bar__v" style="width:' . round(100 * $d['wins'] / $total, 1) . '%"></i><i class="w-bar__n" style="width:' . round(100 * $d['draws'] / $total, 1) . '%"></i><i class="w-bar__d" style="width:' . round(100 * $d['defeats'] / $total, 1) . '%"></i></div>';
        $h .= '<p class="w-text w-center small">' . e($c->tr('Buts : ' . $d['goals_for'] . ' marqués, ' . $d['goals_against'] . ' encaissés', 'Goals: ' . $d['goals_for'] . ' scored, ' . $d['goals_against'] . ' conceded')) . '</p><ul class="w-list w-list--tight">';
        if ($d['biggest_win']) {
            $b = $d['biggest_win'];
            $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Plus large victoire', 'Biggest win')) . '</span> <a href="' . e($b['href']) . '" target="_blank" rel="noopener">' . e($b['label']) . '</a> <small class="w-score">' . e($date($b['date'])) . '</small></li>';
        }
        if ($d['top_scorer']) {
            $s = $d['top_scorer'];
            $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Meilleur buteur du duel', 'Top scorer of the fixture')) . '</span> <a href="' . e($s['href']) . '" target="_blank" rel="noopener">' . e($s['name']) . '</a> <b class="w-right">' . $s['goals'] . '</b></li>';
        }
        $l = $d['last'];
        $h .= '<li><span class="w-kicker w-inline">' . e($c->tr('Dernier match', 'Last meeting')) . '</span> <a href="' . e($l['href']) . '" target="_blank" rel="noopener">' . e($l['label']) . '</a> <small class="w-score">' . e($date($l['date'])) . '</small></li></ul>';
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Tout le face-à-face', 'The full head-to-head')) . ' →</a></p>';
    }
}
