<?php

declare(strict_types=1);

namespace App\Shape;

/**
 * Remplissage d'un contour par balayage horizontal.
 *
 * Plutôt que de tester chaque point tiré au hasard contre toutes les arêtes
 * (coûteux : O(tirages × arêtes)), on découpe la forme en lignes de balayage et
 * on range chaque arête dans les seules lignes qu'elle traverse. Le coût devient
 * proportionnel au nombre d'arêtes, et la répartition des points sur la surface
 * est exacte car pondérée par la longueur des segments intérieurs.
 */
final class ScanlineFill
{
    /**
     * @param  list<list<array{float,float}>>  $subpaths
     * @param  array{float,float,float,float}  $bounds  [minX, minY, largeur, hauteur]
     * @return list<array{float,float}>
     */
    public static function sample(
        array $subpaths,
        array $bounds,
        int $count,
        Rng $rng,
        bool $evenOdd = false,
        int $rows = 0
    ): array {
        [$minX, $minY, $width, $height] = $bounds;
        if ($width <= 0 || $height <= 0 || $count <= 0) {
            return [];
        }

        // Assez de lignes pour que la forme reste fidèle, sans exploser la mémoire.
        if ($rows <= 0) {
            $rows = (int) max(96, min(1400, ceil(sqrt($count) * 6)));
        }
        $rowHeight = $height / $rows;

        /** @var array<int, list<array{float,int}>> $crossings x et sens de traversée */
        $crossings = [];

        foreach ($subpaths as $sp) {
            $n = count($sp);
            if ($n < 2) {
                continue;
            }
            for ($i = 0; $i < $n; $i++) {
                $a = $sp[$i];
                // Le dernier segment referme implicitement le contour.
                $b = $i + 1 < $n ? $sp[$i + 1] : $sp[0];
                if ($a[1] === $b[1]) {
                    continue; // Une arête horizontale ne traverse aucune ligne de balayage.
                }

                $yLow = min($a[1], $b[1]);
                $yHigh = max($a[1], $b[1]);
                $first = (int) floor((($yLow - $minY) / $rowHeight) - 0.5);
                $last = (int) ceil((($yHigh - $minY) / $rowHeight) + 0.5);
                $first = max(0, $first);
                $last = min($rows - 1, $last);

                $dir = $b[1] > $a[1] ? 1 : -1;
                $slope = ($b[0] - $a[0]) / ($b[1] - $a[1]);

                for ($r = $first; $r <= $last; $r++) {
                    $y = $minY + ($r + 0.5) * $rowHeight;
                    if ($y < $yLow || $y >= $yHigh) {
                        continue;
                    }
                    $crossings[$r][] = [$a[0] + ($y - $a[1]) * $slope, $dir];
                }
            }
        }

        // Reconstruction des segments intérieurs ligne par ligne.
        $spans = [];
        $total = 0.0;
        foreach ($crossings as $row => $list) {
            if (count($list) < 2) {
                continue;
            }
            usort($list, static fn(array $p, array $q): int => $p[0] <=> $q[0]);

            $winding = 0;
            $spanStart = null;
            foreach ($list as $index => [$x, $dir]) {
                $wasInside = $evenOdd ? ($winding % 2 !== 0) : ($winding !== 0);
                $winding += $evenOdd ? 1 : $dir;
                $isInside = $evenOdd ? ($winding % 2 !== 0) : ($winding !== 0);

                if (!$wasInside && $isInside) {
                    $spanStart = $x;
                } elseif ($wasInside && !$isInside && $spanStart !== null) {
                    $length = $x - $spanStart;
                    if ($length > 0) {
                        $total += $length;
                        $spans[] = [$row, $spanStart, $length, $total];
                    }
                    $spanStart = null;
                }
            }
        }

        if ($spans === [] || $total <= 0.0) {
            return [];
        }

        // Tirage stratifié : un point par tranche de surface égale, décalé aléatoirement.
        $points = [];
        $spanCount = count($spans);
        $cursor = 0;
        for ($i = 0; $i < $count; $i++) {
            $target = (($i + $rng->next()) / $count) * $total;
            while ($cursor < $spanCount - 1 && $spans[$cursor][3] < $target) {
                $cursor++;
            }
            [$row, $startX, $length] = $spans[$cursor];
            $points[] = [
                $startX + $rng->next() * $length,
                $minY + ($row + $rng->next()) * $rowHeight,
            ];
        }

        return $points;
    }
}
