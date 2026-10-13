<?php
declare(strict_types=1);

namespace App\Widgets\Items;

use App\Data\Collections;
use App\Data\Derived;
use App\Widgets\Context;
use App\Widgets\Map;
use App\Widgets\Widget;

/**
 * Carte des stades : les stades où le club a joué (matchs publiés, stades géolocalisés du
 * référentiel, comme la page /interactif/carto/), en points proportionnels au nombre de matchs,
 * sur un fond de carte dessiné par le musée (Map : aucune tuile ni service extérieur), et les
 * stades les plus fréquentés. Zone : France (défaut) ou Europe.
 */
final class CarteStades extends Widget
{
    public const W = 600;
    public const H = 560;

    public function key(): string
    {
        return 'carte-stades';
    }

    public function name(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Map of the grounds' : 'Carte des stades';
    }

    public function description(string $lang = 'fr'): string
    {
        return $lang === 'en' ? 'Every ground where the club has played, on a map, with the most visited ones.'
            : 'Tous les stades où le club a joué, sur une carte, avec les plus fréquentés.';
    }

    public function options(): array
    {
        return ['zone' => ['label' => 'Zone', 'label_en' => 'Area', 'type' => 'select', 'choices' => ['france' => 'France', 'europe' => 'Europe'], 'default' => 'france']];
    }

    public function height(): int
    {
        return 640;
    }

    public function data(array $opt, Context $c): ?array
    {
        $ref = [];
        foreach ((array) Collections::get('stades', []) as $s) {
            if (is_array($s) && isset($s['id'], $s['lat'], $s['lng']) && $s['lat'] !== null && $s['lng'] !== null) {
                $ref[(string) $s['id']] = $s;
            }
        }
        $M = Derived::part('matches');
        $all = [];
        foreach (Derived::part('stades') as $key => $st) {
            $n = 0;
            foreach ((array) ($st['matches'] ?? []) as $mid) {
                $n += !empty($M[$mid]['v']) ? 1 : 0;
            }
            if ($n > 0 && isset($ref[$key])) {
                $all[(string) $key] = ['key' => (string) $key, 'name' => (string) $ref[$key]['name'], 'city' => (string) ($ref[$key]['city'] ?? ''), 'matches' => $n, 'lat' => (float) $ref[$key]['lat'], 'lng' => (float) $ref[$key]['lng']];
            }
        }
        if (!$all) {
            return null;
        }
        uasort($all, fn ($a, $b) => $b['matches'] <=> $a['matches'] ?: strcmp($a['name'], $b['name']));
        $map = Map::project(Map::ZONES[$opt['zone']], self::W, self::H);
        $home = function_exists('stadium_key') ? stadium_key() : '';
        $points = [];
        $out = 0;
        foreach ($all as $s) {
            $p = Map::point($map, $s['lat'], $s['lng']);
            if ($p === null) {
                $out++;
                continue;
            }
            $points[] = ['x' => $p[0], 'y' => $p[1], 'n' => $s['matches'], 'name' => $s['name'], 'home' => $s['key'] === $home];
        }
        $top = [];
        foreach (array_slice($all, 0, 5, true) as $s) {
            $top[] = ['name' => $s['name'], 'city' => $s['city'], 'matches' => $s['matches'], 'href' => '/bilans/stade-' . $s['key'] . '/'];
        }
        return [
            'zone' => $opt['zone'],
            'stadiums' => count($all),
            'outside' => $out,
            'map' => ['w' => $map['w'], 'h' => $map['h'], 'land' => $map['land']],
            'points' => array_reverse($points), // les plus gros points dessinés en dernier
            'top' => $top,
            'href' => '/interactif/carto/',
        ];
    }

    /** Carte en SVG (iframe et écran) : terres, points proportionnels, stade du club en jaune. */
    public static function svg(array $d, string $cls = 'w-map'): string
    {
        $m = $d['map'];
        $max = max(1, ...array_map(fn ($p) => $p['n'], $d['points'] ?: [['n' => 1]]));
        $h = '<svg class="' . e($cls) . '" viewBox="0 0 ' . $m['w'] . ' ' . $m['h'] . '" role="img" aria-label="' . e(count($d['points']) . ' stades') . '">'
            . '<path class="w-map__land" d="' . e(Map::svgPath($m)) . '"/>';
        foreach ($d['points'] as $p) {
            $r = round(3 + 15 * sqrt($p['n'] / $max), 1);
            $h .= '<circle class="w-map__pt' . ($p['home'] ? ' is-home' : '') . '" cx="' . $p['x'] . '" cy="' . $p['y'] . '" r="' . $r . '"><title>' . e($p['name'] . ' : ' . $p['n']) . '</title></circle>';
        }
        return $h . '</svg>';
    }

    public function body(array $d, Context $c): string
    {
        $h = '<p class="w-eyebrow">' . e($c->tr('Carte des stades', 'Map of the grounds')) . ' · ' . e($c->tr($d['stadiums'] . ' stades', $d['stadiums'] . ' grounds')) . '</p>';
        $h .= '<a class="w-mapbox" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . self::svg($d) . '</a>';
        if ($d['outside'] > 0) {
            $h .= '<p class="w-text w-center small">' . e($c->tr('+ ' . $d['outside'] . ' stade' . ($d['outside'] > 1 ? 's' : '') . ' hors de la carte', '+ ' . $d['outside'] . ' ground' . ($d['outside'] > 1 ? 's' : '') . ' off the map')) . '</p>';
        }
        $h .= '<ol class="w-rank">';
        foreach ($d['top'] as $i => $s) {
            $h .= '<li><span class="w-rank__n">' . ($i + 1) . '</span><a href="' . e($s['href']) . '" target="_blank" rel="noopener"><b>' . e($s['name']) . '</b>' . ($s['city'] !== '' ? '<small>' . e($s['city']) . '</small>' : '') . '</a><span class="w-rank__v">' . $s['matches'] . ' <small>' . e($c->tr('matchs', 'matches')) . '</small></span></li>';
        }
        return $h . '</ol><p><a class="w-btn" href="' . e($d['href']) . '" target="_blank" rel="noopener">' . e($c->tr('La carte du musée', 'The museum map')) . ' →</a></p>';
    }
}
