<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\Club;

/**
 * Contenus de départ des outils interactifs (quiz, frise, maillots, épopées, lieux).
 * Rédigés à partir de faits établis — la plupart tirés du site lui-même — et
 * marqués « à valider » : les historiens les relisent dans le back-office.
 */
final class Seeds
{
    public static function quiz(): array
    {
        [$q, $en] = Club::content('seed-quiz', []) + [[], []];
        return array_map(fn ($x, $e) => ['q' => $x[0], 'a' => $x[1], 'c' => $x[2], 'fact' => $x[3], 'q_en' => $e[0], 'a_en' => $e[1], 'fact_en' => $e[2], 'validated' => false], $q, $en);
    }

    public static function frise(): array
    {
        [$e, $en] = Club::content('seed-frise', []) + [[], []];
        return array_map(fn ($x, $t) => ['year' => $x[0], 'title' => $x[1], 'text' => $x[2], 'title_en' => $t[0], 'text_en' => $t[1], 'tone' => $x[3], 'image' => $x[4] ?? null, 'href' => null, 'validated' => false], $e, $en);
    }

    public static function maillots(): array
    {
        $img = Club::content('seed-maillots', []);
        return array_map(fn ($y) => ['era' => (string) $y, 'label' => 'Années ' . ($y < 2000 ? substr((string) $y, 2) : $y), 'image' => $img[$y], 'text' => ''], array_keys($img));
    }

    public static function epopees(): array
    {
        [$s, $en] = Club::content('seed-epopees', []) + [[], []];
        return array_map(fn ($x, $e) => ['y' => $x[0], 's' => $x[1], 'type' => $x[2], 't' => $x[3], 'h' => $x[4], 'p' => $x[5], 'type_en' => $e[0], 'h_en' => $e[1], 'p_en' => $e[2], 'll' => $x[6], 'win' => $x[7], 'href' => null, 'validated' => false], $s, $en);
    }

    public static function lieux(): array
    {
        return Club::content('seed-lieux', []);
    }
}
