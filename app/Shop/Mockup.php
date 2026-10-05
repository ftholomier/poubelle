<?php
declare(strict_types=1);

namespace App\Shop;

/**
 * Aperçus des produits : le dessin d'une face posé sur une maquette du produit vierge (dessinée
 * en vectoriel, dans la couleur choisie) : t-shirt, sweat, mug, tote bag, casquette, écharpe,
 * poster ou carte, sticker. Sert à l'éditeur du back-office et, plus tard, à la boutique.
 * Renvoie le SVG et la zone d'impression (coordonnées de la maquette) pour l'éditeur.
 */
final class Mockup
{
    /**
     * @param array $face face du modèle (w, h, bleed, bg, layers)
     * @return array{svg:string,area:array{x:float,y:float,w:float,h:float},vb:array{0:float,1:float}}
     */
    public static function render(string $kind, string $faceKey, array $face, string $color, array $values = []): array
    {
        [$w, $h] = [(float) $face['w'], (float) $face['h']];
        $design = Vector::svgShapes(Vector::shapes($face['layers'] ?? [], $values), $face['bg'] ?: null, [0, 0, $w, $h]);
        $color = Vector::hex($color ?: '#FFFFFF', '#FFFFFF');
        $dark = self::isDark($color);
        $stroke = $dark ? 'rgba(255,255,255,.18)' : 'rgba(14,31,77,.28)';
        $nest = fn (float $x, float $y, float $ww, float $hh, string $vb = '', string $extra = ''): string => '<svg x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($ww, 2) . '" height="' . round($hh, 2) . '" viewBox="' . ($vb ?: "0 0 $w $h") . '" preserveAspectRatio="none" overflow="hidden"' . $extra . '>' . $design . '</svg>';
        $defs = '<defs><linearGradient id="mk-light" x1="0" x2="1"><stop offset="0" stop-color="#000" stop-opacity=".16"/><stop offset=".22" stop-color="#fff" stop-opacity=".10"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/><stop offset=".8" stop-color="#000" stop-opacity=".06"/><stop offset="1" stop-color="#000" stop-opacity=".22"/></linearGradient>'
            . '<linearGradient id="mk-fold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".08"/><stop offset="1" stop-color="#000" stop-opacity=".10"/></linearGradient>'
            . '<filter id="mk-shadow" x="-10%" y="-10%" width="120%" height="125%"><feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#0e1f4d" flood-opacity=".22"/></filter></defs>';

        switch ($kind) {
            case 'tee':
            case 'hoodie':
                $vbw = 1000;
                $vbh = 1100;
                $back = in_array($faceKey, ['dos', 'back'], true);
                $body = 'M380 60 C' . ($back ? '430 95 570 95' : '420 150 580 150') . ' 620 60 L820 112 L992 330 L862 428 L790 362 L792 1062 Q500 1086 208 1062 L210 362 L138 428 L8 330 L180 112 Z';
                $s = '<path d="' . $body . '" fill="' . $color . '" stroke="' . $stroke . '" stroke-width="3" filter="url(#mk-shadow)"/>';
                if ($kind === 'hoodie') {
                    $s .= '<path d="M372 64 C330 -6 670 -6 628 64 C600 ' . ($back ? '96' : '170') . ' 400 ' . ($back ? '96' : '170') . ' 372 64 Z" fill="' . $color . '" stroke="' . $stroke . '" stroke-width="3"/>';
                    if (!$back) {
                        $s .= '<path d="M330 820 L670 820 L700 960 L300 960 Z" fill="none" stroke="' . $stroke . '" stroke-width="3"/><path d="M470 140 L466 300 M530 140 L534 300" stroke="' . $stroke . '" stroke-width="4" fill="none"/>';
                    }
                } else {
                    $s .= '<path d="M380 60 C' . ($back ? '430 95 570 95' : '420 150 580 150') . ' 620 60" fill="none" stroke="' . $stroke . '" stroke-width="14"/>';
                }
                $s .= '<path d="' . $body . '" fill="url(#mk-fold)"/>';
                // Zone d'impression : poitrine (ou dos), centrée ; échelle commune aux deux faces.
                $k = ($kind === 'hoodie' ? 360 : 400) / ($kind === 'hoodie' ? 260 : 280);
                $aw = $w * $k;
                $ah = $h * $k;
                $ax = 500 - $aw / 2;
                $ay = $kind === 'hoodie' ? ($back ? 230 : 250) : ($back ? 200 : 230);
                $s .= $nest($ax, $ay, $aw, $ah);
                break;

            case 'mug':
                $vbw = 1000;
                $vbh = 820;
                $s = '<g filter="url(#mk-shadow)"><path d="M770 260 C960 250 960 580 770 590" fill="none" stroke="' . $color . '" stroke-width="64" stroke-linecap="round"/><path d="M770 260 C960 250 960 580 770 590" fill="none" stroke="' . $stroke . '" stroke-width="2"/>'
                    . '<path d="M230 150 L770 150 L770 720 Q500 770 230 720 Z" fill="' . $color . '" stroke="' . $stroke . '" stroke-width="3"/></g>'
                    . '<ellipse cx="500" cy="150" rx="270" ry="34" fill="#e9e6df" stroke="' . $stroke . '" stroke-width="3"/>';
                // Face visible : le centre du tour (environ la moitié), déformée par le cylindre (bandes).
                $vis = $w * 0.52;
                $k = 540 / $vis;
                $ah = $h * $k;
                $ay = 435 - $ah / 2;
                $bands = 18;
                for ($i = 0; $i < $bands; $i++) {
                    $a0 = -M_PI / 2 + M_PI * $i / $bands;
                    $a1 = -M_PI / 2 + M_PI * ($i + 1) / $bands;
                    $sx0 = 500 + 270 * sin($a0);
                    $sx1 = 500 + 270 * sin($a1);
                    $mx0 = $w / 2 + $vis / 2 * ($a0 / (M_PI / 2));
                    $mx1 = $w / 2 + $vis / 2 * ($a1 / (M_PI / 2));
                    $s .= $nest($sx0, $ay, $sx1 - $sx0 + 0.6, $ah, round($mx0, 3) . ' 0 ' . round($mx1 - $mx0, 3) . ' ' . $h);
                }
                $s .= '<rect x="230" y="150" width="540" height="590" fill="url(#mk-light)" style="mix-blend-mode:multiply"/>';
                $ax = 230;
                $aw = 540;
                break;

            case 'tote':
                $vbw = 1000;
                $vbh = 1120;
                $s = '<path d="M330 330 C330 60 670 60 670 330" fill="none" stroke="' . $color . '" stroke-width="34"/><path d="M330 330 C330 60 670 60 670 330" fill="none" stroke="' . $stroke . '" stroke-width="2"/>'
                    . '<path d="M170 300 L830 300 L846 1080 L154 1080 Z" fill="' . $color . '" stroke="' . $stroke . '" stroke-width="3" filter="url(#mk-shadow)"/><path d="M170 300 L830 300 L846 1080 L154 1080 Z" fill="url(#mk-fold)"/>';
                $k = 440 / 280;
                $aw = $w * $k;
                $ah = $h * $k;
                $ax = 500 - $aw / 2;
                $ay = 430;
                $s .= $nest($ax, $ay, $aw, $ah);
                break;

            case 'cap':
                $vbw = 1000;
                $vbh = 760;
                $crown = 'M170 520 C170 200 330 90 500 90 C670 90 830 200 830 520 Z';
                $s = '<g filter="url(#mk-shadow)"><path d="M120 520 C160 470 840 470 880 520 C930 600 760 690 500 690 C240 690 70 600 120 520 Z" fill="' . $color . '" stroke="' . $stroke . '" stroke-width="3"/>'
                    . '<path d="' . $crown . '" fill="' . $color . '" stroke="' . $stroke . '" stroke-width="3"/></g>'
                    . '<path d="M500 92 L500 520 M300 150 C330 260 340 420 330 520 M700 150 C670 260 660 420 670 520" stroke="' . $stroke . '" stroke-width="2" fill="none"/><circle cx="500" cy="96" r="12" fill="' . $color . '" stroke="' . $stroke . '"/>'
                    . '<path d="' . $crown . '" fill="url(#mk-light)" opacity=".7"/>';
                $k = 360 / 100;
                $aw = $w * $k;
                $ah = $h * $k;
                $ax = 500 - $aw / 2;
                $ay = 250;
                $s .= '<clipPath id="mk-crown"><path d="' . $crown . '"/></clipPath><g clip-path="url(#mk-crown)">' . $nest($ax, $ay, $aw, $ah) . '</g>';
                break;

            case 'scarf':
                $k = 1000 / $w;
                $vbw = 1080;
                $ah = $h * $k;
                $vbh = $ah + 120;
                $ax = 40;
                $ay = 50;
                $aw = 1000;
                $s = '<g filter="url(#mk-shadow)"><rect x="' . $ax . '" y="' . $ay . '" width="' . $aw . '" height="' . round($ah, 2) . '" fill="#ddd"/>' . $nest($ax, $ay, $aw, $ah) . '</g>';
                // Franges aux deux bouts, de la couleur du fond.
                $fr = $face['bg'] ?: '#0E1F4D';
                for ($i = 0; $i < 14; $i++) {
                    $yy = $ay + 4 + $i * ($ah - 8) / 13;
                    $s .= '<line x1="' . ($ax - 30) . '" y1="' . round($yy, 1) . '" x2="' . $ax . '" y2="' . round($yy, 1) . '" stroke="' . $fr . '" stroke-width="4" stroke-linecap="round"/>'
                        . '<line x1="' . ($ax + $aw) . '" y1="' . round($yy, 1) . '" x2="' . ($ax + $aw + 30) . '" y2="' . round($yy, 1) . '" stroke="' . $fr . '" stroke-width="4" stroke-linecap="round"/>';
                }
                $s .= '<rect x="' . $ax . '" y="' . $ay . '" width="' . $aw . '" height="' . round($ah, 2) . '" fill="url(#mk-fold)"/>';
                break;

            case 'sticker':
                $k = 600 / max($w, $h);
                $aw = $w * $k;
                $ah = $h * $k;
                $vbw = $aw + 120;
                $vbh = $ah + 120;
                $ax = 60;
                $ay = 50;
                $s = '<rect x="' . ($ax - 14) . '" y="' . ($ay - 14) . '" width="' . ($aw + 28) . '" height="' . ($ah + 28) . '" rx="42" fill="#fff" filter="url(#mk-shadow)"/>'
                    . '<clipPath id="mk-st"><rect x="' . $ax . '" y="' . $ay . '" width="' . $aw . '" height="' . $ah . '" rx="30"/></clipPath><g clip-path="url(#mk-st)">' . $nest($ax, $ay, $aw, $ah) . '</g>';
                break;

            default: // paper : poster encadré, carte posée
                $k = 760 / max($w, $h);
                $aw = $w * $k;
                $ah = $h * $k;
                $frame = $w >= 250 || $h >= 250 ? 18 : 0;
                $vbw = $aw + 2 * $frame + 120;
                $vbh = $ah + 2 * $frame + 130;
                $ax = 60 + $frame;
                $ay = 50 + $frame;
                $s = $frame
                    ? '<rect x="60" y="50" width="' . round($aw + 2 * $frame, 2) . '" height="' . round($ah + 2 * $frame, 2) . '" fill="#1b1b1b" filter="url(#mk-shadow)"/>'
                    : '<rect x="' . $ax . '" y="' . $ay . '" width="' . round($aw, 2) . '" height="' . round($ah, 2) . '" fill="#fff" filter="url(#mk-shadow)"/>';
                $s .= '<rect x="' . $ax . '" y="' . $ay . '" width="' . round($aw, 2) . '" height="' . round($ah, 2) . '" fill="#fff"/>' . $nest($ax, $ay, $aw, $ah);
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . round($vbw, 2) . ' ' . round($vbh, 2) . '" class="mockup">' . $defs . $s . '</svg>';
        return ['svg' => $svg, 'area' => ['x' => round($ax, 2), 'y' => round($ay, 2), 'w' => round($aw, 2), 'h' => round($ah ?? 0, 2)], 'vb' => [round($vbw, 2), round($vbh, 2)]];
    }

    public static function isDark(string $hex): bool
    {
        [$r, $g, $b] = sscanf(Vector::hex($hex, '#FFFFFF'), '#%02x%02x%02x');
        return 0.299 * $r + 0.587 * $g + 0.114 * $b < 140;
    }
}
