<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Le voile sombre posé sur la photo du bandeau d'accueil.
 *
 * C'est lui, et lui seul, qui rend le titre blanc lisible : les photos du
 * diaporama changent, et rien ne garantit qu'une vue d'hiver enneigée ou un
 * verger en fleurs laisse au texte les 4,5:1 qu'exige le RGAA.
 *
 * **Pourquoi une borne basse, et pourquoi ici.** Le réglage était offert de 0
 * à 100 dans le back-office. Une mairie qui trouve ses photos trop sombres
 * descend le curseur, la page reste belle à l'œil, et le titre passe sous le
 * seuil sans que rien ne le dise. L'auditeur `bandeau.py` ne mesurait alors
 * que la valeur du jour : il aurait constaté la faute après coup, en ligne.
 *
 * La borne est donc une **constante mesurée**, pas un chiffre choisi :
 * `bandeau.py` force cette valeur exacte sur chacune des photos du diaporama,
 * à trois largeurs, et échoue si une seule descend sous 4,5:1. Changer le jeu
 * de photos peut donc obliger à relever la borne — c'est le sens de la
 * mesure, et c'est ce que l'auditeur dira.
 *
 * Ne la baissez pas pour faire passer une photo : retirez la photo, ou
 * assombrissez-la au traitement.
 */
final class Bandeau
{
    /**
     * En dessous, le titre blanc n'est plus garanti à 4,5:1.
     *
     * Valeur établie par `outils/verifs/bandeau.py` sur les six vues du
     * diaporama d'Angeot : à 85 %, le pire pixel de la zone de texte donne
     * **4,72:1** sur `mairie-ecole.jpg` à 390 px, la plus claire des six ; à
     * 80 % il tombe à 4,18:1 sur deux d'entre elles. La marge est mince, et
     * c'est voulu : plus haut, on interdirait à la mairie un réglage qui
     * tient ; plus bas, on lui laisserait servir un titre illisible.
     *
     * Ajouter une photo plus claire au diaporama fera échouer l'auditeur.
     * C'est le signal attendu : assombrissez la photo, ou retirez-la.
     */
    public const VOILE_MINI = 85;

    public const VOILE_MAXI = 100;

    /** Le réglage ramené dans ses bornes. */
    public static function voile(mixed $brut, int $defaut = self::VOILE_MAXI): int
    {
        if ($brut === null || $brut === '') {
            $brut = $defaut;
        }

        return max(self::VOILE_MINI, min(self::VOILE_MAXI, (int) $brut));
    }
}
