<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Derived;
use App\Front\Explore;
use App\Front\Palmares;
use App\Widgets\Context;
use App\Widgets\Widget;

/**
 * Saison en un coup d'œil (données de la page /matchs/<saison>/ : Explore::seasonData) :
 * division et classement final, bilan V/N/D et buts, meilleurs buteurs, entraîneur, titres
 * (palmarès). Saison choisie (?saison=AAAA-AAAA) ; par défaut la saison en cours si un match y
 * est fiché, sinon la dernière saison qui en a.
 */
final class Saison extends Widget
{
    public function key(): string
    {
        return 'saison';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Season at a glance' : 'Saison en un coup d’œil';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'A season of the club: final position, wins, draws and defeats, top scorers, coach and titles.'
            : 'Une saison du club : classement, bilan victoires, nuls et défaites, meilleurs buteurs, entraîneur et titres.';
    }

    public function options(): array
    {
        return ['saison' => ['label' => 'Saison (AAAA-AAAA ; vide : en cours ou dernière)', 'label_en' => 'Season (YYYY-YYYY; empty: current or latest)', 'type' => 'text', 'default' => '', 'pattern' => '/^(18|19|20)\d\d-(18|19|20)\d\d$/']];
    }

    public function height(): int
    {
        return 580;
    }

    /** Saison par défaut : en cours si un match y est fiché, sinon la plus récente qui en a. */
    public static function defaultSeason(): ?string
    {
        $S = Derived::part('seasons');
        $cur = Explore::currentSeason();
        if (!empty($S[$cur]['matches'])) {
            return $cur;
        }
        $best = null;
        foreach ($S as $k => $v) {
            if (!empty($v['matches']) && strcmp((string) $k, $cur) < 0 && ($best === null || strcmp((string) $k, $best) > 0)) {
                $best = (string) $k;
            }
        }
        return $best;
    }

    public function data(array $opt, Context $c): ?array
    {
        $season = $opt['saison'] !== '' ? $opt['saison'] : self::defaultSeason();
        $v = $season ? Explore::seasonData($season) : null;
        if (!$v) {
            return null;
        }
        $x = $v['vars'];
        $res = ['V' => 0, 'N' => 0, 'D' => 0];
        $gf = $ga = 0;
        foreach ($x['matches'] as $m) {
            if (isset($res[$m['result'] ?? ''])) {
                $res[$m['result']]++;
            }
            if ($m['us'] !== null) {
                $gf += (int) $m['us'];
                $ga += (int) $m['them'];
            }
        }
        $division = Derived::part('seasons')[$season]['division'] ?? null;
        $place = null;
        foreach ($x['sums'] as $s) {
            if (($s['k'] ?? '') === t('classement final')) {
                $place = ['rank' => (string) $s['v'], 'detail' => (string) ($s['title'] ?? '')];
            }
        }
        $titles = [];
        foreach (Palmares::data()['groups'] as $g) {
            foreach ($g['titles'] as $t) {
                if ($t['season'] === $season) {
                    $titles[] = ['competition' => (string) $g['comp']] + ($t['href'] ? ['href' => self::local($t['href'])] : []);
                }
            }
        }
        $coach = $x['coaches'][0] ?? null;
        return [
            'season' => $season,
            'label' => (string) $x['label'],
            'division' => $division ? (string) $division : null,
            'position' => $place,
            'matches' => count($x['matches']),
            'wins' => $res['V'],
            'draws' => $res['N'],
            'defeats' => $res['D'],
            'goals_for' => $gf,
            'goals_against' => $ga,
            'scorers' => array_map(fn ($s) => ['name' => (string) $s['name'], 'goals' => (int) $s['g']] + ($s['href'] ? ['href' => self::local($s['href'])] : []), array_slice($x['scorers'], 0, 5)),
            'coach' => $coach ? ['name' => (string) $coach['name']] + ($coach['href'] ? ['href' => self::local($coach['href'])] : []) : null,
            'titles' => $titles,
            'href' => '/matchs/' . $season . '/',
        ];
    }

    public function body(array $d, Context $c): string
    {
        $link = fn (array $p, string $inner) => isset($p['href']) ? '<a href="' . e($p['href']) . '" target="_blank" rel="noopener">' . $inner . '</a>' : $inner;
        $h = '<p class="w-eyebrow">' . e($c->tr('La saison', 'The season')) . ($d['division'] ? ' · ' . e($d['division']) : '') . '</p>';
        $h .= '<p class="w-title"><a href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Saison ', 'Season ') . $d['label']) . '</a></p>';
        if ($d['titles']) {
            $h .= '<p class="w-trophies">' . implode('', array_map(fn ($t) => '<span>🏆 ' . $link($t, e($t['competition'])) . '</span>', $d['titles'])) . '</p>';
        }
        $h .= '<div class="w-stats w-stats--4">';
        if ($d['position']) {
            $h .= '<span class="is-key" title="' . e($d['position']['detail']) . '"><b>' . e($d['position']['rank']) . '</b>' . e($c->tr('classement', 'position')) . '</span>';
        }
        if ($d['matches'] > 0) {
            $h .= '<span><b>' . $d['wins'] . '</b>' . e($c->tr('victoires', 'wins')) . '</span><span><b>' . $d['draws'] . '</b>' . e($c->tr('nuls', 'draws')) . '</span><span><b>' . $d['defeats'] . '</b>' . e($c->tr('défaites', 'defeats')) . '</span>';
        }
        $h .= '</div>' . ($d['matches'] > 0 ? '<p class="w-text w-center small">' . e($c->tr($d['matches'] . ' matchs fichés · buts ' . $d['goals_for'] . '-' . $d['goals_against'], $d['matches'] . ' matches on record · goals ' . $d['goals_for'] . '-' . $d['goals_against'])) . '</p>' : '<p class="w-text w-center small">' . e($c->tr('Aucun match de cette saison n’est encore fiché.', 'No match of this season on record yet.')) . '</p>');
        if ($d['scorers']) {
            $h .= '<p class="w-kicker">' . e($c->tr('Meilleurs buteurs', 'Top scorers')) . '</p><ul class="w-list w-list--tight">';
            foreach ($d['scorers'] as $s) {
                $h .= '<li>' . $link($s, e($s['name'])) . ' <b class="w-right">' . $s['goals'] . '</b></li>';
            }
            $h .= '</ul>';
        }
        if ($d['coach']) {
            $h .= '<p class="w-text"><span class="w-kicker w-inline">' . e($c->tr('Entraîneur', 'Coach')) . '</span> ' . $link($d['coach'], e($d['coach']['name'])) . '</p>';
        }
        return $h . '<p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('Toute la saison', 'The whole season')) . ' →</a></p>';
    }
}
