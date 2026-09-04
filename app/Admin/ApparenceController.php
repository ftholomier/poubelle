<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Charte;
use App\Core\Content;
use App\Core\Csrf;
use App\Core\Parametres;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Écran Apparence : couleur de la commune, disposition du menu, taille du logo.
 *
 * Les choix sont des réglages de présentation, pas du contenu : ils vivent
 * dans data/admin/parametres.json, hors git. Une mise à jour du code ne peut
 * donc pas ramener le site à sa disposition d'origine.
 */
final class ApparenceController
{
    /** Hauteur du logo dans la barre, en pixels sur grand écran. */
    public const LOGO_DEFAUT = 52;

    /* Les bornes ne sont pas décoratives, et le haut n'est pas une question
       de goût. Sous 36 px le logo devient illisible sur téléphone, où il est
       déjà réduit d'un tiers. Le plafond, lui, est dicté par le plus petit
       écran : à 320 px de large le logo est réduit à 62 % et son rapport est
       de 2,35, donc 120 px de référence font 175 px de large, centrés entre
       deux bords où le burger occupe déjà les trente premiers. Au-delà, les
       deux se touchent et mise-en-page.py le voit.

       Le plafond est le même dans les deux modes. En débordement il laisse
       24 px de dépassement sous une barre de 96 ; si la barre suit, elle
       monte à 164 px — c'est beaucoup, mais c'est précisément ce que « la
       barre suit le logo » veut dire, et l'aperçu de l'écran le montre avant
       d'enregistrer. */
    public const LOGO_MIN = 36;
    public const LOGO_MAX = 120;
    public const LOGO_PAS = 2;

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
        private readonly Content $content,
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
            'logo'    => self::hauteurLogo($this->parametres),
            'couleur' => self::couleur($this->parametres),
            // L'aperçu de la palette montre ce que la couleur choisie produit
            // réellement, jeton par jeton : la mairie voit les cinq tons et
            // les fonds avant d'enregistrer, plutôt que de découvrir le site
            // repeint après coup.
            'palette' => (new Charte(self::couleur($this->parametres)))->jetons(),
            'deborde' => self::logoDeborde($this->parametres),
            'bornes'  => ['min' => self::LOGO_MIN, 'max' => self::LOGO_MAX, 'pas' => self::LOGO_PAS],
            // L'aperçu montre le vrai logo à sa vraie taille : c'est ce qui
            // rend le réglage compréhensible sans aller-retour sur le site.
            // Version claire, parce que le fond de l'aperçu est sombre comme
            // la barre.
            'logoSrc' => (string) $this->content->get(
                'site',
                'logo.clair',
                'assets/img/logo/logo-angeot-clair.svg'
            ),
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

        $logo    = self::borner($_POST['logo'] ?? self::LOGO_DEFAUT);
        $deborde = isset($_POST['logo_deborde']);
        // Charte::normaliser retombe sur la couleur livrée devant n'importe
        // quelle saisie : le champ est un sélecteur de couleur, mais le
        // fichier de paramètres se recopie d'un site à l'autre.
        $couleur = Charte::normaliser((string) ($_POST['couleur'] ?? Charte::DEFAUT));

        $actuel = $this->parametres->tout();
        $actuel['apparence']['menu']         = $choix;
        $actuel['apparence']['logo']         = $logo;
        $actuel['apparence']['logo_deborde'] = $deborde;
        $actuel['apparence']['couleur']      = $couleur;

        try {
            $this->parametres->enregistrer($actuel);
            Session::flash('succes', 'Apparence enregistrée : « '
                . self::MENUS[$choix]['nom'] . ' », logo de ' . $logo . ' px, '
                . ($deborde ? 'qui déborde de la barre' : 'la barre suit sa taille')
                . ', couleur ' . $couleur
                . '. Rechargez le site pour la voir.');
        } catch (RuntimeException $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    /**
     * Hauteur du logo enregistrée, ramenée dans les bornes.
     *
     * Statique parce que le site public en a besoin sans passer par
     * l'administration : routes.php la partage avec le gabarit, et c'est la
     * même fonction qui borne des deux côtés. Elle repasse par borner() à la
     * lecture — un fichier de paramètres recopié d'un autre site, ou modifié
     * à la main, ne doit pas pouvoir poser un logo de six cents pixels dans
     * la barre.
     */
    public static function hauteurLogo(Parametres $parametres): int
    {
        return self::borner($parametres->get('apparence.logo', self::LOGO_DEFAUT));
    }

    /**
     * Couleur de la commune, ramenée à un hexadécimal valable.
     *
     * Statique et repassant par la normalisation, pour la même raison que la
     * hauteur du logo : le site public la lit sans passer par
     * l'administration, et un fichier de paramètres modifié à la main ne doit
     * pas pouvoir poser autre chose qu'une couleur.
     */
    public static function couleur(Parametres $parametres): string
    {
        return Charte::normaliser((string) $parametres->get('apparence.couleur', Charte::DEFAUT));
    }

    /** La barre laisse-t-elle le logo la dépasser ? */
    public static function logoDeborde(Parametres $parametres): bool
    {
        return (bool) $parametres->get('apparence.logo_deborde', false);
    }

    /** @param mixed $valeur */
    private static function borner(mixed $valeur): int
    {
        $n = is_numeric($valeur) ? (int) round((float) $valeur) : self::LOGO_DEFAUT;
        return max(self::LOGO_MIN, min(self::LOGO_MAX, $n));
    }
}
