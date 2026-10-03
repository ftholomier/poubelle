<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Générateur PDF minimal (sans bibliothèque) : pages A4, polices standard Helvetica
 * (encodage WinAnsi, accents français), texte, texte justifié par lignes, traits,
 * rectangles, cases à cocher et images JPEG. Sert aux reçus fiscaux des dons.
 * Coordonnées en points, origine en haut à gauche (converties pour PDF).
 */
final class Pdf
{
    public const W = 595.28;
    public const H = 841.89;

    private array $pages = [];
    private int $cur = -1;
    private array $images = [];

    private const FONTS = ['R' => 'Helvetica', 'B' => 'Helvetica-Bold', 'I' => 'Helvetica-Oblique'];

    /** Chasses Helvetica / Helvetica-Bold (AFM, millièmes d'em) pour les caractères ASCII 32 à 126. */
    private const WR = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
    private const WB = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];
    private const WX = ['€' => 556, '«' => 556, '»' => 556, '’' => 222, '‘' => 222, '“' => 333, '”' => 333, '–' => 556, '—' => 1000, '…' => 1000, 'œ' => 944, 'Œ' => 1000, '°' => 400, "\u{a0}" => 278, '·' => 278, '×' => 584, 'ß' => 611];

    public function addPage(): self
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
        return $this;
    }

    public function text(float $x, float $y, string $s, float $size = 10, string $font = 'R', array $rgb = [0.055, 0.122, 0.302]): self
    {
        $this->out(sprintf('BT %s rg /F%s %.2F Tf %.2F %.2F Td (%s) Tj ET', self::rgb($rgb), $font, $size, $x, self::H - $y, self::esc($s)));
        return $this;
    }

    /** Texte aligné : 'L', 'C' (centré sur $x) ou 'R' (aligné à droite sur $x). */
    public function textAlign(float $x, float $y, string $s, float $size, string $font, string $align, array $rgb = [0.055, 0.122, 0.302]): self
    {
        $w = self::width($s, $size, $font);
        return $this->text($align === 'C' ? $x - $w / 2 : ($align === 'R' ? $x - $w : $x), $y, $s, $size, $font, $rgb);
    }

    /** Paragraphe avec retour à la ligne automatique. Renvoie l'ordonnée sous le dernier ligne. */
    public function paragraph(float $x, float $y, float $width, string $s, float $size = 10, string $font = 'R', float $leading = 1.35, array $rgb = [0.055, 0.122, 0.302]): float
    {
        foreach (preg_split('/\R/u', $s) as $para) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($para)) as $word) {
                $try = $line === '' ? $word : "$line $word";
                if ($line !== '' && self::width($try, $size, $font) > $width) {
                    $this->text($x, $y, $line, $size, $font, $rgb);
                    $y += $size * $leading;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            if ($line !== '') {
                $this->text($x, $y, $line, $size, $font, $rgb);
            }
            $y += $size * $leading;
        }
        return $y;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.8, array $rgb = [0.055, 0.122, 0.302]): self
    {
        $this->out(sprintf('%s RG %.2F w %.2F %.2F m %.2F %.2F l S', self::rgb($rgb), $w, $x1, self::H - $y1, $x2, self::H - $y2));
        return $this;
    }

    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = [0.055, 0.122, 0.302], float $lw = 0.8): self
    {
        $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $this->out(trim(($fill ? self::rgb($fill) . ' rg ' : '') . ($stroke ? self::rgb($stroke) . ' RG ' : '') . sprintf('%.2F w %.2F %.2F %.2F %.2F re %s', $lw, $x, self::H - $y - $h, $w, $h, $op)));
        return $this;
    }

    /** Case à cocher (carré de $s points, croix si cochée). */
    public function checkbox(float $x, float $y, bool $checked, float $s = 9): self
    {
        $this->rect($x, $y, $s, $s);
        if ($checked) {
            $this->line($x + 1.6, $y + 1.6, $x + $s - 1.6, $y + $s - 1.6, 1.2)->line($x + $s - 1.6, $y + 1.6, $x + 1.6, $y + $s - 1.6, 1.2);
        }
        return $this;
    }

    /** Image JPEG (données binaires) placée en ($x, $y) sur $w × $h points. */
    public function jpeg(string $data, float $x, float $y, float $w, float $h): self
    {
        $info = @getimagesizefromstring($data);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) {
            return $this;
        }
        $key = 'Im' . (count($this->images) + 1);
        $this->images[$key] = ['data' => $data, 'w' => $info[0], 'h' => $info[1], 'cs' => ($info['channels'] ?? 3) === 1 ? '/DeviceGray' : '/DeviceRGB'];
        $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, self::H - $y - $h, $key));
        return $this;
    }

    /** Largeur d'un texte en points. */
    public static function width(string $s, float $size, string $font = 'R'): float
    {
        $table = $font === 'B' ? self::WB : self::WR;
        $w = 0;
        foreach (mb_str_split($s) as $ch) {
            $o = mb_ord($ch);
            if ($o >= 32 && $o <= 126) {
                $w += $table[$o - 32];
            } elseif (isset(self::WX[$ch])) {
                $w += self::WX[$ch];
            } else {
                $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ch);
                $b = $base !== false && $base !== '' ? ord($base[0]) : 0;
                $w += $b >= 32 && $b <= 126 ? $table[$b - 32] : 556;
            }
        }
        return $w * $size / 1000;
    }

    public function output(string $title = ''): string
    {
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $n = 3;
        $fontRefs = [];
        foreach (self::FONTS as $k => $base) {
            $objs[$n] = "<< /Type /Font /Subtype /Type1 /BaseFont /$base /Encoding /WinAnsiEncoding >>";
            $fontRefs[] = "/F$k $n 0 R";
            $n++;
        }
        $imgRefs = [];
        foreach ($this->images as $key => $im) {
            $objs[$n] = ['dict' => "/Type /XObject /Subtype /Image /Width {$im['w']} /Height {$im['h']} /ColorSpace {$im['cs']} /BitsPerComponent 8 /Filter /DCTDecode", 'stream' => $im['data']];
            $imgRefs[] = "/$key $n 0 R";
            $n++;
        }
        $res = '<< /Font << ' . implode(' ', $fontRefs) . ' >>' . ($imgRefs ? ' /XObject << ' . implode(' ', $imgRefs) . ' >>' : '') . ' >>';
        $kids = [];
        foreach ($this->pages as $content) {
            $objs[$n] = ['dict' => '', 'stream' => $content];
            $objs[$n + 1] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>', self::W, self::H, $res, $n);
            $kids[] = ($n + 1) . ' 0 R';
            $n += 2;
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $objs[$n] = '<< /Producer (Sochaux Retro) /Title (' . self::esc($title) . ') /CreationDate (D:' . date('YmdHis') . ') >>';
        $info = $n;
        ksort($objs);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[$i] = strlen($pdf);
            if (is_array($o)) {
                $pdf .= "$i 0 obj\n<< " . trim($o['dict'] . ' /Length ' . strlen($o['stream'])) . " >>\nstream\n" . $o['stream'] . "\nendstream\nendobj\n";
            } else {
                $pdf .= "$i 0 obj\n$o\nendobj\n";
            }
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($n + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $n; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        return $pdf . "trailer\n<< /Size " . ($n + 1) . " /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    private function out(string $op): void
    {
        if ($this->cur < 0) {
            $this->addPage();
        }
        $this->pages[$this->cur] .= $op . "\n";
    }

    private static function rgb(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', $c[0], $c[1], $c[2]);
    }

    private static function esc(string $s): string
    {
        $s = strtr($s, ["\u{202f}" => ' ', "\u{2009}" => ' ']);
        $enc = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return strtr($enc, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}
