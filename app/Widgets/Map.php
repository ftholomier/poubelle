<?php
declare(strict_types=1);

namespace App\Widgets;

/**
 * Fond de carte sans service extérieur (widget « carte des stades », écrans) : les contours des
 * pays de public/assets/vendor/countries-110m.topojson (celui de la page Carto), projetés en
 * équirectangulaire corrigée (cos de la latitude moyenne) sur une zone et une taille données.
 * Rien n'est chargé ailleurs : le dessin est fait en SVG (iframe, écran) ou avec GD (images PNG).
 */
final class Map
{
    /** Zones proposées : [lat min, lat max, lng min, lng max]. */
    public const ZONES = [
        'france' => [41.2, 51.3, -5.3, 9.8],
        'europe' => [35.0, 60.5, -11.0, 30.0],
    ];

    /** @var array<int,list<array{0:float,1:float}>>|null arcs décodés (degrés) */
    private static ?array $arcs = null;
    private static ?array $geoms = null;

    /**
     * Projette une zone sur $w × $h pixels : polygones des terres (listes de points [x, y]) et
     * fonction de projection des points. @return array{w:int,h:int,land:list<list<array{0:float,1:float}>>,bbox:array}
     */
    public static function project(array $bbox, int $w, int $h): array
    {
        [$la0, $la1, $lo0, $lo1] = self::fit($bbox, $w, $h);
        $k = cos(deg2rad(($la0 + $la1) / 2));
        $sx = $w / (($lo1 - $lo0) * $k);
        $sy = $h / ($la1 - $la0);
        $land = [];
        foreach (self::rings() as $ring) {
            $minLo = $maxLo = $ring[0][0];
            $minLa = $maxLa = $ring[0][1];
            foreach ($ring as [$lo, $la]) {
                $minLo = min($minLo, $lo);
                $maxLo = max($maxLo, $lo);
                $minLa = min($minLa, $la);
                $maxLa = max($maxLa, $la);
            }
            if ($maxLo < $lo0 || $minLo > $lo1 || $maxLa < $la0 || $minLa > $la1) {
                continue;
            }
            // Points hors de la vue ramenés juste au bord (invisible) : contours plus légers.
            $pts = [];
            foreach ($ring as [$lo, $la]) {
                $p = [round(max(-20, min($w + 20, ($lo - $lo0) * $k * $sx)), 1), round(max(-20, min($h + 20, ($la1 - $la) * $sy)), 1)];
                if (!$pts || $pts[count($pts) - 1] !== $p) {
                    $pts[] = $p;
                }
            }
            $out = static fn (float $v, int $max) => $v < 0 || $v > $max;
            $keep = [];
            foreach ($pts as $i => $p) {
                $a = $pts[$i - 1] ?? null;
                $b = $pts[$i + 1] ?? null;
                // point au milieu d'un segment du bord (hors vue) : inutile
                if ($a && $b && (($out($p[0], $w) && $a[0] === $p[0] && $b[0] === $p[0]) || ($out($p[1], $h) && $a[1] === $p[1] && $b[1] === $p[1]))) {
                    continue;
                }
                $keep[] = $p;
            }
            $pts = $keep;
            if (count($pts) >= 3) {
                $land[] = $pts;
            }
        }
        return ['w' => $w, 'h' => $h, 'land' => $land, 'bbox' => [$la0, $la1, $lo0, $lo1]];
    }

    /** Point [x, y] d'une latitude et longitude sur une carte projetée, ou null hors de la zone. */
    public static function point(array $map, float $lat, float $lng): ?array
    {
        [$la0, $la1, $lo0, $lo1] = $map['bbox'];
        if ($lat < $la0 || $lat > $la1 || $lng < $lo0 || $lng > $lo1) {
            return null;
        }
        $k = cos(deg2rad(($la0 + $la1) / 2));
        return [round(($lng - $lo0) * $k * $map['w'] / (($lo1 - $lo0) * $k), 1), round(($la1 - $lat) * $map['h'] / ($la1 - $la0), 1)];
    }

    /** Chemin SVG des terres (un seul « d »). */
    public static function svgPath(array $map, float $scale = 1.0): string
    {
        $d = '';
        foreach ($map['land'] as $pts) {
            foreach ($pts as $i => [$x, $y]) {
                $d .= ($i ? 'L' : 'M') . round($x * $scale, 1) . ' ' . round($y * $scale, 1);
            }
            $d .= 'Z';
        }
        return $d;
    }

    /** Zone élargie pour garder les proportions de $w × $h (rien n'est déformé). */
    private static function fit(array $b, int $w, int $h): array
    {
        [$la0, $la1, $lo0, $lo1] = $b;
        $k = cos(deg2rad(($la0 + $la1) / 2));
        $gw = ($lo1 - $lo0) * $k;
        $gh = $la1 - $la0;
        if ($gw / $gh > $w / $h) {
            $need = $gw * $h / $w;
            $la0 -= ($need - $gh) / 2;
            $la1 += ($need - $gh) / 2;
        } else {
            $need = $gh * $w / $h / $k;
            $mid = ($lo0 + $lo1) / 2;
            $lo0 = $mid - $need / 2;
            $lo1 = $mid + $need / 2;
        }
        return [$la0, $la1, $lo0, $lo1];
    }

    /** Anneaux extérieurs de tous les pays (longitude, latitude). @return list<list<array{0:float,1:float}>> */
    private static function rings(): array
    {
        if (self::$geoms === null) {
            self::$geoms = [];
            $j = json_decode((string) @file_get_contents(PUBLIC_PATH . '/assets/vendor/countries-110m.topojson'), true);
            if (!is_array($j) || !isset($j['arcs'], $j['transform'])) {
                return [];
            }
            [$kx, $ky] = $j['transform']['scale'];
            [$tx, $ty] = $j['transform']['translate'];
            self::$arcs = [];
            foreach ($j['arcs'] as $arc) {
                $x = $y = 0;
                $pts = [];
                foreach ($arc as $p) {
                    $x += $p[0];
                    $y += $p[1];
                    $pts[] = [$x * $kx + $tx, $y * $ky + $ty];
                }
                self::$arcs[] = $pts;
            }
            foreach ($j['objects']['countries']['geometries'] ?? [] as $g) {
                $polys = $g['type'] === 'Polygon' ? [$g['arcs']] : ($g['type'] === 'MultiPolygon' ? $g['arcs'] : []);
                foreach ($polys as $poly) {
                    $ring = [];
                    foreach ((array) ($poly[0] ?? []) as $a) {
                        $pts = $a >= 0 ? self::$arcs[$a] : array_reverse(self::$arcs[~$a]);
                        if ($ring) {
                            array_shift($pts);
                        }
                        array_push($ring, ...$pts);
                    }
                    if ($ring) {
                        self::$geoms[] = $ring;
                    }
                }
            }
        }
        return self::$geoms;
    }
}
