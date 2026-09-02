<?php

declare(strict_types=1);

namespace App\Theme;

/**
 * Charte graphique complète, dérivée d'une seule couleur dominante.
 *
 * Changer cette couleur suffit à repeindre tout le site : fond, textes,
 * bordures, halos, dégradé des particules et poussière d'ambiance. Chaque
 * valeur reste surchargeable individuellement depuis content/site.json.
 */
final class Palette
{
    /** Écarts de teinte appliqués aux deux couleurs d'appoint, par harmonie. */
    public const HARMONIES = [
        'analogue'       => 'Analogue — dégradé dans la même famille, toujours sûr',
        'complementaire' => 'Complémentaire — contraste franc',
        'duo'            => 'Duo chaud-froid — deux pôles opposés, comme un ciel étoilé',
        'monochrome'     => 'Monochrome — une seule teinte, jouée en luminosité',
    ];

    private const ROTATIONS = [
        'analogue'       => [22.0, 46.0],
        'complementaire' => [20.0, 180.0],
        'duo'            => [18.0, 200.0],
        'monochrome'     => [0.0, 0.0],
    ];

    /**
     * @param  array<string,mixed> $theme section « theme » de content/site.json
     * @return array<string,mixed>
     */
    public static function build(array $theme): array
    {
        $dominant = Color::fromHex((string) ($theme['dominant'] ?? $theme['accent'] ?? '#7b01f7'));
        $harmony = (string) ($theme['harmony'] ?? 'duo');
        if (!isset(self::ROTATIONS[$harmony])) {
            $harmony = 'duo';
        }
        [$shiftA, $shiftB] = self::ROTATIONS[$harmony];

        // Un gris n'a pas de teinte : lui en imposer une le rendrait rouge.
        // On respecte alors le choix, en se contentant d'assurer la lisibilité.
        $neutral = $dominant->saturation < 0.1;

        // Une dominante trop sombre ne tiendrait pas sur fond noir : on la ramène
        // dans une plage où elle reste lisible, sans toucher à sa teinte.
        $accent = $dominant
            ->withSaturation($neutral ? $dominant->saturation : max($dominant->saturation, 0.45))
            ->withLightness(min(max($dominant->lightness, $neutral ? 0.62 : 0.42), 0.78));

        $accent2 = $harmony === 'monochrome'
            ? $accent->adjust(-0.05, 0.14)
            : $accent->rotate($shiftA)->adjust(0.02, 0.06);

        $accent3 = $harmony === 'monochrome'
            ? $accent->adjust(-0.18, 0.3)
            : $accent->rotate($shiftB)->adjust(-0.05, 0.22);

        // Le fond garde une trace de la dominante : deux couleurs différentes
        // ne donnent pas le même noir, et l'ensemble reste cohérent.
        $tint = static fn(float $amount): float => $neutral ? 0.0 : $amount;
        $background = $accent->withSaturation($tint(0.42))->withLightness(0.043);
        $surface = $accent->withSaturation($tint(0.34))->withLightness(0.085);
        $foreground = $accent->withSaturation($tint(0.28))->withLightness(0.96);
        $muted = $accent->withSaturation($tint(0.16))->withLightness(0.62);

        $palette = [
            'dominant'   => $dominant->toHex(),
            'harmony'    => $harmony,
            'accent'     => $accent->toHex(),
            'accent2'    => $accent2->toHex(),
            'accent3'    => $accent3->toHex(),
            'background' => $background->toHex(),
            'surface'    => $surface->toHex(),
            'foreground' => $foreground->toHex(),
            // Deux nuances intermédiaires, pour les paragraphes et les mentions
            // discrètes : sans elles, la feuille de style retomberait sur des
            // gris écrits en dur, insensibles à la couleur choisie.
            'foregroundSoft' => $foreground->adjust($tint(0.04), -0.12)->toHex(),
            'foregroundDim'  => $foreground->adjust($tint(0.02), -0.22)->toHex(),
            'muted'      => self::ensureContrast($muted, $background, 4.5)->toHex(),
            'line'       => $foreground->toRgba(0.12),
            'lineStrong' => $foreground->toRgba(0.26),
            'veil'       => $background->toRgba(0.72),
            'glowA'      => $accent->toRgba(0.18),
            'glowB'      => $accent3->toRgba(0.13),
            'shadow'     => $accent->toRgba(0.42),
            'particles'  => [
                'colorStart' => $accent->toHex(),
                'colorMid'   => $accent2->toHex(),
                'colorEnd'   => $accent3->adjust(0.0, 0.12)->toHex(),
                // La poussière d'ambiance reprend les mêmes teintes, éclaircies :
                // elle doit se lire comme une brume, pas comme une seconde forme.
                'dustA'      => $accent->adjust(-0.1, 0.16)->toHex(),
                'dustB'      => $accent3->adjust(-0.05, 0.2)->toHex(),
                'dustC'      => $foreground->toHex(),
                'size'       => 2.4,
                'opacity'    => 0.92,
            ],
        ];

        // Toute valeur explicitement posée dans site.json prime sur la dérivation.
        foreach ($theme as $key => $value) {
            if ($key === 'particles' && is_array($value)) {
                $palette['particles'] = $value + $palette['particles'];
                continue;
            }
            if (is_string($value) && $value !== '' && array_key_exists($key, $palette)) {
                $palette[$key] = $value;
            }
        }

        return $palette;
    }

    /**
     * Éclaircit ou assombrit une couleur jusqu'à atteindre le contraste demandé
     * avec son fond, pour que le texte secondaire reste lisible quelle que soit
     * la dominante choisie.
     */
    private static function ensureContrast(Color $color, Color $background, float $target): Color
    {
        // Sur fond sombre on éclaircit, sur fond clair on assombrit.
        $lighten = $background->relativeLuminance() < 0.5;
        $current = $color;

        for ($step = 0; $step < 40; $step++) {
            if ($current->contrastWith($background) >= $target) {
                return $current;
            }
            $next = $current->adjust(0.0, $lighten ? 0.02 : -0.02);
            // Butée atteinte : inutile d'insister, la couleur ne bouge plus.
            if (abs($next->lightness - $current->lightness) < 1e-6) {
                return $current;
            }
            $current = $next;
        }

        return $current;
    }
}
