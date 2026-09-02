<?php

declare(strict_types=1);

namespace App\Shape;

/**
 * Générateur pseudo-aléatoire déterministe (mulberry32).
 * Une même graine produit toujours le même nuage : le cache reste valide
 * et le rendu est identique d'un serveur à l'autre.
 */
final class Rng
{
    private int $state;

    public function __construct(int $seed = 1337)
    {
        $this->state = $seed & 0xFFFFFFFF;
    }

    public function next(): float
    {
        $this->state = ($this->state + 0x6D2B79F5) & 0xFFFFFFFF;
        $t = $this->state;
        $t = (($t ^ ($t >> 15)) * ($t | 1)) & 0xFFFFFFFF;
        $t ^= ($t + ((($t ^ ($t >> 7)) * ($t | 61)) & 0xFFFFFFFF)) & 0xFFFFFFFF;
        $t &= 0xFFFFFFFF;

        return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296.0;
    }

    public function nextInt(int $bound): int
    {
        return $bound <= 0 ? 0 : (int) floor($this->next() * $bound) % $bound;
    }

    /** Tirage centré sur 0, utile pour l'épaisseur et le bruit. */
    public function nextSigned(): float
    {
        return $this->next() * 2.0 - 1.0;
    }
}
