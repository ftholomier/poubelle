<?php

declare(strict_types=1);

namespace App\Shape;

/**
 * Parseur d'attribut « d » SVG. Il aplatit les courbes (cubiques, quadratiques,
 * arcs elliptiques) en polylignes exploitables pour l'échantillonnage.
 *
 * Toutes les commandes du standard sont gérées : M L H V C S Q T A Z, en
 * majuscule (absolu) comme en minuscule (relatif).
 */
final class PathParser
{
    /** Nombre de segments utilisés pour aplatir une courbe de longueur unitaire. */
    private const CURVE_STEPS = 24;

    /**
     * @return list<array{points: list<array{float,float}>, closed: bool}>
     */
    public static function parse(string $d): array
    {
        $tokens = self::tokenize($d);
        $subpaths = [];
        $current = [];
        $x = $y = 0.0;
        $startX = $startY = 0.0;
        // Points de contrôle mémorisés pour les commandes lisses S et T.
        $lastCubicControl = null;
        $lastQuadControl = null;
        $command = '';
        $i = 0;
        $count = count($tokens);

        $flush = static function (bool $closed) use (&$subpaths, &$current): void {
            if (count($current) >= 2) {
                $subpaths[] = ['points' => $current, 'closed' => $closed];
            }
            $current = [];
        };

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token)) {
                $command = $token;
                $i++;
                if (strtoupper($command) === 'Z') {
                    if ($current !== []) {
                        $current[] = [$startX, $startY];
                        $flush(true);
                    }
                    $x = $startX;
                    $y = $startY;
                    $lastCubicControl = $lastQuadControl = null;
                    continue;
                }
            } elseif ($command === '') {
                $i++;
                continue;
            }

            $relative = $command === strtolower($command);
            $upper = strtoupper($command);
            $need = self::argCount($upper);
            if ($need === 0 || $i + $need > $count) {
                break;
            }

            $args = [];
            for ($k = 0; $k < $need; $k++) {
                $value = $tokens[$i + $k];
                if (is_string($value)) {
                    // Arguments manquants : chemin malformé, on s'arrête proprement.
                    break 2;
                }
                $args[] = $value;
            }
            $i += $need;

            switch ($upper) {
                case 'M':
                    if ($current !== []) {
                        $flush(false);
                    }
                    $x = $relative ? $x + $args[0] : $args[0];
                    $y = $relative ? $y + $args[1] : $args[1];
                    $startX = $x;
                    $startY = $y;
                    $current[] = [$x, $y];
                    // Les paires suivantes d'un M sont des L implicites.
                    $command = $relative ? 'l' : 'L';
                    $lastCubicControl = $lastQuadControl = null;
                    break;

                case 'L':
                    $x = $relative ? $x + $args[0] : $args[0];
                    $y = $relative ? $y + $args[1] : $args[1];
                    $current[] = [$x, $y];
                    $lastCubicControl = $lastQuadControl = null;
                    break;

                case 'H':
                    $x = $relative ? $x + $args[0] : $args[0];
                    $current[] = [$x, $y];
                    $lastCubicControl = $lastQuadControl = null;
                    break;

                case 'V':
                    $y = $relative ? $y + $args[0] : $args[0];
                    $current[] = [$x, $y];
                    $lastCubicControl = $lastQuadControl = null;
                    break;

                case 'C':
                case 'S':
                    if ($upper === 'C') {
                        $c1x = $relative ? $x + $args[0] : $args[0];
                        $c1y = $relative ? $y + $args[1] : $args[1];
                        $c2x = $relative ? $x + $args[2] : $args[2];
                        $c2y = $relative ? $y + $args[3] : $args[3];
                        $nx  = $relative ? $x + $args[4] : $args[4];
                        $ny  = $relative ? $y + $args[5] : $args[5];
                    } else {
                        // Le premier point de contrôle est le reflet du précédent.
                        [$rx, $ry] = $lastCubicControl ?? [$x, $y];
                        $c1x = 2 * $x - $rx;
                        $c1y = 2 * $y - $ry;
                        $c2x = $relative ? $x + $args[0] : $args[0];
                        $c2y = $relative ? $y + $args[1] : $args[1];
                        $nx  = $relative ? $x + $args[2] : $args[2];
                        $ny  = $relative ? $y + $args[3] : $args[3];
                    }
                    self::flattenCubic($current, $x, $y, $c1x, $c1y, $c2x, $c2y, $nx, $ny);
                    $lastCubicControl = [$c2x, $c2y];
                    $lastQuadControl = null;
                    $x = $nx;
                    $y = $ny;
                    break;

                case 'Q':
                case 'T':
                    if ($upper === 'Q') {
                        $cx = $relative ? $x + $args[0] : $args[0];
                        $cy = $relative ? $y + $args[1] : $args[1];
                        $nx = $relative ? $x + $args[2] : $args[2];
                        $ny = $relative ? $y + $args[3] : $args[3];
                    } else {
                        [$rx, $ry] = $lastQuadControl ?? [$x, $y];
                        $cx = 2 * $x - $rx;
                        $cy = 2 * $y - $ry;
                        $nx = $relative ? $x + $args[0] : $args[0];
                        $ny = $relative ? $y + $args[1] : $args[1];
                    }
                    self::flattenQuadratic($current, $x, $y, $cx, $cy, $nx, $ny);
                    $lastQuadControl = [$cx, $cy];
                    $lastCubicControl = null;
                    $x = $nx;
                    $y = $ny;
                    break;

                case 'A':
                    $nx = $relative ? $x + $args[5] : $args[5];
                    $ny = $relative ? $y + $args[6] : $args[6];
                    self::flattenArc(
                        $current,
                        $x,
                        $y,
                        abs($args[0]),
                        abs($args[1]),
                        $args[2],
                        $args[3] != 0.0,
                        $args[4] != 0.0,
                        $nx,
                        $ny
                    );
                    $lastCubicControl = $lastQuadControl = null;
                    $x = $nx;
                    $y = $ny;
                    break;
            }
        }

        $flush(false);

        return $subpaths;
    }

    /**
     * Découpe la chaîne en commandes (string) et nombres (float).
     *
     * @return list<string|float>
     */
    private static function tokenize(string $d): array
    {
        $out = [];
        preg_match_all(
            '/([MmLlHhVvCcSsQqTtAaZz])|(-?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?)/',
            $d,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $m) {
            if (($m[1] ?? '') !== '') {
                $out[] = $m[1];
            } else {
                $out[] = (float) $m[2];
            }
        }

        return $out;
    }

    private static function argCount(string $command): int
    {
        return match ($command) {
            'M', 'L', 'T' => 2,
            'H', 'V'      => 1,
            'C'           => 6,
            'S', 'Q'      => 4,
            'A'           => 7,
            default       => 0,
        };
    }

    /**
     * @param list<array{float,float}> $out
     */
    private static function flattenCubic(
        array &$out,
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3
    ): void {
        $steps = self::stepsFor(
            self::dist($x0, $y0, $x1, $y1)
            + self::dist($x1, $y1, $x2, $y2)
            + self::dist($x2, $y2, $x3, $y3)
        );
        for ($s = 1; $s <= $steps; $s++) {
            $t = $s / $steps;
            $u = 1 - $t;
            $out[] = [
                $u * $u * $u * $x0 + 3 * $u * $u * $t * $x1 + 3 * $u * $t * $t * $x2 + $t * $t * $t * $x3,
                $u * $u * $u * $y0 + 3 * $u * $u * $t * $y1 + 3 * $u * $t * $t * $y2 + $t * $t * $t * $y3,
            ];
        }
    }

    /**
     * @param list<array{float,float}> $out
     */
    private static function flattenQuadratic(
        array &$out,
        float $x0,
        float $y0,
        float $cx,
        float $cy,
        float $x1,
        float $y1
    ): void {
        $steps = self::stepsFor(self::dist($x0, $y0, $cx, $cy) + self::dist($cx, $cy, $x1, $y1));
        for ($s = 1; $s <= $steps; $s++) {
            $t = $s / $steps;
            $u = 1 - $t;
            $out[] = [
                $u * $u * $x0 + 2 * $u * $t * $cx + $t * $t * $x1,
                $u * $u * $y0 + 2 * $u * $t * $cy + $t * $t * $y1,
            ];
        }
    }

    /**
     * Conversion d'un arc elliptique SVG en polyligne (annexe F.6 de la spécification).
     *
     * @param list<array{float,float}> $out
     */
    private static function flattenArc(
        array &$out,
        float $x0,
        float $y0,
        float $rx,
        float $ry,
        float $rotationDeg,
        bool $largeArc,
        bool $sweep,
        float $x1,
        float $y1
    ): void {
        if ($rx == 0.0 || $ry == 0.0 || ($x0 == $x1 && $y0 == $y1)) {
            $out[] = [$x1, $y1];
            return;
        }

        $phi = deg2rad($rotationDeg);
        $cosPhi = cos($phi);
        $sinPhi = sin($phi);

        $dx2 = ($x0 - $x1) / 2.0;
        $dy2 = ($y0 - $y1) / 2.0;
        $x1p = $cosPhi * $dx2 + $sinPhi * $dy2;
        $y1p = -$sinPhi * $dx2 + $cosPhi * $dy2;

        // Agrandissement des rayons s'ils sont trop petits pour relier les deux points.
        $lambda = ($x1p * $x1p) / ($rx * $rx) + ($y1p * $y1p) / ($ry * $ry);
        if ($lambda > 1.0) {
            $scale = sqrt($lambda);
            $rx *= $scale;
            $ry *= $scale;
        }

        $sign = ($largeArc !== $sweep) ? 1.0 : -1.0;
        $num = $rx * $rx * $ry * $ry - $rx * $rx * $y1p * $y1p - $ry * $ry * $x1p * $x1p;
        $den = $rx * $rx * $y1p * $y1p + $ry * $ry * $x1p * $x1p;
        $coef = $den == 0.0 ? 0.0 : $sign * sqrt(max(0.0, $num / $den));

        $cxp = $coef * ($rx * $y1p) / $ry;
        $cyp = $coef * -($ry * $x1p) / $rx;
        $cx = $cosPhi * $cxp - $sinPhi * $cyp + ($x0 + $x1) / 2.0;
        $cy = $sinPhi * $cxp + $cosPhi * $cyp + ($y0 + $y1) / 2.0;

        $angle = static fn(float $ux, float $uy, float $vx, float $vy): float => (
            ($ux * $vy - $uy * $vx < 0 ? -1.0 : 1.0)
            * acos(max(-1.0, min(1.0, ($ux * $vx + $uy * $vy)
                / (sqrt($ux * $ux + $uy * $uy) * sqrt($vx * $vx + $vy * $vy)))))
        );

        $theta1 = $angle(1.0, 0.0, ($x1p - $cxp) / $rx, ($y1p - $cyp) / $ry);
        $deltaTheta = $angle(
            ($x1p - $cxp) / $rx,
            ($y1p - $cyp) / $ry,
            (-$x1p - $cxp) / $rx,
            (-$y1p - $cyp) / $ry
        );
        if (!$sweep && $deltaTheta > 0) {
            $deltaTheta -= 2 * M_PI;
        } elseif ($sweep && $deltaTheta < 0) {
            $deltaTheta += 2 * M_PI;
        }

        $steps = self::stepsFor(abs($deltaTheta) * max($rx, $ry));
        for ($s = 1; $s <= $steps; $s++) {
            $theta = $theta1 + $deltaTheta * ($s / $steps);
            $out[] = [
                $cx + $rx * cos($theta) * $cosPhi - $ry * sin($theta) * $sinPhi,
                $cy + $rx * cos($theta) * $sinPhi + $ry * sin($theta) * $cosPhi,
            ];
        }
    }

    private static function stepsFor(float $approxLength): int
    {
        return max(4, min(self::CURVE_STEPS * 4, (int) ceil($approxLength / 2.0) + 4));
    }

    private static function dist(float $x0, float $y0, float $x1, float $y1): float
    {
        return hypot($x1 - $x0, $y1 - $y0);
    }
}
