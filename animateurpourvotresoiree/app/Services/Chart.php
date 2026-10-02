<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Graphiques SVG générés côté serveur (aucune bibliothèque, aucun script) :
 * barres, courbes multi-séries, anneau et mini-courbe. Les infobulles utilisent <title>.
 */
final class Chart
{
    private const COLORS = ['#ff4f3a', '#8f7bff', '#ffd23f', '#5fd3ff', '#c8f560', '#ff9ec7', '#1c1233'];

    /** @param array<string,int|float> $data libellé => valeur */
    public static function bars(array $data, array $opt = []): string
    {
        $w = (int) ($opt['width'] ?? 720);
        $h = (int) ($opt['height'] ?? 200);
        $color = (string) ($opt['color'] ?? '#ff4f3a');
        $padL = 36;
        $padB = 26;
        $padT = 10;
        $n = max(1, count($data));
        $max = max(1, (float) max($data ?: [0]));
        $max = self::nice($max);
        $innerW = $w - $padL - 6;
        $innerH = $h - $padB - $padT;
        $slot = $innerW / $n;
        $bw = max(2, $slot * 0.68);
        $every = (int) ($opt['label_every'] ?? max(1, (int) ceil($n / 10)));
        $fmt = $opt['format'] ?? static fn ($k) => self::shortLabel((string) $k);
        $svg = self::open($w, $h, (string) ($opt['title'] ?? ''));
        $svg .= self::grid($w, $h, $padL, $padT, $innerH, $max);
        $i = 0;
        foreach ($data as $label => $v) {
            $bh = $max > 0 ? ($v / $max) * $innerH : 0;
            $x = $padL + $i * $slot + ($slot - $bw) / 2;
            $y = $padT + $innerH - $bh;
            $svg .= '<rect x="' . self::f($x) . '" y="' . self::f($y) . '" width="' . self::f($bw) . '" height="' . self::f(max(0, $bh)) . '" rx="3" fill="' . e($color) . '" stroke="#1c1233" stroke-width="1.5"><title>' . e($fmt($label) . ' : ' . self::num($v)) . '</title></rect>';
            if ($i % $every === 0) {
                $svg .= '<text x="' . self::f($padL + $i * $slot + $slot / 2) . '" y="' . ($h - 8) . '" text-anchor="middle" class="ch-l">' . e($fmt($label)) . '</text>';
            }
            $i++;
        }
        return $svg . '</svg>';
    }

    /**
     * Courbes : ['Vues' => [date => valeur], 'Messages' => [...]] (mêmes clés pour toutes les séries).
     * @param array<string,array<string,int|float>> $series
     */
    public static function lines(array $series, array $opt = []): string
    {
        $w = (int) ($opt['width'] ?? 720);
        $h = (int) ($opt['height'] ?? 220);
        $padL = 36;
        $padB = 26;
        $padT = 10;
        $first = reset($series) ?: [];
        $keys = array_keys($first);
        $n = max(2, count($keys));
        $max = 1;
        foreach ($series as $s) {
            $max = max($max, (float) max($s ?: [0]));
        }
        $max = self::nice($max);
        $innerW = $w - $padL - 10;
        $innerH = $h - $padB - $padT;
        $every = (int) ($opt['label_every'] ?? max(1, (int) ceil($n / 8)));
        $fmt = $opt['format'] ?? static fn ($k) => self::shortLabel((string) $k);
        $svg = self::open($w, $h, (string) ($opt['title'] ?? ''));
        $svg .= self::grid($w, $h, $padL, $padT, $innerH, $max);
        foreach ($keys as $i => $k) {
            if ($i % $every === 0) {
                $svg .= '<text x="' . self::f($padL + $i * $innerW / ($n - 1)) . '" y="' . ($h - 8) . '" text-anchor="middle" class="ch-l">' . e($fmt($k)) . '</text>';
            }
        }
        $ci = 0;
        foreach ($series as $name => $s) {
            $color = $opt['colors'][$ci] ?? self::COLORS[$ci % count(self::COLORS)];
            $pts = [];
            $i = 0;
            foreach ($s as $k => $v) {
                $pts[] = [$padL + $i * $innerW / ($n - 1), $padT + $innerH - ($v / $max) * $innerH, $k, $v];
                $i++;
            }
            $d = '';
            foreach ($pts as $j => $p) {
                $d .= ($j ? 'L' : 'M') . self::f($p[0]) . ' ' . self::f($p[1]);
            }
            if (!empty($opt['area']) && $ci === 0 && $pts) {
                $svg .= '<path d="' . $d . 'L' . self::f(end($pts)[0]) . ' ' . ($padT + $innerH) . 'L' . self::f($pts[0][0]) . ' ' . ($padT + $innerH) . 'Z" fill="' . e($color) . '" opacity=".14"/>';
            }
            $svg .= '<path d="' . $d . '" fill="none" stroke="' . e($color) . '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>';
            if (count($pts) <= 45) {
                foreach ($pts as $p) {
                    $svg .= '<circle cx="' . self::f($p[0]) . '" cy="' . self::f($p[1]) . '" r="3.5" fill="#fff" stroke="' . e($color) . '" stroke-width="2"><title>' . e($name . ' — ' . $fmt($p[2]) . ' : ' . self::num($p[3])) . '</title></circle>';
                }
            }
            $ci++;
        }
        $svg .= '</svg>';
        if (count($series) > 1 || !empty($opt['legend'])) {
            $leg = '<div class="ch-legend">';
            $ci = 0;
            foreach (array_keys($series) as $name) {
                $color = $opt['colors'][$ci] ?? self::COLORS[$ci % count(self::COLORS)];
                $leg .= '<span><i style="background:' . e($color) . '"></i>' . e((string) $name) . '</span>';
                $ci++;
            }
            $svg .= $leg . '</div>';
        }
        return $svg;
    }

    /** @param array<string,int|float> $data */
    public static function donut(array $data, array $opt = []): string
    {
        $size = (int) ($opt['size'] ?? 180);
        $total = array_sum($data);
        $r = 70;
        $c = 2 * M_PI * $r;
        $svg = '<div class="ch-donut"><svg viewBox="0 0 180 180" width="' . $size . '" height="' . $size . '" role="img" aria-label="' . e((string) ($opt['title'] ?? 'Répartition')) . '">';
        $svg .= '<circle cx="90" cy="90" r="' . $r . '" fill="none" stroke="#efe6d6" stroke-width="26"/>';
        $offset = 0.0;
        $i = 0;
        $legend = '';
        foreach ($data as $label => $v) {
            $color = $opt['colors'][$label] ?? self::COLORS[$i % count(self::COLORS)];
            $len = $total > 0 ? $v / $total * $c : 0;
            $svg .= '<circle cx="90" cy="90" r="' . $r . '" fill="none" stroke="' . e($color) . '" stroke-width="26" stroke-dasharray="' . self::f($len) . ' ' . self::f($c - $len) . '" stroke-dashoffset="' . self::f(-$offset) . '" transform="rotate(-90 90 90)"><title>' . e($label . ' : ' . self::num($v) . ($total ? ' (' . round($v / $total * 100) . ' %)' : '')) . '</title></circle>';
            $legend .= '<li><i style="background:' . e($color) . '"></i>' . e((string) $label) . ' <b>' . self::num($v) . '</b></li>';
            $offset += $len;
            $i++;
        }
        $svg .= '<text x="90" y="88" text-anchor="middle" class="ch-big">' . self::num($total) . '</text><text x="90" y="108" text-anchor="middle" class="ch-l">' . e((string) ($opt['unit'] ?? 'total')) . '</text>';
        return $svg . '</svg><ul class="ch-donut-legend">' . $legend . '</ul></div>';
    }

    /** Mini-courbe pour les cartes de chiffres clés. @param array<int|string,int|float> $values */
    public static function spark(array $values, string $color = '#ff4f3a'): string
    {
        $values = array_values($values);
        $n = count($values);
        if ($n < 2) {
            return '';
        }
        $max = max(1, max($values));
        $d = '';
        foreach ($values as $i => $v) {
            $d .= ($i ? 'L' : 'M') . self::f($i * 100 / ($n - 1)) . ' ' . self::f(28 - ($v / $max) * 26);
        }
        return '<svg class="spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true"><path d="' . $d . '" fill="none" stroke="' . e($color) . '" stroke-width="2.5" vector-effect="non-scaling-stroke"/></svg>';
    }

    // ------------------------------------------------------------- utilitaires

    private static function open(int $w, int $h, string $title): string
    {
        return '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title !== '' ? $title : 'Graphique') . '" preserveAspectRatio="xMidYMid meet">';
    }

    private static function grid(int $w, int $h, int $padL, int $padT, float $innerH, float $max): string
    {
        $g = '';
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $innerH - $innerH * $i / 4;
            $g .= '<line x1="' . $padL . '" x2="' . ($w - 4) . '" y1="' . self::f($y) . '" y2="' . self::f($y) . '" class="ch-grid"/>';
            $g .= '<text x="' . ($padL - 6) . '" y="' . self::f($y + 4) . '" text-anchor="end" class="ch-l">' . self::num($max * $i / 4) . '</text>';
        }
        return $g;
    }

    private static function nice(float $max): float
    {
        if ($max <= 4) {
            return 4;
        }
        $pow = 10 ** floor(log10($max));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $pow >= $max) {
                return $m * $pow;
            }
        }
        return $max;
    }

    private static function shortLabel(string $k): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $k, $m)) {
            return $m[3] . '/' . $m[2];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $k, $m)) {
            return ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int) $m[2]] . ' ' . substr($m[1], 2);
        }
        return $k;
    }

    private static function num(int|float $v): string
    {
        return $v >= 1000 ? number_format($v, 0, ',', ' ') : (string) (round($v, 1) == (int) $v ? (int) $v : round($v, 1));
    }

    private static function f(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
