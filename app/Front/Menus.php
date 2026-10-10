<?php
declare(strict_types=1);

namespace App\Front;

use App\Data\Collections;

/**
 * Menus du site modifiables dans le back-office (Éditorial › Menus) : entrées du menu principal,
 * colonne « Explorer » du menu Matchs, outils du menu Interactif et colonnes du pied de page.
 * Tant que rien n'est enregistré, les valeurs de départ ci-dessous s'appliquent. Libellés en
 * français ; l'anglais vient du champ « (EN) » s'il est rempli, sinon de l'écran Traductions.
 */
final class Menus
{
    public const NAME = 'menus';

    /** Entrées du menu principal qui ouvrent un grand menu déroulant (clé => description). */
    public const MEGA = ['matchs' => 'Matchs (compétitions, décennies, Explorer)', 'nos-lions' => 'Nos Lions (rubriques des joueurs)', 'supporters' => 'Supporters (sous-rubriques)', 'infrastructures' => 'Infrastructures (sous-rubriques)', 'symboles' => 'Symboles (sous-rubriques)', 'interactif' => 'Interactif (outils ci-dessous)'];

    public static function defaults(): array
    {
        $l = fn (string $label, string $href, array $more = []) => ['label' => $label, 'label_en' => '', 'href' => $href] + $more;
        $tool = fn (string $icon, string $label, string $d, string $href) => ['icon' => $icon, 'label' => $label, 'label_en' => '', 'd' => $d, 'd_en' => '', 'href' => $href];
        return [
            'buttons' => [
                $l('Contribuer', '/contribuer/', ['key' => 'contribuer']),
                $l('Boutique', '/boutique/', ['key' => 'boutique']),
                $l('Faire un don', '/faire-un-don/', ['key' => 'don']),
            ],
            'nav' => [
                $l('Accueil', '/', ['key' => 'accueil']),
                $l('Matchs', '/matchs/', ['key' => 'matchs']),
                $l('Nos Lions', '/nos-lions/', ['key' => 'nos-lions']),
                $l('Supporters', '/supporters/', ['key' => 'supporters']),
                $l('Infrastructures', '/infrastructures/', ['key' => 'infrastructures']),
                $l('Symboles', '/symboles/', ['key' => 'symboles']),
                $l('Interactif', '/interactif/', ['key' => 'interactif']),
            ],
            'matchs_explore' => [
                $l('Saisons', '/saisons/'), $l('Face-à-face', '/face-a-face/'), $l('Palmarès', '/palmares/'), $l('Records', '/records/'),
                $l('Les chiffres', '/chiffres/'), $l('Bilan à Bonal', '/bilans/stade-auguste-bonal/'), $l('Bilan en Coupe de France', '/bilans/coupe-de-france/'),
            ],
            'interactif' => [
                ['title' => "Explorer l'histoire", 'title_en' => '', 'tools' => [
                    $tool('¶', 'Grands récits', 'Les grandes histoires du club, racontées d’après les archives.', '/grands-recits/'),
                    $tool('●', 'Rétro-Direct', 'Les grands matchs rejoués en direct, le jour anniversaire.', '/interactif/retro-direct/'),
                    $tool('◎', 'Carto', 'Stades, origines, épopées, lieux.', '/interactif/carto/'),
                    $tool('×', 'Face-à-face', 'Choisissez un adversaire, voyez le bilan.', '/face-a-face/'),
                    $tool('🏆', 'Palmarès', 'Les titres et les finales depuis 1928.', '/palmares/'),
                    $tool('#', 'Records', 'Buteurs, affluences, séries.', '/records/'),
                    $tool('%', 'Les chiffres', '100 statistiques depuis 1929.', '/chiffres/'),
                    $tool('→', 'Frise', "De 1928 à aujourd'hui.", '/interactif/frise/'),
                    $tool('↔', 'Maillots', 'Deux époques, un curseur.', '/interactif/maillots/'),
                ]],
                ['title' => 'Les murs de photos', 'title_en' => '', 'auto' => 'walls', 'tools' => []],
                ['title' => 'Jouer', 'title_en' => '', 'tools' => [
                    $tool('?', 'Quiz', 'Êtes-vous un vrai Lionceau ?', '/interactif/quiz/'),
                    $tool('★', 'Le défi du jour', 'Dix questions, un essai par jour, un classement.', '/interactif/defi/'),
                    $tool('♛', 'Championnat du club-house', 'Les soirées quiz en salle, saison après saison.', '/interactif/quiz-live/championnat/'),
                    $tool('▦', 'Album', 'Collectionnez les cartes des Lions.', '/interactif/album/'),
                    $tool('⟿', 'Fil jaune', 'Reliez deux Lionceaux par leurs matchs.', '/interactif/fil-jaune/'),
                ]],
                ['title' => 'Participer', 'title_en' => '', 'tools' => [
                    $tool('✓', 'Mon carnet du supporter', 'Vos matchs vus au stade, votre bilan.', '/carnet/'),
                    $tool('XI', 'Onze de légende', 'Votez pour le centenaire.', '/centenaire/#onze'),
                    $tool('100', '100 moments', 'Dévoilés un à un jusqu’au centenaire.', '/centenaire/100-moments/'),
                    $tool('✎', 'Contribuer', 'Vos archives enrichissent le musée.', '/contribuer/'),
                    $tool('@', 'Ce jour-là', 'La newsletter du musée.', '/partage-et-newsletter/'),
                    $tool('❝', 'Kit souvenirs', 'Raconte-moi Bonal : à imprimer pour les anciens.', '/interactif/souvenirs/'),
                ]],
            ],
            'footer' => [
                ['title' => 'Explorer', 'title_en' => '', 'links' => [
                    $l('Matchs', '/matchs/'), $l('Saisons', '/saisons/'), $l('Nos Lions', '/nos-lions/'), $l('Face-à-face', '/face-a-face/'),
                    $l('Records', '/records/'), $l('Les chiffres', '/chiffres/'), $l('Le centenaire', '/centenaire/'),
                ]],
                ['title' => 'Interactif', 'title_en' => '', 'links' => [
                    $l('Rétro-Direct', '/interactif/retro-direct/'), $l('Le défi du jour', '/interactif/defi/'), $l('Quiz', '/interactif/quiz/'),
                    $l('Album', '/interactif/album/'), $l('Fil jaune', '/interactif/fil-jaune/'), $l('Frise', '/interactif/frise/'), $l('Tout Interactif', '/interactif/'),
                ]],
                ['title' => 'Participer', 'title_en' => '', 'links' => [
                    $l('Contribuer', '/contribuer/'), $l('La newsletter', '/newsletter/'), $l('L’appli du musée', '/appli/'), $l('Nous contacter', '/contact/'),
                    $l('La boutique', '/boutique/'), $l('L’association Sochaux Rétro', '{association}'), $l('Vidéo teaser', '/teaser/'),
                ]],
            ],
        ];
    }

    /** Menus enregistrés (ou valeurs de départ, partie par partie). */
    public static function get(): array
    {
        $saved = Collections::get(self::NAME, []);
        $saved = is_array($saved) ? $saved : [];
        return array_replace(self::defaults(), array_intersect_key($saved, self::defaults()));
    }

    /** Boutons du haut à droite (contribuer, boutique, don) : [libellé, adresse, externe] ou null s'il est masqué. */
    public static function button(string $key): ?array
    {
        foreach (self::get()['buttons'] as $b) {
            if (($b['key'] ?? '') === $key) {
                $h = empty($b['hidden']) ? self::href((string) ($b['href'] ?? '')) : null;
                return $h ? ['label' => self::text($b), 'href' => $h[0], 'ext' => $h[1]] : null;
            }
        }
        return null;
    }

    /** Libellé affiché : anglais saisi, sinon traduction du français. */
    public static function text(array $x, string $k = 'label'): string
    {
        $en = trim((string) ($x[$k . '_en'] ?? ''));
        return \App\Services\I18n::isEn() && $en !== '' ? $en : t((string) ($x[$k] ?? ''));
    }

    /**
     * Adresse affichée et lien à suivre. « {association} » : le site de l'association ; un lien
     * vers la boutique ou l'appli disparaît quand elles sont fermées.
     * @return array{0:string,1:bool}|null [adresse, externe]
     */
    public static function href(string $h): ?array
    {
        if ($h === '{association}') {
            return [\App\Vitrine\Host::base(), true];
        }
        if (str_starts_with($h, '/boutique') && !\App\Shop\ShopPages::visible()) {
            return null;
        }
        if (str_starts_with($h, '/appli') && !\App\Core\Settings::get('app.enabled', true)) {
            return null;
        }
        if (preg_match('#^https?://#', $h)) {
            return [$h, true];
        }
        return [url($h), false];
    }

    /** Liens prêts à afficher : [libellé, adresse, externe]. */
    public static function links(array $list): array
    {
        $out = [];
        foreach ($list as $x) {
            if (!empty($x['hidden']) || ($h = self::href((string) ($x['href'] ?? ''))) === null) {
                continue;
            }
            $out[] = ['label' => self::text($x), 'href' => $h[0], 'ext' => $h[1]];
        }
        return $out;
    }
}
