<?php

declare(strict_types=1);

namespace App\Shape;

/**
 * Formes procédurales en trois dimensions, générées par pure formule mathématique.
 * Elles ne demandent aucun fichier : il suffit de nommer le préréglage dans le JSON.
 */
final class PresetSampler
{
    public const AVAILABLE = [
        'sphere'  => 'Sphère pleine, répartition de Fibonacci',
        'globe'   => 'Globe filaire, méridiens et parallèles',
        'torus'   => 'Tore',
        'galaxy'  => 'Galaxie spirale à bras',
        'wave'    => 'Nappe ondulante',
        'grid'    => 'Grille plane',
        'cube'    => 'Surface d\'un cube',
        'helix'   => 'Double hélice',
        'tunnel'  => 'Tunnel cylindrique',
        'ring'    => 'Anneau plat',
        'cloud'   => 'Nuage diffus',
        'cone'    => 'Cône',
        'heart'   => 'Cœur',
        'infinity' => 'Ruban de Möbius / lemniscate',
    ];

    /**
     * @param  array<string,mixed> $options
     * @return list<array{float,float,float}>
     */
    public static function sample(string $preset, int $count, array $options = []): array
    {
        $rng = new Rng((int) ($options['seed'] ?? 1337));
        $preset = strtolower($preset);

        return match ($preset) {
            'sphere'   => self::sphere($count, $rng, true),
            'globe'    => self::globe($count, $rng),
            'torus'    => self::torus($count, $rng, (float) ($options['tube'] ?? 0.34)),
            'galaxy'   => self::galaxy($count, $rng, (int) ($options['arms'] ?? 4)),
            'wave'     => self::wave($count, $rng),
            'grid'     => self::grid($count, $rng),
            'cube'     => self::cube($count, $rng),
            'helix'    => self::helix($count, $rng, (int) ($options['turns'] ?? 5)),
            'tunnel'   => self::tunnel($count, $rng),
            'ring'     => self::ring($count, $rng),
            'cloud'    => self::sphere($count, $rng, false),
            'cone'     => self::cone($count, $rng),
            'heart'    => self::heart($count, $rng),
            'infinity' => self::infinity($count, $rng),
            default    => throw new \RuntimeException(
                "Préréglage inconnu : « {$preset} ». Disponibles : " . implode(', ', array_keys(self::AVAILABLE))
            ),
        };
    }

    /** @return list<array{float,float,float}> */
    private static function sphere(int $count, Rng $rng, bool $surface): array
    {
        $points = [];
        // Spirale de Fibonacci : répartition quasi uniforme sans accumulation aux pôles.
        $golden = M_PI * (3.0 - sqrt(5.0));
        for ($i = 0; $i < $count; $i++) {
            $y = 1.0 - ($i / max(1, $count - 1)) * 2.0;
            $radius = sqrt(max(0.0, 1.0 - $y * $y));
            $theta = $golden * $i;
            $scale = $surface ? 1.0 : pow($rng->next(), 1 / 3);
            $points[] = [cos($theta) * $radius * $scale, $y * $scale, sin($theta) * $radius * $scale];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function globe(int $count, Rng $rng): array
    {
        $points = [];
        $lines = 14;
        $perLine = max(2, intdiv($count, $lines * 2));
        for ($l = 0; $l < $lines; $l++) {
            // Méridiens.
            $lon = ($l / $lines) * M_PI;
            for ($i = 0; $i < $perLine; $i++) {
                $lat = ($i / $perLine) * 2 * M_PI;
                $points[] = [cos($lat) * cos($lon), sin($lat), cos($lat) * sin($lon)];
            }
            // Parallèles.
            $lat = (($l + 0.5) / $lines - 0.5) * M_PI;
            $r = cos($lat);
            for ($i = 0; $i < $perLine; $i++) {
                $lon2 = ($i / $perLine) * 2 * M_PI;
                $points[] = [cos($lon2) * $r, sin($lat), sin($lon2) * $r];
            }
        }
        while (count($points) < $count) {
            $points[] = $points[$rng->nextInt(count($points))];
        }

        return array_slice($points, 0, $count);
    }

    /** @return list<array{float,float,float}> */
    private static function torus(int $count, Rng $rng, float $tube): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $u = $rng->next() * 2 * M_PI;
            $v = $rng->next() * 2 * M_PI;
            $r = 1.0 - $tube;
            $points[] = [
                ($r + $tube * cos($v)) * cos($u),
                $tube * sin($v),
                ($r + $tube * cos($v)) * sin($u),
            ];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function galaxy(int $count, Rng $rng, int $arms): array
    {
        $arms = max(2, min(8, $arms));
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            // Densité plus forte au centre, comme un vrai bulbe galactique.
            $radius = pow($rng->next(), 0.65);
            $arm = $rng->nextInt($arms);
            $angle = ($arm / $arms) * 2 * M_PI + $radius * 4.2;
            $spread = (1.0 - $radius * 0.55) * 0.42;
            $angle += $rng->nextSigned() * $spread;
            $points[] = [
                cos($angle) * $radius,
                $rng->nextSigned() * 0.09 * (1.0 - $radius * 0.7),
                sin($angle) * $radius,
            ];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function wave(int $count, Rng $rng): array
    {
        $side = max(2, (int) round(sqrt($count)));
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $x = (($i % $side) / ($side - 1)) * 2 - 1;
            $z = ((intdiv($i, $side) % $side) / ($side - 1)) * 2 - 1;
            $d = hypot($x, $z);
            $points[] = [$x, sin($d * 5.0) * 0.22 * (1.0 - $d * 0.4), $z];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function grid(int $count, Rng $rng): array
    {
        $side = max(2, (int) round(sqrt($count)));
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $points[] = [
                (($i % $side) / ($side - 1)) * 2 - 1,
                ((intdiv($i, $side) % $side) / ($side - 1)) * 2 - 1,
                $rng->nextSigned() * 0.01,
            ];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function cube(int $count, Rng $rng): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $face = $rng->nextInt(6);
            $a = $rng->nextSigned();
            $b = $rng->nextSigned();
            $points[] = match ($face) {
                0 => [1.0, $a, $b],
                1 => [-1.0, $a, $b],
                2 => [$a, 1.0, $b],
                3 => [$a, -1.0, $b],
                4 => [$a, $b, 1.0],
                default => [$a, $b, -1.0],
            };
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function helix(int $count, Rng $rng, int $turns): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $t = $i / max(1, $count - 1);
            $angle = $t * $turns * 2 * M_PI + ($i % 2 === 0 ? 0.0 : M_PI);
            $jitter = $rng->nextSigned() * 0.03;
            $points[] = [cos($angle) * (0.6 + $jitter), $t * 2 - 1, sin($angle) * (0.6 + $jitter)];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function tunnel(int $count, Rng $rng): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = $rng->next() * 2 * M_PI;
            $z = $rng->nextSigned();
            $r = 0.75 + $rng->nextSigned() * 0.05;
            $points[] = [cos($angle) * $r, sin($angle) * $r, $z];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function ring(int $count, Rng $rng): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = ($i / $count) * 2 * M_PI;
            $r = 0.7 + $rng->next() * 0.3;
            $points[] = [cos($angle) * $r, $rng->nextSigned() * 0.02, sin($angle) * $r];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function cone(int $count, Rng $rng): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $t = sqrt($rng->next());
            $angle = $rng->next() * 2 * M_PI;
            $points[] = [cos($angle) * $t, 1.0 - $t * 2.0, sin($angle) * $t];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function heart(int $count, Rng $rng): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $t = $rng->next() * 2 * M_PI;
            // Courbe cardioïde classique, ramenée dans [-1, 1].
            $x = 16 * pow(sin($t), 3) / 17.0;
            $y = (13 * cos($t) - 5 * cos(2 * $t) - 2 * cos(3 * $t) - cos(4 * $t)) / 17.0;
            $shrink = sqrt($rng->next());
            $points[] = [$x * $shrink, $y * $shrink, $rng->nextSigned() * 0.12];
        }

        return $points;
    }

    /** @return list<array{float,float,float}> */
    private static function infinity(int $count, Rng $rng): array
    {
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $t = ($i / $count) * 2 * M_PI;
            $d = 1 + pow(sin($t), 2);
            $points[] = [
                cos($t) / $d + $rng->nextSigned() * 0.03,
                (sin($t) * cos($t)) / $d * 2 + $rng->nextSigned() * 0.03,
                $rng->nextSigned() * 0.08,
            ];
        }

        return $points;
    }
}
