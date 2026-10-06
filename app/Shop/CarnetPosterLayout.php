<?php
declare(strict_types=1);

namespace App\Shop;

/**
 * Mise en page du poster « Ma vie en jaune et bleu » (voir CarnetPoster) : la grille, les couleurs
 * et les briques des posters du musée (PosterLayout), avec les blocs du carnet : le nombre de
 * matchs en grand, le bilan, les saisons au stade, le porte-bonheur, les badges, les grands matchs,
 * les buteurs et les joueurs vus, les adversaires.
 */
final class CarnetPosterLayout extends PosterLayout
{
    public function build(): array
    {
        $this->L = [];
        $this->rect(0, 0, self::W, self::H, self::NAVY);
        $this->header();
        $this->top = self::PAD + 9;
        $bottom = self::H - self::PAD - 6;
        $this->u = (($bottom - $this->top) - self::GAP * (self::ROWS - 1)) / self::ROWS;
        $this->cw = (self::W - 2 * self::PAD - 11 * self::GAP) / 12;
        $d = $this->d;

        // A · le nombre de matchs et le blason (pièce unique, numérotée)
        $this->hero(...$this->box(0, 8, 0, 7));
        $this->crest(...$this->box(8, 4, 0, 7));
        // B · le bilan
        $this->numbers(...$this->box(0, 12, 7, 4));
        // C · les saisons au stade ; le porte-bonheur et les badges
        $this->seasons(...$this->box(0, 7, 11, 11));
        $this->luck(...$this->box(7, 5, 11, 4));
        $this->badges(...$this->box(7, 5, 15, 7));
        // D · les grands matchs ; les buteurs vus
        $this->bigMatches(...$this->box(0, 7, 22, 10));
        $this->ranking(...[...$this->box(7, 5, 22, 10), 'Les buteurs que j’ai vus marquer', $this->top5($d['scorers']), 'buts']);
        // E · les joueurs vus ; les adversaires
        $this->ranking(...[...$this->box(0, 6, 32, 8), 'Les Lionceaux que j’ai le plus vus', array_map(fn ($p) => [$p['name'], $p['n']], array_slice($d['players'], 0, 5)), 'matchs']);
        $this->ranking(...[...$this->box(6, 6, 32, 8), 'Mes adversaires', $this->top5($d['opps']), 'fois']);
        // F · domicile, déplacements, décennies
        $this->band(...$this->box(0, 12, 40, 3));
        $this->footer();
        return $this->L;
    }

    /** @return list<array{0:string,1:int}> */
    private function top5(array $m): array
    {
        $out = [];
        foreach (array_slice($m, 0, 5, true) as $k => $n) {
            $out[] = [(string) $k, (int) $n];
        }
        return $out;
    }

    // ------------------------------------------------------------------ en-tête et pied

    protected function header(): void
    {
        $s = self::pt(16);
        $y = self::PAD;
        $pour = 'pour ' . $this->pour;
        $left = [['Sochaux Rétro · ', 'display-b', self::MIST], ['carnet du supporter', 'display-b', self::YELLOW]];
        $lw = 0;
        foreach ($left as $seg) {
            $lw += self::width(mb_strtoupper($seg[0]), 'display-b', $s, 220);
        }
        $this->row($left, self::PAD, $y, 170, $s, 220);
        $px = self::PAD + min(170, $lw) + 3;
        $right = $this->d['years'] !== '' ? 'Au stade · ' . $this->d['years'] : 'Au stade';
        $dw = self::width(mb_strtoupper($right), 'display-b', $s, 220);
        $room = self::W - self::PAD - $dw - 6 - $px;
        $tw = min($room - 5, self::width(mb_strtoupper($pour), 'display-b', $s, 160));
        $this->rect($px, $y - 1.2, $tw + 5, $s * 25.4 / 72 + 2.4, self::YELLOW);
        $this->text($px + 2.5, $y, $tw + 0.5, $pour, 'display-b', $s, self::NAVY, ['upper' => true, 'spacing' => 160, 'fit' => true, 'min' => $s * 0.5]);
        $this->text(self::W - self::PAD - $dw - 1, $y, $dw + 1, $right, 'display-b', $s, self::MIST, ['upper' => true, 'spacing' => 220, 'align' => 'right']);
    }

    protected function footer(): void
    {
        $s = self::pt(13);
        $y = self::H - self::PAD - 3.6;
        $this->text(self::PAD, $y, 200, 'Matchs cochés dans le carnet du supporter ; scores, buteurs et compositions du musée Sochaux Rétro', 'serif-i', $s, self::MIST);
        $this->text(self::W - self::PAD - 70, $y, 70, 'Poster A3 · 297 × 420 mm', 'serif-i', $s, self::MIST, ['align' => 'right']);
    }

    // ------------------------------------------------------------------ blocs

    /** « Ma vie en jaune et bleu » : le nombre de matchs en très grand. */
    protected function hero(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        $this->rect($x, $y, $w, $h, self::NAVY, self::YELLOW, 0.8);
        $ix = $x + 5;
        $iw = $w - 10;
        $ty = $this->kicker($ix, $y + 3.6, $iw, 'Ma vie en jaune et bleu');
        $num = (string) $d['n'];
        $l = $this->text($ix, $ty - 1, $iw * 0.5, $num, 'display', self::pt(220), self::YELLOW, ['fit' => true, 'lh' => 0.86, 'h' => $y + $h - 4 - $ty, 'dry' => true]);
        $this->L[] = $l;
        $nw = min($iw * 0.5, self::width($num, 'display', \App\Shop\Vector::layout($l)['size']));
        $tx = $ix + $nw + 4;
        $this->text($tx, $ty + 4, $ix + $iw - $tx, $d['n'] > 1 ? 'matchs vus au stade' : 'match vu au stade', 'display', self::pt(44), self::CREAM, ['upper' => true, 'fit' => true, 'lh' => 0.95, 'h' => 22]);
        $where = $d['home'] && $d['away'] ? 'à Bonal et en déplacement' : ($d['away'] ? 'en déplacement' : 'à Bonal');
        $sub = $d['years'] !== '' ? (str_contains($d['years'], '-') ? 'De ' . str_replace('-', ' à ', $d['years']) : 'En ' . $d['years']) . ', ' . $where : ucfirst($where);
        $this->text($tx, $y + $h - 11, $ix + $iw - $tx, $sub, 'serif-i', self::pt(19), self::MIST, ['fit' => true]);
    }

    /** Le bilan : victoires, nuls, défaites, buts vus. */
    private function numbers(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        $this->rect($x, $y, $w, $h, self::YELLOW);
        $items = [[$d['v'], $d['v'] > 1 ? 'victoires' : 'victoire'], [$d['nul'], $d['nul'] > 1 ? 'nuls' : 'nul'], [$d['d'], $d['d'] > 1 ? 'défaites' : 'défaite'], [$d['gf'], 'buts du FCSM vus']];
        $cw = ($w - 9) / count($items);
        foreach ($items as $i => [$n, $lab]) {
            $cx = $x + 4.5 + $i * $cw;
            if ($i > 0) {
                $this->rect($cx - 0.3, $y + 4, 0.6, $h - 8, self::NAVY);
            }
            $l = $this->text($cx + 4, $y + 2.2, $cw - 8, number_format((int) $n, 0, ',', ' '), 'display', self::pt(84), self::NAVY, ['fit' => true, 'lh' => 0.92, 'dry' => true]);
            $this->L[] = $l;
            $nh = \App\Shop\Vector::layout($l)['size'] * 25.4 / 72 * 0.92;
            $this->text($cx + 4, $y + 2.2 + $nh + 0.4, $cw - 8, $lab, 'display-b', self::pt(17), self::NAVY, ['upper' => true, 'spacing' => 140, 'fit' => true]);
        }
    }

    /** Les saisons au stade : un bâton par saison (matchs vus), les victoires en jaune. */
    private function seasons(float $x, float $y, float $w, float $h): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Mes saisons au stade');
        $this->rect($ix, $ty + 0.6, 3.2, 3.2, self::CREAM);
        $this->text($ix + 4.6, $ty, 30, 'matchs vus', 'display-b', self::pt(13), self::CREAM, ['upper' => true, 'spacing' => 100]);
        $this->rect($ix + 38, $ty + 0.6, 3.2, 3.2, self::YELLOW);
        $this->text($ix + 42.6, $ty, 30, 'victoires', 'display-b', self::pt(13), self::YELLOW, ['upper' => true, 'spacing' => 100]);
        $ss = $this->d['seasons_list'];
        if (count($ss) > 24) {
            $ss = array_slice($ss, -24); // les 24 dernières saisons
        }
        $top = $ty + 8;
        $base = $y + $h - 10;
        $max = max(array_column($ss, 'm')) ?: 1;
        $n = count($ss);
        $slot = $iw / max(1, $n);
        $bw = min(9.0, $slot * 0.62);
        $vs = self::pt(13);
        $this->rect($ix, $base, $iw, 0.5, self::MIST);
        foreach ($ss as $i => $s) {
            $cx = $ix + $slot * ($i + 0.5);
            $bh = ($base - $top - 6) * $s['m'] / $max;
            $this->rect($cx - $bw / 2, $base - $bh, $bw, $bh, self::CREAM);
            if ($s['v'] > 0) {
                $vh = ($base - $top - 6) * $s['v'] / $max;
                $this->rect($cx - $bw / 2 + $bw * 0.2, $base - $vh, $bw * 0.6, $vh, self::YELLOW);
            }
            $this->text($cx - $slot / 2, $base - $bh - $vs * 25.4 / 72 - 1, $slot, (string) $s['m'], 'display-b', $vs, self::CREAM, ['align' => 'center', 'fit' => true, 'lh' => 1]);
            $lab = preg_match('/^\d{2}(\d{2})-\d{2}(\d{2})$/', $s['season'], $mm) ? $mm[1] . '-' . $mm[2] : $s['season'];
            $this->text($cx - $slot / 2, $base + 1.6, $slot, $lab, 'display-b', self::pt($n > 14 ? 10 : 13), self::MIST, ['align' => 'center', 'fit' => true, 'min' => self::pt(7), 'lh' => 1]);
        }
    }

    /** Le porte-bonheur : l'écart avec le club sur les mêmes saisons. */
    private function luck(float $x, float $y, float $w, float $h): void
    {
        $l = $this->d['luck'];
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Porte-bonheur ?', self::BLUE);
        if (!$l) {
            $this->text($ix, $ty, $iw, 'Encore quelques matchs, et le musée dira si vous portez chance au club.', 'serif-i', self::pt(15), self::NAVY, ['h' => $y + $h - 3 - $ty, 'fit' => true]);
            return;
        }
        $big = ($l['diff'] >= 0 ? '+' : '−') . abs($l['diff']) . ' pts';
        $bl = $this->text($ix, $ty - 0.5, $iw * 0.45, $big, 'display', self::pt(56), self::NAVY, ['fit' => true, 'lh' => 0.95, 'dry' => true]);
        $this->L[] = $bl;
        $this->text($ix + $iw * 0.47, $ty, $iw * 0.53, $l['mine'] . ' % de victoires sous mes yeux, ' . $l['club'] . ' % pour le club sur les mêmes saisons', 'serif', self::pt(14), self::NAVY, ['lh' => 1.2, 'h' => $y + $h - 3 - $ty, 'fit' => true, 'min' => self::pt(8)]);
    }

    /** Les badges obtenus. */
    private function badges(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::BLUE, self::LINE, 0.5);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $on = array_values(array_filter($this->d['badges'], fn ($b) => $b['on']));
        $ty = $this->kicker($ix, $y + 4, $iw, 'Mes badges · ' . count($on) . '/' . count($this->d['badges']));
        $cols = 2;
        $rows = max(1, (int) ceil(count($on) / $cols));
        $rh = min(8.5, ($y + $h - 3 - $ty) / $rows);
        $colw = $iw / $cols;
        foreach ($on as $i => $b) {
            $bx = $ix + ($i % $cols) * $colw;
            $by = $ty + intdiv($i, $cols) * $rh;
            if ($by + $rh > $y + $h - 1) {
                break;
            }
            $this->circle($bx + 1.6, $by + $rh * 0.38, 1.4, self::YELLOW);
            $this->text($bx + 4.5, $by, $colw - 5, $b['label'], 'display-b', self::pt(14), self::CREAM, ['upper' => true, 'spacing' => 60, 'fit' => true, 'min' => self::pt(8), 'lh' => 1]);
        }
    }

    /** Mes grands matchs : premier, plus belle victoire, plus forte affluence, dernier. */
    private function bigMatches(float $x, float $y, float $w, float $h): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Mes grands matchs');
        $items = $this->d['big'];
        $rh = ($y + $h - 3 - $ty) / max(1, count($items));
        foreach ($items as $i => $m) {
            $ry = $ty + $i * $rh;
            $this->text($ix, $ry, $iw, $m['label'] . ' · ' . implode('/', array_reverse(explode('-', $m['date']))), 'display-b', self::pt(13), self::YELLOW, ['upper' => true, 'spacing' => 100, 'fit' => true]);
            $this->text($ix, $ry + self::pt(13) * 25.4 / 72 + 1.2, $iw, $m['teams'], 'display', self::pt(22), self::CREAM, ['upper' => true, 'fit' => true, 'min' => self::pt(12), 'lh' => 1]);
            $this->text($ix, $ry + self::pt(13) * 25.4 / 72 + self::pt(22) * 25.4 / 72 + 2, $iw, $m['comp'], 'serif-i', self::pt(13), self::MIST, ['fit' => true]);
            if ($i < count($items) - 1) {
                $this->rect($ix, $ry + $rh - 1.2, $iw, 0.25, self::LINE);
            }
        }
    }

    /** Classement : nom et nombre (buteurs, joueurs, adversaires). @param list<array{0:string,1:int}> $items */
    private function ranking(float $x, float $y, float $w, float $h, string $title, array $items, string $unit): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, $title);
        if (!$items) {
            $this->text($ix, $ty, $iw, 'Pas encore dans les données du musée pour ces matchs.', 'serif-i', self::pt(14), self::MIST, ['h' => $y + $h - 3 - $ty, 'fit' => true]);
            return;
        }
        $rh = ($y + $h - 3 - $ty) / count($items);
        $fs = min(self::pt(22), $rh * 72 / 25.4 * 0.62);
        foreach ($items as $i => [$name, $n]) {
            $ry = $ty + $i * $rh;
            $this->text($ix, $ry, 6, (string) ($i + 1), 'display', $fs, self::YELLOW, ['lh' => 1]);
            $cnt = $n . ' ' . $unit;
            $cwid = self::width(mb_strtoupper($cnt), 'display-b', self::pt(13), 80) + 2;
            $this->text($ix + 7, $ry, $iw - 7 - $cwid, $name, 'display', $fs, self::CREAM, ['upper' => true, 'fit' => true, 'min' => self::pt(9), 'lh' => 1]);
            $this->text($ix + $iw - $cwid, $ry + $fs * 25.4 / 72 * 0.3, $cwid, $cnt, 'display-b', self::pt(13), self::YELLOW, ['upper' => true, 'spacing' => 80, 'align' => 'right']);
        }
    }

    /** Bandeau du bas : domicile, déplacements, décennies, saisons. */
    private function band(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        $dec = array_keys($d['decades']);
        $items = [
            'À Bonal' => $d['home'] . ' match' . ($d['home'] > 1 ? 's' : ''),
            'En déplacement' => $d['away'] . ' match' . ($d['away'] > 1 ? 's' : ''),
            'Saisons' => (string) count($d['seasons']),
            'Décennies' => $dec ? implode(', ', array_map(fn ($x) => 'années ' . substr((string) $x, 2) , $dec)) : '—',
        ];
        $this->rect($x, $y, $w, $h, self::YELLOW);
        $cw = ($w - 9) / count($items);
        $i = 0;
        foreach ($items as $lab => $v) {
            $cx = $x + 4.5 + $i * $cw;
            if ($i++ > 0) {
                $this->rect($cx - 0.3, $y + 2.5, 0.6, $h - 5, self::NAVY);
            }
            $this->text($cx + 3, $y + 2.4, $cw - 6, $lab, 'display-b', self::pt(14), self::BLUE, ['upper' => true, 'spacing' => 160, 'fit' => true]);
            $this->text($cx + 3, $y + 7.2, $cw - 6, $v, 'serif-b', self::pt(16), self::NAVY, ['fit' => true, 'min' => self::pt(8)]);
        }
    }
}
