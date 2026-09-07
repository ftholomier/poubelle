<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tolérance de schéma — le contrat qui protège le front en production.
 *
 * Toute donnée lue est fusionnée avec un schéma de référence :
 *  - clé manquante  → valeur par défaut (le front affiche toujours quelque chose) ;
 *  - clé inconnue   → conservée (une ancienne version d'app ne détruit pas des
 *                      données produites par une version plus récente) ;
 *  - type incohérent→ valeur par défaut (jamais d'erreur de type dans les vues).
 */
final class Schema
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $default
     * @return array<string,mixed>
     */
    public static function normalize(array $data, array $default): array
    {
        if ($default === []) {
            return $data;
        }
        $out = $data;

        foreach ($default as $key => $defaultValue) {
            if (!array_key_exists($key, $out)) {
                $out[$key] = $defaultValue;
                continue;
            }
            $value = $out[$key];

            // Listes : on garde le contenu utilisateur, on ne force que le type.
            // Une valeur associative n'est jamais ré-indexée : ce serait perdre
            // ses clés (cas d'un défaut vide `[]` recevant une carte de données).
            if (is_array($defaultValue) && self::isList($defaultValue)) {
                if (!is_array($value)) {
                    $out[$key] = $defaultValue;
                } elseif (self::isList($value)) {
                    $out[$key] = array_values($value);
                } else {
                    $out[$key] = $value;
                }
                continue;
            }
            // Objets : fusion récursive.
            if (is_array($defaultValue)) {
                $out[$key] = is_array($value) ? self::normalize($value, $defaultValue) : $defaultValue;
                continue;
            }
            // Scalaires : coercition douce.
            $out[$key] = self::coerce($value, $defaultValue);
        }

        return $out;
    }

    private static function coerce(mixed $value, mixed $default): mixed
    {
        if ($value === null) {
            return $default;
        }
        return match (true) {
            is_bool($default)   => is_bool($value) ? $value : in_array($value, [1, '1', 'true', 'on', 'yes'], true),
            is_int($default)    => is_numeric($value) ? (int) $value : $default,
            is_float($default)  => is_numeric($value) ? (float) $value : $default,
            is_string($default) => is_scalar($value) ? (string) $value : $default,
            default             => $value,
        };
    }

    public static function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Applique un schéma à chaque élément d'une liste (blocs, avis, documents…).
     *
     * @param array<int,mixed> $items
     * @param array<string,mixed> $itemSchema
     * @return array<int,array<string,mixed>>
     */
    public static function normalizeList(array $items, array $itemSchema): array
    {
        $out = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $out[] = self::normalize($item, $itemSchema);
            }
        }
        return $out;
    }
}
