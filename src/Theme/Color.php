<?php

declare(strict_types=1);

namespace App\Theme;

/**
 * Une couleur, manipulable en teinte / saturation / luminosité.
 *
 * Les dérivations de la charte se font en TSL plutôt qu'en RVB : faire tourner
 * une teinte ou éclaircir une couleur y sont des opérations directes, alors
 * qu'elles demandent des calculs peu lisibles en RVB.
 */
final class Color
{
    private function __construct(
        public readonly float $hue,        // 0 à 360
        public readonly float $saturation, // 0 à 1
        public readonly float $lightness,  // 0 à 1
    ) {
    }

    public static function fromHex(string $hex): self
    {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new \InvalidArgumentException("Couleur hexadécimale invalide : « {$hex} »");
        }

        // Le transtypage est indispensable : en PHP, 255 / 255 rend un entier,
        // et une comparaison stricte avec 0.0 échouerait sur les gris purs.
        $r = (float) hexdec(substr($hex, 0, 2)) / 255;
        $g = (float) hexdec(substr($hex, 2, 2)) / 255;
        $b = (float) hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;

        // Une couleur sans écart entre ses canaux est un gris : sa teinte n'existe pas.
        if ($delta < 1e-9) {
            return new self(0.0, 0.0, $lightness);
        }

        $saturation = $lightness > 0.5
            ? $delta / (2 - $max - $min)
            : $delta / ($max + $min);

        $hue = match (true) {
            abs($max - $r) < 1e-9 => 60 * fmod(($g - $b) / $delta, 6),
            abs($max - $g) < 1e-9 => 60 * ((($b - $r) / $delta) + 2),
            default     => 60 * ((($r - $g) / $delta) + 4),
        };

        return new self(fmod($hue + 360, 360), $saturation, $lightness);
    }

    public static function fromHsl(float $hue, float $saturation, float $lightness): self
    {
        return new self(
            fmod(fmod($hue, 360) + 360, 360),
            max(0.0, min(1.0, $saturation)),
            max(0.0, min(1.0, $lightness))
        );
    }

    /** Fait tourner la teinte sur le cercle chromatique. */
    public function rotate(float $degrees): self
    {
        return self::fromHsl($this->hue + $degrees, $this->saturation, $this->lightness);
    }

    /** Décale saturation et luminosité, chacune bornée à son intervalle. */
    public function adjust(float $saturationDelta = 0.0, float $lightnessDelta = 0.0): self
    {
        return self::fromHsl(
            $this->hue,
            $this->saturation + $saturationDelta,
            $this->lightness + $lightnessDelta
        );
    }

    public function withLightness(float $lightness): self
    {
        return self::fromHsl($this->hue, $this->saturation, $lightness);
    }

    public function withSaturation(float $saturation): self
    {
        return self::fromHsl($this->hue, $saturation, $this->lightness);
    }

    /**
     * @return array{int,int,int}
     */
    public function toRgb(): array
    {
        $c = (1 - abs(2 * $this->lightness - 1)) * $this->saturation;
        $x = $c * (1 - abs(fmod($this->hue / 60, 2) - 1));
        $m = $this->lightness - $c / 2;

        [$r, $g, $b] = match (true) {
            $this->hue < 60  => [$c, $x, 0.0],
            $this->hue < 120 => [$x, $c, 0.0],
            $this->hue < 180 => [0.0, $c, $x],
            $this->hue < 240 => [0.0, $x, $c],
            $this->hue < 300 => [$x, 0.0, $c],
            default          => [$c, 0.0, $x],
        };

        return [
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255),
        ];
    }

    public function toHex(): string
    {
        [$r, $g, $b] = $this->toRgb();

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public function toRgba(float $alpha): string
    {
        [$r, $g, $b] = $this->toRgb();

        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.'));
    }

    /**
     * Luminance relative, au sens du calcul de contraste des WCAG.
     */
    public function relativeLuminance(): float
    {
        $channels = array_map(static function (int $value): float {
            $c = $value / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $this->toRgb());

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /** Rapport de contraste entre deux couleurs, de 1 (identiques) à 21. */
    public function contrastWith(self $other): float
    {
        $a = $this->relativeLuminance();
        $b = $other->relativeLuminance();

        return ((max($a, $b) + 0.05) / (min($a, $b) + 0.05));
    }
}
