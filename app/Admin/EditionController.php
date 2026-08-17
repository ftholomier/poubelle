<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Liste;
use App\Core\Mediatheque;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Écrans d'édition du contenu. Chaque écran lit le JSON, présente un
 * formulaire dédié, et réécrit le JSON via Content::save (sauvegarde
 * automatique de la version précédente à chaque enregistrement).
 */
final class EditionController
{
    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Mediatheque $mediatheque,
    ) {
    }

    /**
     * Photo choisie dans la médiathèque, ou envoyée dans la foulée.
     *
     * Rend null en ayant posé le message qui explique pourquoi : l'appelant
     * n'a plus qu'à rediriger. (Relire le flash pour le vérifier le
     * consommerait, et l'écran n'afficherait plus rien.)
     */
    private function photoSoumise(string $champ): ?string
    {
        $envoi = $_FILES[$champ] ?? null;
        if (is_array($envoi) && ($envoi['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                return $this->mediatheque->televerser($envoi);
            } catch (RuntimeException $e) {
                Session::flash('erreur', $e->getMessage());
                return null;
            }
        }

        $choix = trim((string) ($_POST[$champ] ?? ''));
        if ($choix !== '' && $this->mediatheque->existe($choix)) {
            return $choix;
        }

        Session::flash('erreur', 'Choisissez une photo de la médiathèque, ou envoyez-en une.');
        return null;
    }

    private function rediriger(string $chemin): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    /**
     * Découpe un textarea en liste (une entrée par ligne, vides ignorées).
     *
     * @return string[]
     */
    private static function lignes(string $texte): array
    {
        $lignes = preg_split('/\R/', $texte) ?: [];
        return array_values(array_filter(array_map('trim', $lignes), fn($l) => $l !== ''));
    }

    /**
     * Découpe un textarea en paragraphes (séparés par une ligne vide).
     *
     * @return string[]
     */
    private static function paragraphes(string $texte): array
    {
        $blocs = preg_split('/\R{2,}/', trim($texte)) ?: [];
        return array_values(array_filter(array_map(
            fn($b) => trim(preg_replace('/\s+/', ' ', $b) ?? ''),
            $blocs
        ), fn($b) => $b !== ''));
    }

    // ----- coordonnées et réservation ------------------------------------

    public function site(): string
    {
        return $this->view->render('admin/site', [
            'page' => ['titre' => 'Coordonnées & réservation'],
            'site' => $this->content->load('site'),
        ], 'admin/layout');
    }

    public function siteEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/site');
        }
        $site = $this->content->load('site');

        $site['contact']['telephone'] = trim((string) ($_POST['telephone'] ?? $site['contact']['telephone']));
        $site['contact']['email']     = trim((string) ($_POST['email'] ?? ''));
        $site['adresse']['rue']       = trim((string) ($_POST['rue'] ?? $site['adresse']['rue']));
        $site['adresse']['cp']        = trim((string) ($_POST['cp'] ?? $site['adresse']['cp']));
        $site['adresse']['ville']     = trim((string) ($_POST['ville'] ?? $site['adresse']['ville']));

        foreach (['hebergement', 'etang'] as $cle) {
            $url = trim((string) ($_POST['resa_' . $cle] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                $site['reservation'][$cle]['url'] = $url;
            }
        }

        $site['pied']['seo']       = trim((string) ($_POST['pied_seo'] ?? $site['pied']['seo']));
        $site['pied']['proche_de'] = trim((string) ($_POST['pied_proche'] ?? $site['pied']['proche_de']));

        $this->content->save('site', $site);
        Session::flash('succes', 'Coordonnées enregistrées.');
        return $this->rediriger('/admin/site');
    }

    // ----- accueil --------------------------------------------------------

    public function accueil(): string
    {
        $accueil = $this->content->load('pages/accueil');

        return $this->view->render('admin/accueil', [
            'page'    => ['titre' => 'Page d\'accueil'],
            'accueil' => $accueil,
            'photos'  => self::photosHero($accueil),
            'medias'  => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    /**
     * Photos du bandeau d'accueil, ramenées à une liste ordonnée.
     *
     * @param array<string, mixed> $accueil
     * @return array<int, array{src: string, actif: bool}>
     */
    private static function photosHero(array $accueil): array
    {
        return Liste::photos(
            $accueil['hero']['images'] ?? null,
            (string) ($accueil['hero']['image'] ?? '')
        );
    }

    /**
     * Enregistre les photos du bandeau. hero.image reste synchronisé sur la
     * première photo publiée : les partages sociaux et les anciens gabarits
     * continuent d'y trouver un visuel.
     *
     * @param array<int, array{src: string, actif: bool}> $photos
     */
    private function enregistrerPhotosHero(array $photos, string $message): string
    {
        $accueil = $this->content->load('pages/accueil');
        $accueil['hero']['images'] = array_values($photos);

        $publiees = Liste::publiees($photos);
        $accueil['hero']['image'] = (string) (($publiees[0] ?? $photos[0] ?? [])['src'] ?? '');

        $this->content->save('pages/accueil', $accueil);
        Session::flash($message === '' ? 'erreur' : 'succes', $message);

        return $this->rediriger('/admin/accueil#bandeau');
    }

    public function heroAjout(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/accueil');
        }

        $chemin = $this->photoSoumise('photo');
        if ($chemin === null) {
            return $this->rediriger('/admin/accueil#bandeau');
        }

        $photos = self::photosHero($this->content->load('pages/accueil'));
        foreach ($photos as $photo) {
            if ($photo['src'] === $chemin) {
                Session::flash('erreur', 'Cette photo est déjà dans le diaporama.');
                return $this->rediriger('/admin/accueil#bandeau');
            }
        }

        $photos[] = ['src' => $chemin, 'actif' => true];
        return $this->enregistrerPhotosHero($photos, 'Photo ajoutée au diaporama.');
    }

    public function heroPublication(int $rang): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/accueil');
        }

        $photos = self::photosHero($this->content->load('pages/accueil'));
        [$photos, $enLigne] = Liste::basculer($photos, $rang);

        // un diaporama sans aucune photo laisserait un bandeau noir
        if (!$enLigne && Liste::publiees($photos) === []) {
            Session::flash('erreur', 'Gardez au moins une photo affichée : '
                . 'le bandeau d’accueil serait vide.');
            return $this->rediriger('/admin/accueil#bandeau');
        }

        return $this->enregistrerPhotosHero(
            $photos,
            $enLigne ? 'Photo affichée dans le diaporama.' : 'Photo masquée du diaporama.'
        );
    }

    public function heroOrdre(int $rang): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/accueil');
        }

        $photos = self::photosHero($this->content->load('pages/accueil'));
        $sens   = ($_POST['sens'] ?? '') === 'monter' ? -1 : 1;

        return $this->enregistrerPhotosHero(
            Liste::deplacer($photos, $rang, $sens),
            'Ordre du diaporama mis à jour.'
        );
    }

    public function heroSupprimer(int $rang): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/accueil');
        }

        $photos = self::photosHero($this->content->load('pages/accueil'));
        if (count($photos) < 2) {
            Session::flash('erreur', 'C’est la seule photo du bandeau : remplacez-la '
                . 'plutôt que de la retirer.');
            return $this->rediriger('/admin/accueil#bandeau');
        }

        return $this->enregistrerPhotosHero(
            Liste::retirer($photos, $rang),
            'Photo retirée du diaporama. Elle reste dans la médiathèque.'
        );
    }

    public function accueilEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/accueil');
        }
        $a = $this->content->load('pages/accueil');

        $a['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? $a['hero']['surtitre']));
        $a['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $a['hero']['titre']));
        $a['hero']['texte']    = trim((string) ($_POST['hero_texte'] ?? $a['hero']['texte']));

        foreach ($a['piliers']['items'] as $i => $pilier) {
            $texte = trim((string) ($_POST['pilier_' . $i] ?? ''));
            if ($texte !== '') {
                $a['piliers']['items'][$i]['texte'] = $texte;
            }
        }
        $a['piliers']['cta'] = trim((string) ($_POST['piliers_cta'] ?? $a['piliers']['cta']));

        $a['citation']['titre'] = trim((string) ($_POST['citation_titre'] ?? $a['citation']['titre']));
        $a['citation']['texte'] = trim((string) ($_POST['citation_texte'] ?? $a['citation']['texte']));

        $a['meta']['description'] = trim((string) ($_POST['meta'] ?? $a['meta']['description']));

        $this->content->save('pages/accueil', $a);
        Session::flash('succes', 'Page d\'accueil enregistrée.');
        return $this->rediriger('/admin/accueil');
    }

    // ----- hébergements ---------------------------------------------------

    public function hebergements(): string
    {
        return $this->view->render('admin/hebergements', [
            'page'  => ['titre' => 'Hébergements'],
            'items' => $this->content->load('hebergements')['items'] ?? [],
        ], 'admin/layout');
    }

    /**
     * Squelette complet d'un nouvel hébergement.
     *
     * Toutes les clés lues par les gabarits publics sont présentes : une
     * fiche créée ici s'affiche sans erreur avant même d'être remplie.
     *
     * @return array<mixed>
     */
    private function hebergementVierge(string $slug, string $nom): array
    {
        return [
            'slug'             => $slug,
            'nom'              => $nom,
            // hors ligne au départ : on la remplit et on l'illustre avant
            // de la rendre visible du public
            'actif'            => false,
            'sous_titre'       => '',
            'resume'           => '',
            'capacite'         => 4,
            'prix_a_partir_de' => 0,
            'image'            => '',
            'images_avant'     => [],
            'sections'         => [
                ['titre' => 'Présentation', 'paragraphes' => []],
                ['titre' => 'Le confort',   'paragraphes' => []],
                ['titre' => 'Le cadre',     'paragraphes' => []],
            ],
            'theme'        => ['titre' => 'En images', 'galerie' => []],
            'prestations'  => [
                'titre'      => 'Nos prestations',
                'intro'      => '',
                'groupes'    => [['titre' => '', 'items' => []]],
                'complement' => [],
            ],
            'supplements'  => ['titre' => 'En supplément', 'items' => []],
            'activites'    => ['titre' => 'Les activités à faire', 'intro' => '', 'items' => []],
            'tarification' => ['texte' => '', 'note' => ''],
            'meta'         => ['description' => ''],
        ];
    }

    /**
     * Transforme un nom libre en identifiant d'URL, unique dans la collection.
     *
     * @param string[] $existants
     */
    private function slugUnique(string $nom, array $existants): string
    {
        $base = $nom;
        if (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $base);
            if ($translit !== false) {
                $base = $translit;
            }
        }
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $base) ?? '');
        $base = trim(preg_replace('/-+/', '-', $base) ?? '', '-') ?: 'hebergement';

        $slug = $base;
        $n = 1;
        while (in_array($slug, $existants, true)) {
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }

    public function hebergementCreer(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/hebergements');
        }

        $nom = trim((string) ($_POST['nom'] ?? ''));
        if (mb_strlen($nom) < 2) {
            Session::flash('erreur', 'Donnez un nom d’au moins 2 caractères au nouvel hébergement.');
            return $this->rediriger('/admin/hebergements');
        }

        $donnees = $this->content->load('hebergements');
        $slug = $this->slugUnique($nom, array_column($donnees['items'], 'slug'));

        $donnees['items'][] = $this->hebergementVierge($slug, $nom);
        $this->content->save('hebergements', $donnees);

        Session::flash('succes', $nom . ' a été créé, hors ligne. Complétez la fiche, '
            . 'ajoutez les photos, puis mettez-la en ligne depuis la liste.');
        return $this->rediriger('/admin/hebergements/' . rawurlencode($slug));
    }

    public function hebergementPublication(string $slug): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/hebergements');
        }

        $donnees = $this->content->load('hebergements');
        foreach ($donnees['items'] as $i => $item) {
            if (($item['slug'] ?? '') !== $slug) {
                continue;
            }
            $enLigne = !\App\Core\Content::estPublie($item);
            $donnees['items'][$i]['actif'] = $enLigne;
            $this->content->save('hebergements', $donnees);

            Session::flash('succes', ($item['nom'] ?? $slug) . ($enLigne
                ? ' est désormais visible sur le site.'
                : ' est passé hors ligne : la fiche disparaît du site et du menu.'));
            break;
        }

        return $this->rediriger('/admin/hebergements');
    }

    public function hebergementSupprimer(string $slug): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/hebergements');
        }

        $donnees = $this->content->load('hebergements');
        $restants = array_values(array_filter(
            $donnees['items'],
            fn(array $i) => ($i['slug'] ?? '') !== $slug
        ));

        if (count($restants) === count($donnees['items'])) {
            return $this->rediriger('/admin/hebergements');
        }
        if ($restants === []) {
            Session::flash('erreur', 'Impossible de supprimer le dernier hébergement.');
            return $this->rediriger('/admin/hebergements');
        }

        $nom = '';
        foreach ($donnees['items'] as $i) {
            if (($i['slug'] ?? '') === $slug) {
                $nom = $i['nom'] ?? $slug;
            }
        }

        $donnees['items'] = $restants;
        $this->content->save('hebergements', $donnees);

        Session::flash('succes', $nom . ' a été supprimé. Les photos restent dans la médiathèque.');
        return $this->rediriger('/admin/hebergements');
    }

    public function hebergement(string $slug): string
    {
        $item = $this->content->find('hebergements', $slug);
        if ($item === null) {
            return $this->rediriger('/admin/hebergements');
        }
        return $this->view->render('admin/hebergement', [
            'page' => ['titre' => $item['nom']],
            'item' => $item,
        ], 'admin/layout');
    }

    public function hebergementEnvoi(string $slug): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/hebergements/' . rawurlencode($slug));
        }
        $donnees = $this->content->load('hebergements');
        foreach ($donnees['items'] as $i => $item) {
            if (($item['slug'] ?? '') !== $slug) {
                continue;
            }

            $item['nom']        = trim((string) ($_POST['nom'] ?? $item['nom']));
            $item['sous_titre'] = trim((string) ($_POST['sous_titre'] ?? $item['sous_titre']));
            $item['resume']     = trim((string) ($_POST['resume'] ?? $item['resume']));
            $item['capacite']   = max(1, (int) ($_POST['capacite'] ?? $item['capacite']));
            $item['prix_a_partir_de'] = max(0, (int) ($_POST['prix'] ?? $item['prix_a_partir_de']));

            foreach ($item['sections'] as $s => $section) {
                $titre = trim((string) ($_POST['section_titre_' . $s] ?? ''));
                if ($titre !== '') {
                    $item['sections'][$s]['titre'] = $titre;
                }
                if (isset($_POST['section_texte_' . $s])) {
                    $item['sections'][$s]['paragraphes'] = self::paragraphes((string) $_POST['section_texte_' . $s]);
                }
            }

            foreach ($item['prestations']['groupes'] as $g => $groupe) {
                if (isset($_POST['prestations_' . $g])) {
                    $item['prestations']['groupes'][$g]['items'] = self::lignes((string) $_POST['prestations_' . $g]);
                }
            }
            if (isset($_POST['prestations_complement'])) {
                $item['prestations']['complement'] = self::paragraphes((string) $_POST['prestations_complement']);
            }
            if (isset($_POST['supplements'])) {
                $item['supplements']['items'] = self::lignes((string) $_POST['supplements']);
            }
            if (isset($_POST['activites'])) {
                $item['activites']['items'] = self::lignes((string) $_POST['activites']);
            }

            $item['tarification']['texte'] = trim((string) ($_POST['tarif_texte'] ?? $item['tarification']['texte']));
            $item['tarification']['note']  = trim((string) ($_POST['tarif_note'] ?? $item['tarification']['note']));
            $item['meta']['description']   = trim((string) ($_POST['meta'] ?? $item['meta']['description']));

            $donnees['items'][$i] = $item;
            $this->content->save('hebergements', $donnees);
            Session::flash('succes', $item['nom'] . ' enregistré.');
            break;
        }
        return $this->rediriger('/admin/hebergements/' . rawurlencode($slug));
    }

    // ----- pêche ----------------------------------------------------------

    public function peche(): string
    {
        return $this->view->render('admin/peche', [
            'page'   => ['titre' => 'Pêche'],
            'donnees' => $this->content->load('peche'),
        ], 'admin/layout');
    }

    public function etang(string $slug): string
    {
        $item = $this->content->find('peche', $slug);
        if ($item === null) {
            return $this->rediriger('/admin/peche');
        }
        return $this->view->render('admin/etang', [
            'page' => ['titre' => $item['nom']],
            'item' => $item,
        ], 'admin/layout');
    }

    public function etangEnvoi(string $slug): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/peche/' . rawurlencode($slug));
        }
        $donnees = $this->content->load('peche');
        foreach ($donnees['items'] as $i => $item) {
            if (($item['slug'] ?? '') !== $slug) {
                continue;
            }

            $item['sous_titre'] = trim((string) ($_POST['sous_titre'] ?? $item['sous_titre']));
            $item['resume']     = trim((string) ($_POST['resume'] ?? $item['resume']));
            if (isset($_POST['reservation'])) {
                $item['reservation']['paragraphes'] = self::paragraphes((string) $_POST['reservation']);
            }

            $item['tarification']['intro']     = trim((string) ($_POST['tarif_intro'] ?? $item['tarification']['intro']));
            $item['tarification']['attention'] = trim((string) ($_POST['tarif_attention'] ?? $item['tarification']['attention']));
            foreach ($item['tarification']['tableau']['valeurs'] as $v => $valeur) {
                $nouvelle = trim((string) ($_POST['tarif_' . $v] ?? ''));
                if ($nouvelle !== '') {
                    $item['tarification']['tableau']['valeurs'][$v] = $nouvelle;
                }
            }

            if (isset($item['droits']) && isset($_POST['droits'])) {
                $item['droits']['items'] = self::lignes((string) $_POST['droits']);
            }
            if (isset($item['interdictions']) && isset($_POST['interdictions'])) {
                $item['interdictions']['items'] = self::lignes((string) $_POST['interdictions']);
            }
            if (isset($item['specificites']) && isset($_POST['specificites'])) {
                $item['specificites']['items'] = self::lignes((string) $_POST['specificites']);
            }
            if (array_key_exists('recapitulatif', $item)) {
                $item['recapitulatif'] = trim((string) ($_POST['recapitulatif'] ?? $item['recapitulatif']));
            }

            $donnees['items'][$i] = $item;
            $this->content->save('peche', $donnees);
            Session::flash('succes', $item['nom'] . ' enregistré.');
            break;
        }
        return $this->rediriger('/admin/peche/' . rawurlencode($slug));
    }

    public function pecheReglesEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/peche');
        }
        $donnees = $this->content->load('peche');
        if (isset($_POST['regles'])) {
            $donnees['regles_communes']['paragraphes'] = self::paragraphes((string) $_POST['regles']);
        }
        $donnees['regles_communes']['attention']['texte'] =
            trim((string) ($_POST['attention'] ?? $donnees['regles_communes']['attention']['texte']));
        $this->content->save('peche', $donnees);
        Session::flash('succes', 'Règles communes enregistrées.');
        return $this->rediriger('/admin/peche');
    }

    // ----- boutique -------------------------------------------------------

    public function boutique(): string
    {
        return $this->view->render('admin/boutique', [
            'page'     => ['titre' => 'Boutique'],
            'boutique' => $this->content->load('pages/boutique'),
            'medias'   => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    /**
     * Produit vierge, hors ligne le temps d'être complété.
     *
     * @return array<string, mixed>
     */
    private static function produitVierge(string $nom): array
    {
        return [
            'nom'     => $nom,
            'image'   => '',
            'texte'   => '',
            'details' => [],
            'actif'   => false,
        ];
    }

    public function boutiqueCreer(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/boutique');
        }

        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($nom === '') {
            Session::flash('erreur', 'Indiquez le nom du produit à créer.');
            return $this->rediriger('/admin/boutique');
        }

        $b = $this->content->load('pages/boutique');
        $b['produits'][] = self::produitVierge($nom);
        $rang = count($b['produits']) - 1;
        $this->content->save('pages/boutique', $b);

        Session::flash('succes', $nom . ' a été créé, hors ligne. '
            . 'Complétez la fiche puis mettez-la en ligne.');
        return $this->rediriger('/admin/boutique#produit-' . $rang);
    }

    public function boutiquePublication(int $rang): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/boutique');
        }

        $b = $this->content->load('pages/boutique');
        if (!isset($b['produits'][$rang])) {
            return $this->rediriger('/admin/boutique');
        }

        [$b['produits'], $enLigne] = Liste::basculer($b['produits'], $rang);
        $this->content->save('pages/boutique', $b);

        Session::flash('succes', ($b['produits'][$rang]['nom'] ?? 'Le produit')
            . ($enLigne ? ' est en ligne.' : ' est hors ligne : il disparaît de la boutique.'));
        return $this->rediriger('/admin/boutique#produit-' . $rang);
    }

    public function boutiqueSupprimer(int $rang): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/boutique');
        }

        $b = $this->content->load('pages/boutique');
        if (!isset($b['produits'][$rang])) {
            return $this->rediriger('/admin/boutique');
        }

        $nom = (string) ($b['produits'][$rang]['nom'] ?? 'Le produit');
        $b['produits'] = Liste::retirer($b['produits'], $rang);
        $this->content->save('pages/boutique', $b);

        Session::flash('succes', $nom . ' a été supprimé. La photo reste dans la médiathèque.');
        return $this->rediriger('/admin/boutique');
    }

    public function boutiqueOrdre(int $rang): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/boutique');
        }

        $b = $this->content->load('pages/boutique');
        $sens = ($_POST['sens'] ?? '') === 'monter' ? -1 : 1;
        $b['produits'] = Liste::deplacer($b['produits'], $rang, $sens);
        $this->content->save('pages/boutique', $b);

        Session::flash('succes', 'Ordre des produits mis à jour.');
        return $this->rediriger('/admin/boutique');
    }

    public function boutiquePhoto(int $rang): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/boutique');
        }

        $b = $this->content->load('pages/boutique');
        if (!isset($b['produits'][$rang])) {
            return $this->rediriger('/admin/boutique');
        }

        $chemin = $this->photoSoumise('photo');
        if ($chemin === null) {
            return $this->rediriger('/admin/boutique#produit-' . $rang);
        }

        $b['produits'][$rang]['image'] = $chemin;
        $this->content->save('pages/boutique', $b);

        Session::flash('succes', 'Photo du produit mise à jour.');
        return $this->rediriger('/admin/boutique#produit-' . $rang);
    }

    public function boutiqueEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/boutique');
        }
        $b = $this->content->load('pages/boutique');

        $b['horaires'] = trim((string) ($_POST['horaires'] ?? $b['horaires']));
        foreach ($b['produits'] as $i => $produit) {
            $nom = trim((string) ($_POST['produit_nom_' . $i] ?? ''));
            if ($nom !== '') {
                $b['produits'][$i]['nom'] = $nom;
            }
            $b['produits'][$i]['texte'] = trim((string) ($_POST['produit_texte_' . $i] ?? ($produit['texte'] ?? '')));
            if (isset($_POST['produit_details_' . $i])) {
                $b['produits'][$i]['details'] = self::lignes((string) $_POST['produit_details_' . $i]);
            }
        }

        $this->content->save('pages/boutique', $b);
        Session::flash('succes', 'Boutique enregistrée.');
        return $this->rediriger('/admin/boutique');
    }

    // ----- règlement ------------------------------------------------------

    public function reglement(): string
    {
        return $this->view->render('admin/reglement', [
            'page'      => ['titre' => 'Règlement général'],
            'reglement' => $this->content->load('pages/reglement'),
        ], 'admin/layout');
    }

    public function reglementEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/reglement');
        }
        $r = $this->content->load('pages/reglement');
        if (isset($_POST['regles'])) {
            $r['regles'] = self::lignes((string) $_POST['regles']);
        }
        if (isset($_POST['conclusion'])) {
            $r['conclusion'] = self::paragraphes((string) $_POST['conclusion']);
        }
        $this->content->save('pages/reglement', $r);
        Session::flash('succes', 'Règlement enregistré.');
        return $this->rediriger('/admin/reglement');
    }

    // ----- éditeur avancé -------------------------------------------------

    /** Contenus éditables par l'éditeur avancé. */
    private const AVANCE = [
        'site', 'galerie', 'hebergements', 'peche',
        'pages/accueil', 'pages/boutique', 'pages/contact', 'pages/galerie',
        'pages/hebergements', 'pages/peche', 'pages/reglement', 'pages/mentions-legales',
    ];

    public function avance(?string $nom = null): string
    {
        $nom = $nom !== null && in_array($nom, self::AVANCE, true) ? $nom : 'site';
        return $this->view->render('admin/avance', [
            'page'        => ['titre' => 'Éditeur avancé'],
            'noms'        => self::AVANCE,
            'nom'         => $nom,
            'json'        => json_encode(
                $this->content->load($nom),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'sauvegardes' => $this->content->sauvegardes($nom),
            'erreur'      => Session::flash('erreur_avance'),
        ], 'admin/layout');
    }

    public function avanceEnvoi(): string
    {
        $nom = (string) ($_POST['nom'] ?? 'site');
        if (!Csrf::verifier() || !in_array($nom, self::AVANCE, true)) {
            return $this->rediriger('/admin/avance');
        }
        $cible = '/admin/avance?nom=' . rawurlencode($nom);

        if (isset($_POST['restaurer'])) {
            try {
                $this->content->restaurer($nom, (string) $_POST['restaurer']);
                Session::flash('succes', 'Sauvegarde restaurée.');
            } catch (RuntimeException | \JsonException $e) {
                Session::flash('erreur_avance', 'Restauration impossible : ' . $e->getMessage());
            }
            return $this->rediriger($cible);
        }

        try {
            $donnees = json_decode((string) ($_POST['json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($donnees)) {
                throw new RuntimeException('Le JSON doit décrire un objet.');
            }
            $this->content->save($nom, $donnees);
            Session::flash('succes', 'Contenu « ' . $nom . ' » enregistré.');
        } catch (\JsonException $e) {
            Session::flash('erreur_avance', 'JSON invalide : ' . $e->getMessage());
        } catch (RuntimeException $e) {
            Session::flash('erreur_avance', $e->getMessage());
        }
        return $this->rediriger($cible);
    }
}
