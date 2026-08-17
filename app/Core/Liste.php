<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Opérations communes aux listes ordonnées du back-office : photos du
 * bandeau d'accueil, produits de la boutique.
 *
 * Chaque entrée peut porter une clé « actif ». Son absence vaut publiée :
 * un contenu enregistré avant l'arrivée de cette notion reste visible.
 */
final class Liste
{
    /**
     * @param array<int, mixed> $liste
     * @return array<int, mixed>
     */
    public static function retirer(array $liste, int $rang): array
    {
        unset($liste[$rang]);
        return array_values($liste);
    }

    /**
     * Déplace une entrée d'un cran. $sens vaut -1 (monter) ou 1 (descendre).
     *
     * @param array<int, mixed> $liste
     * @return array<int, mixed>
     */
    public static function deplacer(array $liste, int $rang, int $sens): array
    {
        $vers = $rang + ($sens < 0 ? -1 : 1);
        if (!isset($liste[$rang], $liste[$vers])) {
            return $liste;
        }
        [$liste[$rang], $liste[$vers]] = [$liste[$vers], $liste[$rang]];
        return $liste;
    }

    /**
     * Inverse la publication d'une entrée et rend son nouvel état.
     *
     * @param array<int, array<string, mixed>> $liste
     * @return array{0: array<int, array<string, mixed>>, 1: bool}
     */
    public static function basculer(array $liste, int $rang): array
    {
        if (!isset($liste[$rang])) {
            return [$liste, false];
        }
        $enLigne = !Content::estPublie($liste[$rang]);
        $liste[$rang]['actif'] = $enLigne;

        return [$liste, $enLigne];
    }

    /**
     * Ramène une liste de photos à une forme unique, quelle que soit la
     * façon dont elle a été enregistrée : liste d'objets { src, actif },
     * liste de chemins, ou photo unique d'un contenu antérieur au
     * diaporama. Aucune conversion du fichier JSON n'est nécessaire.
     *
     * @return array<int, array{src: string, actif: bool}>
     */
    public static function photos(mixed $brut, string $repli = ''): array
    {
        if (!is_array($brut) || $brut === []) {
            $repli = trim($repli);
            return $repli === '' ? [] : [['src' => $repli, 'actif' => true]];
        }

        $photos = [];
        foreach ($brut as $photo) {
            $src = is_array($photo) ? trim((string) ($photo['src'] ?? '')) : trim((string) $photo);
            if ($src === '') {
                continue;
            }
            $photos[] = ['src' => $src, 'actif' => !is_array($photo) || Content::estPublie($photo)];
        }

        return $photos;
    }

    /**
     * Ne garde que les entrées publiées.
     *
     * @param array<int, array<string, mixed>> $liste
     * @return array<int, array<string, mixed>>
     */
    public static function publiees(array $liste): array
    {
        return array_values(array_filter($liste, Content::estPublie(...)));
    }
}
