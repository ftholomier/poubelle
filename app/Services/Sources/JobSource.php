<?php
declare(strict_types=1);

namespace App\Services\Sources;

/**
 * Une source d'offres externes.
 *
 * Chaque adaptateur normalise ses résultats vers la même forme que l'index
 * local, plus `external`, `source` et `url`, pour que la liste d'offres
 * affiche les deux origines avec le même composant de carte.
 */
interface JobSource
{
    /** Nom affiché à côté de l'offre (« Indeed », « France Travail »…). */
    public function name(): string;

    /** Identifiant court, utilisé en configuration et en cache. */
    public function key(): string;

    /** Faux si les identifiants manquent : la source est alors simplement ignorée. */
    public function isConfigured(): bool;

    /**
     * Recherche. Ne doit jamais lever : en cas d'échec réseau, renvoyer [].
     *
     * @param  array{q:string,city:string,limit:int,page:int} $criteria
     * @return array<int, array<string, mixed>> offres normalisées
     */
    public function search(array $criteria): array;
}
