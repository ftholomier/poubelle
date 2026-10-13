<?php
declare(strict_types=1);

namespace App\Widgets\Screen;

use App\Core\Club;
use App\Services\Images;
use App\Services\Qr;
use App\Widgets\Context;
use App\Widgets\Frame;

/**
 * Images PNG des slides (export pour les régies qui ne lisent pas de page web) : mêmes modèles
 * que la page écran (Slides), dessinés avec GD et les polices du site (app/Resources/fonts),
 * en 1920 × 1080 ou 1080 × 1920, avec le logo du musée, la mention « non officiel » et l'adresse
 * du musée en bas, comme à l'écran. Une slide de quiz donne deux images : la question (avec le
 * compte à rebours) et la réponse.
 */
final class Image
{
    private const FONTS = APP_DIR . '/Resources/fonts';
    private const FOOT = 120;

    private \GdImage $im;
    private int $w;
    private int $h;
    /** @var array<string,array{0:int,1:int,2:int}> */
    private array $col;

    private function __construct(private Context $c, bool $vertical)
    {
        $this->w = $vertical ? 1080 : 1920;
        $this->h = $vertical ? 1920 : 1080;
        $this->im = imagecreatetruecolor($this->w, $this->h);
        imagealphablending($this->im, true);
        imageantialias($this->im, true);
        $rgb = fn (string $k) => sscanf(Club::color($k), '#%02x%02x%02x');
        $this->col = ['navy' => $rgb('navy'), 'navy2' => $rgb('navy-2'), 'accent' => $rgb('yellow'), 'cream' => $rgb('cream'), 'mist' => $rgb('mist'), 'red' => $rgb('red'), 'green' => [47, 179, 106], 'white' => [255, 255, 255]];
        $this->rect(0, 0, $this->w, $this->h, 'navy');
    }

    /** PNG d'une slide (contenu binaire). $answer : image « réponse » d'un quiz. */
    public static function png(array $slide, Context $c, bool $vertical = false, bool $answer = false): string
    {
        $g = new self($c, $vertical);
        try {
            $g->draw($slide, $answer);
        } catch (\Throwable $e) {
            error_log('Image d’écran (' . ($slide['type'] ?? '?') . ') : ' . $e->getMessage());
        }
        $g->footer();
        ob_start();
        imagepng($g->im, null, 6);
        imagedestroy($g->im);
        return (string) ob_get_clean();
    }

    /** Images d'une slide : [nom => [png, durée en secondes]] (quiz : question puis réponse). */
    public static function frames(array $slide, Context $c, bool $vertical): array
    {
        $n = str_pad((string) $slide['n'], 2, '0', STR_PAD_LEFT) . '-' . $slide['type'];
        if (($slide['layout'] ?? '') === 'quiz') {
            $a = (int) $slide['answerAt'];
            return [$n . '-question.png' => [self::png($slide, $c, $vertical), max(3, (int) $slide['duration'] - $a)], $n . '-reponse.png' => [self::png($slide, $c, $vertical, true), $a]];
        }
        return [$n . '.png' => [self::png($slide, $c, $vertical), (int) $slide['duration']]];
    }

    // ------------------------------------------------------------------ mises en page

    private function draw(array $s, bool $answer): void
    {
        $v = $this->w < $this->h;
        $x = $v ? 70 : 110;
        $y = $v ? 140 : 110;
        $cw = $this->w - 2 * $x;
        $bottom = $this->h - self::FOOT - 50;
        $eyebrow = function (int $x, int $y, int $w) use ($s): int {
            if (empty($s['eyebrow'])) {
                return $y;
            }
            $this->line(mb_strtoupper($s['eyebrow']), 'BigShouldersDisplay-ExtraBold', 40, $x, $y + 40, $w, 'accent', 4);
            return $y + 70;
        };
        switch ($s['layout']) {
            case 'duel':
                $qrw = !empty($s['qr']) ? 300 : 0;
                $y = $eyebrow($x, $y, $cw);
                $fw = $cw - ($v ? 0 : $qrw + 60);
                $y = $this->title($s['title'], $x, $y - ($v ? 0 : 20), $cw, $v ? 96 : 104);
                $y = $this->stats($s['stats'], $x, $y, $cw, $v ? 120 : 118);
                if (!empty($s['bar'])) {
                    $t = max(1, array_sum($s['bar']));
                    $cx = $x;
                    $this->rect($x, $y + 26, $x + $fw, $y + 56, 'navy2');
                    foreach (['accent', 'cream', 'red'] as $i => $k) {
                        $seg = (int) round($fw * $s['bar'][$i] / $t);
                        if ($seg > 0) {
                            $this->rect($cx, $y + 26, min($x + $fw, $cx + $seg), $y + 56, $k);
                        }
                        $cx += $seg;
                    }
                    $y += 56;
                }
                if (!empty($s['sub'])) {
                    $y = $this->para($s['sub'], 'Newsreader-SemiBold', 40, $x, $y + 22, $fw, 2, 'mist');
                }
                foreach ($s['rows'] as $r) {
                    $this->rect($x, $y + 14, $x + $fw, $y + 17, 'navy2');
                    if ($v) {
                        $this->line(mb_strtoupper($r[0]), 'BigShouldersDisplay-ExtraBold', 30, $x, $y + 56, $fw, 'accent', 3);
                        $this->line(mb_strtoupper($r[1]), 'BigShouldersDisplay-ExtraBold', 46, $x, $y + 108, $fw - 10, 'cream');
                        $this->line($r[2], 'Newsreader-Regular', 34, $x, $y + 150, $fw, 'mist');
                        $y += 150;
                    } else {
                        $this->line(mb_strtoupper($r[0]), 'BigShouldersDisplay-ExtraBold', 30, $x, $y + 68, 400, 'accent', 3);
                        $dw = $this->width($r[2], 'Newsreader-Regular', 34);
                        $this->line(mb_strtoupper($r[1]), 'BigShouldersDisplay-ExtraBold', 46, $x + 450, $y + 70, $fw - 450 - $dw - 30, 'cream');
                        $this->line($r[2], 'Newsreader-Regular', 34, $x + $fw - $dw, $y + 68, $dw + 2, 'mist');
                        $y += 76;
                    }
                }
                if ($qrw) {
                    $v ? $this->qr($s['qr'], (int) (($this->w - $qrw) / 2), min($bottom - $qrw, $y + 40), $qrw, $this->c->tr('Tout le duel', 'Full record'))
                        : $this->qr($s['qr'], $x + $cw - $qrw, $bottom - $qrw - 40, $qrw, $this->c->tr('Tout le duel', 'Full record'));
                }
                break;

            case 'list':
                $y = $eyebrow($x, $y, $cw);
                $y = $this->title($s['title'], $x, $y, $cw, $v ? 96 : 112);
                $dense = count($s['rows']) > 3;
                foreach ($s['rows'] as $r) {
                    if ($y + ($v ? 260 : ($dense ? 130 : 170)) > $bottom + 20) {
                        break;
                    }
                    $this->rect($x, $y, $x + $cw, $y + 3, 'navy2');
                    $pill = !empty($s['trophy']) ? 120 : max(250, $this->width(mb_strtoupper($r[0]), 'BigShouldersDisplay-Black', 46) + 60);
                    if ($v) {
                        $this->pill(mb_strtoupper($r[0]), $x, $y + 26, 46);
                        $this->line(mb_strtoupper($r[1]), 'BigShouldersDisplay-ExtraBold', 60, $x, $y + 170, $cw, 'cream');
                        $yy = $this->para($r[2], 'Newsreader-Regular', 36, $x, $y + 186, $cw, 2, 'mist');
                        $y = $yy + 30;
                    } else {
                        $this->pill(mb_strtoupper($r[0]), $x, $y + ($dense ? 24 : 30), $dense ? 40 : 46, $pill);
                        $res = (string) ($r[3] ?? '');
                        $rw = $res !== '' ? 200 : 0;
                        $this->line(mb_strtoupper($r[1]), 'BigShouldersDisplay-ExtraBold', $dense ? 54 : 64, $x + $pill + 40, $y + ($dense ? 74 : 92), $cw - $pill - 40 - $rw, 'cream');
                        $this->line($r[2], 'Newsreader-Regular', $dense ? 30 : 36, $x + $pill + 40, $y + ($dense ? 116 : 142), $cw - $pill - 40 - $rw, 'mist');
                        if ($res !== '') {
                            $label = mb_strtoupper(match ($res) { 'V' => $this->c->tr('Victoire', 'Win'), 'N' => $this->c->tr('Nul', 'Draw'), 'D' => $this->c->tr('Défaite', 'Defeat'), default => $res });
                            $this->pill($label, $x + $cw - 190, $y + 62, 32, 190, $res === 'V' ? 'green' : ($res === 'D' ? 'red' : 'navy2'), 'white');
                        }
                        $y += $dense ? 130 : 170;
                    }
                }
                break;

            case 'rank':
            case 'map':
                $mapw = 0;
                if ($s['layout'] === 'map') {
                    if ($v) {
                        $this->map($s['map'], $x, $y, $cw, 760);
                        $y += 800;
                    } else {
                        $mapw = 860;
                        $this->map($s['map'], $x, $y - 20, $mapw, 820);
                        $x += $mapw + 70;
                        $cw -= $mapw + 70;
                    }
                }
                $y = $eyebrow($x, $y, $cw);
                $y = $this->title($s['title'], $x, $y, $cw, $v ? 96 : 112);
                $cols = $v || $s['layout'] === 'map' ? 1 : 2;
                $colw = (int) (($cw - ($cols - 1) * 70) / $cols);
                $per = (int) ceil(count($s['rows']) / $cols);
                $rowh = $v ? 92 : ($cols === 1 ? 100 : 104);
                foreach ($s['rows'] as $i => $r) {
                    $cx = $x + intdiv($i, max(1, $per)) * ($colw + 70);
                    $cy = $y + ($i % max(1, $per)) * $rowh;
                    $this->line((string) $r[0], 'BigShouldersDisplay-Black', 56, $cx, $cy + 64, 80, $i < 3 ? 'accent' : 'mist', 0, true);
                    $vw = $this->width($r[3], 'BigShouldersDisplay-Black', 50);
                    $this->line(mb_strtoupper($r[1]), 'BigShouldersDisplay-ExtraBold', 46, $cx + 100, $cy + 52, $colw - 130 - $vw, 'cream');
                    if ($r[2] !== '') {
                        $this->line($r[2], 'Newsreader-Regular', 26, $cx + 100, $cy + 86, $colw - 130 - $vw, 'mist');
                    }
                    $this->line($r[3], 'BigShouldersDisplay-Black', 50, $cx + $colw - $vw, $cy + 64, $vw + 4, 'cream');
                    $this->rect($cx, $cy + $rowh - 3, $cx + $colw, $cy + $rowh, 'navy2');
                }
                break;

            case 'hero':
                $iw = !empty($s['image']['file']) ? ($v ? $cw : 720) : 0;
                if ($iw && $v) {
                    $this->photo($s['image'], $x, $y, $cw, 640);
                    $y += 690;
                } elseif ($iw) {
                    $this->photo($s['image'], $x + $cw - $iw, $y - 20, $iw, 820);
                    $cw -= $iw + 70;
                }
                $y = $eyebrow($x, $y, $cw);
                $big = (string) $s['big'];
                $size = min(300, $this->fit($big, 'BigShouldersDisplay-Black', $cw - 20, 300));
                $this->line($big, 'BigShouldersDisplay-Black', $size, $x, $y + (int) ($size * .82), $cw, 'accent');
                if (!empty($s['unit'])) {
                    $bw = $this->width($big, 'BigShouldersDisplay-Black', $size);
                    if ($bw + 300 < $cw) {
                        $this->line(mb_strtoupper($s['unit']), 'BigShouldersDisplay-Black', 90, $x + $bw + 30, $y + (int) ($size * .82), $cw - $bw - 30, 'cream');
                    } else {
                        $y += 90;
                        $this->line(mb_strtoupper($s['unit']), 'BigShouldersDisplay-Black', 90, $x, $y + (int) ($size * .82), $cw, 'cream');
                    }
                }
                $y += (int) ($size * .9) + 20;
                $y = $this->title($s['title'], $x, $y, $cw, 80);
                if (!empty($s['text'])) {
                    $y = $this->para($s['text'], 'Newsreader-Regular', 44, $x, $y, $cw, 4, 'cream');
                }
                if (!empty($s['sub'])) {
                    $this->para($s['sub'], 'Newsreader-SemiBold', 40, $x, $y + 16, $cw, 2, 'mist');
                }
                break;

            case 'quote':
                $y = $eyebrow($x, $y, $cw);
                if (!empty($s['big'])) {
                    $this->line($s['big'], 'BigShouldersDisplay-Black', 200, $x, $y + 165, $cw, 'accent');
                    $y += 210;
                }
                $top = $y;
                $y = $this->para((string) $s['text'], 'Newsreader-Italic', 76, $x + 64, $y, $cw - 64, $v ? 9 : 6, 'cream', 1.22);
                $this->rect($x, $top + 10, $x + 14, $y, 'accent');
                if (!empty($s['sub'])) {
                    $this->para($this->c->tr('Source : ', 'Source: ') . $s['sub'], 'Newsreader-SemiBold', 40, $x, $y + 20, $cw, 2, 'mist');
                }
                break;

            case 'card':
                [$bw, $bh] = $v ? [$cw, 760] : [620, 820];
                $this->rect($x, $y - 20, $x + $bw, $y - 20 + $bh, 'accent');
                $this->rect($x + 14, $y - 6, $x + $bw - 14, $y - 34 + $bh, 'navy2');
                if (!empty($s['image']['file'])) {
                    $this->photo($s['image'], $x + 14, $y - 6, $bw - 28, $bh - 28, false);
                } else {
                    $iw = $this->width($s['initials'], 'BigShouldersDisplay-Black', 300);
                    $this->line($s['initials'], 'BigShouldersDisplay-Black', 300, $x + (int) (($bw - $iw) / 2), $y - 20 + (int) ($bh / 2) + 120, $bw, 'accent');
                }
                if ($v) {
                    $y += $bh + 20;
                } else {
                    $x += $bw + 70;
                    $cw -= $bw + 70;
                    $y += 60;
                }
                $y = $eyebrow($x, $y, $cw);
                if (!empty($s['badge'])) {
                    $this->pill(mb_strtoupper($s['badge']), $x, $y, 32);
                    $y += 80;
                }
                $this->line(mb_strtoupper($s['first']), 'BigShouldersDisplay-Black', 60, $x, $y + 56, $cw, 'accent', 3);
                $size = min(150, $this->fit(mb_strtoupper($s['last']), 'BigShouldersDisplay-Black', $cw, 150));
                $this->line(mb_strtoupper($s['last']), 'BigShouldersDisplay-Black', $size, $x, $y + 70 + (int) ($size * .85), $cw, 'cream');
                $y += 90 + $size;
                $y = $this->para($s['sub'], 'Newsreader-SemiBold', 40, $x, $y, $cw, 2, 'mist') + 30;
                $this->stats($s['stats'], $x, $y, $cw, 130);
                break;

            case 'match':
                if (!empty($s['image']['file'])) {
                    $this->photo($s['image'], 0, 0, $this->w, $this->h - self::FOOT, false, 92);
                }
                $y = $eyebrow($x, $y, $cw);
                $y += $v ? 60 : 120;
                if ($v) {
                    foreach ([[$s['home'], 'cream', 110], [$s['score'], 'accent', 240], [$s['away'], 'cream', 110]] as [$t, $k, $px]) {
                        $t = mb_strtoupper($t);
                        $px = min($px, $this->fit($t, 'BigShouldersDisplay-Black', $cw, $px));
                        $this->line($t, 'BigShouldersDisplay-Black', $px, $x + (int) (($cw - $this->width($t, 'BigShouldersDisplay-Black', $px)) / 2), $y + (int) ($px * .85), $cw, $k);
                        $y += $px + 30;
                    }
                } else {
                    $sw = $this->width($s['score'], 'BigShouldersDisplay-Black', 260);
                    $side = (int) (($cw - $sw - 120) / 2);
                    $hs = min(120, $this->fit(mb_strtoupper($s['home']), 'BigShouldersDisplay-Black', $side, 120));
                    $as = min(120, $this->fit(mb_strtoupper($s['away']), 'BigShouldersDisplay-Black', $side, 120));
                    $this->line(mb_strtoupper($s['home']), 'BigShouldersDisplay-Black', $hs, $x + $side - $this->width(mb_strtoupper($s['home']), 'BigShouldersDisplay-Black', $hs), $y + 170, $side, 'cream');
                    $this->line($s['score'], 'BigShouldersDisplay-Black', 260, $x + $side + 60, $y + 215, $sw + 10, 'accent');
                    $this->line(mb_strtoupper($s['away']), 'BigShouldersDisplay-Black', $as, $x + $side + $sw + 120, $y + 170, $side, 'cream');
                    $y += 270;
                }
                $y = $this->para($s['sub'], 'Newsreader-SemiBold', 40, $x, $y + 10, $cw, 2, 'mist', 1.25, true);
                if (!empty($s['text'])) {
                    $this->para($s['text'], 'Newsreader-Regular', 44, $x + ($v ? 0 : 150), $y + 20, $cw - ($v ? 0 : 300), 4, 'cream', 1.3, true);
                }
                if (!empty($s['qr'])) {
                    $this->qr($s['qr'], $x + $cw - 220, $bottom - 230, 220);
                }
                break;

            case 'photo':
                $y = $eyebrow($x, $y, $cw);
                $ph = $bottom - $y - 150;
                $this->photo($s['image'], $x, $y, $cw, $ph, true);
                $this->para((string) $s['text'], 'Newsreader-SemiBold', 48, $x, $y + $ph + 10, $cw, 1, 'cream', 1.2, true);
                $this->para((string) $s['image']['credit'], 'Newsreader-Regular', 26, $x, $y + $ph + 80, $cw, 1, 'mist', 1.2, true);
                break;

            case 'quiz':
                $q = $s['quiz'];
                $tw = $v ? 0 : 300;
                $y = $eyebrow($x, $y, $cw - $tw);
                $y = $this->para($q['q'], 'Newsreader-SemiBold', 76, $x, $y, $cw - ($v ? 220 : $tw), $v ? 5 : 3, 'cream', 1.15) + 40;
                $cols = $v ? 1 : 2;
                $bw = (int) (($cw - ($v ? 0 : $tw) - ($cols - 1) * 26) / $cols);
                foreach ($q['a'] as $i => $a) {
                    $bx = $x + ($i % $cols) * ($bw + 26);
                    $by = $y + intdiv($i, $cols) * 150;
                    $good = $answer && $i === $q['c'];
                    $dim = $answer && !$good;
                    if ($good) {
                        $this->rect($bx, $by, $bx + $bw, $by + 124, 'green');
                    } else {
                        $this->frame($bx, $by, $bx + $bw, $by + 124, $dim ? 'navy2' : 'mist', 4);
                    }
                    $this->disc($bx + 70, $by + 62, 40, $dim ? 'navy2' : 'accent');
                    $this->line(chr(65 + $i), 'BigShouldersDisplay-Black', 50, $bx + 56, $by + 82, 40, 'navy');
                    $one = $this->width($a, 'Newsreader-SemiBold', 48) <= $bw - 150;
                    $this->para($a, 'Newsreader-SemiBold', $one ? 48 : 40, $bx + 130, $by + ($one ? 34 : 14), $bw - 150, 2, $dim ? 'mist' : ($good ? 'white' : 'cream'), 1.1);
                }
                $y += (int) ceil(count($q['a']) / $cols) * 150 + 20;
                if ($answer) {
                    $this->para($q['fact'] !== '' ? $q['fact'] : $this->c->tr('Bonne réponse : ', 'Right answer: ') . $q['a'][$q['c']], 'Newsreader-Regular', 44, $x, $y, $cw - $tw, 3, 'cream');
                } else {
                    $r = $v ? 90 : 110;
                    $cx = $this->w - ($v ? 70 : 110) - $r;
                    $cy = ($v ? 80 : 70) + $r;
                    $this->disc($cx, $cy, $r, 'accent');
                    $this->disc($cx, $cy, $r - 12, 'navy');
                    $sec = (string) max(1, (int) $s['duration'] - (int) $s['answerAt']);
                    $this->line($sec, 'BigShouldersDisplay-Black', $v ? 86 : 110, $cx - (int) ($this->width($sec, 'BigShouldersDisplay-Black', $v ? 86 : 110) / 2), $cy + ($v ? 22 : 30), $r * 2, 'accent');
                }
                if (!empty($s['qr'])) {
                    $v ? $this->qr($s['qr'], (int) (($this->w - 260) / 2), $bottom - 300, 260, (string) ($s['qrLabel'] ?? ''))
                        : $this->qr($s['qr'], $x + $cw - 250, $bottom - 290, 250, (string) ($s['qrLabel'] ?? ''));
                }
                break;

            case 'timeline':
                $y = $eyebrow($x, $y, $cw);
                $n = count($s['events']);
                if ($v) {
                    $this->rect($x, $y + 20, $x + 10, $bottom, 'accent');
                    $step = (int) (($bottom - $y - 40) / max(1, $n));
                    foreach ($s['events'] as $i => [$yr, $t, $tx]) {
                        $ey = $y + 40 + $i * $step;
                        $this->disc($x + 5, $ey + 30, 26, 'accent');
                        $this->line((string) $yr, 'BigShouldersDisplay-Black', 84, $x + 60, $ey + 70, $cw - 60, 'accent');
                        $this->line(mb_strtoupper($t), 'BigShouldersDisplay-ExtraBold', 42, $x + 60, $ey + 125, $cw - 60, 'cream');
                        $this->para($tx, 'Newsreader-Regular', 28, $x + 60, $ey + 135, $cw - 60, 2, 'mist');
                    }
                } else {
                    $y += 60;
                    $this->rect($x, $y, $x + $cw, $y + 10, 'accent');
                    $colw = (int) (($cw - ($n - 1) * 34) / max(1, $n));
                    foreach ($s['events'] as $i => [$yr, $t, $tx]) {
                        $ex = $x + $i * ($colw + 34);
                        $this->disc($ex + 18, $y + 5, 26, 'navy');
                        $this->disc($ex + 18, $y + 5, 18, 'accent');
                        $this->line((string) $yr, 'BigShouldersDisplay-Black', 110, $ex, $y + 150, $colw, 'accent');
                        $yy = $this->para(mb_strtoupper($t), 'BigShouldersDisplay-ExtraBold', 42, $ex, $y + 170, $colw, 3, 'cream', 1.08);
                        $this->para($tx, 'Newsreader-Regular', 28, $ex, $yy + 6, $colw, 6, 'mist');
                    }
                }
                break;

            case 'countdown':
                $y = $eyebrow($x, $y, $cw);
                $y = $this->title($s['title'], $x, $y, $cw, $v ? 96 : 112, true);
                $left = max(0, (int) $s['countdown'] - time());
                $vals = [[(string) intdiv($left, 86400), $this->c->tr('jours', 'days')], [str_pad((string) (intdiv($left, 3600) % 24), 2, '0', STR_PAD_LEFT), $this->c->tr('heures', 'hours')], [str_pad((string) (intdiv($left, 60) % 60), 2, '0', STR_PAD_LEFT), $this->c->tr('minutes', 'minutes')]];
                $bw = $v ? 520 : 400;
                foreach ($vals as $i => [$num, $lab]) {
                    [$bx, $by] = $v ? [(int) (($this->w - $bw) / 2), $y + 20 + $i * 330] : [$x + (int) (($cw - 3 * $bw - 80) / 2) + $i * ($bw + 40), $y + 30];
                    $i === 0 ? $this->rect($bx, $by, $bx + $bw, $by + 300, 'accent') : $this->frame($bx, $by, $bx + $bw, $by + 300, 'mist', 5);
                    $nw = $this->width($num, 'BigShouldersDisplay-Black', 220);
                    $this->line($num, 'BigShouldersDisplay-Black', min(220, $this->fit($num, 'BigShouldersDisplay-Black', $bw - 30, 220)), $bx + max(15, (int) (($bw - $nw) / 2)), $by + 200, $bw - 30, $i === 0 ? 'navy' : 'accent');
                    $lw = $this->width(mb_strtoupper($lab), 'BigShouldersDisplay-ExtraBold', 44);
                    $this->line(mb_strtoupper($lab), 'BigShouldersDisplay-ExtraBold', 44, $bx + (int) (($bw - $lw) / 2), $by + 268, $bw, $i === 0 ? 'navy' : 'mist');
                }
                $y += $v ? 1030 : 380;
                $this->para($s['sub'], 'Newsreader-SemiBold', 40, $x, $y, $cw, 2, 'mist', 1.25, true);
                break;

            case 'support':
                $y = $eyebrow($x, $y, $cw);
                $y = $this->title($s['title'], $x, $y, $cw, $v ? 96 : 112) + 20;
                $n = count($s['items']);
                $colw = $v ? $cw : (int) min(560, ($cw - ($n - 1) * 60) / max(1, $n));
                $start = $v ? $x : $x + (int) (($cw - $n * $colw - ($n - 1) * 60) / 2);
                foreach ($s['items'] as $i => $it) {
                    [$ix, $iy] = $v ? [$x, $y + $i * 470] : [$start + $i * ($colw + 60), $y];
                    $qs = $v ? 260 : 340;
                    $this->qr($it['qr'], $ix + (int) (($colw - $qs) / 2), $iy, $qs);
                    $lab = mb_strtoupper($it['label']);
                    $this->line($lab, 'BigShouldersDisplay-Black', 64, $ix + max(0, (int) (($colw - $this->width($lab, 'BigShouldersDisplay-Black', 64)) / 2)), $iy + $qs + 80, $colw, 'accent');
                    $this->para($it['text'], 'Newsreader-Regular', 32, $ix, $iy + $qs + 96, $colw, 3, 'mist', 1.3, true);
                }
                break;
        }
    }

    /** Bas de l'image : logo du musée, nom, mention « non officiel », adresse du musée. */
    private function footer(): void
    {
        $top = $this->h - self::FOOT;
        $this->rect(0, $top, $this->w, $this->h, 'navy2');
        $this->rect(0, $top, $this->w, $top + 6, 'accent');
        $v = $this->w < $this->h;
        $x = $v ? 36 : 60;
        $lw = $this->logo($x, $top + 20, $v ? 70 : 84);
        $url = Context::host();
        $us = $v ? 30 : 40;
        $uw = $this->width($url, 'BigShouldersDisplay-ExtraBold', $us);
        $this->line($url, 'BigShouldersDisplay-ExtraBold', $us, $this->w - $x - $uw, $top + 76, $uw + 4, 'accent');
        $tx = $x + $lw + 26;
        $tw = $this->w - $x - $uw - 30 - $tx;
        $this->line(mb_strtoupper(Club::s('site')), 'BigShouldersDisplay-Black', $v ? 36 : 46, $tx, $top + ($v ? 58 : 64), $tw, 'cream');
        $this->line(Frame::mention($this->c), 'Newsreader-Regular', $v ? 22 : 26, $tx, $top + ($v ? 94 : 100), $tw, 'mist');
    }

    // ------------------------------------------------------------------ outils de dessin

    private function c(string $k): int
    {
        $rgb = $this->col[$k] ?? $this->col['cream'];
        return imagecolorallocate($this->im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function rect(int $x0, int $y0, int $x1, int $y1, string $k): void
    {
        imagefilledrectangle($this->im, $x0, $y0, $x1 - 1, $y1 - 1, $this->c($k));
    }

    private function frame(int $x0, int $y0, int $x1, int $y1, string $k, int $t): void
    {
        $this->rect($x0, $y0, $x1, $y0 + $t, $k);
        $this->rect($x0, $y1 - $t, $x1, $y1, $k);
        $this->rect($x0, $y0, $x0 + $t, $y1, $k);
        $this->rect($x1 - $t, $y0, $x1, $y1, $k);
    }

    private function disc(int $cx, int $cy, int $r, string $k): void
    {
        imagefilledellipse($this->im, $cx, $cy, 2 * $r, 2 * $r, $this->c($k));
    }

    private function font(string $name): string
    {
        return self::FONTS . '/' . $name . '.ttf';
    }

    /** Taille GD (points) d'une taille en pixels CSS. */
    private static function pt(int $px): float
    {
        return $px * 0.75;
    }

    private function width(string $s, string $font, int $px): int
    {
        $b = imagettfbbox(self::pt($px), 0, $this->font($font), self::printable($s));
        return abs($b[2] - $b[0]);
    }

    private function fit(string $s, string $font, int $maxW, int $max): int
    {
        $w = $this->width($s, $font, $max);
        return $w <= $maxW || $w === 0 ? $max : max(24, (int) floor($max * $maxW / $w));
    }

    /** Une ligne (raccourcie avec « … » si elle dépasse $maxW). $tracking : espacement des lettres. */
    private function line(string $s, string $font, int $px, int $x, int $base, int $maxW, string $k, int $tracking = 0, bool $center = false): void
    {
        $s = self::printable($s);
        if ($s === '' || $maxW <= 0) {
            return;
        }
        while (mb_strlen($s) > 1 && $this->width($s, $font, $px) + $tracking * mb_strlen($s) > $maxW) {
            $s = rtrim(mb_substr($s, 0, -2)) . '…';
        }
        if ($center) {
            $x += (int) (($maxW - $this->width($s, $font, $px)) / 2);
        }
        if ($tracking === 0) {
            imagettftext($this->im, self::pt($px), 0, $x, $base, $this->c($k), $this->font($font), $s);
            return;
        }
        foreach (mb_str_split($s) as $ch) {
            $box = imagettftext($this->im, self::pt($px), 0, $x, $base, $this->c($k), $this->font($font), $ch);
            $x = $box[2] + $tracking;
        }
    }

    /** Paragraphe (lignes coupées aux espaces, « … » au-delà de $max lignes) ; renvoie le bas du texte. */
    private function para(string $s, string $font, int $px, int $x, int $y, int $maxW, int $max, string $k, float $lh = 1.3, bool $center = false): int
    {
        $lines = [];
        $cur = '';
        foreach (preg_split('/\s+/u', trim(self::printable($s))) ?: [] as $word) {
            $try = $cur === '' ? $word : "$cur $word";
            if ($cur !== '' && $this->width($try, $font, $px) > $maxW) {
                $lines[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        if (count($lines) > $max) {
            $lines = array_slice($lines, 0, $max);
            $lines[$max - 1] .= '…';
        }
        foreach ($lines as $i => $l) {
            $this->line($l, $font, $px, $x, $y + (int) round($px * ($i + 0.85) * $lh), $maxW, $k, 0, $center);
        }
        return $y + (int) round($px * count($lines) * $lh);
    }

    /** Titre en capitales (deux lignes au plus, réduit pour tenir) ; renvoie le bas. */
    private function title(string $s, int $x, int $y, int $w, int $px, bool $center = false): int
    {
        $s = mb_strtoupper($s);
        $size = $this->width($s, 'BigShouldersDisplay-Black', $px) <= $w ? $px : max((int) ($px * .7), $this->fit($s, 'BigShouldersDisplay-Black', $w, $px));
        return $this->para($s, 'BigShouldersDisplay-Black', $size, $x, $y, $w, 2, 'cream', 0.98, $center) + 34;
    }

    /** Cases de chiffres (la première en jaune si « clé ») ; renvoie le bas. */
    private function stats(array $st, int $x, int $y, int $w, int $px): int
    {
        $n = max(1, count($st));
        $bw = (int) (($w - ($n - 1) * 26) / $n);
        $bh = $px + 90;
        foreach ($st as $i => $s) {
            $bx = $x + $i * ($bw + 26);
            !empty($s[2]) ? $this->rect($bx, $y, $bx + $bw, $y + $bh, 'accent') : $this->frame($bx, $y, $bx + $bw, $y + $bh, 'navy2', 4);
            $num = (string) $s[0];
            $size = min($px, $this->fit($num, 'BigShouldersDisplay-Black', $bw - 20, $px));
            $this->line($num, 'BigShouldersDisplay-Black', $size, $bx + 10, $y + 18 + (int) ($px * .82), $bw - 20, !empty($s[2]) ? 'navy' : 'cream', 0, true);
            $this->line(mb_strtoupper((string) $s[1]), 'BigShouldersDisplay-ExtraBold', 34, $bx + 10, $y + $bh - 22, $bw - 20, !empty($s[2]) ? 'navy' : 'mist', 0, true);
        }
        return $y + $bh;
    }

    private function pill(string $s, int $x, int $y, int $px, ?int $w = null, string $bg = 'accent', string $fg = 'navy'): void
    {
        $tw = $this->width($s, 'BigShouldersDisplay-Black', $px);
        $w ??= $tw + 56;
        $h = (int) ($px * 1.6);
        $this->rect($x + (int) ($h / 2), $y, $x + $w - (int) ($h / 2), $y + $h, $bg);
        $this->disc($x + (int) ($h / 2), $y + (int) ($h / 2), (int) ($h / 2), $bg);
        $this->disc($x + $w - (int) ($h / 2), $y + (int) ($h / 2), (int) ($h / 2), $bg);
        $this->line($s, 'BigShouldersDisplay-Black', $px, $x + 10, $y + (int) ($h / 2 + $px * .36), $w - 20, $fg, 0, true);
    }

    /** QR code (fond blanc, modules bleu marine) et sa légende. */
    private function qr(string $url, int $x, int $y, int $size, string $label = ''): void
    {
        $m = Qr::matrix($url);
        $n = count($m);
        $pad = 14;
        $lh = $label !== '' ? 40 : 0;
        $this->rect($x, $y, $x + $size, $y + $size + $lh, 'white');
        $mod = ($size - 2 * $pad) / ($n + 2);
        $navy = $this->c('navy');
        foreach ($m as $r => $row) {
            foreach ($row as $col => $dark) {
                if ($dark) {
                    $px = (int) round($x + $pad + ($col + 1) * $mod);
                    $py = (int) round($y + $pad + ($r + 1) * $mod);
                    imagefilledrectangle($this->im, $px, $py, (int) round($x + $pad + ($col + 2) * $mod) - 1, (int) round($y + $pad + ($r + 2) * $mod) - 1, $navy);
                }
            }
        }
        if ($label !== '') {
            $this->line(mb_strtoupper($label), 'BigShouldersDisplay-ExtraBold', 24, $x + 8, $y + $size + 26, $size - 16, 'navy', 0, true);
        }
    }

    /** Photo recadrée dans la zone ($contain : entière, centrée) ; $dim : voile bleu (0–127, 127 = aucun). */
    private function photo(?array $img, int $x, int $y, int $w, int $h, bool $contain = false, int $dim = 127): void
    {
        $file = $img['file'] ?? null;
        $src = $file && is_file($file) ? Images::open($file) : null;
        if (!$src) {
            return;
        }
        [$sw, $sh] = [imagesx($src), imagesy($src)];
        if ($contain) {
            $k = min($w / $sw, $h / $sh);
            $dw = (int) round($sw * $k);
            $dh = (int) round($sh * $k);
            imagecopyresampled($this->im, $src, $x + (int) (($w - $dw) / 2), $y + (int) (($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
        } else {
            $k = max($w / $sw, $h / $sh);
            $cw = (int) round($w / $k);
            $ch = (int) round($h / $k);
            imagecopyresampled($this->im, $src, $x, $y, (int) (($sw - $cw) / 2), (int) max(0, min($sh - $ch, $sh * .35 - $ch / 2)), $w, $h, $cw, $ch);
        }
        imagedestroy($src);
        if ($dim < 127) {
            $n = $this->col['navy'];
            imagefilledrectangle($this->im, $x, $y, $x + $w - 1, $y + $h - 1, imagecolorallocatealpha($this->im, $n[0], $n[1], $n[2], 127 - $dim));
        }
        if (!$contain && ($img['credit'] ?? '') !== '') {
            $cw = $this->width($img['credit'], 'Newsreader-Regular', 22) + 24;
            imagefilledrectangle($this->im, $x + $w - $cw - 16, $y + $h - 50, $x + $w - 16, $y + $h - 14, imagecolorallocatealpha($this->im, 0, 0, 0, 50));
            $this->line($img['credit'], 'Newsreader-Regular', 22, $x + $w - $cw - 4, $y + $h - 24, $cw, 'white');
        }
    }

    /** Carte des stades (terres, points proportionnels, stade du club en rouge). */
    private function map(array $d, int $x, int $y, int $w, int $h): void
    {
        $m = $d['map'];
        $k = min($w / $m['w'], $h / $m['h']);
        $ox = $x + (int) (($w - $m['w'] * $k) / 2);
        $oy = $y + (int) (($h - $m['h'] * $k) / 2);
        $land = imagecolorallocatealpha($this->im, 255, 255, 255, 110);
        $edge = imagecolorallocatealpha($this->im, 255, 255, 255, 80);
        $clip = [$ox, $oy, $ox + (int) ($m['w'] * $k), $oy + (int) ($m['h'] * $k)];
        imagesetclip($this->im, ...$clip);
        foreach ($m['land'] as $pts) {
            $flat = [];
            foreach ($pts as [$px, $py]) {
                $flat[] = (int) round($ox + $px * $k);
                $flat[] = (int) round($oy + $py * $k);
            }
            if (count($flat) >= 6) {
                imagefilledpolygon($this->im, $flat, $land);
                imagepolygon($this->im, $flat, $edge);
            }
        }
        $max = max(1, ...array_map(fn ($p) => $p['n'], $d['points'] ?: [['n' => 1]]));
        $a = $this->col['accent'];
        foreach ($d['points'] as $p) {
            $r = (int) round((3 + 15 * sqrt($p['n'] / $max)) * $k);
            $cx = (int) round($ox + $p['x'] * $k);
            $cy = (int) round($oy + $p['y'] * $k);
            if ($p['home']) {
                $this->disc($cx, $cy, $r + 3, 'white');
                $this->disc($cx, $cy, $r, 'red');
            } else {
                imagefilledellipse($this->im, $cx, $cy, 2 * $r, 2 * $r, imagecolorallocatealpha($this->im, $a[0], $a[1], $a[2], 55));
                imageellipse($this->im, $cx, $cy, 2 * $r, 2 * $r, $this->c('accent'));
            }
        }
        imagesetclip($this->im, 0, 0, $this->w - 1, $this->h - 1);
    }

    /** Logo du musée ; renvoie sa largeur. */
    private function logo(int $x, int $y, int $h): int
    {
        $logo = @imagecreatefrompng(PUBLIC_PATH . Club::s('logo_png'));
        if (!$logo) {
            return 0;
        }
        $w = (int) round(imagesx($logo) * $h / imagesy($logo));
        imagecopyresampled($this->im, $logo, $x, $y, 0, 0, $w, $h, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
        return $w;
    }

    /** Texte imprimable avec les polices : emoji et pictogrammes retirés (sinon un carré vide). */
    private static function printable(string $s): string
    {
        $s = (string) preg_replace('/[\x{2190}-\x{21FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}\x{20E3}\x{1F000}-\x{1FAFF}\x{E0000}-\x{E007F}]/u', '', $s);
        return trim((string) preg_replace('/ {2,}/', ' ', $s));
    }
}
