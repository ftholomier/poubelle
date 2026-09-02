<?php

declare(strict_types=1);

namespace App\Shape;

/**
 * Transforme un fichier SVG en nuage de points.
 *
 * Deux modes :
 *  - « fill »    : les points remplissent la surface (tirage par rejet + test d'appartenance) ;
 *  - « outline » : les points suivent le contour, répartis à distance constante.
 */
final class SvgSampler
{
    /**
     * @param  array<string,mixed> $options
     * @return array{points: list<array{float,float}>, viewBox: array{float,float,float,float}}
     */
    public static function sample(string $file, int $count, array $options = []): array
    {
        $subpaths = self::extract($file, $viewBox);
        if ($subpaths === []) {
            throw new \RuntimeException('Aucune géométrie exploitable dans ' . basename($file));
        }

        $mode = strtolower((string) ($options['mode'] ?? 'fill'));
        $rng = new Rng((int) ($options['seed'] ?? 1337));

        $points = $mode === 'outline'
            ? self::sampleOutline($subpaths, $count, $rng, (float) ($options['jitter'] ?? 0.0))
            : self::sampleFill($subpaths, $count, $rng, (string) ($options['fillRule'] ?? 'nonzero'));

        return ['points' => $points, 'viewBox' => $viewBox];
    }

    /**
     * Convertit toutes les primitives du document en polylignes, transformations appliquées.
     *
     * @param  array{float,float,float,float}|null $viewBox
     * @return list<list<array{float,float}>>
     */
    public static function extract(string $file, ?array &$viewBox = null): array
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Fichier SVG introuvable : ' . $file);
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = $doc->loadXML((string) file_get_contents($file), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $doc->documentElement === null) {
            throw new \RuntimeException('SVG illisible : ' . basename($file));
        }

        $root = $doc->documentElement;
        $viewBox = self::readViewBox($root);

        $subpaths = [];
        self::walk($root, new Matrix2D(), $subpaths);

        if ($viewBox === null) {
            $viewBox = self::boundsOf($subpaths);
        }

        return $subpaths;
    }

    /**
     * Parcours récursif de l'arbre en cumulant les transformations parentes.
     *
     * @param list<list<array{float,float}>> $out
     */
    private static function walk(\DOMElement $node, Matrix2D $parent, array &$out): void
    {
        $matrix = $parent;
        if ($node->hasAttribute('transform')) {
            $matrix = $parent->multiply(Matrix2D::fromAttribute($node->getAttribute('transform')));
        }

        // Les éléments non rendus ne doivent pas contribuer à la géométrie.
        $tag = strtolower($node->localName ?? '');
        if (in_array($tag, ['defs', 'clippath', 'mask', 'symbol', 'marker', 'metadata'], true)) {
            return;
        }
        if (stripos($node->getAttribute('display'), 'none') !== false) {
            return;
        }

        foreach (self::primitiveToSubpaths($tag, $node) as $subpath) {
            $transformed = [];
            foreach ($subpath as [$x, $y]) {
                $transformed[] = $matrix->apply($x, $y);
            }
            if (count($transformed) >= 2) {
                $out[] = $transformed;
            }
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                self::walk($child, $matrix, $out);
            }
        }
    }

    /**
     * @return list<list<array{float,float}>>
     */
    private static function primitiveToSubpaths(string $tag, \DOMElement $node): array
    {
        $num = static fn(string $name, float $default = 0.0): float => $node->hasAttribute($name)
            ? (float) $node->getAttribute($name)
            : $default;

        switch ($tag) {
            case 'path':
                $d = $node->getAttribute('d');
                if ($d === '') {
                    return [];
                }
                return array_map(
                    static fn(array $sp): array => $sp['points'],
                    PathParser::parse($d)
                );

            case 'circle':
                return [self::ellipse($num('cx'), $num('cy'), $num('r'), $num('r'))];

            case 'ellipse':
                return [self::ellipse($num('cx'), $num('cy'), $num('rx'), $num('ry'))];

            case 'rect':
                $x = $num('x');
                $y = $num('y');
                $w = $num('width');
                $h = $num('height');
                if ($w <= 0 || $h <= 0) {
                    return [];
                }
                return [[[$x, $y], [$x + $w, $y], [$x + $w, $y + $h], [$x, $y + $h], [$x, $y]]];

            case 'line':
                return [[[$num('x1'), $num('y1')], [$num('x2'), $num('y2')]]];

            case 'polyline':
            case 'polygon':
                $points = self::parsePoints($node->getAttribute('points'));
                if (count($points) < 2) {
                    return [];
                }
                if ($tag === 'polygon') {
                    $points[] = $points[0];
                }
                return [$points];

            default:
                return [];
        }
    }

    /**
     * @return list<array{float,float}>
     */
    private static function ellipse(float $cx, float $cy, float $rx, float $ry): array
    {
        if ($rx <= 0 || $ry <= 0) {
            return [];
        }
        $steps = max(24, (int) ceil(max($rx, $ry) * 1.5));
        $steps = min($steps, 256);
        $points = [];
        for ($i = 0; $i <= $steps; $i++) {
            $t = ($i / $steps) * 2 * M_PI;
            $points[] = [$cx + $rx * cos($t), $cy + $ry * sin($t)];
        }

        return $points;
    }

    /**
     * @return list<array{float,float}>
     */
    private static function parsePoints(string $raw): array
    {
        preg_match_all('/-?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?/', $raw, $m);
        $values = array_map('floatval', $m[0]);
        $points = [];
        for ($i = 0; $i + 1 < count($values); $i += 2) {
            $points[] = [$values[$i], $values[$i + 1]];
        }

        return $points;
    }

    /**
     * @return array{float,float,float,float}|null
     */
    private static function readViewBox(\DOMElement $root): ?array
    {
        $vb = trim($root->getAttribute('viewBox'));
        if ($vb !== '') {
            $parts = array_map('floatval', preg_split('/[\s,]+/', $vb) ?: []);
            if (count($parts) === 4 && $parts[2] > 0 && $parts[3] > 0) {
                return [$parts[0], $parts[1], $parts[2], $parts[3]];
            }
        }
        $w = (float) $root->getAttribute('width');
        $h = (float) $root->getAttribute('height');
        if ($w > 0 && $h > 0) {
            return [0.0, 0.0, $w, $h];
        }

        return null;
    }

    /**
     * @param  list<list<array{float,float}>> $subpaths
     * @return array{float,float,float,float}
     */
    private static function boundsOf(array $subpaths): array
    {
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach ($subpaths as $sp) {
            foreach ($sp as [$x, $y]) {
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        if (!is_finite($minX)) {
            return [0.0, 0.0, 1.0, 1.0];
        }

        return [$minX, $minY, max(1e-6, $maxX - $minX), max(1e-6, $maxY - $minY)];
    }

    /**
     * Remplissage de la surface, délégué au balayage par lignes.
     *
     * @param  list<list<array{float,float}>> $subpaths
     * @return list<array{float,float}>
     */
    private static function sampleFill(array $subpaths, int $count, Rng $rng, string $fillRule): array
    {
        $bounds = self::boundsOf($subpaths);
        $points = ScanlineFill::sample($subpaths, $bounds, $count, $rng, $fillRule === 'evenodd');

        // Un SVG composé uniquement de traits n'a aucune surface : on suit alors le contour.
        if (count($points) < max(8, (int) ($count * 0.02))) {
            return self::sampleOutline($subpaths, $count, $rng, 0.0);
        }

        return $points;
    }

    /**
     * Répartit les points le long des contours proportionnellement à leur longueur.
     *
     * @param  list<list<array{float,float}>> $subpaths
     * @return list<array{float,float}>
     */
    private static function sampleOutline(array $subpaths, int $count, Rng $rng, float $jitter): array
    {
        $segments = [];
        $total = 0.0;
        foreach ($subpaths as $sp) {
            $n = count($sp);
            for ($i = 0; $i + 1 < $n; $i++) {
                $len = hypot($sp[$i + 1][0] - $sp[$i][0], $sp[$i + 1][1] - $sp[$i][1]);
                if ($len <= 0.0) {
                    continue;
                }
                $total += $len;
                $segments[] = [$sp[$i], $sp[$i + 1], $total];
            }
        }
        if ($segments === [] || $total <= 0.0) {
            return [];
        }

        $points = [];
        for ($i = 0; $i < $count; $i++) {
            // Répartition régulière + léger décalage pour éviter les alignements visibles.
            $target = (($i + 0.5) / $count) * $total;
            $seg = self::findSegment($segments, $target);
            [$a, $b, $end] = $seg;
            $segLen = hypot($b[0] - $a[0], $b[1] - $a[1]);
            $t = $segLen > 0 ? 1.0 - (($end - $target) / $segLen) : 0.0;
            $t = max(0.0, min(1.0, $t));
            $x = $a[0] + ($b[0] - $a[0]) * $t;
            $y = $a[1] + ($b[1] - $a[1]) * $t;
            if ($jitter > 0.0) {
                $x += ($rng->next() - 0.5) * $jitter;
                $y += ($rng->next() - 0.5) * $jitter;
            }
            $points[] = [$x, $y];
        }

        return $points;
    }

    /**
     * Recherche dichotomique du segment contenant une abscisse curviligne.
     *
     * @param  list<array{array{float,float},array{float,float},float}> $segments
     * @return array{array{float,float},array{float,float},float}
     */
    private static function findSegment(array $segments, float $target): array
    {
        $lo = 0;
        $hi = count($segments) - 1;
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($segments[$mid][2] < $target) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }

        return $segments[$lo];
    }

    /**
     * Test d'appartenance : lancer de rayon horizontal, règle non-zéro ou pair-impair.
     *
     * @param list<list<array{float,float}>> $subpaths
     */
    private static function isInside(array $subpaths, float $x, float $y, bool $evenOdd): bool
    {
        $winding = 0;
        $crossings = 0;

        foreach ($subpaths as $sp) {
            $n = count($sp);
            for ($i = 0; $i + 1 < $n; $i++) {
                [$x1, $y1] = $sp[$i];
                [$x2, $y2] = $sp[$i + 1];
                if (($y1 > $y) === ($y2 > $y)) {
                    continue;
                }
                $t = ($y - $y1) / ($y2 - $y1);
                if ($x < $x1 + $t * ($x2 - $x1)) {
                    $crossings++;
                    $winding += $y2 > $y1 ? 1 : -1;
                }
            }
            // Les sous-chemins ouverts sont refermés implicitement, comme au rendu SVG.
            if ($n >= 2 && ($sp[0][0] !== $sp[$n - 1][0] || $sp[0][1] !== $sp[$n - 1][1])) {
                [$x1, $y1] = $sp[$n - 1];
                [$x2, $y2] = $sp[0];
                if (($y1 > $y) !== ($y2 > $y)) {
                    $t = ($y - $y1) / ($y2 - $y1);
                    if ($x < $x1 + $t * ($x2 - $x1)) {
                        $crossings++;
                        $winding += $y2 > $y1 ? 1 : -1;
                    }
                }
            }
        }

        return $evenOdd ? ($crossings % 2 === 1) : ($winding !== 0);
    }
}
