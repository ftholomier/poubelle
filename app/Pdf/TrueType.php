<?php
declare(strict_types=1);

namespace App\Pdf;

/**
 * Police TrueType pour l'export PDF : lecture des métriques (largeurs, hauteurs, correspondance
 * caractère → glyphe) et sous-ensemble embarqué dans le PDF (seuls les glyphes utilisés gardent
 * leur dessin : le fichier reste léger).
 */
final class TrueType
{
    public string $name;
    public int $unitsPerEm = 1000;
    public int $ascent = 800;
    public int $descent = -200;
    public int $capHeight = 700;
    public array $bbox = [0, -200, 1000, 800];
    public float $italicAngle = 0.0;
    public int $numGlyphs = 0;
    /** @var array<int,int> point de code → glyphe */
    public array $cmap = [];
    /** @var array<int,int> glyphe → avance (unités de la police) */
    public array $advances = [];
    /** @var array<int,int> glyphes utilisés → point de code (pour le copier-coller) */
    public array $used = [0 => 0];

    private string $data;
    /** @var array<string,array{0:int,1:int}> */
    private array $tables = [];
    private int $locFormat = 0;

    /** Remplacements des caractères absents de la police. */
    private const FALLBACK = [
        0x202F => 0x00A0, 0x2009 => 0x0020, 0x200A => 0x0020, 0x2007 => 0x0020, 0x2002 => 0x0020, 0x2003 => 0x0020,
        0x00A0 => 0x0020, 0x2011 => 0x002D, 0x2010 => 0x002D, 0x2212 => 0x002D, 0x2012 => 0x2013, 0x2015 => 0x2014,
        0x2032 => 0x2019, 0x2033 => 0x201D, 0x02BC => 0x2019, 0x0060 => 0x2018, 0x2027 => 0x00B7, 0x2219 => 0x00B7,
        0x2022 => 0x00B7, 0x25A0 => 0x00B7, 0x2192 => 0x003E, 0x2190 => 0x003C, 0x2713 => 0x0076, 0x00AD => -1,
        0x200B => -1, 0x200C => -1, 0x200D => -1, 0xFEFF => -1, 0x2028 => 0x0020, 0x2029 => 0x0020, 0x0085 => 0x0020,
    ];

    public function __construct(string $path)
    {
        $this->data = (string) file_get_contents($path);
        $n = $this->u16(4);
        for ($i = 0; $i < $n; $i++) {
            $p = 12 + 16 * $i;
            $this->tables[substr($this->data, $p, 4)] = [$this->u32($p + 8), $this->u32($p + 12)];
        }
        $head = $this->tables['head'][0];
        $this->unitsPerEm = $this->u16($head + 18);
        $this->bbox = [$this->i16($head + 36), $this->i16($head + 38), $this->i16($head + 40), $this->i16($head + 42)];
        $this->locFormat = $this->i16($head + 50);
        $hhea = $this->tables['hhea'][0];
        $this->ascent = $this->i16($hhea + 4);
        $this->descent = $this->i16($hhea + 6);
        $nh = $this->u16($hhea + 34);
        $this->numGlyphs = $this->u16($this->tables['maxp'][0] + 4);
        if (isset($this->tables['OS/2'])) {
            $os2 = $this->tables['OS/2'][0];
            $this->ascent = $this->i16($os2 + 68) ?: $this->ascent;
            $this->descent = $this->i16($os2 + 70) ?: $this->descent;
            if ($this->u16($os2) >= 2 && $this->tables['OS/2'][1] >= 90) {
                $this->capHeight = $this->i16($os2 + 88) ?: (int) ($this->ascent * 0.7);
            }
        }
        if (isset($this->tables['post'])) {
            $this->italicAngle = $this->i16($this->tables['post'][0] + 4) + $this->u16($this->tables['post'][0] + 6) / 65536;
        }
        $hmtx = $this->tables['hmtx'][0];
        $last = 0;
        for ($g = 0; $g < $this->numGlyphs; $g++) {
            if ($g < $nh) {
                $last = $this->u16($hmtx + 4 * $g);
            }
            $this->advances[$g] = $last;
        }
        $this->readCmap();
        $this->name = $this->readName() ?: pathinfo($path, PATHINFO_FILENAME);
    }

    /** @var array<int,int> point de code → glyphe retenu (-1 : caractère ignoré) */
    private array $resolved = [];
    /** @var array<int,int> point de code → caractère réellement imprimé (texte copiable du PDF) */
    private array $drawn = [];

    /** Glyphe d'un caractère, avec remplacement des caractères absents de la police. */
    private function gid(int $cp, string $ch): int
    {
        if (isset($this->resolved[$cp])) {
            return $this->resolved[$cp];
        }
        $c = $cp;
        $guard = 0;
        while (!isset($this->cmap[$c]) && isset(self::FALLBACK[$c]) && $guard++ < 4) {
            $c = self::FALLBACK[$c];
        }
        if ($c === -1 || ($cp < 32 && $cp !== 9)) {
            return $this->resolved[$cp] = -1;
        }
        if ($cp === 9) {
            $c = 0x20;
        }
        if (!isset($this->cmap[$c])) {
            // Absent de la police : lettre accentuée ou stylisée (« 𝗣 » des tweets, « ᵉ ») → la lettre
            // de base ; emoji, pictogramme, flèche → ignoré ; sinon un point d'interrogation.
            $c = 0x3F;
            if (class_exists(\Normalizer::class)) {
                $forms = class_exists(\IntlChar::class) && \IntlChar::isalpha($cp) ? [\Normalizer::FORM_D, \Normalizer::FORM_KD] : [\Normalizer::FORM_D];
                foreach ($forms as $form) {
                    $base = \Normalizer::normalize($ch, $form);
                    $b = is_string($base) && $base !== '' ? mb_ord(mb_substr($base, 0, 1, 'UTF-8'), 'UTF-8') : false;
                    if ($b !== false && $b !== $cp && isset($this->cmap[$b])) {
                        $c = $b;
                        break;
                    }
                }
            }
            if ($c === 0x3F && self::decorative($cp)) {
                return $this->resolved[$cp] = -1;
            }
        }
        $this->drawn[$cp] = $c;
        return $this->resolved[$cp] = $this->cmap[$c] ?? 0;
    }

    /** Emoji, pictogrammes, flèches, variantes d'affichage, drapeaux : rien à imprimer sans eux. */
    private static function decorative(int $cp): bool
    {
        if (($cp >= 0x2190 && $cp <= 0x21FF) || ($cp >= 0x2900 && $cp <= 0x297F) || ($cp >= 0x2B00 && $cp <= 0x2BFF)
            || ($cp >= 0xFE00 && $cp <= 0xFE0F) || ($cp >= 0x1F000 && $cp <= 0x1FAFF) || ($cp >= 0xE0000 && $cp <= 0xE007F)) {
            return true;
        }
        return class_exists(\IntlChar::class) && in_array(\IntlChar::charType($cp), [\IntlChar::CHAR_CATEGORY_OTHER_SYMBOL, \IntlChar::CHAR_CATEGORY_NON_SPACING_MARK, \IntlChar::CHAR_CATEGORY_ENCLOSING_MARK], true);
    }

    /** Glyphes d'un texte (UTF-8), en notant les glyphes employés. @return list<int> */
    public function glyphs(string $text): array
    {
        $out = [];
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false || ($g = $this->gid($cp, $ch)) < 0) {
                continue;
            }
            $this->used[$g] ??= $this->drawn[$cp] ?? $cp;
            $out[] = $g;
        }
        return $out;
    }

    /** Largeur d'un texte, en millièmes de corps. */
    public function width(string $text): float
    {
        $w = 0;
        foreach (mb_str_split($text, 1, 'UTF-8') as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp !== false && ($g = $this->gid($cp, $ch)) >= 0) {
                $w += $this->advances[$g] ?? 0;
            }
        }
        return $w * 1000 / $this->unitsPerEm;
    }

    public function glyphWidth(int $g): int
    {
        return (int) round(($this->advances[$g] ?? 0) * 1000 / $this->unitsPerEm);
    }

    /** Programme de police réduit aux glyphes employés (les autres sont vidés, les numéros restent). */
    public function subset(): string
    {
        $glyf = $this->tables['glyf'][0];
        $loca = $this->tables['loca'][0];
        $offset = fn (int $g) => $this->locFormat ? $this->u32($loca + 4 * $g) : 2 * $this->u16($loca + 2 * $g);
        // Glyphes composés : on garde aussi leurs composants.
        $keep = $this->used;
        $stack = array_keys($keep);
        while ($stack) {
            $g = array_pop($stack);
            $start = $offset($g);
            $len = $offset($g + 1) - $start;
            if ($len <= 0 || $this->i16($glyf + $start) >= 0) {
                continue;
            }
            $p = $glyf + $start + 10;
            do {
                $flags = $this->u16($p);
                $comp = $this->u16($p + 2);
                if (!isset($keep[$comp])) {
                    $keep[$comp] = 0;
                    $stack[] = $comp;
                }
                $p += 4 + (($flags & 0x0001) ? 4 : 2);
                $p += ($flags & 0x0008) ? 2 : (($flags & 0x0040) ? 4 : (($flags & 0x0080) ? 8 : 0));
            } while ($flags & 0x0020);
        }
        $newGlyf = '';
        $newLoca = '';
        for ($g = 0; $g < $this->numGlyphs; $g++) {
            $newLoca .= pack('N', strlen($newGlyf));
            if (isset($keep[$g])) {
                $start = $offset($g);
                $len = $offset($g + 1) - $start;
                if ($len > 0) {
                    $newGlyf .= substr($this->data, $glyf + $start, $len);
                    $newGlyf .= str_repeat("\0", (4 - strlen($newGlyf) % 4) % 4);
                }
            }
        }
        $newLoca .= pack('N', strlen($newGlyf));
        $head = $this->table('head');
        $head = substr_replace($head, "\0\0\0\0", 8, 4);
        $head = substr_replace($head, pack('n', 1), 50, 2);
        $tables = ['head' => $head, 'hhea' => $this->table('hhea'), 'maxp' => $this->table('maxp'), 'hmtx' => $this->table('hmtx'), 'loca' => $newLoca, 'glyf' => $newGlyf];
        foreach (['OS/2', 'cmap', 'post', 'name', 'cvt ', 'fpgm', 'prep'] as $t) {
            if (isset($this->tables[$t])) {
                $tables[$t] = $this->table($t);
            }
        }
        ksort($tables, SORT_STRING);
        $n = count($tables);
        $es = (int) floor(log($n, 2));
        $sr = 16 * (2 ** $es);
        $out = pack('Nnnnn', 0x00010000, $n, $sr, $es, $n * 16 - $sr);
        $pos = 12 + 16 * $n;
        $body = '';
        foreach ($tables as $tag => $t) {
            $out .= $tag . pack('NNN', self::checksum($t), $pos + strlen($body), strlen($t));
            $body .= $t . str_repeat("\0", (4 - strlen($t) % 4) % 4);
        }
        $font = $out . $body;
        // Somme de contrôle globale dans la table head
        $adj = (0xB1B0AFBA - self::checksum($font)) & 0xFFFFFFFF;
        $headPos = 12 + 16 * $n;
        foreach ($tables as $tag => $t) {
            if ($tag === 'head') {
                break;
            }
            $headPos += strlen($t) + (4 - strlen($t) % 4) % 4;
        }
        return substr_replace($font, pack('N', $adj), $headPos + 8, 4);
    }

    private static function checksum(string $s): int
    {
        $s .= str_repeat("\0", (4 - strlen($s) % 4) % 4);
        $sum = 0;
        foreach (unpack('N*', $s) ?: [] as $v) {
            $sum = ($sum + $v) & 0xFFFFFFFF;
        }
        return $sum;
    }

    private function table(string $tag): string
    {
        [$o, $l] = $this->tables[$tag];
        return substr($this->data, $o, $l);
    }

    private function readCmap(): void
    {
        $base = $this->tables['cmap'][0];
        $n = $this->u16($base + 2);
        $best = null;
        $bestScore = -1;
        for ($i = 0; $i < $n; $i++) {
            $pid = $this->u16($base + 4 + 8 * $i);
            $eid = $this->u16($base + 6 + 8 * $i);
            $off = $base + $this->u32($base + 8 + 8 * $i);
            $fmt = $this->u16($off);
            $score = match (true) {
                $pid === 3 && $eid === 10 && $fmt === 12 => 4,
                $pid === 0 && $fmt === 12 => 3,
                $pid === 3 && $eid === 1 && $fmt === 4 => 2,
                $pid === 0 && $fmt === 4 => 1,
                default => -1,
            };
            if ($score > $bestScore) {
                $best = $off;
                $bestScore = $score;
            }
        }
        if ($best === null) {
            return;
        }
        if ($this->u16($best) === 12) {
            $groups = $this->u32($best + 12);
            for ($i = 0; $i < $groups; $i++) {
                $p = $best + 16 + 12 * $i;
                [$s, $e, $g] = [$this->u32($p), $this->u32($p + 4), $this->u32($p + 8)];
                for ($c = $s; $c <= $e && $c < 0x30000; $c++) {
                    $this->cmap[$c] = $g + $c - $s;
                }
            }
            return;
        }
        $seg = $this->u16($best + 6) / 2;
        $ends = $best + 14;
        $starts = $ends + 2 * $seg + 2;
        $deltas = $starts + 2 * $seg;
        $ranges = $deltas + 2 * $seg;
        for ($i = 0; $i < $seg; $i++) {
            $end = $this->u16($ends + 2 * $i);
            $start = $this->u16($starts + 2 * $i);
            $delta = $this->i16($deltas + 2 * $i);
            $ro = $this->u16($ranges + 2 * $i);
            for ($c = $start; $c <= $end && $c !== 0xFFFF; $c++) {
                if ($ro === 0) {
                    $g = ($c + $delta) & 0xFFFF;
                } else {
                    $g = $this->u16($ranges + 2 * $i + $ro + 2 * ($c - $start));
                    $g = $g ? ($g + $delta) & 0xFFFF : 0;
                }
                if ($g) {
                    $this->cmap[$c] = $g;
                }
            }
        }
    }

    private function readName(): string
    {
        if (!isset($this->tables['name'])) {
            return '';
        }
        $base = $this->tables['name'][0];
        $n = $this->u16($base + 2);
        $strings = $base + $this->u16($base + 4);
        for ($i = 0; $i < $n; $i++) {
            $p = $base + 6 + 12 * $i;
            if ($this->u16($p + 6) === 6) {
                $raw = substr($this->data, $strings + $this->u16($p + 10), $this->u16($p + 8));
                $s = $this->u16($p) === 3 ? (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE') : $raw;
                return (string) preg_replace('/[^A-Za-z0-9\-]/', '', $s);
            }
        }
        return '';
    }

    private function u16(int $p): int
    {
        return unpack('n', $this->data, $p)[1];
    }

    private function i16(int $p): int
    {
        $v = $this->u16($p);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private function u32(int $p): int
    {
        return unpack('N', $this->data, $p)[1];
    }
}
