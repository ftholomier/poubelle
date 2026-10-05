<?php
declare(strict_types=1);

namespace App\Shop;

use App\Pdf\TrueType;
use App\Pdf\Writer;

/**
 * Moteur de dessin de la boutique : une face d'un produit (dimensions en millimètres) et ses
 * calques — logo de l'association, textes, rectangles, ellipses — deviennent des formes
 * vectorielles. Les textes sont convertis en tracés (contours des lettres des polices du site) :
 * le même dessin sert à l'aperçu (SVG) et au fichier de l'imprimeur (PDF vectoriel, au format
 * exact, avec fonds perdus, traits de coupe et couleurs CMJN), sans police à fournir ni photo.
 *
 * Calque (tableau) : type (logo, text, rect, ellipse), x, y, w, h (mm, depuis le coin haut gauche
 * du format fini) ; logo : style (couleurs, mono), color ; texte : text, font, size (points),
 * color, align (left, center, right), upper, spacing (millièmes de cadratin), lh (interligne),
 * fit (réduit pour tenir sur la largeur), mode (fixed, client), field, label, max ; formes :
 * fill, stroke, sw (épaisseur en mm), r (arrondi).
 */
final class Vector
{
    public const FONTS = [
        'display' => ['BigShouldersDisplay-Black.ttf', 'Big Shoulders Black'],
        'display-b' => ['BigShouldersDisplay-ExtraBold.ttf', 'Big Shoulders ExtraBold'],
        'serif' => ['Newsreader-Regular.ttf', 'Newsreader'],
        'serif-b' => ['Newsreader-SemiBold.ttf', 'Newsreader SemiBold'],
        'serif-i' => ['Newsreader-Italic.ttf', 'Newsreader Italique'],
    ];
    /** Couleurs de la charte (nom => hexadécimal), avec leur équivalent CMJN pour l'impression. */
    public const PALETTE = [
        'Bleu nuit' => '#0E1F4D', 'Bleu blason' => '#094687', 'Bleu roi' => '#1E3FA8', 'Jaune' => '#F6C400',
        'Jaune blason' => '#FDC729', 'Crème' => '#F3EDDF', 'Blanc' => '#FFFFFF', 'Noir' => '#111111', 'Rouge' => '#B3261E', 'Gris' => '#8A8F9C',
    ];
    private const CMYK = [
        '#0E1F4D' => [1, .85, .3, .45], '#094687' => [1, .7, .1, .1], '#1E3FA8' => [.95, .75, 0, 0], '#F6C400' => [0, .2, 1, 0],
        '#FDC729' => [0, .2, .9, 0], '#F3EDDF' => [.03, .05, .12, 0], '#FFFFFF' => [0, 0, 0, 0], '#111111' => [0, 0, 0, 1],
        '#000000' => [0, 0, 0, 1], '#B3261E' => [.15, 1, 1, .1], '#8A8F9C' => [.45, .35, .25, .1],
    ];
    public const LOGO = APP_DIR . '/Resources/shop/logo-sochaux-retro.svg';
    /** Rapport hauteur / largeur du logo. */
    public const LOGO_RATIO = 1407 / 1242;
    private const MM = 72 / 25.4;

    /** @var array<string,TrueType> */
    private static array $fonts = [];
    private static ?array $logo = null;

    public static function font(string $key): TrueType
    {
        $key = isset(self::FONTS[$key]) ? $key : 'display';
        return self::$fonts[$key] ??= new TrueType(APP_DIR . '/Resources/fonts/' . self::FONTS[$key][0]);
    }

    public static function hex(?string $c, string $default = '#0E1F4D'): string
    {
        $c = strtoupper(trim((string) $c));
        return preg_match('/^#[0-9A-F]{6}$/', $c) ? $c : $default;
    }

    // ------------------------------------------------------------------ calques → formes

    /**
     * Formes d'une face : [d (commandes en mm), fill, stroke, sw, rule, layer].
     * $values : champs remplis par le client (field => texte).
     * @return list<array>
     */
    public static function shapes(array $layers, array $values = []): array
    {
        $out = [];
        foreach ($layers as $i => $l) {
            $type = (string) ($l['type'] ?? '');
            $x = (float) ($l['x'] ?? 0);
            $y = (float) ($l['y'] ?? 0);
            $w = max(1.0, (float) ($l['w'] ?? 10));
            $h = max(0.2, (float) ($l['h'] ?? 10));
            $id = (string) ($l['id'] ?? $i);
            if ($type === 'logo') {
                $s = $w / 1242;
                $paths = self::logo();
                $mono = ($l['style'] ?? 'couleurs') === 'mono';
                foreach ($paths as $k => [$fill, $d]) {
                    if ($mono && $k === 0) {
                        continue; // en une couleur : seulement le blason, le lion et les lettres
                    }
                    $out[] = ['d' => self::transform($d, $s, $x, $y), 'fill' => $mono ? self::hex($l['color'] ?? null, '#FDC729') : $fill, 'stroke' => null, 'sw' => 0, 'rule' => 'evenodd', 'layer' => $id];
                }
            } elseif ($type === 'text') {
                foreach (self::text($l, $values) as $sh) {
                    $out[] = $sh + ['layer' => $id];
                }
            } elseif ($type === 'rect' || $type === 'ellipse') {
                $d = $type === 'rect' ? self::rect($x, $y, $w, $h, (float) ($l['r'] ?? 0)) : self::ellipse($x + $w / 2, $y + $h / 2, $w / 2, $h / 2);
                $fill = ($l['fill'] ?? '') !== '' ? self::hex($l['fill']) : null;
                $stroke = ($l['stroke'] ?? '') !== '' && (float) ($l['sw'] ?? 0) > 0 ? self::hex($l['stroke']) : null;
                if ($fill || $stroke) {
                    $out[] = ['d' => $d, 'fill' => $fill, 'stroke' => $stroke, 'sw' => (float) ($l['sw'] ?? 0), 'rule' => 'nonzero', 'layer' => $id];
                }
            }
        }
        return $out;
    }

    /** Texte d'un calque après remplacement des champs du client. */
    public static function textOf(array $l, array $values = []): string
    {
        $t = (string) ($l['text'] ?? '');
        if (($l['mode'] ?? 'fixed') === 'client') {
            $f = (string) ($l['field'] ?? '');
            $v = trim((string) ($values[$f] ?? ''));
            $t = $v !== '' ? mb_substr($v, 0, max(1, (int) ($l['max'] ?? 40))) : $t;
        }
        return !empty($l['upper']) ? mb_strtoupper($t) : $t;
    }

    /** Lignes d'un texte (retour à la ligne sur la largeur du calque) et corps final. */
    public static function layout(array $l, array $values = []): array
    {
        $font = self::font((string) ($l['font'] ?? 'display'));
        $size = max(2.0, min(400.0, (float) ($l['size'] ?? 24)));
        $w = max(1.0, (float) ($l['w'] ?? 100));
        $sp = (float) ($l['spacing'] ?? 0) / 1000;
        $text = self::textOf($l, $values);
        $measure = fn (string $s, float $pt): float => ($font->width($s) / 1000 + $sp * max(0, mb_strlen($s) - 1)) * $pt * 25.4 / 72;
        if (!empty($l['fit'])) {
            $longest = max(array_map(fn ($line) => $measure($line, 1), explode("\n", $text)) ?: [0]);
            if ($longest > 0 && $longest * $size > $w) {
                $size = max(2.0, $w / $longest);
            }
        }
        $lines = [];
        foreach (explode("\n", $text) as $para) {
            $cur = '';
            foreach (preg_split('/ +/u', $para) ?: [] as $word) {
                $try = $cur === '' ? $word : $cur . ' ' . $word;
                if ($cur !== '' && $measure($try, $size) > $w) {
                    $lines[] = $cur;
                    $cur = $word;
                } else {
                    $cur = $try;
                }
            }
            $lines[] = $cur;
        }
        return ['lines' => $lines, 'size' => $size, 'width' => fn (string $s): float => $measure($s, $size)];
    }

    /** Hauteur occupée par un calque de texte (mm). */
    public static function textHeight(array $l, array $values = []): float
    {
        $lay = self::layout($l, $values);
        $mm = $lay['size'] * 25.4 / 72;
        return count($lay['lines']) * $mm * (float) ($l['lh'] ?? 1.1);
    }

    private static function text(array $l, array $values): array
    {
        $lay = self::layout($l, $values);
        $font = self::font((string) ($l['font'] ?? 'display'));
        $mm = $lay['size'] * 25.4 / 72;
        $k = $mm / $font->unitsPerEm;
        $sp = (float) ($l['spacing'] ?? 0) / 1000 * $mm;
        $lh = $mm * (float) ($l['lh'] ?? 1.1);
        $x0 = (float) ($l['x'] ?? 0);
        $w = (float) ($l['w'] ?? 100);
        // Première ligne de base : la hauteur des capitales sous le haut du calque, plus un peu d'air.
        $base = (float) ($l['y'] ?? 0) + ($lh - $mm) / 2 + $font->ascent * $k * 0.92;
        $align = (string) ($l['align'] ?? 'left');
        $cmds = [];
        foreach ($lay['lines'] as $n => $line) {
            $lw = ($lay['width'])($line);
            $pen = $x0 + ($align === 'center' ? ($w - $lw) / 2 : ($align === 'right' ? $w - $lw : 0));
            $by = $base + $n * $lh;
            foreach (mb_str_split($line) as $ch) {
                $g = $font->glyphOf($ch);
                if ($g < 0) {
                    continue;
                }
                foreach ($font->outline($g) as $c) {
                    $t = $c[0];
                    $pts = [];
                    for ($i = 1; $i + 1 < count($c); $i += 2) {
                        $pts[] = $pen + $c[$i] * $k;
                        $pts[] = $by - $c[$i + 1] * $k;
                    }
                    $cmds[] = array_merge([$t], $pts);
                }
                $pen += $font->glyphWidth($g) * $mm / 1000 + $sp;
            }
        }
        return $cmds ? [['d' => $cmds, 'fill' => self::hex($l['color'] ?? null), 'stroke' => null, 'sw' => 0, 'rule' => 'nonzero']] : [];
    }

    /** Logo vectorisé : [[couleur, commandes en unités du dessin]] (fond bleu, puis parties jaunes). */
    private static function logo(): array
    {
        if (self::$logo !== null) {
            return self::$logo;
        }
        $svg = (string) @file_get_contents(self::LOGO);
        preg_match_all('/<path fill="(#[0-9A-Fa-f]{6})"[^>]* d="([^"]+)"/', $svg, $m, PREG_SET_ORDER);
        self::$logo = [];
        foreach ($m as $p) {
            self::$logo[] = [strtoupper($p[1]), self::parse($p[2])];
        }
        return self::$logo;
    }

    /** Tracé SVG simple (M, L, C, Q, Z absolus) → commandes. */
    public static function parse(string $d): array
    {
        preg_match_all('/([MLCQZ])([^MLCQZ]*)/i', $d, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $c) {
            $n = array_map('floatval', preg_split('/[\s,]+/', trim($c[2]), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $t = strtoupper($c[1]);
            $per = ['M' => 2, 'L' => 2, 'C' => 6, 'Q' => 4, 'Z' => 0][$t];
            if ($per === 0) {
                $out[] = ['Z'];
                continue;
            }
            foreach (array_chunk($n, $per) as $k => $chunk) {
                if (count($chunk) === $per) {
                    $out[] = array_merge([$t === 'M' && $k > 0 ? 'L' : $t], $chunk);
                }
            }
        }
        return $out;
    }

    private static function transform(array $cmds, float $s, float $dx, float $dy): array
    {
        foreach ($cmds as &$c) {
            for ($i = 1; $i < count($c); $i += 2) {
                $c[$i] = $c[$i] * $s + $dx;
                $c[$i + 1] = $c[$i + 1] * $s + $dy;
            }
        }
        return $cmds;
    }

    private static function rect(float $x, float $y, float $w, float $h, float $r): array
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        if ($r <= 0) {
            return [['M', $x, $y], ['L', $x + $w, $y], ['L', $x + $w, $y + $h], ['L', $x, $y + $h], ['Z']];
        }
        $c = $r * 0.5523;
        return [
            ['M', $x + $r, $y], ['L', $x + $w - $r, $y], ['C', $x + $w - $r + $c, $y, $x + $w, $y + $r - $c, $x + $w, $y + $r],
            ['L', $x + $w, $y + $h - $r], ['C', $x + $w, $y + $h - $r + $c, $x + $w - $r + $c, $y + $h, $x + $w - $r, $y + $h],
            ['L', $x + $r, $y + $h], ['C', $x + $r - $c, $y + $h, $x, $y + $h - $r + $c, $x, $y + $h - $r],
            ['L', $x, $y + $r], ['C', $x, $y + $r - $c, $x + $r - $c, $y, $x + $r, $y], ['Z'],
        ];
    }

    private static function ellipse(float $cx, float $cy, float $rx, float $ry): array
    {
        [$a, $b] = [$rx * 0.5523, $ry * 0.5523];
        return [
            ['M', $cx + $rx, $cy], ['C', $cx + $rx, $cy + $b, $cx + $a, $cy + $ry, $cx, $cy + $ry],
            ['C', $cx - $a, $cy + $ry, $cx - $rx, $cy + $b, $cx - $rx, $cy], ['C', $cx - $rx, $cy - $b, $cx - $a, $cy - $ry, $cx, $cy - $ry],
            ['C', $cx + $a, $cy - $ry, $cx + $rx, $cy - $b, $cx + $rx, $cy], ['Z'],
        ];
    }

    /** Boîte englobante d'une forme (mm) : [x1, y1, x2, y2]. */
    public static function bbox(array $cmds): ?array
    {
        $b = null;
        foreach ($cmds as $c) {
            for ($i = 1; $i + 1 < count($c); $i += 2) {
                $b = $b ? [min($b[0], $c[$i]), min($b[1], $c[$i + 1]), max($b[2], $c[$i]), max($b[3], $c[$i + 1])] : [$c[$i], $c[$i + 1], $c[$i], $c[$i + 1]];
            }
        }
        return $b;
    }

    // ------------------------------------------------------------------ SVG

    public static function pathData(array $cmds): string
    {
        $f = fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        $s = '';
        foreach ($cmds as $c) {
            $s .= $c[0];
            for ($i = 1; $i < count($c); $i++) {
                $s .= ($i > 1 ? ' ' : '') . $f((float) $c[$i]);
            }
        }
        return $s;
    }

    /** Contenu SVG (sans balise <svg>) des formes, en millimètres. */
    public static function svgShapes(array $shapes, ?string $bg = null, array $box = [0, 0, 0, 0]): string
    {
        $s = $bg ? '<rect x="' . $box[0] . '" y="' . $box[1] . '" width="' . $box[2] . '" height="' . $box[3] . '" fill="' . self::hex($bg) . '"/>' : '';
        foreach ($shapes as $sh) {
            $s .= '<path d="' . self::pathData($sh['d']) . '" fill="' . ($sh['fill'] ?: 'none') . '"'
                . ($sh['rule'] === 'evenodd' ? ' fill-rule="evenodd"' : '')
                . ($sh['stroke'] ? ' stroke="' . $sh['stroke'] . '" stroke-width="' . round($sh['sw'], 2) . '"' : '')
                . ' data-layer="' . htmlspecialchars((string) $sh['layer'], ENT_QUOTES) . '"/>';
        }
        return $s;
    }

    /** SVG autonome d'une face (format fini, fonds perdus visibles si demandés). */
    public static function svg(array $side, array $values = [], bool $bleed = false): string
    {
        [$w, $h] = [(float) $side['w'], (float) $side['h']];
        $b = $bleed ? (float) ($side['bleed'] ?? 0) : 0;
        $inner = self::svgShapes(self::shapes($side['layers'] ?? [], $values), $side['bg'] ?? null, [-$b, -$b, $w + 2 * $b, $h + 2 * $b]);
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . (-$b) . ' ' . (-$b) . ' ' . ($w + 2 * $b) . ' ' . ($h + 2 * $b) . '" width="' . ($w + 2 * $b) . 'mm" height="' . ($h + 2 * $b) . 'mm">' . $inner . '</svg>';
    }

    // ------------------------------------------------------------------ PDF pour l'imprimeur

    /**
     * PDF vectoriel, une page par face : format fini + fonds perdus, traits de coupe et repères
     * dans la marge (10 mm), couleurs en CMJN (ou RVB). $faces : [name, side, values].
     * @param list<array{name:string,side:array,values?:array}> $faces
     */
    public static function pdf(array $faces, array $o = []): string
    {
        $pdf = new Writer();
        $cmyk = $o['cmyk'] ?? true;
        $marks = $o['marks'] ?? true;
        $pdf->info = ['Title' => (string) ($o['title'] ?? 'Sochaux Rétro'), 'Author' => 'Sochaux Rétro', 'Subject' => 'Fichier d’impression', 'Creator' => 'Sochaux Rétro · boutique'];
        foreach ($faces as $face) {
            $side = $face['side'];
            [$w, $h, $b] = [(float) $side['w'], (float) $side['h'], (float) ($side['bleed'] ?? 0)];
            $m = $marks ? 10.0 : 0.0;
            $pw = ($w + 2 * $b + 2 * $m) * self::MM;
            $ph = ($h + 2 * $b + 2 * $m) * self::MM;
            $page = $pdf->addPage($pw, $ph);
            $ox = $m + $b;
            $oy = $m + $b;
            $X = fn (float $x): float => ($x + $ox) * self::MM;
            $Y = fn (float $y): float => $ph - ($y + $oy) * self::MM;
            $col = function (string $hex, bool $fill) use ($cmyk): string {
                if ($cmyk) {
                    [$c, $mm, $y, $k] = self::toCmyk($hex);
                    return sprintf('%.3F %.3F %.3F %.3F %s', $c, $mm, $y, $k, $fill ? 'k' : 'K');
                }
                [$r, $g, $bb] = sscanf($hex, '#%02x%02x%02x');
                return sprintf('%.3F %.3F %.3F %s', $r / 255, $g / 255, $bb / 255, $fill ? 'rg' : 'RG');
            };
            $ops = '';
            // Trim box/Bleed box pour les logiciels de l'imprimeur.
            if (!empty($side['bg'])) {
                $ops .= 'q ' . $col(self::hex($side['bg']), true) . sprintf(' %.2F %.2F %.2F %.2F re f Q', $X(-$b), $Y($h + $b), ($w + 2 * $b) * self::MM, ($h + 2 * $b) * self::MM) . "\n";
            }
            // Le dessin ne déborde jamais au-delà des fonds perdus.
            $ops .= sprintf('q %.2F %.2F %.2F %.2F re W n', $X(-$b), $Y($h + $b), ($w + 2 * $b) * self::MM, ($h + 2 * $b) * self::MM) . "\n";
            foreach (self::shapes($side['layers'] ?? [], $face['values'] ?? []) as $sh) {
                $path = '';
                $cx = $cy = 0.0;
                foreach ($sh['d'] as $c) {
                    switch ($c[0]) {
                        case 'M':
                            $path .= sprintf('%.2F %.2F m ', $X($c[1]), $Y($c[2]));
                            [$cx, $cy] = [$c[1], $c[2]];
                            break;
                        case 'L':
                            $path .= sprintf('%.2F %.2F l ', $X($c[1]), $Y($c[2]));
                            [$cx, $cy] = [$c[1], $c[2]];
                            break;
                        case 'C':
                            $path .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $X($c[1]), $Y($c[2]), $X($c[3]), $Y($c[4]), $X($c[5]), $Y($c[6]));
                            [$cx, $cy] = [$c[5], $c[6]];
                            break;
                        case 'Q':
                            // Courbe quadratique → cubique (PDF).
                            [$qx, $qy, $ex, $ey] = [$c[1], $c[2], $c[3], $c[4]];
                            $path .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $X($cx + 2 / 3 * ($qx - $cx)), $Y($cy + 2 / 3 * ($qy - $cy)), $X($ex + 2 / 3 * ($qx - $ex)), $Y($ey + 2 / 3 * ($qy - $ey)), $X($ex), $Y($ey));
                            [$cx, $cy] = [$ex, $ey];
                            break;
                        case 'Z':
                            $path .= 'h ';
                    }
                }
                $paint = $sh['fill'] && $sh['stroke'] ? ($sh['rule'] === 'evenodd' ? 'B*' : 'B') : ($sh['fill'] ? ($sh['rule'] === 'evenodd' ? 'f*' : 'f') : 'S');
                $ops .= 'q ' . ($sh['fill'] ? $col($sh['fill'], true) . ' ' : '') . ($sh['stroke'] ? $col($sh['stroke'], false) . sprintf(' %.3F w ', $sh['sw'] * self::MM) : '') . $path . $paint . " Q\n";
            }
            $ops .= "Q\n";
            if ($marks) {
                // Traits de coupe aux quatre coins (hors fonds perdus), en noir de repérage.
                $ops .= 'q 0.25 w ' . ($cmyk ? '1 1 1 1 K' : '0 0 0 RG') . "\n";
                foreach ([[0, 0], [$w, 0], [0, $h], [$w, $h]] as [$cx, $cy]) {
                    $sx = $cx === 0 ? -1 : 1;
                    $sy = $cy === 0 ? -1 : 1;
                    $ops .= sprintf('%.2F %.2F m %.2F %.2F l S ', $X($cx + $sx * ($b + 2)), $Y($cy), $X($cx + $sx * ($b + 9)), $Y($cy));
                    $ops .= sprintf('%.2F %.2F m %.2F %.2F l S ', $X($cx), $Y($cy + $sy * ($b + 2)), $X($cx), $Y($cy + $sy * ($b + 9)));
                }
                $ops .= "Q\n";
            }
            $pdf->write($page, $ops);
            $pdf->boxes($page, [$X(0), $Y($h), $X($w), $Y(0)], [$X(-$b), $Y($h + $b), $X($w + $b), $Y(-$b)]);
            if ($marks && ($face['name'] ?? '') !== '') {
                // Repère dans la marge (hors format) : produit, face, couleur, commande.
                $tt = $pdf->font('repere', APP_DIR . '/Resources/fonts/' . self::FONTS['display-b'][0]);
                $hexs = '';
                foreach ($tt->glyphs((string) $face['name']) as $g) {
                    $hexs .= sprintf('%04X', $g);
                }
                $pdf->write($page, sprintf('BT /%s 7 Tf %s %.2F %.2F Td <%s> Tj ET', $pdf->fontRes('repere'), $cmyk ? '0 0 0 1 k' : '0 0 0 rg', ($m + $b + 12) * self::MM, 3.5 * self::MM, $hexs));
            }
        }
        return $pdf->output();
    }

    /** CMJN d'une couleur : valeur de la charte, sinon conversion simple. @return array{0:float,1:float,2:float,3:float} */
    public static function toCmyk(string $hex): array
    {
        $hex = self::hex($hex);
        if (isset(self::CMYK[$hex])) {
            return self::CMYK[$hex];
        }
        [$r, $g, $b] = array_map(fn ($v) => $v / 255, sscanf($hex, '#%02x%02x%02x'));
        $k = 1 - max($r, $g, $b);
        if ($k >= 1) {
            return [0, 0, 0, 1];
        }
        return [(1 - $r - $k) / (1 - $k), (1 - $g - $k) / (1 - $k), (1 - $b - $k) / (1 - $k), $k];
    }
}
