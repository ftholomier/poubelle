<?php

declare(strict_types=1);

namespace App\Shape;

/**
 * Matrice affine 2D [a c e / b d f / 0 0 1], au format des transformations SVG.
 */
final class Matrix2D
{
    public function __construct(
        public float $a = 1.0,
        public float $b = 0.0,
        public float $c = 0.0,
        public float $d = 1.0,
        public float $e = 0.0,
        public float $f = 0.0,
    ) {
    }

    public function multiply(self $m): self
    {
        return new self(
            $this->a * $m->a + $this->c * $m->b,
            $this->b * $m->a + $this->d * $m->b,
            $this->a * $m->c + $this->c * $m->d,
            $this->b * $m->c + $this->d * $m->d,
            $this->a * $m->e + $this->c * $m->f + $this->e,
            $this->b * $m->e + $this->d * $m->f + $this->f,
        );
    }

    /**
     * @return array{float,float}
     */
    public function apply(float $x, float $y): array
    {
        return [
            $this->a * $x + $this->c * $y + $this->e,
            $this->b * $x + $this->d * $y + $this->f,
        ];
    }

    /**
     * Analyse un attribut transform SVG : translate, scale, rotate, skewX, skewY, matrix.
     */
    public static function fromAttribute(string $transform): self
    {
        $matrix = new self();
        preg_match_all('/(\w+)\s*\(([^)]*)\)/', $transform, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $args = array_map('floatval', preg_split('/[\s,]+/', trim($m[2])) ?: []);
            $next = match (strtolower($m[1])) {
                'translate' => new self(1, 0, 0, 1, $args[0] ?? 0, $args[1] ?? 0),
                'scale'     => new self($args[0] ?? 1, 0, 0, $args[1] ?? ($args[0] ?? 1), 0, 0),
                'matrix'    => new self(
                    $args[0] ?? 1,
                    $args[1] ?? 0,
                    $args[2] ?? 0,
                    $args[3] ?? 1,
                    $args[4] ?? 0,
                    $args[5] ?? 0
                ),
                'rotate'    => self::rotation($args),
                'skewx'     => new self(1, 0, tan(deg2rad($args[0] ?? 0)), 1, 0, 0),
                'skewy'     => new self(1, tan(deg2rad($args[0] ?? 0)), 0, 1, 0, 0),
                default     => new self(),
            };
            $matrix = $matrix->multiply($next);
        }

        return $matrix;
    }

    /**
     * @param list<float> $args
     */
    private static function rotation(array $args): self
    {
        $angle = deg2rad($args[0] ?? 0);
        $rot = new self(cos($angle), sin($angle), -sin($angle), cos($angle), 0, 0);
        // rotate(angle, cx, cy) pivote autour d'un centre arbitraire.
        if (isset($args[1], $args[2])) {
            $toOrigin = new self(1, 0, 0, 1, $args[1], $args[2]);
            $back = new self(1, 0, 0, 1, -$args[1], -$args[2]);
            return $toOrigin->multiply($rot)->multiply($back);
        }

        return $rot;
    }
}
