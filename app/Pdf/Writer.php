<?php
declare(strict_types=1);

namespace App\Pdf;

/**
 * Écriture d'un fichier PDF 1.7 : pages, polices TrueType embarquées (sous-ensembles, texte
 * sélectionnable et copiable), images JPEG / PNG avec transparence, liens, signets et métadonnées.
 */
final class Writer
{
    /** @var array<int,string> */
    private array $objects = [];
    private int $next = 1;
    /** @var list<array{id:int,w:float,h:float,content:string,annots:list<string>}> */
    private array $pages = [];
    /** @var array<string,array{font:TrueType,id:int,res:string}> */
    private array $fonts = [];
    /** @var array<string,array{id:int,res:string,w:int,h:int}> */
    private array $images = [];
    /** @var list<array{title:string,page:int,y:float,level:int}> */
    private array $outlines = [];
    /** @var array<string,string> */
    public array $info = [];
    public string $lang = 'fr-FR';
    private int $pagesId;

    public function __construct()
    {
        $this->pagesId = $this->reserve();
    }

    public function reserve(): int
    {
        return $this->next++;
    }

    public function set(int $id, string $body): void
    {
        $this->objects[$id] = $body;
    }

    public function add(string $body): int
    {
        $id = $this->reserve();
        $this->objects[$id] = $body;
        return $id;
    }

    /** Nouvelle page ; renvoie son numéro (à partir de 0). */
    public function addPage(float $w, float $h): int
    {
        $this->pages[] = ['id' => $this->reserve(), 'w' => $w, 'h' => $h, 'content' => '', 'annots' => []];
        return count($this->pages) - 1;
    }

    /** Format fini (TrimBox) et fonds perdus (BleedBox) d'une page, en points PDF : [x1, y1, x2, y2]. */
    public function boxes(int $page, array $trim, array $bleed): void
    {
        $this->pages[$page]['boxes'] = sprintf(' /TrimBox [%.2F %.2F %.2F %.2F] /BleedBox [%.2F %.2F %.2F %.2F]', ...array_merge($trim, $bleed));
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function write(int $page, string $ops): void
    {
        $this->pages[$page]['content'] .= $ops . "\n";
    }

    public function font(string $key, string $path): TrueType
    {
        if (!isset($this->fonts[$key])) {
            $this->fonts[$key] = ['font' => new TrueType($path), 'id' => $this->reserve(), 'res' => 'F' . (count($this->fonts) + 1)];
        }
        return $this->fonts[$key]['font'];
    }

    public function fontRes(string $key): string
    {
        return $this->fonts[$key]['res'];
    }

    /**
     * Image (JPEG, PNG, WebP, GIF) : JPEG embarqué tel quel, les autres convertis
     * (PNG avec transparence : masque alpha conservé).
     * @return array{res:string,w:int,h:int}|null
     */
    public function image(string $key, string $bytes, bool $keepAlpha = false): ?array
    {
        if (isset($this->images[$key])) {
            return $this->images[$key];
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info) {
            return null;
        }
        [$w, $h, $type] = $info;
        if ($type === IMAGETYPE_JPEG && ($info['channels'] ?? 3) !== 4) {
            $cs = ($info['channels'] ?? 3) === 1 ? '/DeviceGray' : '/DeviceRGB';
            $id = $this->add("<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace $cs /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($bytes) . " >>\nstream\n" . $bytes . "\nendstream");
            return $this->images[$key] = ['id' => $id, 'res' => 'Im' . (count($this->images) + 1), 'w' => $w, 'h' => $h];
        }
        $im = @imagecreatefromstring($bytes);
        if (!$im) {
            return null;
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        if ($keepAlpha) {
            // Pixels RGB + masque alpha, compressés (logo et pictogrammes)
            $rgb = '';
            $alpha = '';
            $hasAlpha = false;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $c = imagecolorat($im, $x, $y);
                    $a = ($c >> 24) & 0x7F;
                    $rgb .= chr(($c >> 16) & 0xFF) . chr(($c >> 8) & 0xFF) . chr($c & 0xFF);
                    $alpha .= chr((int) round(255 - $a * 255 / 127));
                    $hasAlpha = $hasAlpha || $a > 0;
                }
            }
            imagedestroy($im);
            $smask = '';
            if ($hasAlpha) {
                $z = (string) gzcompress($alpha, 6);
                $sid = $this->add("<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($z) . " >>\nstream\n" . $z . "\nendstream");
                $smask = " /SMask $sid 0 R";
            }
            $z = (string) gzcompress($rgb, 6);
            $id = $this->add("<< /Type /XObject /Subtype /Image /Width $w /Height $h /ColorSpace /DeviceRGB /BitsPerComponent 8$smask /Filter /FlateDecode /Length " . strlen($z) . " >>\nstream\n" . $z . "\nendstream");
            return $this->images[$key] = ['id' => $id, 'res' => 'Im' . (count($this->images) + 1), 'w' => $w, 'h' => $h];
        }
        // Photo : fond blanc sous la transparence, puis JPEG
        $bg = imagecreatetruecolor($w, $h);
        imagefill($bg, 0, 0, (int) imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $im, 0, 0, 0, 0, $w, $h);
        imagedestroy($im);
        ob_start();
        imagejpeg($bg, null, 84);
        $jpg = (string) ob_get_clean();
        imagedestroy($bg);
        return $this->image($key, $jpg);
    }

    public function imageRes(string $key): ?string
    {
        return $this->images[$key]['res'] ?? null;
    }

    /** Lien vers une adresse web sur une zone de la page (coordonnées PDF). */
    public function link(int $page, float $x1, float $y1, float $x2, float $y2, string $uri): void
    {
        $this->pages[$page]['annots'][] = sprintf('<< /Type /Annot /Subtype /Link /Rect [%.2F %.2F %.2F %.2F] /Border [0 0 0] /A << /S /URI /URI %s >> >>', $x1, $y1, $x2, $y2, self::str($uri, false));
    }

    public function outline(string $title, int $page, float $y, int $level = 0): void
    {
        $this->outlines[] = ['title' => $title, 'page' => $page, 'y' => $y, 'level' => $level];
    }

    /** Chaîne PDF : texte en UTF-16 si nécessaire. */
    public static function str(string $s, bool $unicode = true): string
    {
        if ($unicode && preg_match('/[^\x20-\x7E]/', $s)) {
            return '<FEFF' . strtoupper(bin2hex((string) mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
        }
        return '(' . strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => '\\n']) . ')';
    }

    public function output(): string
    {
        // Polices : sous-ensembles et tables de correspondance pour le copier-coller
        $fontRes = [];
        foreach ($this->fonts as $f) {
            $fontRes[] = '/' . $f['res'] . ' ' . $f['id'] . ' 0 R';
            $this->writeFont($f['font'], $f['id']);
        }
        $imgRes = array_map(fn ($i) => '/' . $i['res'] . ' ' . $i['id'] . ' 0 R', $this->images);
        $resId = $this->add('<< /ProcSet [/PDF /Text /ImageB /ImageC] /Font << ' . implode(' ', $fontRes) . ' >> /XObject << ' . implode(' ', $imgRes) . ' >> >>');
        $kids = [];
        foreach ($this->pages as $p) {
            $z = (string) gzcompress($p['content'], 6);
            $cid = $this->add('<< /Length ' . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream");
            $annots = '';
            if ($p['annots']) {
                $ids = array_map(fn ($a) => $this->add($a) . ' 0 R', $p['annots']);
                $annots = ' /Annots [' . implode(' ', $ids) . ']';
            }
            $this->set($p['id'], sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F]%s /Resources %d 0 R /Contents %d 0 R%s >>', $this->pagesId, $p['w'], $p['h'], $p['boxes'] ?? '', $resId, $cid, $annots));
            $kids[] = $p['id'] . ' 0 R';
        }
        $this->set($this->pagesId, '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>');
        $outlinesId = $this->writeOutlines();
        $catalog = $this->add('<< /Type /Catalog /Pages ' . $this->pagesId . ' 0 R' . ($outlinesId ? " /Outlines $outlinesId 0 R /PageMode /UseOutlines" : '') . ' /Lang ' . self::str($this->lang, false) . ' /ViewerPreferences << /DisplayDocTitle true >> >>');
        $info = $this->info + ['Producer' => 'Sochaux Rétro', 'CreationDate' => 'D:' . date('YmdHisO')];
        $info['CreationDate'] = preg_replace('/([+-]\d{2})(\d{2})$/', "$1'$2'", $info['CreationDate']);
        $parts = [];
        foreach ($info as $k => $v) {
            $parts[] = '/' . $k . ' ' . self::str((string) $v, $k !== 'CreationDate');
        }
        $infoId = $this->add('<< ' . implode(' ', $parts) . ' >>');

        ksort($this->objects);
        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($this->objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $size = max(array_keys($this->objects)) + 1;
        $out .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $out .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $fid = md5($out);
        $out .= "trailer\n<< /Size $size /Root $catalog 0 R /Info $infoId 0 R /ID [<$fid> <$fid>] >>\nstartxref\n$xref\n%%EOF\n";
        return $out;
    }

    private function writeFont(TrueType $f, int $id): void
    {
        $prog = $f->subset();
        $used = $f->used;
        ksort($used);
        $tag = '';
        $h = crc32(implode(',', array_keys($used)) . $f->name);
        for ($i = 0; $i < 6; $i++) {
            $tag .= chr(65 + ($h >> (5 * $i)) % 26);
        }
        $base = $tag . '+' . $f->name;
        $scale = 1000 / $f->unitsPerEm;
        $z = (string) gzcompress($prog, 9);
        $file = $this->add('<< /Length ' . strlen($z) . ' /Length1 ' . strlen($prog) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream");
        $italic = abs($f->italicAngle) > 0.1;
        $flags = 4 | ($italic ? 64 : 0) | (stripos($f->name, 'newsreader') !== false ? 2 : 0);
        $desc = $this->add(sprintf(
            '<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox [%d %d %d %d] /ItalicAngle %.1F /Ascent %d /Descent %d /CapHeight %d /StemV %d /FontFile2 %d 0 R >>',
            $base, $flags, $f->bbox[0] * $scale, $f->bbox[1] * $scale, $f->bbox[2] * $scale, $f->bbox[3] * $scale,
            $f->italicAngle, $f->ascent * $scale, $f->descent * $scale, $f->capHeight * $scale,
            preg_match('/black|bold/i', $f->name) ? 140 : 80, $file
        ));
        // Largeurs des glyphes employés
        $w = '';
        foreach (array_keys($used) as $g) {
            $w .= $g . ' [' . $f->glyphWidth($g) . '] ';
        }
        $cid = $this->add("<< /Type /Font /Subtype /CIDFontType2 /BaseFont /$base /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor $desc 0 R /DW 500 /W [ $w] /CIDToGIDMap /Identity >>");
        // Correspondance glyphe → caractère (recherche et copier-coller)
        $map = '';
        foreach (array_chunk($used, 100, true) as $chunk) {
            $map .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $g => $cp) {
                $map .= sprintf("<%04X> <%s>\n", $g, strtoupper(bin2hex((string) mb_convert_encoding(mb_chr(max(32, $cp), 'UTF-8'), 'UTF-16BE', 'UTF-8'))));
            }
            $map .= "endbfchar\n";
        }
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n" . $map . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
        $zc = (string) gzcompress($cmap, 6);
        $uni = $this->add('<< /Length ' . strlen($zc) . " /Filter /FlateDecode >>\nstream\n" . $zc . "\nendstream");
        $this->set($id, "<< /Type /Font /Subtype /Type0 /BaseFont /$base /Encoding /Identity-H /DescendantFonts [$cid 0 R] /ToUnicode $uni 0 R >>");
    }

    private function writeOutlines(): ?int
    {
        if (!$this->outlines) {
            return null;
        }
        $root = $this->reserve();
        $ids = array_map(fn () => $this->reserve(), $this->outlines);
        // Parent de chaque entrée : la dernière entrée de niveau inférieur
        $parent = [];
        $children = [-1 => []];
        $stack = [-1];
        foreach ($this->outlines as $i => $o) {
            while (count($stack) > 1 && $this->outlines[end($stack)]['level'] >= $o['level']) {
                array_pop($stack);
            }
            $parent[$i] = end($stack);
            $children[$parent[$i]][] = $i;
            $children[$i] = [];
            $stack[] = $i;
        }
        foreach ($this->outlines as $i => $o) {
            $sib = $children[$parent[$i]];
            $k = array_search($i, $sib, true);
            $d = '<< /Title ' . self::str($o['title']) . ' /Parent ' . ($parent[$i] === -1 ? $root : $ids[$parent[$i]]) . ' 0 R';
            $d .= isset($sib[$k - 1]) ? ' /Prev ' . $ids[$sib[$k - 1]] . ' 0 R' : '';
            $d .= isset($sib[$k + 1]) ? ' /Next ' . $ids[$sib[$k + 1]] . ' 0 R' : '';
            if ($children[$i]) {
                $d .= ' /First ' . $ids[$children[$i][0]] . ' 0 R /Last ' . $ids[end($children[$i])] . ' 0 R /Count ' . count($children[$i]);
            }
            $page = $this->pages[$o['page']];
            $d .= sprintf(' /Dest [%d 0 R /XYZ 0 %.2F null] >>', $page['id'], $o['y']);
            $this->set($ids[$i], $d);
        }
        $top = $children[-1];
        $this->set($root, '<< /Type /Outlines /First ' . $ids[$top[0]] . ' 0 R /Last ' . $ids[end($top)] . ' 0 R /Count ' . count($top) . ' >>');
        return $root;
    }
}
