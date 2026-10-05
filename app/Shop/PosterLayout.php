<?php
declare(strict_types=1);

namespace App\Shop;

/**
 * Mise en page du poster souvenir (voir Poster) : une mosaïque de blocs sur une grille de
 * 12 colonnes, dessinée en millimètres pour l'A3 (297 × 420). Chaque bloc devient des calques du
 * moteur vectoriel (rectangles, ronds, textes, logo). Les blocs absents d'une fiche sont remplacés
 * (fiche technique) ou leur place revient au récit.
 */
final class PosterLayout
{
    private const W = 297.0;
    private const H = 420.0;
    private const PAD = 10.0;
    private const GAP = 3.5;
    private const ROWS = 43;

    private const NAVY = '#0E1F4D';
    private const BLUE = '#094687';
    private const STRIPE = '#0D4C90';
    private const BOX = '#13286A';
    private const LINE = '#2F3F75';
    private const RULE = '#6E6640';
    private const PITCHLINE = '#8A9BBE';
    private const YELLOW = '#F6C400';
    private const CREAM = '#F3EDDF';
    private const MIST = '#AEB6CE';

    private array $L = [];
    private float $top = 0;
    private float $u = 0;
    private float $cw = 0;
    private int $n = 0;
    private array $used = [];

    public function __construct(private array $d, private string $pour, private string $no)
    {
    }

    /** px de la maquette (1190 px de large) → points. */
    private static function pt(float $px): float
    {
        return $px * 0.7075;
    }

    public function build(): array
    {
        $d = $this->d;
        $this->L = [];
        $this->rect(0, 0, self::W, self::H, self::NAVY);
        $this->header();
        $this->top = self::PAD + 9;
        $bottom = self::H - self::PAD - 6;
        $this->u = (($bottom - $this->top) - self::GAP * (self::ROWS - 1)) / self::ROWS;
        $this->cw = (self::W - 2 * self::PAD - 11 * self::GAP) / 12;

        $quotes = $d['quotes'];
        $an = $d['anecdote'];
        $q0 = array_shift($quotes);
        $rest = array_slice($quotes, 0, 2);
        $bandD = $an || $rest ? 7 : 0;
        $bandF = $d['season'] ? 3 : 0;
        // Place du récit à un corps de lecture ; ce qui reste agrandit le terrain et le film.
        $free = self::ROWS - 5 - 12 - 6 - $bandD - $bandF;
        $extra = max(0, min(14, $free - $this->recitRows()));
        $b = 12 + $extra;
        // A · l'affiche et le blason (5 rangées)
        $this->hero(...$this->box(0, 8, 0, 5));
        $this->crest(...$this->box(8, 4, 0, 5));
        // B · terrain, film, tribunes et première citation
        $this->pitch(...$this->box(0, 5, 5, $b));
        $this->film(...$this->box(5, 4, 5, $b));
        if ($d['spectators'] > 0) {
            $this->crowd(...$this->box(9, 3, 5, 5));
            $q0 ? $this->quote($q0, ...$this->box(9, 3, 10, $b - 5)) : $this->facts(...$this->box(9, 3, 10, $b - 5));
        } else {
            $q0 ? $this->quote($q0, ...$this->box(9, 3, 5, $b)) : $this->facts(...$this->box(9, 3, 5, $b));
        }
        // C · l'enjeu (ou la fiche technique) et le chiffre (6 rangées)
        $row = 5 + $b;
        $wide = $d['figure'] ? 7 : 12;
        if ($d['enjeu'] !== '') {
            $this->enjeu(...$this->box(0, $wide, $row, 6));
        } elseif (!empty($this->used['facts']) && $quotes) {
            $this->quote(array_shift($quotes), ...$this->box(0, $wide, $row, 6));
        } else {
            $this->facts(...$this->box(0, $wide, $row, 6));
        }
        if ($d['figure']) {
            $this->figure(...$this->box(7, 5, $row, 6));
        }
        $row += 6;
        // D · anecdote et citations (7 rangées)
        if ($bandD) {
            if ($an && $rest) {
                $this->anecdote($an, ...$this->box(0, 5, $row, 7));
                $this->duel($rest, ...$this->box(5, 7, $row, 7));
            } elseif ($an) {
                $this->anecdote($an, ...$this->box(0, 12, $row, 7));
            } else {
                $this->duel($rest, ...$this->box(0, 12, $row, 7));
            }
            $row += 7;
        }
        // E · le récit, F · la saison (3 rangées)
        $this->recit(...$this->box(0, 12, $row, self::ROWS - $bandF - $row));
        if ($bandF) {
            $this->season(...$this->box(0, 12, self::ROWS - 3, 3));
        }
        $this->footer();
        return $this->L;
    }

    /** Rangées qu'il faut au récit, sur quatre colonnes, au corps de lecture. */
    private function recitRows(): int
    {
        $iw = self::W - 2 * self::PAD - 9;
        $cw = ($iw - 3 * 5.5) / 4;
        $size = self::pt(13.5);
        $lines = Vector::layout($this->text(0, 0, $cw, $this->d['recit'], 'serif', $size, self::NAVY, ['lh' => 1.36, 'dry' => true]))['lines'];
        $h = ceil(count($lines) / 4) * $size * 25.4 / 72 * 1.36 + 15;
        return max(4, (int) ceil(($h + self::GAP) / ($this->u + self::GAP)));
    }

    /** Cadre d'un bloc : [x, y, w, h] en mm. */
    private function box(int $col, int $span, int $row, int $rows): array
    {
        return [
            self::PAD + $col * ($this->cw + self::GAP),
            $this->top + $row * ($this->u + self::GAP),
            $span * $this->cw + ($span - 1) * self::GAP,
            $rows * $this->u + ($rows - 1) * self::GAP,
        ];
    }

    // ------------------------------------------------------------------ primitives

    private function id(): string
    {
        return 'p' . (++$this->n);
    }

    private function rect(float $x, float $y, float $w, float $h, string $fill = '', string $stroke = '', float $sw = 0, float $r = 0): void
    {
        $this->L[] = ['id' => $this->id(), 'type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'fill' => $fill, 'stroke' => $stroke, 'sw' => $sw, 'r' => $r];
    }

    private function circle(float $cx, float $cy, float $r, string $fill = '', string $stroke = '', float $sw = 0): void
    {
        $this->L[] = ['id' => $this->id(), 'type' => 'ellipse', 'x' => $cx - $r, 'y' => $cy - $r, 'w' => 2 * $r, 'h' => 2 * $r, 'fill' => $fill, 'stroke' => $stroke, 'sw' => $sw];
    }

    /** Calque de texte (o : align, upper, spacing, lh, h, fit, min, valign). */
    private function text(float $x, float $y, float $w, string $t, string $font, float $size, string $color, array $o = []): array
    {
        $l = ['id' => $this->id(), 'type' => 'text', 'x' => $x, 'y' => $y, 'w' => max(1.0, $w), 'text' => $t, 'font' => $font, 'size' => $size, 'color' => $color,
            'align' => $o['align'] ?? 'left', 'upper' => $o['upper'] ?? false, 'spacing' => $o['spacing'] ?? 0, 'lh' => $o['lh'] ?? 1.15,
            'h' => $o['h'] ?? 0, 'fit' => $o['fit'] ?? false, 'min' => $o['min'] ?? max(2.0, $size * 0.5), 'valign' => $o['valign'] ?? 'top', 'mode' => 'fixed'];
        if (empty($o['dry'])) {
            $this->L[] = $l;
        }
        return $l;
    }

    /** Largeur d'un texte (mm). */
    private static function width(string $t, string $font, float $size, float $spacing = 0): float
    {
        $f = Vector::font($font);
        return ($f->width($t) / 1000 + $spacing / 1000 * max(0, mb_strlen($t) - 1)) * $size * 25.4 / 72;
    }

    /** Hauteur d'un calque de texte (mm), après réduction éventuelle. */
    private static function height(array $l): float
    {
        return Vector::textHeight($l);
    }

    private function kicker(float $x, float $y, float $w, string $t, string $color = self::YELLOW): float
    {
        $s = self::pt(15);
        $this->text($x, $y, $w, $t, 'display-b', $s, $color, ['upper' => true, 'spacing' => 200, 'fit' => true, 'min' => $s * 0.6]);
        return $y + $s * 25.4 / 72 * 1.15 + 2.2;
    }

    private function panel(float $x, float $y, float $w, float $h, string $fill = self::BOX, string $stroke = self::LINE, float $sw = 0.5): void
    {
        $this->rect($x, $y, $w, $h, $fill, $stroke, $sw);
    }

    /** Segments de texte en ligne : [[texte, police, couleur]], centrés ou alignés à gauche. */
    private function row(array $segs, float $x, float $y, float $w, float $size, float $spacing, string $align = 'left'): void
    {
        $sw = fn (array $s, float $sz) => $s[1] === 'dot' ? $sz * 0.55 : self::width(mb_strtoupper($s[0]), $s[1], $sz, $spacing);
        $total = 0;
        foreach ($segs as $s) {
            $total += $sw($s, $size);
        }
        $k = $total > $w ? $w / $total : 1;
        $size *= $k;
        $total *= $k;
        $pen = $align === 'center' ? $x + ($w - $total) / 2 : ($align === 'right' ? $x + $w - $total : $x);
        foreach ($segs as $s) {
            $w1 = $sw($s, $size);
            if ($s[1] === 'dot') {
                // Séparateur : un petit rond (la police n'a pas d'étoile).
                $this->circle($pen + $w1 / 2, $y + $size * 25.4 / 72 * 0.5, $size * 0.045, $s[2]);
            } else {
                $this->text($pen, $y, $w1 + 1, $s[0], $s[1], $size, $s[2], ['upper' => true, 'spacing' => $spacing]);
            }
            $pen += $w1;
        }
    }

    private static function initials(string $name): string
    {
        $p = array_values(array_filter(preg_split('/[\s-]+/u', $name) ?: []));
        return mb_strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr(count($p) > 1 ? end($p) : '', 0, 1));
    }

    // ------------------------------------------------------------------ en-tête et pied

    private function header(): void
    {
        $s = self::pt(16);
        $y = self::PAD;
        $pour = 'pour ' . $this->pour;
        $left = [['Sochaux Rétro · ', 'display-b', self::MIST], ['le musée des Lionceaux', 'display-b', self::YELLOW]];
        $lw = 0;
        foreach ($left as $seg) {
            $lw += self::width(mb_strtoupper($seg[0]), 'display-b', $s, 220);
        }
        $this->row($left, self::PAD, $y, 170, $s, 220);
        // Cartouche « pour Prénom Nom ».
        $px = self::PAD + min(170, $lw) + 3;
        $date = implode(' · ', array_reverse(explode('-', (string) $this->d['date'])));
        $dw = self::width($date, 'display-b', $s, 220);
        $room = self::W - self::PAD - $dw - 6 - $px;
        $tw = min($room - 5, self::width(mb_strtoupper($pour), 'display-b', $s, 160));
        $this->rect($px, $y - 1.2, $tw + 5, $s * 25.4 / 72 + 2.4, self::YELLOW);
        $this->text($px + 2.5, $y, $tw + 0.5, $pour, 'display-b', $s, self::NAVY, ['upper' => true, 'spacing' => 160, 'fit' => true, 'min' => $s * 0.5]);
        $this->text(self::W - self::PAD - $dw - 1, $y, $dw + 1, $date, 'display-b', $s, self::MIST, ['spacing' => 220, 'align' => 'right']);
    }

    private function footer(): void
    {
        $s = self::pt(13);
        $y = self::H - self::PAD - 3.6;
        $this->text(self::PAD, $y, 200, 'Faits, citations et temps forts tirés de la fiche du match et de son récit · musée Sochaux Rétro', 'serif-i', $s, self::MIST);
        $this->text(self::W - self::PAD - 70, $y, 70, 'Poster A3 · 297 × 420 mm', 'serif-i', $s, self::MIST, ['align' => 'right']);
    }

    // ------------------------------------------------------------------ blocs

    private function hero(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        $this->rect($x, $y, $w, $h, self::NAVY, self::YELLOW, 0.8);
        $ix = $x + 5;
        $iw = $w - 10;
        $this->kicker($ix, $y + 3.2, $iw, $d['kicker']);
        // Score : deux ronds jaunes cerclés, un tiret entre les deux.
        $r = 10.0;
        $cy = $y + $h * 0.5 + 0.8;
        $cx = $x + $w / 2;
        $c1 = $cx - $r - 4.5;
        $c2 = $cx + $r + 4.5;
        foreach ([[$c1, $d['sh']], [$c2, $d['sa']]] as [$c, $v]) {
            $this->circle($c, $cy, $r + 1.75, '', self::YELLOW, 0.75);
            $this->circle($c, $cy, $r, self::YELLOW);
            $ns = self::pt(64) * (strlen((string) $v) > 1 ? 0.75 : 1);
            $this->text($c - $r, $cy - $ns * 25.4 / 72 * 0.62, 2 * $r, (string) $v, 'display', $ns, self::NAVY, ['align' => 'center', 'lh' => 1]);
        }
        $this->rect($cx - 2.5, $cy - 0.9, 5, 1.8, self::CREAM);
        $ts = self::pt(66);
        $tw = $c1 - $r - 4 - $ix;
        foreach ([[$ix, $d['home'], 'right'], [$c2 + $r + 4, $d['away'], 'left']] as [$tx, $name, $al]) {
            $l = $this->text($tx, 0, $tw, $name, 'display', $ts, self::CREAM, ['upper' => true, 'align' => $al, 'fit' => true, 'min' => 18, 'lh' => 1, 'dry' => true]);
            $sz = Vector::layout($l)['size'];
            $l['y'] = $cy - $sz * 25.4 / 72 * 0.62;
            $this->L[] = $l;
        }
        $sub = [[$d['when'], 'display-b', self::CREAM]];
        foreach (array_filter(explode(' · ', $d['extra'])) as $e) {
            $sub[] = ['', 'dot', self::YELLOW];
            $sub[] = [$e, 'display-b', self::CREAM];
        }
        $this->row($sub, $ix, $y + $h - 7.2, $iw, self::pt(17), 140, 'center');
    }

    private function crest(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::BLUE, self::LINE, 0.5);
        $lw = min($w * 0.42, ($h - 22) / 1.133);
        $this->L[] = ['id' => $this->id(), 'type' => 'logo', 'x' => $x + ($w - $lw) / 2, 'y' => $y + 3.5, 'w' => $lw, 'style' => 'couleurs', 'color' => '#FDC729'];
        $sw = $w * 0.7;
        $sx = $x + ($w - $sw) / 2;
        $sy = $y + 3.5 + $lw * 1.133 + 2.5;
        $sh = $y + $h - 3 - $sy;
        $this->rect($sx, $sy, $sw, $sh, '', self::YELLOW, 0.75);
        $this->text($sx, $sy + 1.6, $sw, 'Pièce unique', 'display-b', self::pt(14), self::YELLOW, ['upper' => true, 'spacing' => 140, 'align' => 'center', 'fit' => true]);
        $no = $this->no !== '' ? 'N° ' . $this->no : 'N° à la commande';
        $this->text($sx, $sy + 1.6 + self::pt(14) * 25.4 / 72 * 1.2, $sw, $no, 'display', self::pt(26), self::YELLOW, ['upper' => true, 'spacing' => 80, 'align' => 'center', 'fit' => true]);
    }

    private function pitch(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $coach = $d['coach'] !== '' ? 'Les onze de ' . $d['coach'] : 'Le onze de départ';
        $ty = $this->kicker($ix, $y + 4, $iw, $coach . ($d['formation'] !== '' ? ' · ' . $d['formation'] : ''));
        $cap = null;
        foreach ($d['players'] as $p) {
            if (!empty($p['captain'])) {
                $cap = $p;
            }
        }
        $noteH = $cap ? 7 : 0;
        $px = $ix;
        $py = $ty;
        $pw = $iw;
        $ph = $y + $h - 4 - $noteH - $py;
        $this->rect($px, $py, $pw, $ph, self::BLUE, self::CREAM, 0.75);
        for ($sy = $py + 0.4, $i = 0; $sy < $py + $ph - 0.5; $sy += 11, $i++) {
            if ($i % 2 === 0) {
                $this->rect($px + 0.4, $sy, $pw - 0.8, min(5.5, $py + $ph - 0.4 - $sy), self::STRIPE);
            }
        }
        $in = 3.5;
        $this->rect($px + $in, $py + $in, $pw - 2 * $in, $ph - 2 * $in, '', self::PITCHLINE, 0.5);
        $this->rect($px + $in, $py + $ph / 2 - 0.25, $pw - 2 * $in, 0.5, self::PITCHLINE);
        $cr = $pw * 0.14;
        $this->circle($px + $pw / 2, $py + $ph / 2, $cr, '', self::PITCHLINE, 0.5);
        $bw = $pw * 0.46;
        $bh = $ph * 0.13;
        $this->rect($px + ($pw - $bw) / 2, $py + $in, $bw, $bh, '', self::PITCHLINE, 0.5);
        $this->rect($px + ($pw - $bw) / 2, $py + $ph - $in - $bh, $bw, $bh, '', self::PITCHLINE, 0.5);
        // Les joueurs : un rond jaune numéroté, le nom sur une étiquette.
        $r = 5.2;
        $ns = self::pt(16);
        // Le gardien en bas ; les joueurs de champ répartis sur le reste du terrain.
        $field = array_filter($d['players'], fn ($p) => strtoupper((string) ($p['position'] ?? '')) !== 'G');
        $ys = array_map(fn ($p) => (float) $p['y'], $field ?: $d['players']);
        [$y0, $y1] = [min($ys), max($ys)];
        $gy = $py + $ph - 15;
        foreach ($d['players'] as $p) {
            $cx = $px + 7 + ($pw - 14) * ((float) $p['x'] - 10) / 80;
            $cy = strtoupper((string) ($p['position'] ?? '')) === 'G' ? $gy
                : $py + 8.5 + ($gy - 19.5 - $py - 8.5) * ($y1 > $y0 ? ((float) $p['y'] - $y0) / ($y1 - $y0) : 0.5);
            $this->circle($cx, $cy, $r + 0.6, self::NAVY);
            $this->circle($cx, $cy, $r, !empty($p['captain']) ? self::CREAM : self::YELLOW);
            $num = (string) ($p['num'] ?? $p['n'] ?? '');
            $this->text($cx - $r, $cy - 2.6, 2 * $r, $num, 'display', self::pt(24), self::NAVY, ['align' => 'center', 'lh' => 1]);
            $name = mb_strtoupper((string) ($p['short'] ?? $p['display'] ?? '')) . (!empty($p['captain']) ? ' (C)' : '');
            $nw = min($pw * 0.42, self::width($name, 'display-b', $ns, 0) + 3);
            $nx = max($px + 0.8, min($px + $pw - 0.8 - $nw, $cx - $nw / 2));
            $ny = $cy + $r + 1;
            $this->rect($nx, $ny, $nw, $ns * 25.4 / 72 + 1.3, self::NAVY);
            $this->text($nx + 1.5, $ny + 0.45, $nw - 3, $name, 'display-b', $ns, self::CREAM, ['align' => 'center', 'fit' => true, 'min' => $ns * 0.6, 'lh' => 1.1]);
        }
        if ($cap) {
            $this->text($ix, $y + $h - 4 - $noteH + 2, $iw, 'Capitaine : ' . ($cap['display'] ?? ''), 'serif-i', self::pt(15), self::CREAM, ['fit' => true]);
        }
    }

    private function film(float $x, float $y, float $w, float $h): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Le film du match');
        $items = $this->d['film'];
        $avail = $y + $h - 4 - $ty;
        $r = 5.2;
        $tx = $ix + 2 * $r + 3;
        $tw = $ix + $iw - $tx;
        // Le plus grand corps où tout tient ; sinon on retire les temps forts sans but.
        for ($size = self::pt(15); ; $size -= 0.4) {
            $hs = [];
            foreach ($items as $it) {
                $hs[] = max(2 * $r, self::height($this->text($tx, 0, $tw, $it['text'], !empty($it['goal']) ? 'serif-b' : 'serif', $size, self::CREAM, ['lh' => 1.18, 'dry' => true])));
            }
            $total = array_sum($hs) + 1.4 * (count($hs) - 1);
            if ($total <= $avail) {
                break;
            }
            if ($size < self::pt(12.5)) {
                $drop = null;
                foreach ($items as $i => $it) {
                    if (empty($it['goal']) && empty($it['end'])) {
                        $drop = $i;
                    }
                }
                if ($drop === null) {
                    break;
                }
                array_splice($items, $drop, 1);
                $size = self::pt(15) + 0.4;
            }
        }
        $gap = count($hs) > 1 ? min(5.0, 1.4 + ($avail - $total) / (count($hs) - 1)) : 0;
        $yy = $ty;
        $centers = [];
        foreach ($items as $i => $it) {
            $centers[] = $yy + $hs[$i] / 2;
            $yy += $hs[$i] + $gap;
        }
        if (count($centers) > 1) {
            $this->rect($ix + $r - 0.4, $centers[0], 0.8, end($centers) - $centers[0], self::RULE);
        }
        foreach ($items as $i => $it) {
            $cy = $centers[$i];
            $goal = !empty($it['goal']);
            $end = !empty($it['end']);
            $this->circle($ix + $r, $cy, $r, $goal ? self::YELLOW : self::NAVY, $end ? self::CREAM : self::YELLOW, 0.75);
            $m = (string) $it['min'];
            $ms = self::pt(19) * (mb_strlen($m) > 3 ? 0.78 : 1);
            $this->text($ix, $cy - $ms * 25.4 / 72 * 0.55, 2 * $r, $m, 'display', $ms, $goal ? self::NAVY : ($end ? self::CREAM : self::YELLOW), ['align' => 'center', 'lh' => 1]);
            $l = $this->text($tx, 0, $tw, $it['text'], $goal ? 'serif-b' : 'serif', $size, $goal ? self::YELLOW : self::CREAM, ['lh' => 1.18, 'dry' => true]);
            $l['y'] = $cy - self::height($l) / 2;
            $this->L[] = $l;
        }
    }

    private function crowd(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::YELLOW);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Tribunes', self::NAVY);
        $n = number_format($this->d['spectators'], 0, ',', ' ');
        $l = $this->text($ix, $ty, $iw, $n, 'display', self::pt(72), self::NAVY, ['fit' => true, 'lh' => 0.95, 'dry' => true]);
        $this->L[] = $l;
        $nh = Vector::layout($l)['size'] * 25.4 / 72 * 0.95;
        $this->text($ix, $ty + $nh + 1.2, $iw, 'spectateurs', 'display-b', self::pt(17), self::NAVY, ['upper' => true, 'spacing' => 140]);
    }

    /** Citation : grandes capitales, trait jaune, pastille aux initiales. */
    private function quote(array $q, float $x, float $y, float $w, float $h, string $kick = 'Ils ont dit'): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, $kick);
        // Plus le bloc est large, plus la citation peut être grande.
        $this->quoteBody($q, $ix, $ty + 1, $iw, $y + $h - 4 - $ty - 1, self::pt($w > 120 ? 40 : 27));
    }

    private function quoteBody(array $q, float $x, float $y, float $w, float $h, float $size, string $side = ''): void
    {
        $whoH = 11;
        if ($side !== '') {
            $this->text($x, $y, $w, $side, 'display', self::pt(12), self::MIST, ['upper' => true, 'spacing' => 200]);
            $y += 5;
            $h -= 5;
        }
        $qh = $h - $whoH - 2;
        $l = $this->text($x + 4, $y, $w - 4, $q['text'], 'display', $size, self::CREAM, ['upper' => true, 'lh' => 1.04, 'h' => $qh, 'fit' => true, 'min' => self::pt(12), 'valign' => 'middle', 'dry' => true]);
        $this->L[] = $l;
        $lay = Vector::layout($l);
        $th = count($lay['lines']) * $lay['size'] * 25.4 / 72 * 1.04;
        $this->rect($x, $y + ($qh - $th) / 2, 1.5, $th, self::YELLOW);
        $wy = $y + $h - $whoH + 1;
        $this->circle($x + 5, $wy + 5, 5, self::YELLOW);
        $this->text($x, $wy + 5 - 2.1, 10, self::initials($q['who']), 'display', self::pt(17), self::NAVY, ['align' => 'center', 'lh' => 1]);
        $this->text($x + 12.5, $wy + 1.2, $w - 12.5, $q['who'], 'display-b', self::pt(14), self::CREAM, ['upper' => true, 'spacing' => 100, 'fit' => true]);
        if (($q['role'] ?? '') !== '') {
            $this->text($x + 12.5, $wy + 5.6, $w - 12.5, $q['role'], 'serif-i', self::pt(13), self::MIST, ['fit' => true]);
        }
    }

    /** Une ou deux citations côte à côte (« face à face »). */
    private function duel(array $qs, float $x, float $y, float $w, float $h): void
    {
        if (count($qs) === 1) {
            $this->quote($qs[0], $x, $y, $w, $h);
            return;
        }
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Face à face · ils ont dit');
        $cw = ($iw - 8) / 2;
        $bh = $y + $h - 4 - $ty;
        $opp = $this->d['dm']['opp'] ?? '';
        foreach ($qs as $i => $q) {
            $side = match ($q['side'] ?? '') { 'sochaux' => 'Côté Sochaux', 'adversaire' => $opp !== '' ? 'Côté ' . $opp : '', default => '' };
            $this->quoteBody($q, $ix + $i * ($cw + 8), $ty, $cw, $bh, self::pt(23), $side);
        }
        for ($dy = $ty; $dy < $y + $h - 5; $dy += 2.4) {
            $this->rect($ix + $cw + 3.6, $dy, 0.5, 1.2, self::LINE);
        }
    }

    private function anecdote(array $a, float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::YELLOW);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'L’anecdote', self::NAVY);
        $avail = $y + $h - 4 - $ty;
        $title = $this->text($ix, $ty, $iw, $a['title'], 'display', self::pt(38), self::NAVY, ['upper' => true, 'lh' => 0.92, 'h' => $avail * 0.42, 'fit' => true, 'min' => self::pt(20), 'dry' => true]);
        $this->L[] = $title;
        $th = self::height($title);
        $this->text($ix, $ty + $th + 2, $iw, $a['text'], 'serif', self::pt($w > 120 ? 26 : 21), self::NAVY, ['lh' => 1.28, 'h' => $avail - $th - 2, 'fit' => true, 'min' => self::pt(11), 'valign' => 'middle']);
    }

    private function figure(float $x, float $y, float $w, float $h): void
    {
        $f = $this->d['figure'];
        $this->rect($x, $y, $w, $h, self::NAVY, self::CREAM, 0.75);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Le chiffre');
        $avail = $y + $h - 4 - $ty;
        $r = min(13.0, $avail / 2);
        $cy = $ty + $avail / 2;
        $this->circle($ix + $r, $cy, $r, self::YELLOW);
        $l = $this->text($ix + 1.5, 0, 2 * $r - 3, $f['n'], 'display', self::pt(76), self::NAVY, ['align' => 'center', 'fit' => true, 'lh' => 1, 'dry' => true]);
        $l['y'] = $cy - Vector::layout($l)['size'] * 25.4 / 72 * 0.6;
        $this->L[] = $l;
        $tx = $ix + 2 * $r + 5;
        $this->text($tx, $ty, $ix + $iw - $tx, $f['text'], 'serif', self::pt(18), self::CREAM, ['lh' => 1.3, 'h' => $avail, 'fit' => true, 'min' => self::pt(11), 'valign' => 'middle']);
    }

    private function enjeu(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Avant le match', self::BLUE);
        $this->text($ix, $ty, $iw, $this->d['enjeu'], 'serif', self::pt(21), self::NAVY, ['lh' => 1.32, 'h' => $y + $h - 4 - $ty, 'fit' => true, 'min' => self::pt(12), 'valign' => 'middle']);
    }

    /** Fiche technique : stade, arbitre, spectateurs, entraîneur, système. */
    private function facts(float $x, float $y, float $w, float $h): void
    {
        $this->used['facts'] = true;
        $d = $this->d;
        $mt = (array) (\App\Data\Fiches::get((int) $d['id'])['match'] ?? []);
        $rows = array_filter([
            'Stade' => trim((string) preg_replace('/\s*\(\d+\)\s*$/', '', (string) ($mt['stadium'] ?? ''))),
            'Arbitre' => (string) ($mt['referee'] ?? ''),
            'Entraîneur' => $d['coach'],
            'Système' => $d['formation'],
            'Saison' => (string) ($d['dm']['season'] ?? ''),
        ]);
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'La fiche du match', self::BLUE);
        $rh = min(9.0, ($y + $h - 4 - $ty) / max(1, count($rows)));
        foreach (array_values($rows) as $i => $v) {
            $k = array_keys($rows)[$i];
            $ry = $ty + $i * $rh;
            $this->text($ix, $ry, $iw * 0.36, $k, 'display-b', self::pt(14), self::BLUE, ['upper' => true, 'spacing' => 120, 'fit' => true]);
            $this->text($ix + $iw * 0.38, $ry - 0.3, $iw * 0.62, $v, 'serif-b', self::pt(16), self::NAVY, ['fit' => true]);
            $this->rect($ix, $ry + $rh - 1.6, $iw, 0.25, '#D9CFB8');
        }
    }

    /** Le récit, en colonnes justifiées à gauche, au plus grand corps qui tient. */
    private function recit(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Le récit du match · d’après la fiche du musée', self::BLUE);
        $avail = $y + $h - 4 - $ty;
        $gap = 5.5;
        $text = $this->d['recit'];
        $lh = 1.36;
        $lines = [];
        $per = 0;
        $cols = 4;
        $cw = 0;
        // Le plus grand corps qui tient (le récit court remplit sa place en gros, sur trois colonnes).
        for ($size = self::pt(14); $size >= self::pt(9.4); $size -= 0.2) {
            $cols = 4;
            $cw = ($iw - ($cols - 1) * $gap) / $cols;
            $lines = Vector::layout($this->text(0, 0, $cw, $text, 'serif', $size, self::NAVY, ['lh' => $lh, 'dry' => true]))['lines'];
            $per = (int) floor($avail / ($size * 25.4 / 72 * $lh));
            if (count($lines) <= $per * $cols) {
                break;
            }
        }
        $size = max($size, self::pt(9.4));
        if (count($lines) > $per * $cols) {
            // Trop long même petit : on s'arrête à la dernière phrase entière qui tient.
            $keep = implode(' ', array_slice($lines, 0, $per * $cols));
            $cut = max(mb_strrpos($keep, '. ') ?: 0, mb_strrpos($keep, '! ') ?: 0);
            $text = $cut > 200 ? mb_substr($keep, 0, $cut + 1) : rtrim($keep, ' ,;') . '…';
            $lines = Vector::layout($this->text(0, 0, $cw, $text, 'serif', $size, self::NAVY, ['lh' => $lh, 'dry' => true]))['lines'];
        }
        // Colonnes équilibrées.
        $per = (int) ceil(count($lines) / $cols);
        for ($c = 0; $c < $cols; $c++) {
            $chunk = array_slice($lines, $c * $per, $per);
            if ($chunk) {
                $this->text($ix + $c * ($cw + $gap), $ty, $cw + 0.5, implode("\n", $chunk), 'serif', $size, self::NAVY, ['lh' => $lh]);
            }
        }
    }

    private function season(float $x, float $y, float $w, float $h): void
    {
        $s = $this->d['season'];
        $this->rect($x, $y, $w, $h, self::YELLOW);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 2.6, $iw * 0.7, 'Saison ' . $s['season'] . ' · Sochaux ' . $s['level'], self::NAVY);
        $px = $ix;
        foreach ([[$s['w'], 'victoires'], [$s['d'], 'nuls'], [$s['l'], 'défaites'], [$s['gf'], 'buts marqués'], [$s['ga'], 'encaissés']] as [$n, $lab]) {
            $ns = self::pt(34);
            $nw = self::width((string) $n, 'display', $ns);
            $lw = self::width(mb_strtoupper($lab), 'display-b', self::pt(13), 100);
            $this->text($px, $ty - 1, $nw + 1, (string) $n, 'display', $ns, self::NAVY, ['lh' => 0.9]);
            $this->text($px, $ty - 1 + $ns * 25.4 / 72 * 0.9 + 0.3, $lw + 1, $lab, 'display-b', self::pt(13), self::NAVY, ['upper' => true, 'spacing' => 100]);
            $px += max($nw, $lw) + 9;
        }
        if ($this->d['opp_level'] !== '' && ($this->d['dm']['opp'] ?? '') !== '') {
            $t = 'Adversaire du jour : ' . $this->d['dm']['opp'] . ' (' . $this->d['opp_level'] . ')';
            $this->text($ix + $iw * 0.5, $y + $h - 7.5, $iw * 0.5, $t, 'serif-i', self::pt(17), self::NAVY, ['align' => 'right', 'fit' => true]);
        }
    }
}
