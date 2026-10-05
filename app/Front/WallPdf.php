<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Request;
use App\Core\Response;
use App\Pdf\Layout;
use App\Services\Images;
use App\Services\PhotoWall;

/**
 * Export PDF des murs de photos : le tirage affiché à l'écran (mêmes photos dans le même ordre,
 * même numéro de planche ou d'édition, même motif), mis en page pour l'impression :
 * - planche-contact : A4, six bandes de film de six vues, numéro et crédit dans la marge ;
 * - Le Lion illustré : A4, une page de journal (Une, deux articles, « En images », brèves) ;
 * - grande mosaïque : A4 paysage, le motif (photos teintées comme à l'écran), puis les mêmes
 *   photos en couleurs avec tous les crédits.
 * La page envoie les codes des photos montrées (formulaire caché du bouton « Télécharger en
 * PDF ») : seules les photos des murs sont acceptées, un code inconnu est ignoré. Chaque photo
 * du PDF est un lien vers sa fiche.
 */
final class WallPdf
{
    /** Photos au plus par mur (planche : 6 bandes de 6 ; journal : Une, 2 articles, 4 « En images », 6 brèves ; mosaïque : 24 × 11 cases). */
    private const MAX = ['planche' => 36, 'journal' => 13, 'mosaique' => 264];
    /** Papier photo de la planche, encre des mentions au crayon, film, marges imprimées du film. */
    private const PAPER = 'F4EFE2';
    private const RED = 'C4321F';
    private const FILM = '141414';
    private const AMBER = 'E7A93A';
    /** Papier journal, encre, rouge des surtitres, gris des crédits. */
    private const NEWS = 'F8F4E8';
    private const INK = '1B1B1B';
    private const KICK = 'B0261B';
    private const GREY = '5A5A5A';

    /** POST /interactif/{mur}/pdf/ (GET : retour au mur). */
    public static function handle(Request $req, string $kind): Response
    {
        $wall = url(Walls::WALLS[$kind][0]);
        if ($req->method !== 'POST' || !isset(Walls::PDF[$kind])) {
            return Response::redirect($wall);
        }
        $codes = array_values(array_filter(explode('.', $req->str('photos')), fn ($c) => preg_match('/^[0-9a-f]{10}$/', $c) === 1));
        $photos = PhotoWall::byCodes(array_slice($codes, 0, self::MAX[$kind]));
        if (!$photos) {
            return Response::redirect($wall);
        }
        $views = array_map([Walls::class, 'view'], $photos);
        $f = Walls::filters($req);
        $n = max(0, min(99999, (int) $req->str('n')));
        $motif = $req->str('motif');
        $motif = isset(Walls::MOTIFS[$motif]) ? $motif : '100';
        [$name, $build] = match ($kind) {
            'planche' => ['sochaux-retro-planche-contact-' . sprintf('%04d', $n), fn () => self::contactSheet($views, $n, $f)],
            'journal' => ['sochaux-retro-le-lion-illustre-' . $n, fn () => self::newspaper($views, $n, $f)],
            default => ['sochaux-retro-mosaique-' . $motif, fn () => self::mosaic($views, $motif, $f)],
        };
        return PdfExport::respond($req, 'mur-' . $kind, $name, $build, false);
    }

    // ================================================================== planche-contact

    private static function contactSheet(array $views, int $sheet, array $f): string
    {
        $title = t('Planche-contact');
        $no = t('Planche n°') . ' ' . sprintf('%04d', $sheet);
        $l = PdfExport::layout(Walls::WALLS['planche'][0], $title . ' · ' . $no, $title . ' · ' . $no, t('Les murs de photos'));
        $l->ml = $l->mr = 30;
        $l->newPage();
        $l->fillPage(self::PAPER);
        self::band($l, $title);
        // Mentions au crayon gras, comme sur la planche du labo : numéro et date.
        $l->text($l->ml + 2, 80, $no, 'serif-i', 17, self::RED);
        $date = date_fr(date('Y-m-d'));
        $l->text($l->pw - $l->mr - 2 - $l->width($date, 'serif-i', 17), 80, $date, 'serif-i', 17, self::RED);

        $cw = $l->cw();
        [$pad, $gap] = [9.0, 6.0];
        $fw = ($cw - 2 * $pad - 5 * $gap) / 6;
        $fh = $fw * 2 / 3;
        $sh = 15 + $fh + 19;
        $y = 92.0;
        foreach (array_chunk($views, 6, true) as $s => $strip) {
            $l->rotated(random_int(-4, 4) / 10, $l->ml + $cw / 2, $y + $sh / 2, function () use ($l, $strip, $s, $y, $cw, $sh, $pad, $gap, $fw, $fh) {
                $l->rect($l->ml, $y, $cw, $sh, self::FILM);
                // Perforations, en haut et en bas
                for ($x = $l->ml + 5; $x + 4 < $l->ml + $cw - 3; $x += 11) {
                    $l->rect($x, $y + 2.6, 4, 3.6, self::PAPER);
                    $l->rect($x, $y + $sh - 6.2, 4, 3.6, self::PAPER);
                }
                $l->polygon([[$l->ml + $pad, $y + 10], [$l->ml + $pad + 4.2, $y + 7.6], [$l->ml + $pad + 4.2, $y + 12.4]], self::AMBER);
                $l->text($l->ml + $pad + 7, $y + 12.3, '400 · SOCHAUX RÉTRO · ' . sprintf('%02d', $s + 1), 'display-b', 6, self::AMBER, 1.1);
                $i = 0;
                foreach ($strip as $k => $p) {
                    $x = $l->ml + $pad + $i++ * ($fw + $gap);
                    $l->rect($x, $y + 15, $fw, $fh, '2A2A2A');
                    if ($img = self::image($l, $p['rel'], 480)) {
                        $l->drawImage($img, $x, $y + 15, $fw, $fh, true, 0.5);
                    }
                    self::link($l, $x, $y + 15, $fw, $fh, $p['href']);
                    $num = (string) ($k + 1);
                    $l->text($x, $y + 15 + $fh + 8.4, $num, 'display-b', 6.6, self::PAPER, 0.3);
                    $nw = $l->width($num, 'display-b', 6.6, 0.3) + 4;
                    $cred = $l->fit('© ' . mb_strtoupper($p['credit']), 'display-b', 6.2, $fw - $nw, 0.35);
                    $l->text($x + $nw, $y + 15 + $fh + 8.4, $cred, 'display-b', 6.2, self::AMBER, 0.35);
                }
            });
            $y += $sh + 8;
        }

        // Ce que montre la planche, et ses crédits
        $l->y = $y + 6;
        $choice = self::choice($f);
        $l->para([
            Layout::run(sprintf(t('%d photos tirées au hasard dans la médiathèque du musée'), count($views)) . ($choice !== '' ? ' (' . $choice . ')' : '') . '. ', 'serif', 9, self::INK),
            Layout::run(t('Cliquez sur une photo pour ouvrir sa fiche sur le site.'), 'serif-i', 9, 'muted'),
        ], ['after' => 3, 'lh' => 1.3]);
        $l->para([
            Layout::run(mb_strtoupper(t('Crédits photo de cette planche')) . self::colon(), 'display-b', 7.6, 'navy'),
            Layout::run(self::creditList($views) . '.', 'serif', 8.4, 'muted'),
        ], ['after' => 0, 'lh' => 1.3]);
        return $l->finish();
    }

    // ================================================================== Le Lion illustré

    private static function newspaper(array $views, int $number, array $f): string
    {
        $name = t('Le Lion illustré');
        $l = PdfExport::layout(Walls::WALLS['journal'][0], $name . ' · ' . t('N°') . ' ' . $number, $name . ' · ' . t('N°') . ' ' . number_format($number, 0, ',', ' '), t('Les murs de photos'));
        $l->ml = $l->mr = 30;
        $l->newPage();
        $l->fillPage(self::NEWS);
        self::band($l, $name);
        $cw = $l->cw();
        $x0 = $l->ml;
        $bottom = $l->ph - 54;

        // Titre du journal
        $l->text($x0, 66, t('Journal photographique du musée Sochaux Rétro'), 'serif-i', 8.6, '444444');
        $r = t('Une édition unique, tirée pour vous');
        $l->text($x0 + $cw - $l->width($r, 'serif-i', 8.6), 66, $r, 'serif-i', 8.6, '444444');
        $l->line($x0, 70, $x0 + $cw, 70, self::INK, 0.6);
        $t = mb_strtoupper($name);
        $size = 60;
        while ($size > 30 && $l->width($t, 'display', $size, 1) > $cw) {
            $size -= 2;
        }
        $base = 78 + $l->cap('display', $size) + $size * 0.12; // place pour l'accent de « ILLUSTRÉ »
        $l->text($x0 + ($cw - $l->width($t, 'display', $size, 1)) / 2, $base, $t, 'display', $size, 'navy', 1);
        $ly = $base + 7;
        $l->line($x0, $ly, $x0 + $cw, $ly, self::INK, 0.8);
        $special = Walls::special($f);
        $parts = [[mb_strtoupper(date_fr(date('Y-m-d'), true)), self::INK], [mb_strtoupper(t('N°')) . ' ' . number_format($number, 0, ',', ' '), self::INK]];
        if ($special !== '') {
            $parts[] = [mb_strtoupper(t('Édition spéciale') . self::colon() . $special), self::KICK];
        }
        $parts[] = [mb_strtoupper(t('Gratuit')), self::INK];
        $sep = '   ·   ';
        $total = array_sum(array_map(fn ($p) => $l->width($p[0], 'display-b', 8, 1), $parts)) + (count($parts) - 1) * $l->width($sep, 'display-b', 8, 1);
        $x = $x0 + ($cw - $total) / 2;
        foreach ($parts as $i => [$txt, $col]) {
            if ($i) {
                $x += $l->text($x, $ly + 11, $sep, 'display-b', 8, self::INK, 1);
            }
            $x += $l->text($x, $ly + 11, $txt, 'display-b', 8, $col, 1);
        }
        $l->line($x0, $ly + 15.5, $x0 + $cw, $ly + 15.5, self::INK, 0.8);
        $l->line($x0, $ly + 18, $x0 + $cw, $ly + 18, self::INK, 0.8);
        $top = $ly + 28;

        $lead = $views[0];
        $side = array_slice($views, 1, 2);
        $row = array_slice($views, 3, 4);
        $briefs = array_slice($views, 7, 6);

        // Bas de page d'abord (hauteur connue), la Une prend le reste.
        $foot = t('Toutes les photos de cette édition sont créditées et viennent de la médiathèque du musée.');
        $footH = 16;
        $rowW = ($cw - 3 * 14) / 4;
        $rowH = $rowW * 2 / 3;
        $rowCaps = array_map(fn ($p) => self::clamp($l, $p['caption'] !== '' ? $p['caption'] : $p['title'], 'serif-i', 8, self::INK, $rowW, 3), $row);
        $rowBand = $row ? 30 + $rowH + 5 + max(array_map(fn ($c) => self::h($c), $rowCaps)) + 11 : 0;
        $bw = ($cw - 5 * 10) / 6;
        $briefCaps = array_map(fn ($p) => self::clamp($l, Walls::headline($p), 'serif-b', 7.6, self::INK, $bw, 3, 1.2), $briefs);
        $briefBand = $briefs ? 30 + $bw + 5 + max(array_map(fn ($c) => self::h($c), $briefCaps)) + 10 : 0;
        $avail = $bottom - $footH - $rowBand - $briefBand - ($rowBand ? 12 : 0) - ($briefBand ? 12 : 0) - $top;

        // La Une : article principal à gauche, deux articles à droite
        $sw = $side ? 160 : 0;
        $lw = $cw - ($side ? $sw + 25 : 0);
        $leadHead = self::headlineLines($l, Walls::headline($lead), $lw, 26, 16, 2);
        $leadCap = $lead['caption'] !== '' && $lead['caption'] !== Walls::headline($lead) ? self::clamp($l, $lead['caption'], 'serif-i', 9, self::INK, $lw, 2) : [];
        $leadText = 13 + self::h($leadHead) + 6 + 5 + self::h($leadCap) + 11;
        $sideItems = [];
        foreach ($side as $p) {
            $head = self::headlineLines($l, Walls::headline($p), $sw, 13, 10, 3);
            $cap = $p['caption'] !== '' && $p['caption'] !== Walls::headline($p) ? self::clamp($l, $p['caption'], 'serif-i', 7.8, self::INK, $sw, 2) : [];
            $sideItems[] = [$p, $head, $cap, 12 + self::h($head) + 4 + 4 + self::h($cap) + 10];
        }
        $sideText = array_sum(array_column($sideItems, 3)) + (count($sideItems) > 1 ? 14 : 0);
        $sidePh = $sideItems ? max(55, min($sw * 0.75, ($avail - $sideText) / count($sideItems))) : 0;
        $frontH = $sideItems ? min($avail, $sideText + $sidePh * count($sideItems)) : $avail;
        $leadPh = max(150, min($lw * 0.72, $frontH - $leadText));
        $frontH = max($frontH, $leadText + $leadPh);

        self::article($l, $lead, $x0, $top, $lw, $leadHead, $leadCap, $leadPh, 8.6, 1200);
        if ($sideItems) {
            $sx = $x0 + $lw + 25;
            $l->line($sx - 12.5, $top, $sx - 12.5, $top + $frontH, self::INK, 0.6);
            $y = $top;
            foreach ($sideItems as $j => [$p, $head, $cap, $th]) {
                if ($j) {
                    $l->line($sx, $y - 7, $sx + $sw, $y - 7, self::INK, 0.6);
                }
                self::article($l, $p, $sx, $y, $sw, $head, $cap, $sidePh, 7.6, 480);
                $y += $th + $sidePh + 14;
            }
        }
        $y = $top + $frontH + 12;

        // En images
        if ($row) {
            $y = self::rubric($l, t('En images'), $y);
            foreach ($row as $i => $p) {
                $x = $x0 + $i * ($rowW + 14);
                self::photo($l, $p, $x, $y, $rowW, $rowH, 480);
                $yy = $y + $rowH + 5;
                $yy += $l->drawLines($rowCaps[$i], $x, $yy, $rowW);
                self::credit($l, $p, $x, $yy + 7.5, $rowW, 6.4);
            }
            $y += $rowBand - 30 + 12;
        }
        // Brèves
        if ($briefs) {
            $y = self::rubric($l, t('Brèves'), $y);
            foreach ($briefs as $i => $p) {
                $x = $x0 + $i * ($bw + 10);
                self::photo($l, $p, $x, $y, $bw, $bw, 320);
                $yy = $y + $bw + 5;
                $yy += $l->drawLines($briefCaps[$i], $x, $yy, $bw);
                self::link($l, $x, $y + $bw + 5, $bw, self::h($briefCaps[$i]), $p['href']);
                self::credit($l, $p, $x, $yy + 7, $bw, 5.8, false);
            }
        }
        // Pied du journal
        $l->line($x0, $bottom - $footH + 2, $x0 + $cw, $bottom - $footH + 2, self::INK, 0.6);
        $l->text($x0 + ($cw - $l->width($foot, 'serif-i', 8.4)) / 2, $bottom - 3, $foot, 'serif-i', 8.4, '555555');
        return $l->finish();
    }

    /** Article : surtitre rouge, titre, photo, légende, crédit (titre et photo mènent à la fiche). */
    private static function article(Layout $l, array $p, float $x, float $y, float $w, array $head, array $cap, float $ph, float $creditSize, int $px): void
    {
        $l->text($x, $y + 8, $l->fit(mb_strtoupper(Walls::kicker($p)), 'display-b', 7.6, $w, 1.1), 'display-b', 7.6, self::KICK, 1.1);
        $y += 13;
        $hh = $l->drawLines($head, $x, $y, $w);
        self::link($l, $x, $y, $w, $hh, $p['href']);
        $y += $hh + 6;
        self::photo($l, $p, $x, $y, $w, $ph, $px);
        $y += $ph + 5;
        $y += $l->drawLines($cap, $x, $y, $w);
        self::credit($l, $p, $x, $y + 8, $w, $creditSize);
    }

    private static function photo(Layout $l, array $p, float $x, float $y, float $w, float $h, int $px): void
    {
        $l->rect($x, $y, $w, $h, 'DDD5C2');
        if ($img = self::image($l, $p['rel'], $px)) {
            $l->drawImage($img, $x, $y, $w, $h, true, 0.35);
        }
        self::link($l, $x, $y, $w, $h, $p['href']);
    }

    private static function credit(Layout $l, array $p, float $x, float $base, float $w, float $size, bool $word = true): void
    {
        $t = $l->fit(mb_strtoupper(($word ? t('Photo') . self::colon() : '© ') . $p['credit']), 'display-b', $size, $w, 0.9);
        $l->text($x, $base, $t, 'display-b', $size, self::GREY, 0.9);
    }

    /** Intertitre de rubrique sous un double filet ; renvoie le haut du contenu. */
    private static function rubric(Layout $l, string $title, float $y): float
    {
        $l->line($l->ml, $y, $l->ml + $l->cw(), $y, self::INK, 0.8);
        $l->line($l->ml, $y + 2.5, $l->ml + $l->cw(), $y + 2.5, self::INK, 0.8);
        $l->text($l->ml, $y + 19, mb_strtoupper($title), 'display', 13, self::INK, 2);
        return $y + 30;
    }

    /** Titre en capitales, réduit jusqu'à tenir en $max lignes (puis coupé). */
    private static function headlineLines(Layout $l, string $t, float $w, float $size, float $min, int $max): array
    {
        $t = mb_strtoupper($t);
        while ($size > $min && count($l->wrap([Layout::run($t, 'display-b', $size, '111111')], $w, 1.02)) > $max) {
            $size -= 1;
        }
        return self::clamp($l, $t, 'display-b', $size, '111111', $w, $max, 1.02);
    }

    // ================================================================== grande mosaïque

    private static function mosaic(array $views, string $motif, array $f): string
    {
        $name = t('La grande mosaïque');
        $label = t(Walls::MOTIFS[$motif]);
        $l = PdfExport::layout(Walls::WALLS['mosaique'][0], $name . ' · ' . $label, $name . ' · ' . $label, t('Les murs de photos'));
        [$l->pw, $l->ph] = [Layout::H, Layout::W];
        $l->ml = $l->mr = 30;
        $grid = Walls::grid($motif, true);
        $rows = count($grid);
        $cols = count($grid[0]);
        $cells = [];
        $list = $views;
        while (count($list) < $rows * $cols) {
            $list = array_merge($list, $views);
        }
        $cw = $l->cw();
        $pitch = $cw / $cols;
        $gap = 2.0;
        $credits = [];
        foreach ($views as $p) {
            $credits[$p['who']] = ($credits[$p['who']] ?? 0) + 1;
        }
        arsort($credits);
        $fmt = fn (array $c): string => implode(', ', array_map(fn ($who, $n) => $who . ' (' . $n . ')', array_keys($c), $c));

        foreach (['motif', 'couleurs'] as $pg => $mode) {
            $l->newPage();
            $l->dark[$l->page] = true;
            $l->fillPage('navy');
            $l->logo($l->ml, 16, 46);
            $l->text($l->ml + 44, 34, mb_strtoupper($name), 'display', 22, 'cream', 0.6);
            $sub = $pg === 0 ? mb_strtoupper(t('Le motif')) . self::colon() : mb_strtoupper(t('Les photos en couleurs')) . ' · ';
            $w1 = $l->text($l->ml + 44, 51, $sub, 'display-b', 9.4, 'cream', 1.1);
            $l->text($l->ml + 44 + $w1, 51, mb_strtoupper($label), 'display-b', 9.4, 'yellow', 1.1);
            $choice = self::choice($f);
            if ($choice !== '') {
                $c = mb_strtoupper($choice);
                $l->text($l->pw - $l->mr - $l->width($c, 'display-b', 9, 1.1), 51, $c, 'display-b', 9, 'mist', 1.1);
            }
            $top = 66.0;
            $k = 0;
            foreach ($grid as $y => $line) {
                foreach ($line as $x => $on) {
                    $p = $list[$k++];
                    $tx = $l->ml + $x * $pitch;
                    $ty = $top + $y * $pitch;
                    $s = $pitch - $gap;
                    $img = self::tile($l, $p['rel'], $mode === 'motif' ? ($on ? 'on' : 'off') : 'color');
                    if ($img) {
                        $l->drawImage($img, $tx, $ty, $s, $s);
                    } else {
                        $l->rect($tx, $ty, $s, $s, $on && $mode === 'motif' ? 'yellow' : '12245A');
                    }
                    self::link($l, $tx, $ty, $s, $s, $p['href']);
                }
            }
            $l->y = $top + $rows * $pitch + 10;
            if ($pg === 0) {
                $more = count($credits) - 10;
                $l->para([
                    Layout::run(sprintf(t('%d photos de la médiathèque du musée dessinent le motif.'), count($views)) . ' ', 'serif', 9, 'cream'),
                    Layout::run(t('Page suivante : les mêmes photos en couleurs, et tous les crédits.') . ' ' . t('Cliquez sur une photo pour ouvrir sa fiche sur le site.'), 'serif-i', 9, 'mist'),
                ], ['after' => 3, 'lh' => 1.3]);
                $l->para([
                    Layout::run(mb_strtoupper(t('Crédits photo de cette mosaïque')) . self::colon(), 'display-b', 7.8, 'cream'),
                    Layout::run($fmt(array_slice($credits, 0, 10, true)) . ($more > 0 ? ', ' . t('et {n} autres photographes ou sources', ['n' => $more]) . '.' : '.'), 'serif', 8.4, 'mist'),
                ], ['after' => 0, 'lh' => 1.3]);
            } else {
                $l->para([
                    Layout::run(mb_strtoupper(t('Tous les crédits photo')) . self::colon(), 'display-b', 7.8, 'cream'),
                    Layout::run($fmt($credits) . '.', 'serif', 8, 'mist'),
                ], ['after' => 0, 'lh' => 1.28]);
            }
        }
        return $l->finish();
    }

    /**
     * Case de la mosaïque : photo recadrée au carré ; « on » (motif) et « off » (fond) en niveaux de
     * gris teintés comme à l'écran (luminosité, contraste, puis jaune ou bleu en « produit ») ;
     * « color » telle quelle. Les gris sont teintés par la palette : rapide, même pour 264 cases.
     */
    private static function tile(Layout $l, string $rel, string $tone): ?array
    {
        $file = Images::derivative($rel, 160);
        $src = $file ? @imagecreatefromstring((string) file_get_contents($file)) : false;
        if (!$src) {
            return null;
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $side = min($w, $h);
        $px = 112;
        $im = imagecreatetruecolor($px, $px);
        imagecopyresampled($im, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $px, $px, $side, $side);
        imagedestroy($src);
        if ($tone !== 'color') {
            imagefilter($im, IMG_FILTER_GRAYSCALE);
            imagetruecolortopalette($im, false, 256);
            [$b, $c, $rgb] = $tone === 'on' ? [1.75, 0.8, [246, 196, 0]] : [0.6, 1.15, [20, 42, 104]];
            for ($i = 0, $n = imagecolorstotal($im); $i < $n; $i++) {
                $g = imagecolorsforindex($im, $i)['red'] / 255;
                $v = max(0.0, min(1.0, (min(1.0, $g * $b) - 0.5) * $c + 0.5));
                imagecolorset($im, $i, (int) round($v * $rgb[0]), (int) round($v * $rgb[1]), (int) round($v * $rgb[2]));
            }
        }
        ob_start();
        imagejpeg($im, null, 82);
        imagedestroy($im);
        return $l->pdf->image('tile-' . $tone . '-' . md5($rel), (string) ob_get_clean());
    }

    // ================================================================== commun

    /** Bandeau du haut : bleu nuit, blason qui déborde, nom du musée, étiquette jaune du document. */
    private static function band(Layout $l, string $label): void
    {
        $h = 44;
        $l->rect(0, 0, $l->pw, $h, 'navy');
        $l->rect(0, $h, $l->pw, 3, 'yellow');
        $l->logo($l->ml - 4, 5, 52);
        $x = $l->ml + 44;
        $l->text($x, 22, mb_strtoupper($l->site), 'display', 15, 'cream', 0.5);
        $l->text($x, 35, $l->tagline, 'serif-i', 8.6, 'mist');
        $lab = mb_strtoupper($label);
        $kw = $l->width($lab, 'display', 10, 1.1) + 20;
        $kx = $l->pw - $l->mr - $kw;
        $l->rect($kx + 3, 14, $kw, 19, 'deep');
        $l->rect($kx, 11, $kw, 19, 'yellow');
        $l->text($kx + 10, 20.5 + $l->cap('display', 10) / 2, $lab, 'display', 10, 'navy', 1.1);
    }

    private static function image(Layout $l, string $rel, int $width): ?array
    {
        $file = Images::derivative($rel, $width);
        return $file ? $l->loadImage($file) : null;
    }

    /** Zone cliquable vers la fiche d'une photo (coordonnées de la page, y depuis le haut). */
    private static function link(Layout $l, float $x, float $y, float $w, float $h, string $href): void
    {
        if ($href !== '' && $h > 0) {
            $l->pdf->link($l->page, $x, $l->ph - $y - $h, $x + $w, $l->ph - $y, base_url() . $href);
        }
    }

    /** Texte coupé à $max lignes (avec « … »). */
    private static function clamp(Layout $l, string $text, string $font, float $size, string $color, float $w, int $max, float $lh = 1.25): array
    {
        $lines = $l->wrap([Layout::run($text, $font, $size, $color)], $w, $lh);
        if (count($lines) <= $max) {
            return $lines;
        }
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        while (count($words) > 1) {
            array_pop($words);
            $lines = $l->wrap([Layout::run(rtrim(implode(' ', $words), ' ,;:.–-') . '…', $font, $size, $color)], $w, $lh);
            if (count($lines) <= $max) {
                return $lines;
            }
        }
        return array_slice($lines, 0, $max);
    }

    /** Deux-points : espace avant en français seulement. */
    private static function colon(): string
    {
        return \App\Services\I18n::isEn() ? ': ' : ' : ';
    }

    private static function h(array $lines): float
    {
        return array_sum(array_column($lines, 'h'));
    }

    /** Filtres choisis, en clair (« années 1990 · Francis Reinoso »). */
    private static function choice(array $f): string
    {
        return Walls::special($f);
    }

    /** Crédits regroupés, les plus présents d'abord : « L'Est Républicain (12), Lionel Vadam (5)… ». */
    private static function creditList(array $views): string
    {
        $c = [];
        foreach ($views as $p) {
            $c[$p['who']] = ($c[$p['who']] ?? 0) + 1;
        }
        arsort($c);
        return implode(', ', array_map(fn ($who, $n) => $who . ' (' . $n . ')', array_keys($c), $c));
    }
}
