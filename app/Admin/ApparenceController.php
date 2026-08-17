<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Csrf;
use App\Core\Parametres;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Écran Apparence : disposition du menu de navigation.
 *
 * Le choix est un réglage de présentation, pas du contenu : il vit dans
 * data/admin/parametres.json, hors git. Une mise à jour du code ne peut donc
 * pas ramener le site à la disposition d'origine.
 */
final class ApparenceController
{
    /** Dispositions proposées, et ce qu'elles changent pour le visiteur. */
    public const MENUS = [
        'lateral' => [
            'nom'     => 'Menu latéral (burger)',
            'resume'  => 'Un bouton à trois barres ouvre un panneau qui glisse depuis la gauche.',
            'detail'  => 'Le logo reste au centre de l’en-tête, bien visible. Le panneau laisse '
                       . 'de la place aux sous-menus et aux coordonnées. C’est la disposition '
                       . 'la plus sobre, et celle qui se comporte de la même façon sur toutes '
                       . 'les tailles d’écran.',
        ],
        'horizontal' => [
            'nom'     => 'Menu horizontal en haut',
            'resume'  => 'Les rubriques sont affichées côte à côte dans l’en-tête, avec sous-menus déroulants.',
            'detail'  => 'Toutes les rubriques sont visibles d’un coup d’œil, sans clic : c’est '
                       . 'la disposition la plus classique pour un site professionnel. Sur '
                       . 'téléphone et petite tablette, où une barre horizontale ne tiendrait '
                       . 'pas, le menu latéral reprend automatiquement la main.',
        ],
    ];

    public function __construct(
        private readonly View $view,
        private readonly Parametres $parametres,
    ) {
    }

    private function rediriger(): string
    {
        header('Location: ' . url('/admin/apparence'), true, 303);
        return '';
    }

    public function ecran(): string
    {
        return $this->view->render('admin/apparence', [
            'page'    => ['titre' => 'Apparence'],
            'menus'   => self::MENUS,
            'courant' => (string) $this->parametres->get('apparence.menu', 'lateral'),
        ], 'admin/layout');
    }

    public function envoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $choix = (string) ($_POST['menu'] ?? '');
        if (!isset(self::MENUS[$choix])) {
            Session::flash('erreur', 'Disposition de menu inconnue.');
            return $this->rediriger();
        }

        $actuel = $this->parametres->tout();
        $actuel['apparence']['menu'] = $choix;

        try {
            $this->parametres->enregistrer($actuel);
            Session::flash('succes', 'Disposition enregistrée : « '
                . self::MENUS[$choix]['nom'] . ' ». Rechargez le site pour la voir.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }
}
