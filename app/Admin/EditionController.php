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
    /** Contenus proposés à l'éditeur avancé. */
    private const CONTENUS = [
        'site'                  => 'Coordonnées et menu',
        'services'              => 'Services',
        'valeurs'               => 'Valeurs',
        'pages/accueil'         => 'Page d’accueil',
        'pages/la-societe'      => 'Page « La société »',
        'pages/nos-services'    => 'Page « Nos services »',
        'pages/nos-valeurs'     => 'Page « Nos valeurs »',
        'pages/contact'         => 'Page « Contact »',
        'pages/mentions-legales' => 'Mentions légales',
    ];

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

    /**
     * Photo facultative : une valeur vide conserve l'image déjà en place
     * plutôt que de la retirer. Sans cela, enregistrer un texte ferait
     * disparaître la photo à chaque fois.
     */
    private function photoFacultative(string $champ, string $actuelle): string
    {
        $envoi = $_FILES[$champ] ?? null;
        if (is_array($envoi) && ($envoi['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                return $this->mediatheque->televerser($envoi);
            } catch (RuntimeException $e) {
                Session::flash('erreur', $e->getMessage());
                return $actuelle;
            }
        }

        $choix = trim((string) ($_POST[$champ] ?? ''));

        return $choix !== '' && $this->mediatheque->existe($choix) ? $choix : $actuelle;
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

    /**
     * Adresse (slug) libre et lisible, dérivée d'un intitulé.
     *
     * @param string[] $existants
     */
    private static function slugUnique(string $nom, array $existants): string
    {
        $base = \App\Core\Seo::normaliser($nom);
        if ($base === '') {
            $base = 'fiche';
        }

        $slug = $base;
        $n = 2;
        while (in_array($slug, $existants, true)) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    // ================================================== coordonnées et menu

    public function site(): string
    {
        return $this->view->render('admin/site', [
            'page' => ['titre' => 'Coordonnées & navigation'],
            'site' => $this->content->load('site'),
        ], 'admin/layout');
    }

    public function siteEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/site');
        }
        $site = $this->content->load('site');

        $site['nom']      = trim((string) ($_POST['nom'] ?? $site['nom']));
        $site['baseline'] = trim((string) ($_POST['baseline'] ?? $site['baseline']));
        $site['accroche'] = trim((string) ($_POST['accroche'] ?? $site['accroche']));

        $site['contact']['telephone'] = trim((string) ($_POST['telephone'] ?? ''));
        $site['contact']['email']     = trim((string) ($_POST['email'] ?? ''));
        $site['contact']['horaires']  = trim((string) ($_POST['horaires'] ?? ''));

        $site['adresse']['rue']   = trim((string) ($_POST['rue'] ?? $site['adresse']['rue']));
        $site['adresse']['cp']    = trim((string) ($_POST['cp'] ?? $site['adresse']['cp']));
        $site['adresse']['ville'] = trim((string) ($_POST['ville'] ?? $site['adresse']['ville']));

        $site['fondation']['annee']      = trim((string) ($_POST['annee'] ?? ''));
        $site['fondation']['fondatrice'] = trim((string) ($_POST['fondatrice'] ?? ''));
        $site['fondation']['qualite']    = trim((string) ($_POST['qualite'] ?? ''));

        // Le bouton d'appel à l'action pointe vers une page du site : une
        // adresse interne suffit, et suit le préfixe de langue.
        $site['reservation']['principal']['libelle'] = trim((string) ($_POST['cta_libelle'] ?? ''))
            ?: $site['reservation']['principal']['libelle'];
        $site['reservation']['principal']['url'] = '/' . ltrim(trim((string) ($_POST['cta_url'] ?? '')), '/');

        $site['pied']['seo']       = trim((string) ($_POST['pied_seo'] ?? ''));
        $site['pied']['proche_de'] = trim((string) ($_POST['pied_proche'] ?? ''));
        $site['pied']['copyright'] = trim((string) ($_POST['pied_copyright'] ?? $site['pied']['copyright']));

        // --- menu : un libellé et une adresse par ligne, séparés par « | »
        $menu = [];
        foreach (self::lignes((string) ($_POST['menu'] ?? '')) as $ligne) {
            $parts = array_map('trim', explode('|', $ligne, 2));
            if (($parts[0] ?? '') === '') {
                continue;
            }
            $entree = ['libelle' => $parts[0], 'url' => '/' . ltrim($parts[1] ?? '', '/')];
            // Le sous-menu de la page des services est reconstruit à
            // l'affichage depuis la collection : le déclarer vide suffit à
            // l'activer, et rien n'est à ressaisir quand un service change.
            if (rtrim($entree['url'], '/') === rtrim($this->cheminServices(), '/')) {
                $entree['sous_menu'] = [];
            }
            $menu[] = $entree;
        }
        if ($menu !== []) {
            $site['menu'] = $menu;
        }

        $this->content->save('site', $site);
        Session::flash('succes', 'Coordonnées et navigation enregistrées.');
        return $this->rediriger('/admin/site');
    }

    /** Adresse actuelle de la page des services, slug personnalisé compris. */
    private function cheminServices(): string
    {
        $seo = $GLOBALS['seo'] ?? null;

        return $seo instanceof \App\Core\Seo ? $seo->cheminSource('nos-services') : '/nos-services';
    }

    // ============================================================== accueil

    public function accueil(): string
    {
        return $this->view->render('admin/accueil', [
            'page'    => ['titre' => 'Page d’accueil'],
            'accueil' => $this->content->load('pages/accueil'),
            'medias'  => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    public function accueilEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/accueil');
        }
        $a = $this->content->load('pages/accueil');

        $a['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));

        $a['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $a['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $a['hero']['titre']));
        $a['hero']['texte']    = trim((string) ($_POST['hero_texte'] ?? ''));
        $a['hero']['image']    = $this->photoFacultative('hero_image', (string) $a['hero']['image']);

        // --- indicateurs : « valeur | unité | libellé » par ligne
        $indicateurs = [];
        foreach (self::lignes((string) ($_POST['indicateurs'] ?? '')) as $ligne) {
            $p = array_map('trim', explode('|', $ligne, 3));
            if (($p[0] ?? '') === '') {
                continue;
            }
            $indicateurs[] = ['valeur' => $p[0], 'unite' => $p[1] ?? '', 'libelle' => $p[2] ?? ''];
        }
        $a['indicateurs']['items'] = $indicateurs;

        foreach (['services', 'valeurs'] as $bloc) {
            $a[$bloc]['surtitre'] = trim((string) ($_POST[$bloc . '_surtitre'] ?? ''));
            $a[$bloc]['titre']    = trim((string) ($_POST[$bloc . '_titre'] ?? ''));
            $a[$bloc]['texte']    = trim((string) ($_POST[$bloc . '_texte'] ?? ''));
        }

        $a['societe']['surtitre']    = trim((string) ($_POST['societe_surtitre'] ?? ''));
        $a['societe']['titre']       = trim((string) ($_POST['societe_titre'] ?? ''));
        $a['societe']['paragraphes'] = self::paragraphes((string) ($_POST['societe_texte'] ?? ''));
        $a['societe']['image']       = $this->photoFacultative('societe_image', (string) $a['societe']['image']);

        $points = [];
        foreach (self::lignes((string) ($_POST['societe_points'] ?? '')) as $i => $ligne) {
            $p = array_map('trim', explode('|', $ligne, 2));
            if (($p[0] ?? '') === '') {
                continue;
            }
            $points[] = [
                'numero' => str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'titre'  => $p[0],
                'texte'  => $p[1] ?? '',
            ];
        }
        $a['societe']['points'] = $points;

        $a['serenite']['surtitre']  = trim((string) ($_POST['serenite_surtitre'] ?? ''));
        $a['serenite']['titre']     = trim((string) ($_POST['serenite_titre'] ?? ''));
        $a['serenite']['texte']     = trim((string) ($_POST['serenite_texte'] ?? ''));
        $a['serenite']['chiffre']   = trim((string) ($_POST['serenite_chiffre'] ?? ''));
        $a['serenite']['legende']   = trim((string) ($_POST['serenite_legende'] ?? ''));
        $a['serenite']['arguments'] = self::lignes((string) ($_POST['serenite_arguments'] ?? ''));
        $a['serenite']['image']     = $this->photoFacultative('serenite_image', (string) $a['serenite']['image']);

        $this->content->save('pages/accueil', $a);
        Session::flash('succes', 'Page d’accueil enregistrée.');
        return $this->rediriger('/admin/accueil');
    }

    // =========================================================== la société

    public function societe(): string
    {
        return $this->view->render('admin/societe', [
            'page'    => ['titre' => 'Page « La société »'],
            'societe' => $this->content->load('pages/la-societe'),
            'medias'  => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    public function societeEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/societe');
        }
        $c = $this->content->load('pages/la-societe');

        $c['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));

        $c['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $c['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $c['hero']['titre']));
        $c['hero']['texte']    = trim((string) ($_POST['hero_texte'] ?? ''));
        $c['hero']['image']    = $this->photoFacultative('hero_image', (string) $c['hero']['image']);

        $c['histoire']['surtitre']    = trim((string) ($_POST['histoire_surtitre'] ?? ''));
        $c['histoire']['titre']       = trim((string) ($_POST['histoire_titre'] ?? ''));
        $c['histoire']['paragraphes'] = self::paragraphes((string) ($_POST['histoire_texte'] ?? ''));

        foreach ([0, 1] as $i) {
            if (!isset($c['piliers']['items'][$i])) {
                continue;
            }
            $c['piliers']['items'][$i]['label'] = trim((string) ($_POST['pilier_label_' . $i] ?? ''));
            $c['piliers']['items'][$i]['titre'] = trim((string) ($_POST['pilier_titre_' . $i] ?? ''));
            $c['piliers']['items'][$i]['texte'] = trim((string) ($_POST['pilier_texte_' . $i] ?? ''));
            $c['piliers']['items'][$i]['image'] = $this->photoFacultative(
                'pilier_image_' . $i,
                (string) $c['piliers']['items'][$i]['image']
            );
        }

        $c['clients']['surtitre'] = trim((string) ($_POST['clients_surtitre'] ?? ''));
        $c['clients']['titre']    = trim((string) ($_POST['clients_titre'] ?? ''));
        $c['clients']['texte']    = trim((string) ($_POST['clients_texte'] ?? ''));

        foreach ($c['clients']['items'] as $i => $client) {
            $c['clients']['items'][$i]['nom']   = trim((string) ($_POST['client_nom_' . $i] ?? $client['nom']));
            $c['clients']['items'][$i]['texte'] = trim((string) ($_POST['client_texte_' . $i] ?? $client['texte']));
        }

        $c['engagement']['surtitre']    = trim((string) ($_POST['engagement_surtitre'] ?? ''));
        $c['engagement']['titre']       = trim((string) ($_POST['engagement_titre'] ?? ''));
        $c['engagement']['paragraphes'] = self::paragraphes((string) ($_POST['engagement_texte'] ?? ''));

        $this->content->save('pages/la-societe', $c);
        Session::flash('succes', 'Page « La société » enregistrée.');
        return $this->rediriger('/admin/societe');
    }

    // ============================================================= services

    public function services(): string
    {
        return $this->view->render('admin/services', [
            'page'     => ['titre' => 'Services'],
            'services' => $this->content->load('services'),
            'pageIntro' => $this->content->load('pages/nos-services'),
        ], 'admin/layout');
    }

    /**
     * @return array<string, mixed>
     */
    private static function serviceVierge(string $slug, string $nom): array
    {
        return [
            'slug'   => $slug,
            'nom'    => $nom,
            'icone'  => 'conseil',
            'actif'  => false,
            'resume' => '',
            'image'  => '',
            'hero'   => ['surtitre' => 'Nos services', 'titre' => $nom],
            'meta'   => ['description' => ''],
            'sections' => [
                ['titre' => '', 'paragraphes' => ['']],
            ],
        ];
    }

    public function serviceCreer(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/services');
        }

        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($nom === '') {
            Session::flash('erreur', 'Donnez un nom au service.');
            return $this->rediriger('/admin/services');
        }

        $services = $this->content->load('services');
        $slugs = array_column($services['items'], 'slug');
        $slug  = self::slugUnique($nom, $slugs);

        $services['items'][] = self::serviceVierge($slug, $nom);
        $this->content->save('services', $services);

        Session::flash('succes', 'Service créé. Complétez sa fiche, puis publiez-la.');
        return $this->rediriger('/admin/services/' . $slug);
    }

    public function service(string $slug): string
    {
        $item = $this->content->find('services', $slug);
        if ($item === null) {
            Session::flash('erreur', 'Service introuvable.');
            return $this->rediriger('/admin/services');
        }

        return $this->view->render('admin/service', [
            'page'   => ['titre' => $item['nom'] ?? 'Service'],
            'item'   => $item,
            'medias' => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    public function serviceEnvoi(string $slug): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/services/' . $slug);
        }

        $services = $this->content->load('services');
        $rang = $this->rangDe($services['items'], $slug);
        if ($rang === null) {
            Session::flash('erreur', 'Service introuvable.');
            return $this->rediriger('/admin/services');
        }

        $item = $services['items'][$rang];

        $item['nom']    = trim((string) ($_POST['nom'] ?? $item['nom']));
        $item['icone']  = trim((string) ($_POST['icone'] ?? $item['icone']));
        $item['resume'] = trim((string) ($_POST['resume'] ?? ''));
        $item['image']  = $this->photoFacultative('image', (string) ($item['image'] ?? ''));

        $item['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $item['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $item['nom']));
        $item['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));

        // Trois sections au maximum : au-delà, l'éditeur avancé prend le
        // relais. Un formulaire à rallonge se remplit mal.
        $sections = [];
        for ($i = 0; $i < 3; $i++) {
            $titre = trim((string) ($_POST['section_titre_' . $i] ?? ''));
            $texte = (string) ($_POST['section_texte_' . $i] ?? '');
            $liste = self::lignes((string) ($_POST['section_liste_' . $i] ?? ''));
            $paragraphes = self::paragraphes($texte);

            if ($titre === '' && $paragraphes === [] && $liste === []) {
                continue;
            }

            $section = ['titre' => $titre];
            if ($paragraphes !== []) {
                $section['paragraphes'] = $paragraphes;
            }
            if ($liste !== []) {
                $section['liste'] = $liste;
            }
            $sections[] = $section;
        }
        $item['sections'] = $sections;

        $services['items'][$rang] = $item;
        $this->content->save('services', $services);

        Session::flash('succes', 'Service enregistré.');
        return $this->rediriger('/admin/services/' . $slug);
    }

    public function servicePublication(string $slug): string
    {
        return $this->basculerFiche('services', $slug, '/admin/services');
    }

    public function serviceOrdre(string $slug): string
    {
        return $this->deplacerFiche('services', $slug, '/admin/services');
    }

    public function serviceSupprimer(string $slug): string
    {
        return $this->supprimerFiche('services', $slug, '/admin/services');
    }

    public function servicesIntroEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/services');
        }

        $p = $this->content->load('pages/nos-services');
        $p['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));
        $p['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $p['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $p['hero']['titre']));
        $p['hero']['texte']    = trim((string) ($_POST['hero_texte'] ?? ''));

        $this->content->save('pages/nos-services', $p);
        Session::flash('succes', 'En-tête de la page « Nos services » enregistré.');
        return $this->rediriger('/admin/services');
    }

    // ============================================================== valeurs

    public function valeurs(): string
    {
        return $this->view->render('admin/valeurs', [
            'page'      => ['titre' => 'Valeurs'],
            'valeurs'   => $this->content->load('valeurs'),
            'pageIntro' => $this->content->load('pages/nos-valeurs'),
            'medias'    => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    public function valeurCreer(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/valeurs');
        }

        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($nom === '') {
            Session::flash('erreur', 'Donnez un nom à la valeur.');
            return $this->rediriger('/admin/valeurs');
        }

        $valeurs = $this->content->load('valeurs');
        $slug = self::slugUnique($nom, array_column($valeurs['items'], 'slug'));

        $valeurs['items'][] = [
            'slug' => $slug, 'nom' => $nom, 'actif' => false,
            'resume' => '', 'image' => '', 'paragraphes' => [''],
        ];
        $this->content->save('valeurs', $valeurs);

        Session::flash('succes', 'Valeur créée. Complétez son texte, puis publiez-la.');
        return $this->rediriger('/admin/valeurs');
    }

    public function valeursEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/valeurs');
        }

        $valeurs = $this->content->load('valeurs');

        foreach ($valeurs['items'] as $i => $valeur) {
            $slug = (string) $valeur['slug'];
            $valeurs['items'][$i]['nom'] = trim((string) ($_POST['nom_' . $slug] ?? $valeur['nom']));
            $valeurs['items'][$i]['resume'] = trim((string) ($_POST['resume_' . $slug] ?? ''));
            $valeurs['items'][$i]['paragraphes'] = self::paragraphes((string) ($_POST['texte_' . $slug] ?? ''));
            $valeurs['items'][$i]['image'] = $this->photoFacultative(
                'image_' . $slug,
                (string) ($valeur['image'] ?? '')
            );
        }

        $this->content->save('valeurs', $valeurs);
        Session::flash('succes', 'Valeurs enregistrées.');
        return $this->rediriger('/admin/valeurs');
    }

    public function valeurPublication(string $slug): string
    {
        return $this->basculerFiche('valeurs', $slug, '/admin/valeurs');
    }

    public function valeurOrdre(string $slug): string
    {
        return $this->deplacerFiche('valeurs', $slug, '/admin/valeurs');
    }

    public function valeurSupprimer(string $slug): string
    {
        return $this->supprimerFiche('valeurs', $slug, '/admin/valeurs');
    }

    public function valeursIntroEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/valeurs');
        }

        $p = $this->content->load('pages/nos-valeurs');
        $p['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));
        $p['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $p['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $p['hero']['titre']));
        $p['hero']['texte']    = trim((string) ($_POST['hero_texte'] ?? ''));

        $this->content->save('pages/nos-valeurs', $p);
        Session::flash('succes', 'En-tête de la page « Nos valeurs » enregistré.');
        return $this->rediriger('/admin/valeurs');
    }

    // ============================================================== contact

    public function contact(): string
    {
        return $this->view->render('admin/contact', [
            'page'    => ['titre' => 'Page « Contact »'],
            'contact' => $this->content->load('pages/contact'),
            'medias'  => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    public function contactEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/contact');
        }
        $c = $this->content->load('pages/contact');

        $c['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));

        $c['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $c['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $c['hero']['titre']));
        $c['hero']['texte']    = trim((string) ($_POST['hero_texte'] ?? ''));
        $c['hero']['image']    = $this->photoFacultative('hero_image', (string) $c['hero']['image']);

        $c['introduction']['titre'] = trim((string) ($_POST['intro_titre'] ?? ''));
        $c['introduction']['texte'] = trim((string) ($_POST['intro_texte'] ?? ''));

        $c['adresse']['titre']      = trim((string) ($_POST['adresse_titre'] ?? ''));
        $c['adresse']['complement'] = trim((string) ($_POST['adresse_complement'] ?? ''));
        $c['adresse']['carte']['libelle'] = trim((string) ($_POST['carte_libelle'] ?? ''));
        $carteUrl = trim((string) ($_POST['carte_url'] ?? ''));
        $c['adresse']['carte']['url'] = filter_var($carteUrl, FILTER_VALIDATE_URL) ? $carteUrl : '';

        $c['formulaire']['titre']   = trim((string) ($_POST['form_titre'] ?? ''));
        $c['formulaire']['texte']   = trim((string) ($_POST['form_texte'] ?? ''));
        $c['formulaire']['mention'] = trim((string) ($_POST['form_mention'] ?? ''));
        $objets = self::lignes((string) ($_POST['form_objets'] ?? ''));
        if ($objets !== []) {
            $c['formulaire']['objets'] = $objets;
        }

        $c['faq']['surtitre'] = trim((string) ($_POST['faq_surtitre'] ?? ''));
        $c['faq']['titre']    = trim((string) ($_POST['faq_titre'] ?? ''));
        $c['faq']['relance']['titre'] = trim((string) ($_POST['faq_relance_titre'] ?? ''));
        $c['faq']['relance']['texte'] = trim((string) ($_POST['faq_relance_texte'] ?? ''));

        // Questions : une par bloc « question || réponse », séparés par une
        // ligne vide. Deux barres verticales, pour qu'une question contenant
        // un « | » ne soit pas coupée en deux.
        $faq = [];
        foreach (preg_split('/\R{2,}/', trim((string) ($_POST['faq_items'] ?? ''))) ?: [] as $bloc) {
            $p = array_map('trim', explode('||', $bloc, 2));
            if (($p[0] ?? '') === '' || ($p[1] ?? '') === '') {
                continue;
            }
            $faq[] = [
                'question' => preg_replace('/\s+/', ' ', $p[0]) ?? '',
                'reponse'  => preg_replace('/\s+/', ' ', $p[1]) ?? '',
            ];
        }
        $c['faq']['items'] = $faq;

        $this->content->save('pages/contact', $c);
        Session::flash('succes', 'Page « Contact » enregistrée.');
        return $this->rediriger('/admin/contact');
    }

    // ==================================================== opérations de liste

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function rangDe(array $items, string $slug): ?int
    {
        foreach ($items as $rang => $item) {
            if (($item['slug'] ?? '') === $slug) {
                return $rang;
            }
        }
        return null;
    }

    private function basculerFiche(string $collection, string $slug, string $retour): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger($retour);
        }

        $donnees = $this->content->load($collection);
        $rang = $this->rangDe($donnees['items'], $slug);
        if ($rang === null) {
            Session::flash('erreur', 'Fiche introuvable.');
            return $this->rediriger($retour);
        }

        [$items, $enLigne] = Liste::basculer($donnees['items'], $rang);
        $donnees['items'] = $items;
        $this->content->save($collection, $donnees);

        Session::flash('succes', $enLigne ? 'Fiche publiée.' : 'Fiche retirée du site.');
        return $this->rediriger($retour);
    }

    private function deplacerFiche(string $collection, string $slug, string $retour): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger($retour);
        }

        $donnees = $this->content->load($collection);
        $rang = $this->rangDe($donnees['items'], $slug);
        if ($rang === null) {
            return $this->rediriger($retour);
        }

        $sens = ($_POST['sens'] ?? 'bas') === 'haut' ? -1 : 1;
        $donnees['items'] = Liste::deplacer($donnees['items'], $rang, $sens);
        $this->content->save($collection, $donnees);

        return $this->rediriger($retour);
    }

    private function supprimerFiche(string $collection, string $slug, string $retour): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger($retour);
        }

        $donnees = $this->content->load($collection);
        $rang = $this->rangDe($donnees['items'], $slug);
        if ($rang === null) {
            Session::flash('erreur', 'Fiche introuvable.');
            return $this->rediriger($retour);
        }

        // Une suppression accidentelle reste rattrapable : l'enregistrement
        // conserve la version précédente, restaurable depuis l'éditeur avancé.
        $nom = (string) ($donnees['items'][$rang]['nom'] ?? $slug);
        $donnees['items'] = Liste::retirer($donnees['items'], $rang);
        $this->content->save($collection, $donnees);

        Session::flash('succes', '« ' . $nom . ' » supprimé. '
            . 'La version précédente reste restaurable depuis l’Éditeur avancé.');
        return $this->rediriger($retour);
    }

    // ====================================================== éditeur avancé

    public function avance(?string $nom = null): string
    {
        $nom = $nom !== null && isset(self::CONTENUS[$nom]) ? $nom : 'site';

        return $this->view->render('admin/avance', [
            'page'        => ['titre' => 'Éditeur avancé'],
            'contenus'    => self::CONTENUS,
            'nom'         => $nom,
            'json'        => json_encode(
                $this->content->load($nom),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'sauvegardes' => $this->content->sauvegardes($nom),
        ], 'admin/layout');
    }

    public function avanceEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/avance');
        }

        $nom = (string) ($_POST['nom'] ?? '');
        if (!isset(self::CONTENUS[$nom])) {
            Session::flash('erreur', 'Contenu inconnu.');
            return $this->rediriger('/admin/avance');
        }

        $retour = '/admin/avance?nom=' . urlencode($nom);

        $restauration = trim((string) ($_POST['restaurer'] ?? ''));
        if ($restauration !== '') {
            try {
                $this->content->restaurer($nom, $restauration);
                Session::flash('succes', 'Version restaurée.');
            } catch (RuntimeException $e) {
                Session::flash('erreur', $e->getMessage());
            }
            return $this->rediriger($retour);
        }

        $donnees = json_decode((string) ($_POST['json'] ?? ''), true);
        if (!is_array($donnees)) {
            Session::flash('erreur', 'JSON invalide : ' . json_last_error_msg()
                . '. Rien n’a été enregistré.');
            return $this->rediriger($retour);
        }

        $this->content->save($nom, $donnees);
        Session::flash('succes', 'Contenu enregistré.');
        return $this->rediriger($retour);
    }
}
