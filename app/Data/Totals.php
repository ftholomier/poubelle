<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\Club;

/**
 * Totaux de référence par joueur (matchs et buts officiels de toute la carrière au club), quand le musée
 * s'appuie sur un classement publié plus complet que ses propres compositions : comptes rendus absents
 * ou incomplets, remplaçants sans minute… Fichier facultatif config/clubs/<id>/totaux-reference.php :
 *
 *   ['source' => ['label' => …, 'url' => …, 'date' => 'AAAA-MM-JJ'],
 *    'joueurs' => ['/chemin/de-la-fiche/' => ['nom' => …, 'matchs' => 308, 'buts' => 256], …]]
 *
 * Seuls les totaux toutes compétitions officielles sont remplacés (pas les filtres par décennie ou
 * compétition, calculés sur les matchs racontés). Sans fichier, rien ne change.
 */
final class Totals
{
    /** @var array<string,array<int,array{matchs:?int,buts:?int}>> par musée : id de fiche => totaux */
    private static array $byId = [];

    /** @return array<int,array{matchs:?int,buts:?int}> */
    public static function all(): array
    {
        $club = Club::id();
        if (isset(self::$byId[$club])) {
            return self::$byId[$club];
        }
        $out = [];
        foreach ((array) (Club::content('totaux-reference', [])['joueurs'] ?? []) as $path => $t) {
            $s = Index::byPath((string) $path);
            if ($s && ($s['type'] ?? '') === 'personne') {
                $out[(int) $s['id']] = [
                    'matchs' => isset($t['matchs']) ? (int) $t['matchs'] : null,
                    'buts' => isset($t['buts']) ? (int) $t['buts'] : null,
                ];
            }
        }
        return self::$byId[$club] = $out;
    }

    /** @return array{matchs:?int,buts:?int}|null */
    public static function forPerson(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Source du classement de référence (libellé, adresse, date du relevé), ou null. */
    public static function source(): ?array
    {
        $s = Club::content('totaux-reference', [])['source'] ?? null;
        return is_array($s) ? $s : null;
    }
}
