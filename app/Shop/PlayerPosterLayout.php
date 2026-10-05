<?php
declare(strict_types=1);

namespace App\Shop;

/**
 * Mise en page du poster d'un joueur (voir PlayerPoster) : la même grille, les mêmes couleurs et les
 * mêmes briques que le poster d'un match (PosterLayout), avec ses propres blocs : le nom en grand,
 * les grands chiffres, la carrière saison par saison, la fiche d'identité, le palmarès, les grands
 * matchs, les records, l'anecdote, la citation, le récit et les jalons.
 */
final class PlayerPosterLayout extends PosterLayout
{
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

        $q = $d['quotes'][0] ?? null;
        $an = $d['anecdote'];
        $side = $d['records'] || $d['figure'] || $d['mates'];
        $bandE = $an || $q || $d['portrait'] !== '' ? 6 : 0;
        $bandG = $d['milestones'] ? 3 : 0;
        $big = $d['big'] ? 7 : 0;
        // Le récit prend ce qui reste ; s'il est court, la carrière s'agrandit.
        $free = self::ROWS - 6 - 4 - 11 - $big - $bandE - $bandG;
        $extra = max(0, min(6, $free - $this->recitRows()));
        $c = 11 + $extra;
        // A · le nom et le blason
        $this->hero(...$this->box(0, 8, 0, 6));
        $this->crest(...$this->box(8, 4, 0, 6));
        // B · les grands chiffres
        $this->numbers(...$this->box(0, 12, 6, 4));
        // C · la carrière, la fiche d'identité et le palmarès
        $row = 10;
        $this->career(...$this->box(0, 7, $row, $c));
        if ($d['honours']) {
            $ih = (int) max(5, round($c * 0.55));
            $this->identity(...$this->box(7, 5, $row, $ih));
            $this->honours(...$this->box(7, 5, $row + $ih, $c - $ih));
        } else {
            $this->identity(...$this->box(7, 5, $row, $c));
        }
        $row += $c;
        // D · les grands matchs et les records (ou le chiffre, ou les coéquipiers)
        if ($big) {
            $this->bigMatches(...$this->box(0, $side ? 7 : 12, $row, 7));
            if ($side) {
                $this->side(...$this->box(7, 5, $row, 7));
            }
            $row += 7;
        }
        // E · l'anecdote et la citation (ou le portrait)
        if ($bandE) {
            $right = $q ? fn ($x, $y, $w, $h) => $this->quote($q, $x, $y, $w, $h, 'Ils ont dit') : ($d['portrait'] !== '' ? fn ($x, $y, $w, $h) => $this->portrait($x, $y, $w, $h) : null);
            if ($an && $right) {
                $this->anecdote($an, ...$this->box(0, 5, $row, 6));
                $right(...$this->box(5, 7, $row, 6));
            } elseif ($an) {
                $this->anecdote($an, ...$this->box(0, 12, $row, 6));
            } else {
                $right(...$this->box(0, 12, $row, 6));
            }
            $row += 6;
        }
        // F · le récit, G · les jalons
        $this->recit(...$this->box(0, 12, $row, self::ROWS - $bandG - $row));
        if ($bandG) {
            $this->milestones(...$this->box(0, 12, self::ROWS - 3, 3));
        }
        $this->footer();
        return $this->L;
    }

    protected function recitKicker(): string
    {
        return 'Son histoire · d’après la fiche du musée';
    }

    // ------------------------------------------------------------------ en-tête et pied

    protected function header(): void
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
        $px = self::PAD + min(170, $lw) + 3;
        $right = $this->d['years'] !== '' ? 'Au FCSM · ' . $this->d['years'] : 'Au FCSM';
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
        $this->text(self::PAD, $y, 200, 'Chiffres, faits et citations tirés de sa fiche et des compositions du musée Sochaux Rétro', 'serif-i', $s, self::MIST);
        $this->text(self::W - self::PAD - 70, $y, 70, 'Poster A3 · 297 × 420 mm', 'serif-i', $s, self::MIST, ['align' => 'right']);
    }

    // ------------------------------------------------------------------ blocs

    /** Le nom : prénom en capitales, nom en très grand, poste et années. */
    protected function hero(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        $this->rect($x, $y, $w, $h, self::NAVY, self::YELLOW, 0.8);
        $ix = $x + 5;
        $iw = $w - 10;
        $kick = implode(' · ', array_filter([$d['legend'] ? 'Légende du club' : '', $d['position']]));
        $ty = $this->kicker($ix, $y + 3.6, $iw, $kick !== '' ? $kick : 'Lionceau');
        $fs = self::pt(40);
        if ($d['first'] !== '') {
            $this->text($ix, $ty + 0.5, $iw, $d['first'], 'display-b', $fs, self::CREAM, ['upper' => true, 'spacing' => 60, 'fit' => true, 'lh' => 1]);
            $ty += $fs * 25.4 / 72 + 1.5;
        }
        $sub = $d['nickname'] !== '' ? '« ' . $d['nickname'] . ' »' : ($d['years'] !== '' ? 'Au FC Sochaux-Montbéliard de ' . str_replace('-', ' à ', $d['years']) : '');
        $subH = $sub !== '' ? 8 : 0;
        $avail = $y + $h - 4 - $subH - $ty;
        $l = $this->text($ix, $ty, $iw, $d['last'], 'display', self::pt(150), self::YELLOW, ['upper' => true, 'fit' => true, 'min' => self::pt(30), 'lh' => 0.9, 'h' => $avail, 'valign' => 'middle', 'dry' => true]);
        $this->L[] = $l;
        if ($sub !== '') {
            $this->text($ix, $y + $h - 4 - $subH + 1.5, $iw, $sub, 'serif-i', self::pt(19), self::CREAM, ['fit' => true]);
        }
    }

    /** Les grands chiffres : matchs, buts, saisons, minutes. */
    private function numbers(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        $this->rect($x, $y, $w, $h, self::YELLOW);
        $items = array_values(array_filter([
            [$d['matches'], $d['matches'] > 1 ? 'matchs' : 'match'],
            [$d['goals'], $d['goals'] > 1 ? 'buts' : 'but'],
            [$d['seasons_n'], $d['seasons_n'] > 1 ? 'saisons' : 'saison'],
            $d['minutes'] > 0 ? [$d['minutes'], 'minutes jouées'] : null,
        ]));
        $cw = ($w - 9) / count($items);
        foreach ($items as $i => [$n, $lab]) {
            $cx = $x + 4.5 + $i * $cw;
            if ($i > 0) {
                $this->rect($cx - 0.3, $y + 4, 0.6, $h - 8, self::NAVY);
            }
            $num = number_format((int) $n, 0, ',', ' ');
            $l = $this->text($cx + 4, $y + 2.2, $cw - 8, $num, 'display', self::pt(84), self::NAVY, ['fit' => true, 'lh' => 0.92, 'dry' => true]);
            $this->L[] = $l;
            $nh = \App\Shop\Vector::layout($l)['size'] * 25.4 / 72 * 0.92;
            $this->text($cx + 4, $y + 2.2 + $nh + 0.4, $cw - 8, $lab, 'display-b', self::pt(17), self::NAVY, ['upper' => true, 'spacing' => 140, 'fit' => true]);
        }
    }

    /** La carrière : un bâton par saison (matchs), les buts en jaune. */
    private function career(float $x, float $y, float $w, float $h): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Sa carrière, saison par saison');
        $ss = $this->d['seasons'];
        // Légende.
        $ly = $ty;
        $this->rect($ix, $ly + 0.6, 3.2, 3.2, self::CREAM);
        $this->text($ix + 4.6, $ly, 30, 'matchs', 'display-b', self::pt(13), self::CREAM, ['upper' => true, 'spacing' => 100]);
        $this->rect($ix + 38, $ly + 0.6, 3.2, 3.2, self::YELLOW);
        $this->text($ix + 42.6, $ly, 30, 'buts', 'display-b', self::pt(13), self::YELLOW, ['upper' => true, 'spacing' => 100]);
        $top = $ly + 8;
        $base = $y + $h - 10;
        if (!$ss) {
            $this->text($ix, $top, $iw, 'Ses matchs ne figurent pas encore dans les compositions du musée.', 'serif-i', self::pt(16), self::MIST);
            return;
        }
        $max = max(array_column($ss, 'm')) ?: 1;
        $n = count($ss);
        $slot = $iw / $n;
        $bw = min(9.0, $slot * 0.62);
        $vs = self::pt(13);
        $this->rect($ix, $base, $iw, 0.5, self::MIST);
        foreach ($ss as $i => $s) {
            $cx = $ix + $slot * ($i + 0.5);
            $bh = ($base - $top - 6) * $s['m'] / $max;
            $this->rect($cx - $bw / 2, $base - $bh, $bw, $bh, self::CREAM);
            if ($s['g'] > 0) {
                $gh = ($base - $top - 6) * $s['g'] / $max;
                $this->rect($cx - $bw / 2 + $bw * 0.2, $base - $gh, $bw * 0.6, $gh, self::YELLOW);
            }
            $this->text($cx - $slot / 2, $base - $bh - $vs * 25.4 / 72 - 1, $slot, (string) $s['m'], 'display-b', $vs, self::CREAM, ['align' => 'center', 'fit' => true, 'lh' => 1]);
            // « 1987-1988 » → « 87-88 », en biais si les saisons sont nombreuses.
            $lab = preg_match('/^\d{2}(\d{2})-\d{2}(\d{2})$/', $s['season'], $mm) ? $mm[1] . '-' . $mm[2] : $s['season'];
            $this->text($cx - $slot / 2, $base + 1.6, $slot, $lab, 'display-b', self::pt($n > 14 ? 10 : 13), self::MIST, ['align' => 'center', 'fit' => true, 'min' => self::pt(7), 'lh' => 1]);
        }
    }

    /** Fiche d'identité. */
    private function identity(float $x, float $y, float $w, float $h): void
    {
        $rows = $this->d['identity'];
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Sa fiche', self::BLUE);
        if (!$rows) {
            return;
        }
        $rh = min(10.0, ($y + $h - 3 - $ty) / count($rows));
        $i = 0;
        foreach ($rows as $k => $v) {
            $ry = $ty + $i++ * $rh;
            $this->text($ix, $ry + 0.3, $iw * 0.3, $k, 'display-b', self::pt(13), self::BLUE, ['upper' => true, 'spacing' => 100, 'fit' => true]);
            $this->text($ix + $iw * 0.32, $ry, $iw * 0.68, $v, 'serif-b', self::pt(15), self::NAVY, ['fit' => true, 'min' => self::pt(8)]);
            $this->rect($ix, $ry + $rh - 1.6, $iw, 0.25, '#D9CFB8');
        }
    }

    /** Palmarès : une puce jaune par titre. */
    private function honours(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::BLUE, self::LINE, 0.5);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Palmarès au FCSM');
        $items = $this->d['honours'];
        $avail = $y + $h - 3.5 - $ty;
        for ($size = self::pt(16); ; $size -= 0.3) {
            $hs = array_map(fn ($t) => self::height($this->text($ix + 4.5, 0, $iw - 4.5, $t, 'serif', $size, self::CREAM, ['lh' => 1.2, 'dry' => true])), $items);
            if (array_sum($hs) + 1.4 * (count($hs) - 1) <= $avail || $size < self::pt(9)) {
                break;
            }
            if ($size < self::pt(11) && count($items) > 1) {
                array_pop($items);
                $size = self::pt(16) + 0.3;
            }
        }
        $yy = $ty;
        foreach ($items as $i => $t) {
            $this->circle($ix + 1.4, $yy + $size * 25.4 / 72 * 0.6, 1.3, self::YELLOW);
            $this->text($ix + 4.5, $yy, $iw - 4.5, $t, 'serif', $size, self::CREAM, ['lh' => 1.2]);
            $yy += $hs[$i] + 1.4;
        }
    }

    /** Ses grands matchs : date, affiche, compétition, ses buts. */
    private function bigMatches(float $x, float $y, float $w, float $h): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Ses grands matchs');
        $items = $this->d['big'];
        $rh = ($y + $h - 3 - $ty) / max(1, count($items));
        foreach ($items as $i => $m) {
            $ry = $ty + $i * $rh;
            $date = implode('/', array_reverse(explode('-', $m['date'])));
            $this->text($ix, $ry + 0.6, 24, $date, 'display-b', self::pt(15), self::YELLOW, ['spacing' => 60, 'fit' => true]);
            $tx = $ix + 25;
            $badge = $m['goals'] > 0 ? ($m['goals'] > 1 ? $m['goals'] . ' buts' : '1 but') : ($m['captain'] ? 'capitaine' : '');
            $bw = $badge !== '' ? self::width(mb_strtoupper($badge), 'display-b', self::pt(13), 100) + 4 : 0;
            $this->text($tx, $ry, $ix + $iw - $tx - $bw - 2, $m['teams'], 'display', self::pt(19), self::CREAM, ['upper' => true, 'fit' => true, 'min' => self::pt(12), 'lh' => 1]);
            $this->text($tx, $ry + self::pt(19) * 25.4 / 72 + 0.8, $ix + $iw - $tx, $m['comp'], 'serif-i', self::pt(13), self::MIST, ['fit' => true]);
            if ($badge !== '') {
                $this->rect($ix + $iw - $bw, $ry + 0.3, $bw, self::pt(13) * 25.4 / 72 + 1.6, $m['goals'] > 0 ? self::YELLOW : self::CREAM);
                $this->text($ix + $iw - $bw, $ry + 1.1, $bw, $badge, 'display-b', self::pt(13), self::NAVY, ['upper' => true, 'spacing' => 100, 'align' => 'center']);
            }
            if ($i < count($items) - 1) {
                $this->rect($ix, $ry + $rh - 1.2, $iw, 0.25, self::LINE);
            }
        }
    }

    /** Colonne de droite : records, sinon le chiffre clé, sinon les coéquipiers. */
    private function side(float $x, float $y, float $w, float $h): void
    {
        $d = $this->d;
        if ($d['records']) {
            $this->records($x, $y, $w, $h);
        } elseif ($d['figure']) {
            $this->figure($x, $y, $w, $h);
        } else {
            $this->mates($x, $y, $w, $h);
        }
    }

    private function records(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::YELLOW);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Dans les records du club', self::NAVY);
        $items = $this->d['records'];
        $rh = ($y + $h - 3 - $ty) / count($items);
        $r = min(5.0, $rh / 2 - 0.5);
        foreach ($items as $i => $rec) {
            $cy = $ty + $i * $rh + $rh / 2 - 0.6;
            $this->circle($ix + $r, $cy, $r, self::NAVY);
            $rank = $rec['rank'] === 1 ? '1er' : $rec['rank'] . 'e';
            $this->text($ix, $cy - 2.1, 2 * $r, $rank, 'display', self::pt(mb_strlen($rank) > 2 ? 17 : 21), self::YELLOW, ['align' => 'center', 'lh' => 1, 'fit' => true]);
            $tx = $ix + 2 * $r + 3;
            $this->text($tx, $cy - 4.6, $ix + $iw - $tx, $rec['label'], 'display-b', self::pt(14), self::NAVY, ['upper' => true, 'spacing' => 60, 'fit' => true, 'min' => self::pt(8)]);
            $this->text($tx, $cy + 0.4, $ix + $iw - $tx, $rec['value'], 'serif-b', self::pt(16), self::NAVY, ['fit' => true]);
        }
    }

    private function mates(float $x, float $y, float $w, float $h): void
    {
        $this->panel($x, $y, $w, $h);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Ses fidèles coéquipiers');
        $items = $this->d['mates'];
        $rh = ($y + $h - 3 - $ty) / max(1, count($items));
        foreach ($items as $i => $m) {
            $ry = $ty + $i * $rh;
            $this->text($ix, $ry, $iw, $m['name'], 'display', self::pt(22), self::CREAM, ['upper' => true, 'fit' => true, 'lh' => 1]);
            $this->text($ix, $ry + self::pt(22) * 25.4 / 72 + 0.6, $iw, $m['n'] . ' matchs ensemble', 'serif-i', self::pt(14), self::YELLOW, ['fit' => true]);
        }
    }

    /** Le portrait en une phrase (quand il n'y a pas de citation). */
    private function portrait(float $x, float $y, float $w, float $h): void
    {
        $this->rect($x, $y, $w, $h, self::CREAM);
        $ix = $x + 4.5;
        $iw = $w - 9;
        $ty = $this->kicker($ix, $y + 4, $iw, 'Pour Sochaux, il était…', self::BLUE);
        $this->rect($ix, $ty, 1.5, $y + $h - 4 - $ty, self::YELLOW);
        $this->text($ix + 5, $ty, $iw - 5, $this->d['portrait'], 'serif-b', self::pt(24), self::NAVY, ['lh' => 1.25, 'h' => $y + $h - 4 - $ty, 'fit' => true, 'min' => self::pt(12), 'valign' => 'middle']);
    }

    /** Les jalons : premier match, premier but, dernier match. */
    private function milestones(float $x, float $y, float $w, float $h): void
    {
        $items = $this->d['milestones'];
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

