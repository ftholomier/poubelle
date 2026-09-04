<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Ce que la diffusion attend d'un réseau social.
 *
 * `Diffusion` n'a jamais eu besoin de toute la classe `Reseaux` : elle publie,
 * et lit un permalien. Nommer ce besoin a deux vertus, et la seconde a
 * découvert un défaut.
 *
 * D'abord, la dépendance devient lisible : on voit d'un coup d'œil ce que la
 * file demande à Meta, et rien d'autre — ni les jetons, ni le dialogue de
 * connexion, ni les réglages.
 *
 * Ensuite, elle rend la file **vérifiable**. La logique la plus délicate du
 * dossier n'est pas l'appel à Meta, c'est ce qui se passe quand un seul des
 * deux réseaux répond : l'autre doit rester en file et être réessayé. Sans
 * doublure, cette branche ne pouvait être exercée qu'en publiant vraiment,
 * donc jamais — et elle était fausse : la publication était retirée de la file
 * et inscrite comme réussie. `outils/verifs/file.php` la mesure désormais.
 */
interface Publicateur
{
    /** Publie sur la Page. Rend l'identifiant du post, ou lève. */
    public function publierFacebook(string $texte, string $imageUrl = '', string $lien = ''): string;

    /** Publie sur le compte Instagram. Rend l'identifiant du média, ou lève. */
    public function publierInstagram(string $texte, string $imageUrl): string;

    /** L'adresse publique d'un média Instagram. Chaîne vide si indisponible. */
    public function permalienInstagram(string $mediaId): string;
}
