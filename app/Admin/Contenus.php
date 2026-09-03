<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Seo;

/**
 * Inventaire des fichiers de contenu du site, avec leur nom d'écran.
 *
 * Quatre écrans en avaient chacun leur copie : l'éditeur avancé, la
 * médiathèque (pour dire où une photo sert), les traductions et le tableau de
 * bord. Une page ajoutée n'apparaissait donc que dans celui qu'on pensait à
 * mettre à jour — et le premier symptôme était une photo déclarée « libre »
 * alors qu'elle servait sur la page oubliée.
 *
 * L'inventaire se déduit désormais de la table des pages et de celle des
 * collections : ajouter une page suffit.
 */
final class Contenus
{
    /**
     * Fichier de contenu => intitulé lisible.
     *
     * @return array<string, string>
     */
    public static function tout(): array
    {
        $contenus = ['site' => 'Coordonnées et menu'];

        foreach (ContenuController::COLLECTIONS as $nom => $reglages) {
            $contenus[$nom] = $reglages['nom'];
        }
        foreach (ContenuController::LISTES as $nom => $reglages) {
            $contenus[$nom] = $reglages['nom'];
        }
        $contenus['conseil'] = 'Conseil municipal';

        foreach (ContenuController::GROUPES as $cles) {
            foreach ($cles as $cle) {
                $contenus['pages/' . $cle] = 'Page « ' . (Seo::PAGES[$cle]['nom'] ?? $cle) . ' »';
            }
        }
        // Trois pages ne figurent pas dans les groupes de l'éditeur de pages,
        // parce qu'elles ont leur écran propre. Elles restent du contenu.
        $contenus['pages/accueil'] = 'Page d’accueil';
        $contenus['pages/contact'] = 'Page « Contact »';
        $contenus['pages/demande'] = 'Page « Écrire à la mairie »';

        return $contenus;
    }

    /**
     * Les seuls contenus qui portent des photos.
     *
     * La médiathèque s'en sert pour dire où une image est employée : y
     * chercher dans un fichier de numéros de téléphone ne coûte rien, mais
     * allonge la liste sans rien apprendre.
     *
     * @return array<string, string>
     */
    public static function avecPhotos(): array
    {
        $sans = ['numeros', 'services-etat', 'documents', 'agenda', 'commissions'];

        return array_diff_key(self::tout(), array_flip($sans));
    }
}
