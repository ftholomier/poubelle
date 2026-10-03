<?php
declare(strict_types=1);

namespace App\Services;

/**
 * QR code (ISO/IEC 18004) sans bibliothèque : mode octet, correction d'erreurs M (15 %),
 * versions 1 à 10 (213 octets au plus), choix du meilleur masque. Sert aux documents
 * imprimés (kit souvenirs) : matrice de modules, dessin SVG.
 */
final class Qr
{
    /** Par version (1 à 10, niveau M) : [codewords de correction par bloc, [[blocs, données par bloc], …]]. */
    private const BLOCKS = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
    ];
    private const ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    private const REMAINDER = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0];

    private static array $exp = [];
    private static array $log = [];

    /**
     * Matrice du QR code de $text (true = module sombre), sans la marge.
     * @return list<list<bool>>
     */
    public static function matrix(string $text): array
    {
        $len = strlen($text);
        $version = 0;
        foreach (self::BLOCKS as $v => [$ec, $groups]) {
            $data = 0;
            foreach ($groups as [$n, $k]) {
                $data += $n * $k;
            }
            if ($len <= $data - 2 - ($v >= 10 ? 1 : 0)) {
                $version = $v;
                break;
            }
        }
        if (!$version) {
            throw new \InvalidArgumentException('Texte trop long pour le QR code (213 octets au plus).');
        }
        $codewords = self::codewords($text, $version);
        $size = 17 + 4 * $version;
        [$m, $fn] = self::functionPatterns($version, $size);
        self::placeData($m, $fn, $codewords, self::REMAINDER[$version], $size);
        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $t = self::applyMask($m, $fn, $mask, $size);
            self::drawFormat($t, $mask, $size);
            if ($version >= 7) {
                self::drawVersion($t, $version, $size);
            }
            $score = self::penalty($t, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $t;
            }
        }
        return $best;
    }

    /** Dessin SVG (carrés fusionnés par ligne), marge de 4 modules. */
    public static function svg(string $text, int $px = 4, string $dark = '#0E1F4D', string $light = '#FFFFFF'): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $w = ($n + 8) * $px;
        $path = '';
        foreach ($m as $y => $row) {
            for ($x = 0; $x < $n; $x++) {
                if ($row[$x]) {
                    $start = $x;
                    while ($x + 1 < $n && $row[$x + 1]) {
                        $x++;
                    }
                    $path .= 'M' . (($start + 4) * $px) . ' ' . (($y + 4) * $px) . 'h' . (($x - $start + 1) * $px) . 'v' . $px . 'h-' . (($x - $start + 1) * $px) . 'z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $w . '" width="' . $w . '" height="' . $w . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="' . $light . '"/><path d="' . $path . '" fill="' . $dark . '"/></svg>';
    }

    // ------------------------------------------------------------------ données et correction d'erreurs

    private static function codewords(string $text, int $version): array
    {
        [$ecLen, $groups] = self::BLOCKS[$version];
        $dataLen = 0;
        foreach ($groups as [$n, $k]) {
            $dataLen += $n * $k;
        }
        $bits = '0100' . str_pad(decbin(strlen($text)), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
        foreach (str_split($text) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $dataLen * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $data = array_map('bindec', str_split($bits, 8));
        for ($i = 0; count($data) < $dataLen; $i++) {
            $data[] = $i % 2 ? 0x11 : 0xEC;
        }
        // Blocs, correction d'erreurs, entrelacement.
        $blocks = [];
        $pos = 0;
        foreach ($groups as [$n, $k]) {
            for ($b = 0; $b < $n; $b++) {
                $d = array_slice($data, $pos, $k);
                $pos += $k;
                $blocks[] = [$d, self::rs($d, $ecLen)];
            }
        }
        $out = [];
        $maxData = max(array_map(fn ($b) => count($b[0]), $blocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($blocks as [$d]) {
                if (isset($d[$i])) {
                    $out[] = $d[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($blocks as [, $e]) {
                $out[] = $e[$i];
            }
        }
        return $out;
    }

    /** Reed-Solomon sur GF(256) (polynôme 0x11D) : $n octets de correction pour $data. */
    private static function rs(array $data, int $n): array
    {
        if (!self::$exp) {
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                self::$exp[$i] = $x;
                self::$log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                self::$exp[$i] = self::$exp[$i - 255];
            }
        }
        // Polynôme générateur : produit des (x - α^i), i = 0 … n-1.
        $gen = [1];
        for ($i = 0; $i < $n; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $c) {
                $next[$j] ^= $c;
                $next[$j + 1] ^= $c ? self::$exp[self::$log[$c] + $i] : 0;
            }
            $gen = $next;
        }
        $res = array_fill(0, $n, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $res[0];
            array_shift($res);
            $res[] = 0;
            if ($factor) {
                for ($j = 0; $j < $n; $j++) {
                    if ($gen[$j + 1]) {
                        $res[$j] ^= self::$exp[self::$log[$gen[$j + 1]] + self::$log[$factor]];
                    }
                }
            }
        }
        return $res;
    }

    // ------------------------------------------------------------------ matrice

    /** Motifs fixes (repérage, synchronisation, alignement, module sombre, zones réservées). */
    private static function functionPatterns(int $version, int $size): array
    {
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $fn = array_fill(0, $size, array_fill(0, $size, false));
        $set = function (int $y, int $x, bool $dark) use (&$m, &$fn, $size) {
            if ($y >= 0 && $x >= 0 && $y < $size && $x < $size) {
                $m[$y][$x] = $dark;
                $fn[$y][$x] = true;
            }
        };
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$oy, $ox]) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $in = $dy >= 0 && $dy <= 6 && $dx >= 0 && $dx <= 6;
                    $ring = $in && ($dy === 0 || $dy === 6 || $dx === 0 || $dx === 6);
                    $core = $dy >= 2 && $dy <= 4 && $dx >= 2 && $dx <= 4;
                    $set($oy + $dy, $ox + $dx, $ring || $core);
                }
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        $pos = self::ALIGN[$version];
        foreach ($pos as $cy) {
            foreach ($pos as $cx) {
                if (($cy === 6 && $cx === 6) || ($cy === 6 && $cx === $size - 7) || ($cy === $size - 7 && $cx === 6)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $set($cy + $dy, $cx + $dx, max(abs($dy), abs($dx)) !== 1);
                    }
                }
            }
        }
        $set(4 * $version + 9, 8, true);
        // Zones du format (et de la version à partir de la 7) : réservées, remplies après le masque.
        for ($i = 0; $i < 9; $i++) {
            if (!$fn[8][$i]) {
                $set(8, $i, false);
            }
            if (!$fn[$i][8]) {
                $set($i, 8, false);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $set(8, $size - 1 - $i, false);
            if (!$fn[$size - 1 - $i][8]) {
                $set($size - 1 - $i, 8, false);
            }
        }
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $set($i, $size - 11 + $j, false);
                    $set($size - 11 + $j, $i, false);
                }
            }
        }
        return [$m, $fn];
    }

    /** Placement en zigzag des codewords, de bas en haut par colonnes de deux. */
    private static function placeData(array &$m, array $fn, array $codewords, int $remainder, int $size): void
    {
        $bits = '';
        foreach ($codewords as $c) {
            $bits .= str_pad(decbin($c), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', $remainder);
        $i = 0;
        $up = true;
        for ($x = $size - 1; $x >= 1; $x -= 2) {
            if ($x === 6) {
                $x = 5;
            }
            for ($k = 0; $k < $size; $k++) {
                $y = $up ? $size - 1 - $k : $k;
                for ($dx = 0; $dx < 2; $dx++) {
                    $xx = $x - $dx;
                    if (!$fn[$y][$xx]) {
                        $m[$y][$xx] = isset($bits[$i]) && $bits[$i] === '1';
                        $i++;
                    }
                }
            }
            $up = !$up;
        }
    }

    private static function applyMask(array $m, array $fn, int $mask, int $size): array
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($fn[$y][$x]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($y + $x) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($y + $x) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
                    6 => (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0,
                    default => (($y + $x) % 2 + ($y * $x) % 3) % 2 === 0,
                };
                if ($flip) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
        return $m;
    }

    /** Format (niveau M = 00, masque), BCH(15,5), deux copies. */
    private static function drawFormat(array &$m, int $mask, int $size): void
    {
        $data = (0b00 << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | ($rem & 0x3FF)) ^ 0x5412;
        $bit = fn (int $i) => (($bits >> $i) & 1) === 1;
        for ($i = 0; $i <= 5; $i++) {
            $m[$i][8] = $bit($i);
        }
        $m[7][8] = $bit(6);
        $m[8][8] = $bit(7);
        $m[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) {
            $m[8][14 - $i] = $bit($i);
        }
        for ($i = 0; $i < 8; $i++) {
            $m[8][$size - 1 - $i] = $bit($i);
        }
        for ($i = 8; $i < 15; $i++) {
            $m[$size - 15 + $i][8] = $bit($i);
        }
        $m[$size - 8][8] = true;
    }

    /** Version (7 et plus), BCH(18,6), deux blocs 6 × 3. */
    private static function drawVersion(array &$m, int $version, int $size): void
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        $bits = ($version << 12) | ($rem & 0xFFF);
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $m[$b][$a] = $dark;
            $m[$a][$b] = $dark;
        }
    }

    /** Pénalité (règles N1 à N4 de la norme) : le masque le plus lisible l'emporte. */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        $dark = 0;
        for ($pass = 0; $pass < 2; $pass++) {
            for ($i = 0; $i < $size; $i++) {
                $run = 1;
                $line = [];
                for ($j = 0; $j < $size; $j++) {
                    $line[] = $pass ? $m[$j][$i] : $m[$i][$j];
                }
                for ($j = 1; $j < $size; $j++) {
                    if ($line[$j] === $line[$j - 1]) {
                        $run++;
                        if ($j === $size - 1 && $run >= 5) {
                            $score += $run - 2;
                        }
                    } else {
                        if ($run >= 5) {
                            $score += $run - 2;
                        }
                        $run = 1;
                    }
                }
                $s = implode('', array_map(fn ($b) => $b ? '1' : '0', $line));
                $score += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
            }
        }
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $dark += $m[$y][$x] ? 1 : 0;
                if ($y < $size - 1 && $x < $size - 1 && $m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $score += intdiv(abs($dark * 20 - $size * $size * 10), $size * $size) * 10;
        return $score;
    }
}
