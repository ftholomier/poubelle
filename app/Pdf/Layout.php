<?php
declare(strict_types=1);

namespace App\Pdf;

/**
 * Mise en page A4 aux couleurs du musée : texte enrichi avec retour à la ligne, titres,
 * listes, citations, tableaux (en-tête répété), photos, galerie, encadrés ; sauts de page,
 * bandeau courant et pied de page numéroté.
 *
 * Les ordonnées sont comptées depuis le haut de la page (y), converties à l'écriture.
 */
final class Layout
{
    public const W = 595.28;
    public const H = 841.89;

    public const FONTS = [
        'display' => 'BigShouldersDisplay-Black.ttf',
        'display-b' => 'BigShouldersDisplay-ExtraBold.ttf',
        'serif' => 'Newsreader-Regular.ttf',
        'serif-b' => 'Newsreader-SemiBold.ttf',
        'serif-i' => 'Newsreader-Italic.ttf',
    ];

    public const C = [
        'navy' => '0E1F4D', 'deep' => '07112E', 'blue' => '1E3FA8', 'yellow' => 'F6C400', 'cream' => 'F3EDDF',
        'paper' => 'FFFDF6', 'sand' => 'E8DFC9', 'muted' => '3A4A75', 'mist' => 'C9CFE0', 'butter' => 'FFF3C2',
        'green' => '267A40', 'red' => 'B3261E', 'white' => 'FFFFFF', 'ink' => '1B2547', 'stripe' => '1C2948', 'line' => 'D9D1BC',
    ];

    public Writer $pdf;
    /** Format des pages (A4 portrait ; A4 paysage : largeur et hauteur échangées avant la première page). */
    public float $pw = self::W;
    public float $ph = self::H;
    /** Pages sur fond sombre (pied de page en couleurs claires). @var array<int,bool> */
    public array $dark = [];
    public int $page = -1;
    public float $y = 0;
    public float $ml = 46;
    public float $mr = 46;
    public float $mt = 70;
    public float $mb = 66;
    /** Titre court du bandeau des pages suivantes */
    public string $running = '';
    /** Adresse de la page sur le site (pied de page, lien) */
    public string $url = '';
    public string $site = 'Sochaux Rétro';
    public string $tagline = 'Le musée en ligne du FC Sochaux-Montbéliard';
    public string $exported = '';
    public string $pageWord = 'Page';
    /** @var array<string,TrueType> */
    private array $fonts = [];
    private string $logo;

    public function __construct()
    {
        $this->pdf = new Writer();
        foreach (self::FONTS as $k => $file) {
            $this->fonts[$k] = $this->pdf->font($k, APP_DIR . '/Resources/fonts/' . $file);
        }
        $this->logo = PUBLIC_PATH . '/assets/img/logo-sochaux-retro.png';
    }

    // ------------------------------------------------------------------ bases

    public function cw(): float
    {
        return $this->pw - $this->ml - $this->mr;
    }

    public function bottom(): float
    {
        return $this->ph - $this->mb;
    }

    public function room(): float
    {
        return $this->bottom() - $this->y;
    }

    public function newPage(): void
    {
        $this->page = $this->pdf->addPage($this->pw, $this->ph);
        $this->y = $this->mt;
        if ($this->page > 0) {
            $this->runningHeader();
        }
    }

    /** Saut de page si la place restante est insuffisante. */
    public function ensure(float $h): void
    {
        if ($this->page < 0 || $this->y + $h > $this->bottom()) {
            $this->newPage();
        }
    }

    public function gap(float $h): void
    {
        $this->y += $h;
    }

    private static function rgb(string $c): array
    {
        $hex = self::C[$c] ?? ltrim($c, '#');
        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }

    private static function col(string $c, bool $fill = true): string
    {
        [$r, $g, $b] = self::rgb($c);
        return sprintf('%.3F %.3F %.3F %s', $r, $g, $b, $fill ? 'rg' : 'RG');
    }

    private function op(string $ops): void
    {
        $this->pdf->write($this->page, $ops);
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $stroke = null, float $lw = 1): void
    {
        $paint = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S');
        $this->op('q ' . ($fill ? self::col($fill) . ' ' : '') . ($stroke ? self::col($stroke, false) . sprintf(' %.2F w ', $lw) : '') . sprintf('%.2F %.2F %.2F %.2F re %s Q', $x, $this->ph - $y - $h, $w, $h, $paint));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = 'navy', float $lw = 1): void
    {
        $this->op(sprintf('q %s %.2F w %.2F %.2F m %.2F %.2F l S Q', self::col($color, false), $lw, $x1, $this->ph - $y1, $x2, $this->ph - $y2));
    }

    public function width(string $s, string $font, float $size, float $spacing = 0): float
    {
        $n = mb_strlen($s);
        return $this->fonts[$font]->width($s) * $size / 1000 + ($n > 1 ? $spacing * ($n - 1) : 0);
    }

    /** Hauteur des capitales (pour centrer un texte verticalement). */
    public function cap(string $font, float $size): float
    {
        $f = $this->fonts[$font];
        return $f->capHeight / $f->unitsPerEm * $size;
    }

    public function ascent(string $font, float $size): float
    {
        $f = $this->fonts[$font];
        return $f->ascent / $f->unitsPerEm * $size;
    }

    public function descent(string $font, float $size): float
    {
        $f = $this->fonts[$font];
        return abs($f->descent) / $f->unitsPerEm * $size;
    }

    /** Texte sur une ligne, posé sur sa ligne de base. Renvoie sa largeur. */
    public function text(float $x, float $baseline, string $s, string $font = 'serif', float $size = 10, string $color = 'ink', float $spacing = 0, ?string $link = null): float
    {
        if ($s === '') {
            return 0;
        }
        $gl = $this->fonts[$font]->glyphs($s);
        $hex = '';
        foreach ($gl as $g) {
            $hex .= sprintf('%04X', $g);
        }
        // L'espacement des lettres (Tc) fait partie de l'état graphique : toujours le fixer.
        $this->op(sprintf('BT /%s %.2F Tf %s %.2F Tc %.2F %.2F Td <%s> Tj ET', $this->pdf->fontRes($font), $size, self::col($color), $spacing, $x, $this->ph - $baseline, $hex));
        $w = $this->width($s, $font, $size, $spacing);
        if ($link) {
            $this->pdf->link($this->page, $x, $this->ph - $baseline - $size * 0.25, $x + $w, $this->ph - $baseline + $size * 0.8, $link);
        }
        return $w;
    }

    /** Texte en contour seul (lettres évidées), posé sur sa ligne de base. */
    public function strokeText(float $x, float $baseline, string $s, string $font, float $size, string $color, float $lw = 1.5, float $spacing = 0): float
    {
        if ($s === '') {
            return 0;
        }
        $hex = '';
        foreach ($this->fonts[$font]->glyphs($s) as $g) {
            $hex .= sprintf('%04X', $g);
        }
        $this->op(sprintf('q %s %.2F w 1 j BT /%s %.2F Tf 1 Tr %.2F Tc %.2F %.2F Td <%s> Tj ET Q', self::col($color, false), $lw, $this->pdf->fontRes($font), $size, $spacing, $x, $this->ph - $baseline, $hex));
        return $this->width($s, $font, $size, $spacing);
    }

    /**
     * Texte retourné (à lire tête en bas, comme les solutions d'un jeu), centré sur $cx ;
     * $y : haut de la ligne une fois la page retournée… c'est-à-dire sa ligne de base.
     */
    public function textUpsideDown(float $cx, float $y, string $s, string $font = 'serif', float $size = 10, string $color = 'ink'): void
    {
        if ($s === '') {
            return;
        }
        $hex = '';
        foreach ($this->fonts[$font]->glyphs($s) as $g) {
            $hex .= sprintf('%04X', $g);
        }
        $w = $this->width($s, $font, $size);
        $this->op(sprintf('BT /%s %.2F Tf %s 0 Tc -1 0 0 -1 %.2F %.2F Tm <%s> Tj ET', $this->pdf->fontRes($font), $size, self::col($color), $cx + $w / 2, $this->ph - $y, $hex));
    }

    /** Texte coupé avec « … » s'il dépasse la largeur donnée. */
    public function fit(string $s, string $font, float $size, float $max, float $spacing = 0): string
    {
        if ($this->width($s, $font, $size, $spacing) <= $max) {
            return $s;
        }
        while ($s !== '' && $this->width($s . '…', $font, $size, $spacing) > $max) {
            $s = mb_substr($s, 0, -1);
        }
        return rtrim($s) . '…';
    }

    // ------------------------------------------------------------------ images

    /** Ressource d'une image (fichier local), ou null. @return array{res:string,w:int,h:int}|null */
    public function loadImage(string $file, bool $alpha = false): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $key = md5($file . (@filemtime($file) ?: 0) . ($alpha ? 'a' : ''));
        $bytes = (string) file_get_contents($file);
        return $this->pdf->image($key, $bytes, $alpha);
    }

    /** Dessine une image ; « cover » : remplit le cadre en recadrant (sinon : contenue). */
    public function drawImage(array $img, float $x, float $y, float $w, float $h, bool $cover = false, float $focusY = 0.3): void
    {
        $iw = $img['w'];
        $ih = $img['h'];
        if ($cover) {
            $s = max($w / $iw, $h / $ih);
            $dw = $iw * $s;
            $dh = $ih * $s;
            $dx = $x + ($w - $dw) / 2;
            $dy = $y + ($h - $dh) * $focusY;
            $this->op(sprintf('q %.2F %.2F %.2F %.2F re W n %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $x, $this->ph - $y - $h, $w, $h, $dw, $dh, $dx, $this->ph - $dy - $dh, $img['res']));
            return;
        }
        $this->op(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, $this->ph - $y - $h, $img['res']));
    }

    public function logo(float $x, float $y, float $h): void
    {
        $img = $this->loadImage($this->logo, true);
        if ($img) {
            $this->drawImage($img, $x, $y, $h * $img['w'] / $img['h'], $h);
        }
    }

    /** Fond de toute la page. */
    public function fillPage(string $color): void
    {
        $this->rect(0, 0, $this->pw, $this->ph, $color);
    }

    /** Polygone plein (sommets en coordonnées de la page, y depuis le haut). @param list<array{0:float,1:float}> $pts */
    public function polygon(array $pts, string $fill): void
    {
        $d = '';
        foreach ($pts as $i => [$x, $y]) {
            $d .= sprintf('%.2F %.2F %s ', $x, $this->ph - $y, $i ? 'l' : 'm');
        }
        $this->op('q ' . self::col($fill) . ' ' . $d . 'h f Q');
    }

    /**
     * Tracé plein fait de segments et de courbes de Bézier, coordonnées de la page (y depuis le haut) :
     * ['M', x, y], ['L', x, y], ['C', x1, y1, x2, y2, x, y]. Contour facultatif.
     */
    public function path(array $cmds, ?string $fill, ?string $stroke = null, float $lw = 1): void
    {
        $d = '';
        foreach ($cmds as $c) {
            $op = array_shift($c);
            $pts = [];
            foreach (array_chunk($c, 2) as [$x, $y]) {
                $pts[] = sprintf('%.2F %.2F', $x, $this->ph - $y);
            }
            $d .= implode(' ', $pts) . ' ' . ['M' => 'm', 'L' => 'l', 'C' => 'c'][$op] . ' ';
        }
        $paint = $fill && $stroke ? 'b' : ($fill ? 'f' : 'S');
        $this->op('q ' . ($fill ? self::col($fill) . ' ' : '') . ($stroke ? self::col($stroke, false) . sprintf(' %.2F w 1 j ', $lw) : '') . $d . 'h ' . $paint . ' Q');
    }

    /**
     * Forme du moteur vectoriel de la boutique (mm, commandes M/L/C/Q/Z, couleurs #hex, règle de
     * remplissage) posée sur la page : origine ($ox, $oy) en points, $k points par mm.
     */
    public function vectorPath(array $sh, float $ox, float $oy, float $k): void
    {
        $X = fn (float $x): float => $ox + $x * $k;
        $Y = fn (float $y): float => $this->ph - ($oy + $y * $k);
        $d = '';
        $cx = $cy = 0.0;
        foreach ($sh['d'] as $c) {
            switch ($c[0]) {
                case 'M':
                case 'L':
                    $d .= sprintf('%.2F %.2F %s ', $X($c[1]), $Y($c[2]), $c[0] === 'M' ? 'm' : 'l');
                    [$cx, $cy] = [$c[1], $c[2]];
                    break;
                case 'C':
                    $d .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $X($c[1]), $Y($c[2]), $X($c[3]), $Y($c[4]), $X($c[5]), $Y($c[6]));
                    [$cx, $cy] = [$c[5], $c[6]];
                    break;
                case 'Q':
                    [$qx, $qy, $ex, $ey] = [$c[1], $c[2], $c[3], $c[4]];
                    $d .= sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $X($cx + 2 / 3 * ($qx - $cx)), $Y($cy + 2 / 3 * ($qy - $cy)), $X($ex + 2 / 3 * ($qx - $ex)), $Y($ey + 2 / 3 * ($qy - $ey)), $X($ex), $Y($ey));
                    [$cx, $cy] = [$ex, $ey];
                    break;
                case 'Z':
                    $d .= 'h ';
            }
        }
        $fill = $sh['fill'] ?? null;
        $stroke = $sh['stroke'] ?? null;
        $eo = ($sh['rule'] ?? '') === 'evenodd';
        $paint = $fill && $stroke ? ($eo ? 'B*' : 'B') : ($fill ? ($eo ? 'f*' : 'f') : 'S');
        $this->op('q ' . ($fill ? self::col(ltrim($fill, '#')) . ' ' : '') . ($stroke ? self::col(ltrim($stroke, '#'), false) . sprintf(' %.3F w ', ($sh['sw'] ?? 0) * $k) : '') . $d . $paint . ' Q');
    }

    /** Dessins faits par $draw tournés de $deg degrés (sens des aiguilles d'une montre) autour de ($cx, $cy). */
    public function rotated(float $deg, float $cx, float $cy, callable $draw): void
    {
        $a = deg2rad(-$deg);
        [$c, $s] = [cos($a), sin($a)];
        $py = $this->ph - $cy;
        $this->op(sprintf('q %.5F %.5F %.5F %.5F %.3F %.3F cm', $c, $s, -$s, $c, $cx - $c * $cx + $s * $py, $py - $s * $cx - $c * $py));
        $draw();
        $this->op('Q');
    }

    // ------------------------------------------------------------------ texte enrichi

    /**
     * Un fragment de texte : t (texte), f (police), s (corps), c (couleur), u (lien),
     * sp (espacement des lettres), pr (blanc après le fragment, en points).
     */
    public static function run(string $t, string $f = 'serif', float $s = 10.5, string $c = 'ink', ?string $u = null, float $sp = 0, float $pr = 0): array
    {
        return ['t' => $t, 'f' => $f, 's' => $s, 'c' => $c, 'u' => $u, 'sp' => $sp, 'pr' => $pr];
    }

    /**
     * Découpe des fragments en lignes de largeur maximale donnée.
     * @return list<array{w:float,asc:float,h:float,frags:list<array>}>
     */
    public function wrap(array $runs, float $width, float $lh = 1.38): array
    {
        $pieces = [];
        foreach ($runs as $r) {
            $r += ['sp' => 0, 'pr' => 0, 'u' => null];
            $t = str_replace(["\r\n", "\r"], "\n", (string) $r['t']);
            $last = null;
            foreach (preg_split('/(\n|[ \t]+)/u', $t, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $tok) {
                $pieces[] = ['t' => $tok, 'r' => $r, 'k' => $tok === "\n" ? 'nl' : (trim($tok, " \t") === '' ? 'sp' : 'w'), 'pr' => 0];
                if (end($pieces)['k'] === 'w') {
                    $last = array_key_last($pieces);
                }
            }
            // Blanc demandé après le fragment : ajouté à son dernier mot
            if ($last !== null && $r['pr'] > 0) {
                $pieces[$last]['pr'] = $r['pr'];
            }
        }
        $lines = [];
        $cur = [];
        $curW = 0.0;
        $pendSp = null;
        $flush = function () use (&$lines, &$cur, &$curW, $lh) {
            $asc = 0;
            $desc = 0;
            $h = 0;
            foreach ($cur as $fr) {
                $asc = max($asc, $this->ascent($fr['r']['f'], $fr['r']['s']));
                $desc = max($desc, $this->descent($fr['r']['f'], $fr['r']['s']));
                $h = max($h, $fr['r']['s'] * $lh);
            }
            $lines[] = ['w' => $curW, 'asc' => $asc, 'desc' => $desc, 'h' => $h ?: 10 * $lh, 'frags' => $cur];
            $cur = [];
            $curW = 0.0;
        };
        foreach ($pieces as $p) {
            if ($p['k'] === 'nl') {
                if (!$cur) {
                    $cur[] = ['t' => '', 'r' => $p['r'], 'x' => 0, 'w' => 0];
                }
                $flush();
                $pendSp = null;
                continue;
            }
            if ($p['k'] === 'sp') {
                if ($cur) {
                    $pendSp = $p;
                }
                continue;
            }
            $r = $p['r'];
            $ww = $this->width($p['t'], $r['f'], $r['s'], $r['sp']) + $p['pr'];
            $spW = $pendSp ? $this->width($pendSp['t'], $pendSp['r']['f'], $pendSp['r']['s']) : 0;
            if ($cur && $curW + $spW + $ww > $width) {
                $flush();
                $pendSp = null;
                $spW = 0;
            }
            if ($pendSp && $cur) {
                $cur[] = ['t' => $pendSp['t'], 'r' => $pendSp['r'], 'x' => $curW, 'w' => $spW];
                $curW += $spW;
                $pendSp = null;
            }
            // Mot plus long que la ligne : coupé
            while ($ww > $width && mb_strlen($p['t']) > 1) {
                $part = '';
                foreach (mb_str_split($p['t']) as $ch) {
                    if ($this->width($part . $ch, $r['f'], $r['s'], $r['sp']) > $width - $curW && $part !== '') {
                        break;
                    }
                    $part .= $ch;
                }
                $pw = $this->width($part, $r['f'], $r['s'], $r['sp']);
                $cur[] = ['t' => $part, 'r' => $r, 'x' => $curW, 'w' => $pw];
                $curW += $pw;
                $flush();
                $p['t'] = mb_substr($p['t'], mb_strlen($part));
                $ww = $this->width($p['t'], $r['f'], $r['s'], $r['sp']);
            }
            $cur[] = ['t' => $p['t'], 'r' => $r, 'x' => $curW, 'w' => $ww];
            $curW += $ww;
        }
        if ($cur) {
            $flush();
        }
        return $lines;
    }

    /** Écrit des lignes déjà découpées à partir de (x, y) ; renvoie la hauteur occupée. */
    public function drawLines(array $lines, float $x, float $y, float $width, string $align = 'left'): float
    {
        $top = $y;
        foreach ($lines as $ln) {
            $dx = $align === 'right' ? $width - $ln['w'] : ($align === 'center' ? ($width - $ln['w']) / 2 : 0);
            $base = $y + ($ln['h'] - $ln['asc'] - $ln['desc']) / 2 + $ln['asc'];
            $this->drawFrags($ln['frags'], $x + $dx, $base);
            $y += $ln['h'];
        }
        return $y - $top;
    }

    private function drawFrags(array $frags, float $x, float $base): void
    {
        // Fragments contigus de même style regroupés
        $groups = [];
        foreach ($frags as $fr) {
            $k = $fr['r']['f'] . '|' . $fr['r']['s'] . '|' . $fr['r']['c'] . '|' . ($fr['r']['u'] ?? '') . '|' . $fr['r']['sp'];
            if ($groups && end($groups)['k'] === $k) {
                $groups[array_key_last($groups)]['t'] .= $fr['t'];
                continue;
            }
            $groups[] = ['k' => $k, 't' => $fr['t'], 'x' => $fr['x'], 'r' => $fr['r']];
        }
        foreach ($groups as $g) {
            $r = $g['r'];
            $t = $g['t'];
            if (trim($t) === '') {
                continue;
            }
            $lead = mb_strlen($t) - mb_strlen(ltrim($t));
            $xx = $x + $g['x'] + ($lead ? $this->width(mb_substr($t, 0, $lead), $r['f'], $r['s']) : 0);
            $t = trim($t);
            $w = $this->text($xx, $base, $t, $r['f'], $r['s'], $r['c'], $r['sp'], $r['u'] ?? null);
            if (!empty($r['u'])) {
                $this->line($xx, $base + 1.6, $xx + $w, $base + 1.6, $r['c'], 0.5);
            }
        }
    }

    /** Paragraphe dans le flux (sauts de page entre les lignes). */
    public function para(array $runs, array $o = []): void
    {
        $x = $o['x'] ?? $this->ml;
        $w = $o['w'] ?? $this->cw();
        $lines = $this->wrap($runs, $w, $o['lh'] ?? 1.38);
        foreach ($lines as $ln) {
            $this->ensure($ln['h']);
            $this->drawLines([$ln], $x, $this->y, $w, $o['align'] ?? 'left');
            $this->y += $ln['h'];
        }
        $this->y += $o['after'] ?? 6;
    }

    /** Hauteur qu'occuperait un texte. */
    public function measure(array $runs, float $w, float $lh = 1.38): float
    {
        return array_sum(array_column($this->wrap($runs, $w, $lh), 'h'));
    }

    // ------------------------------------------------------------------ blocs

    /** Intertitre (signet du PDF) : capitales Big Shoulders et filet jaune. */
    public function h2(string $title, bool $bookmark = true): void
    {
        $this->ensure(64);
        if ($this->y > $this->mt + 2) {
            $this->y += 10;
        }
        $t = mb_strtoupper($title);
        $lines = $this->wrap([self::run($t, 'display', 17, 'navy', null, 0.3)], $this->cw(), 1.05);
        if ($bookmark) {
            $this->pdf->outline($title, $this->page, $this->ph - $this->y + 6);
        }
        $this->y += $this->drawLines($lines, $this->ml, $this->y, $this->cw());
        $this->rect($this->ml, $this->y + 2, 34, 3.2, 'yellow');
        $this->y += 14;
    }

    public function h3(string $title): void
    {
        $this->ensure(40);
        $this->y += 4;
        $lines = $this->wrap([self::run(mb_strtoupper($title), 'display-b', 11.5, 'blue', null, 0.9)], $this->cw(), 1.2);
        $this->y += $this->drawLines($lines, $this->ml, $this->y, $this->cw()) + 3;
    }

    /** Liste à puces carrées jaunes. */
    public function bullets(array $items, array $o = []): void
    {
        $x = $o['x'] ?? $this->ml;
        $w = $o['w'] ?? $this->cw();
        $num = $o['numbered'] ?? false;
        foreach (array_values($items) as $i => $runs) {
            $lines = $this->wrap($runs, $w - 16);
            if (!$lines) {
                continue;
            }
            $first = true;
            foreach ($lines as $ln) {
                $this->ensure($ln['h']);
                if ($first) {
                    if ($num) {
                        $this->text($x, $this->y + ($ln['h'] - $ln['asc'] - $ln['desc']) / 2 + $ln['asc'], ($i + 1 + ($o['start'] ?? 0)) . '.', 'display-b', 10, 'blue');
                    } else {
                        $this->rect($x + 1, $this->y + $ln['h'] / 2 - 2.6, 4.6, 4.6, 'yellow', 'navy', 0.6);
                    }
                    $first = false;
                }
                $this->drawLines([$ln], $x + 16, $this->y, $w - 16);
                $this->y += $ln['h'];
            }
            $this->y += 3;
        }
        $this->y += $o['after'] ?? 4;
    }

    /** Citation : filet jaune à gauche, italique, signature. */
    public function quote(array $runs, ?string $who = null): void
    {
        $w = $this->cw() - 18;
        $lines = $this->wrap($runs, $w, 1.42);
        if ($who) {
            $lines = array_merge($lines, $this->wrap([self::run('— ' . $who, 'display-b', 9.5, 'blue', null, 0.6)], $w, 1.5));
        }
        $total = array_sum(array_column($lines, 'h'));
        if ($total < 150) {
            $this->ensure($total + 4);
        }
        $startY = $this->y;
        $startPage = $this->page;
        foreach ($lines as $ln) {
            if ($this->y + $ln['h'] > $this->bottom()) {
                if ($this->page === $startPage) {
                    $this->rect($this->ml, $startY, 3, $this->y - $startY, 'yellow');
                }
                $this->newPage();
                $startY = $this->y;
                $startPage = $this->page;
            }
            $this->drawLines([$ln], $this->ml + 14, $this->y, $w);
            $this->y += $ln['h'];
        }
        $this->rect($this->ml, $startY, 3, $this->y - $startY, 'yellow');
        $this->y += 9;
    }

    /** Encadré « le chiffre » : grand nombre et légende sur fond jaune. */
    public function keyFigure(string $number, string $text): void
    {
        $nw = $number !== '' ? min(200, $this->width($number, 'display', 38) + 10) : 0;
        $tw = $this->cw() - 36 - $nw;
        $lines = $this->wrap([self::run($text, 'serif-b', 11.5, 'navy')], $tw, 1.3);
        $h = max(58, array_sum(array_column($lines, 'h')) + 26);
        $this->ensure($h + 8);
        $this->rect($this->ml + 4, $this->y + 4, $this->cw(), $h, 'navy');
        $this->rect($this->ml, $this->y, $this->cw(), $h, 'yellow', 'navy', 1.2);
        if ($number !== '') {
            $this->text($this->ml + 16, $this->y + $h / 2 + $this->cap('display', 38) / 2, $number, 'display', 38, 'navy');
        }
        $th = array_sum(array_column($lines, 'h'));
        $this->drawLines($lines, $this->ml + 16 + $nw + ($nw ? 6 : 0), $this->y + ($h - $th) / 2, $tw);
        $this->y += $h + 14;
    }

    /**
     * Grille « libellé / valeur » (fiche technique), sur 2 ou 3 colonnes.
     * @param list<array{0:string,1:string,2?:?string}> $pairs libellé, valeur, lien éventuel
     */
    public function facts(array $pairs, int $cols = 3): void
    {
        $pairs = array_values(array_filter($pairs, fn ($p) => trim((string) ($p[1] ?? '')) !== ''));
        if (!$pairs) {
            return;
        }
        $gap = 10;
        $cw = ($this->cw() - $gap * ($cols - 1)) / $cols;
        foreach (array_chunk($pairs, $cols) as $row) {
            $hs = [];
            $cells = [];
            foreach ($row as $p) {
                $l1 = $this->wrap([self::run(mb_strtoupper($p[0]), 'display-b', 7.6, 'muted', null, 0.9)], $cw - 2, 1.25);
                $l2 = $this->wrap([self::run((string) $p[1], 'serif', 10.5, 'navy', $p[2] ?? null)], $cw - 2, 1.3);
                $cells[] = [$l1, $l2];
                $hs[] = array_sum(array_column($l1, 'h')) + array_sum(array_column($l2, 'h'));
            }
            $h = max($hs) + 12;
            $this->ensure($h);
            foreach ($cells as $i => [$l1, $l2]) {
                $x = $this->ml + $i * ($cw + $gap);
                $this->line($x, $this->y, $x + $cw, $this->y, 'navy', 1.2);
                $yy = $this->y + 5;
                $yy += $this->drawLines($l1, $x, $yy, $cw);
                $this->drawLines($l2, $x, $yy + 1, $cw);
            }
            $this->y += $h;
        }
        $this->y += 6;
    }

    /**
     * Tableau : en-tête bleu marine (répété sur chaque page), lignes alternées.
     * Une cellule est un texte, ou ['t' => …, 'b' => gras, 'c' => couleur, 'u' => lien, 'runs' => fragments].
     * $o : align (liste par colonne : l, r, c), widths (fractions), size, head (bool), max (largeur maxi d'une colonne en fraction).
     */
    public function table(array $headers, array $rows, array $o = []): void
    {
        $bgs = [];
        foreach ($rows as $i => $r) {
            $bgs[$i] = $r['_bg'] ?? null;
            unset($rows[$i]['_bg']);
        }
        $rows = array_values($rows);
        $bgs = array_values($bgs);
        $n = max(count($headers), $rows ? max(array_map('count', $rows)) : 0);
        if (!$n || !$rows) {
            return;
        }
        $size = $o['size'] ?? 8.6;
        $pad = 5;
        $align = $o['align'] ?? [];
        $toRuns = function ($cell, bool $head = false) use ($size): array {
            if (is_array($cell) && isset($cell['runs'])) {
                return $cell['runs'];
            }
            $t = is_array($cell) ? (string) ($cell['t'] ?? '') : (string) $cell;
            if ($head) {
                return [self::run(mb_strtoupper($t), 'display-b', $size - 0.4, 'yellow', null, 0.6)];
            }
            $b = is_array($cell) && !empty($cell['b']);
            return [self::run($t, $b ? 'serif-b' : 'serif', $size, is_array($cell) ? ($cell['c'] ?? 'ink') : 'ink', is_array($cell) ? ($cell['u'] ?? null) : null)];
        };
        // Largeurs : naturelles, réparties sur la largeur disponible
        $total = $o['w'] ?? $this->cw();
        if (!empty($o['widths'])) {
            $widths = array_map(fn ($f) => $f * $total, $o['widths']);
        } else {
            $nat = array_fill(0, $n, 18.0);
            $min = array_fill(0, $n, 18.0);
            foreach (array_merge([$headers], $rows) as $ri => $row) {
                foreach (array_values($row) as $i => $cell) {
                    $runs = $toRuns($cell, $ri === 0 && $headers);
                    $text = implode('', array_column($runs, 't'));
                    $r0 = $runs[0] ?? self::run('');
                    $nat[$i] = max($nat[$i], min($total * ($o['max'] ?? 0.55), $this->width($text, $r0['f'], $r0['s'], $r0['sp']) + 2 * $pad + 1));
                    foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
                        $min[$i] = max($min[$i], min($total * 0.3, $this->width($word, $r0['f'], $r0['s'], $r0['sp']) + 2 * $pad + 1));
                    }
                }
            }
            $sumNat = array_sum($nat);
            if ($sumNat <= $total) {
                $extra = $total - $sumNat;
                $widths = array_map(fn ($w) => $w + $extra * $w / $sumNat, $nat);
            } else {
                $sumMin = array_sum($min);
                $rest = max(0, $total - $sumMin);
                $flex = array_sum(array_map(fn ($a, $b) => $a - $b, $nat, $min)) ?: 1;
                $widths = array_map(fn ($a, $b) => $b + $rest * ($a - $b) / $flex, $nat, $min);
                $k = $total / array_sum($widths);
                $widths = array_map(fn ($w) => $w * $k, $widths);
            }
        }
        $x0 = $o['x'] ?? $this->ml;
        $drawRow = function (array $row, bool $head, int $ri) use ($toRuns, $widths, $pad, $x0, $align, $n, $o, $bgs) {
            $cells = [];
            $h = 0;
            for ($i = 0; $i < $n; $i++) {
                $cell = array_values($row)[$i] ?? '';
                $lines = $this->wrap($toRuns($cell, $head), $widths[$i] - 2 * $pad, 1.3);
                $cells[] = [$lines, $cell];
                $h = max($h, array_sum(array_column($lines, 'h')));
            }
            $h += $head ? 9 : 7;
            if (!$head && $this->y + $h > $this->bottom()) {
                return null;
            }
            $bg = $head ? 'navy' : ($bgs[$ri] ?? (($o['zebra'] ?? true) && $ri % 2 ? 'cream' : null));
            if ($bg) {
                $this->rect($x0, $this->y, array_sum($widths), $h, $bg);
            }
            $x = $x0;
            foreach ($cells as $i => [$lines, $cell]) {
                $a = ['l' => 'left', 'r' => 'right', 'c' => 'center'][$align[$i] ?? 'l'] ?? 'left';
                $th = array_sum(array_column($lines, 'h'));
                $this->drawLines($lines, $x + $pad, $this->y + ($h - $th) / 2, $widths[$i] - 2 * $pad, $a);
                $x += $widths[$i];
            }
            if (!$head) {
                $this->line($x0, $this->y + $h, $x0 + array_sum($widths), $this->y + $h, 'line', 0.5);
            }
            $this->y += $h;
            return $h;
        };
        $this->ensure(40);
        if ($headers) {
            $drawRow($headers, true, 0);
        }
        foreach (array_values($rows) as $ri => $row) {
            if ($drawRow($row, false, $ri) === null) {
                $this->newPage();
                if ($headers) {
                    $drawRow($headers, true, 0);
                }
                $drawRow($row, false, $ri);
            }
        }
        $this->y += $o['after'] ?? 12;
    }

    /** Photo pleine largeur (ou réduite), légende et crédit dessous. */
    public function figure(?array $img, string $caption = '', array $o = []): void
    {
        if (!$img) {
            return;
        }
        $maxW = $o['w'] ?? $this->cw();
        $maxH = $o['h'] ?? 300;
        $w = $maxW;
        $h = $w * $img['h'] / $img['w'];
        if ($h > $maxH) {
            $h = $maxH;
            $w = $h * $img['w'] / $img['h'];
        }
        $capLines = $caption !== '' ? $this->wrap([self::run($caption, 'serif-i', 8.6, 'muted')], $maxW, 1.3) : [];
        $capH = array_sum(array_column($capLines, 'h'));
        if ($this->y + $h + $capH + 8 > $this->bottom()) {
            if ($h > 160 && $this->room() > 200) {
                // Réduite pour tenir sur la page
                $h = $this->room() - $capH - 12;
                $w = $h * $img['w'] / $img['h'];
            } else {
                $this->newPage();
            }
        }
        $x = ($o['align'] ?? 'center') === 'left' ? $this->ml : $this->ml + ($this->cw() - $w) / 2;
        $this->rect($x + 4, $this->y + 4, $w, $h, 'sand');
        $this->drawImage($img, $x, $this->y, $w, $h);
        $this->rect($x, $this->y, $w, $h, null, 'navy', 1);
        $this->y += $h + 6;
        if ($capLines) {
            $this->y += $this->drawLines($capLines, $this->ml + ($maxW < $this->cw() ? ($this->cw() - $maxW) / 2 : 0), $this->y, $maxW, ($o['align'] ?? 'center') === 'left' ? 'left' : 'center');
        }
        $this->y += $o['after'] ?? 12;
    }

    /**
     * Galerie : vignettes recadrées en grille, légendes dessous.
     * @param list<array{img:array,caption:string}> $items
     */
    public function gallery(array $items, int $cols = 3): void
    {
        $gap = 12;
        $cw = ($this->cw() - $gap * ($cols - 1)) / $cols;
        $ih = $cw * 0.72;
        foreach (array_chunk($items, $cols) as $row) {
            $caps = [];
            $capH = 0;
            foreach ($row as $it) {
                $l = $it['caption'] !== '' ? array_slice($this->wrap([self::run($it['caption'], 'serif-i', 8, 'muted')], $cw, 1.25), 0, 3) : [];
                $caps[] = $l;
                $capH = max($capH, array_sum(array_column($l, 'h')));
            }
            $this->ensure($ih + $capH + 14);
            foreach ($row as $i => $it) {
                $x = $this->ml + $i * ($cw + $gap);
                $this->rect($x + 3, $this->y + 3, $cw, $ih, 'sand');
                $this->drawImage($it['img'], $x, $this->y, $cw, $ih, true);
                $this->rect($x, $this->y, $cw, $ih, null, 'navy', 0.8);
                if ($caps[$i]) {
                    $this->drawLines($caps[$i], $x, $this->y + $ih + 5, $cw);
                }
            }
            $this->y += $ih + $capH + 16;
        }
    }

    // ------------------------------------------------------------------ en-têtes et pieds de page

    /**
     * Grand bandeau de la première page : fond bleu nuit rayé, blason qui déborde,
     * nom du musée et étiquette du type de document.
     */
    public function masthead(string $kind): void
    {
        $h = 96;
        $this->rect(0, 0, $this->pw, $h, 'navy');
        // Rayures obliques discrètes (comme le site)
        $this->op(sprintf('q 0 %.2F %.2F %.2F re W n %s 1.6 w', $this->ph - $h, $this->pw, $h, self::col('stripe', false)));
        for ($x = -120; $x < $this->pw + 60; $x += 15) {
            $this->op(sprintf('%.2F %.2F m %.2F %.2F l', $x, $this->ph - $h, $x + 46, $this->ph));
        }
        $this->op('S Q');
        $this->rect(0, $h, $this->pw, 4, 'yellow');
        // Blason : la pointe dépasse sous le bandeau
        $this->logo($this->ml - 6, 12, 104);
        $x = $this->ml + 92 + 8;
        $this->text($x, 46, mb_strtoupper($this->site), 'display', 26, 'cream', 0.6);
        $this->text($x, 64, $this->tagline, 'serif-i', 10.5, 'mist');
        $this->text($x, 80, $this->url !== '' ? preg_replace('#^https?://#', '', rtrim($this->url, '/')) : '', 'serif', 8, 'mist', 0, $this->url !== '' ? $this->url : null);
        // Étiquette du document
        $kw = $this->width(mb_strtoupper($kind), 'display', 11, 1.2) + 22;
        $this->rect($this->pw - $this->mr - $kw + 3, 33, $kw, 22, 'deep');
        $this->rect($this->pw - $this->mr - $kw, 30, $kw, 22, 'yellow');
        $this->text($this->pw - $this->mr - $kw + 11, 30 + 11 + $this->cap('display', 11) / 2, mb_strtoupper($kind), 'display', 11, 'navy', 1.2);
        $this->y = $h + 34;
    }

    /** Bandeau des pages suivantes : nom du musée et titre du document. */
    private function runningHeader(): void
    {
        $h = 30;
        $this->rect(0, 0, $this->pw, $h, 'navy');
        $this->rect(0, $h, $this->pw, 2.5, 'yellow');
        $this->logo($this->ml - 2, 4, 33);
        $this->text($this->ml + 30, 19.5, mb_strtoupper($this->site), 'display', 11.5, 'cream', 0.5);
        $max = $this->pw - $this->mr - ($this->ml + 160);
        $t = $this->fit($this->running, 'display-b', 10, $max, 0.4);
        $this->text($this->pw - $this->mr - $this->width($t, 'display-b', 10, 0.4), 19.5, $t, 'display-b', 10, 'yellow', 0.4);
        $this->y = $h + 26;
    }

    /** Pieds de page (numérotés « n / total ») ajoutés à la fin. */
    public function finish(): string
    {
        $total = $this->pdf->pageCount();
        for ($p = 0; $p < $total; $p++) {
            $this->page = $p;
            $dark = !empty($this->dark[$p]);
            $y = $this->ph - 42;
            $this->line($this->ml, $y, $this->pw - $this->mr, $y, $dark ? 'muted' : 'navy', 0.8);
            $this->rect($this->ml, $y - 1.2, 24, 2.4, 'yellow');
            $this->text($this->ml, $y + 13, $this->site . ' · ' . $this->tagline, 'serif-i', 7.6, $dark ? 'mist' : 'muted');
            if ($this->url !== '') {
                $u = $this->fit(preg_replace('#^https?://#', '', $this->url), 'serif', 7.4, 330);
                $this->text($this->ml, $y + 24, $u, 'serif', 7.4, $dark ? 'yellow' : 'blue', 0, $this->url);
            }
            if ($this->exported !== '') {
                $ew = $this->width($this->exported, 'serif', 7.4);
                $this->text($this->pw - $this->mr - $ew, $y + 24, $this->exported, 'serif', 7.4, $dark ? 'mist' : 'muted');
            }
            $pg = mb_strtoupper($this->pageWord) . ' ' . ($p + 1) . ' / ' . $total;
            $pw = $this->width($pg, 'display-b', 8.6, 0.8);
            $this->text($this->pw - $this->mr - $pw, $y + 13, $pg, 'display-b', 8.6, $dark ? 'cream' : 'navy', 0.8);
        }
        return $this->pdf->output();
    }
}
