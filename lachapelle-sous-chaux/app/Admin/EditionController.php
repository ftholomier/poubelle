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
    /**
     * Repères chiffrés saisis en clair : « valeur|unité|libellé » par ligne.
     *
     * @return array<int, array{valeur: string, unite: string, libelle: string}>
     */
    private static function reperes(string $brut): array
    {
        $items = [];
        foreach (preg_split('/\r?\n/', trim($brut)) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '') {
                continue;
            }
            // une ligne sans séparateur reste exploitable : elle vaut libellé
            $parts = array_map('trim', explode('|', $ligne));
            $items[] = [
                'valeur'  => $parts[0] ?? '',
                'unite'   => $parts[1] ?? '',
                'libelle' => $parts[2] ?? '',
            ];
        }

        return $items;
    }

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
        // Le menu se saisit sur deux niveaux : une sous-entrée est décalée
        // de deux espaces. On lit donc les lignes brutes, sans les élaguer,
        // car c'est l'indentation qui porte le rattachement.
        $menu = [];
        foreach (preg_split('/\R/', (string) ($_POST['menu'] ?? '')) ?: [] as $brute) {
            if (trim($brute) === '') {
                continue;
            }
            $sousEntree = $menu !== [] && preg_match('/^[ \t]/', $brute) === 1;
            $parts = array_map('trim', explode('|', trim($brute), 2));
            if (($parts[0] ?? '') === '') {
                continue;
            }
            $entree = ['libelle' => $parts[0], 'url' => '/' . ltrim($parts[1] ?? '', '/')];

            if ($sousEntree) {
                $menu[count($menu) - 1]['sous_menu'][] = $entree;
                continue;
            }
            $menu[] = $entree;
        }

        // Une entrée qui pointe vers une collection et n'a pas de sous-entrée
        // saisie voit la sienne reconstruite depuis les fiches publiées ; une
        // entrée qui en a garde les siennes, et le drapeau le dit. Douze
        // démarches ne tiennent pas dans un menu déroulant.
        foreach ($menu as $rang => $entree) {
            if (!in_array(rtrim($entree['url'], '/'), $this->basesDeCollection(), true)) {
                continue;
            }
            if (($entree['sous_menu'] ?? []) === []) {
                $menu[$rang]['sous_menu'] = [];
            } else {
                $menu[$rang]['auto'] = false;
            }
        }

        if ($menu !== []) {
            $site['menu'] = $menu;
        }

        $this->content->save('site', $site);
        Session::flash('succes', 'Coordonnées et navigation enregistrées.');
        return $this->rediriger('/admin/site');
    }

    /**
     * Adresses de base des collections, slugs personnalisés compris.
     *
     * @return string[]
     */
    private function basesDeCollection(): array
    {
        $seo = $GLOBALS['seo'] ?? null;
        if (!$seo instanceof \App\Core\Seo) {
            return ['/demarches', '/actualites'];
        }

        return array_map(
            static fn(string $chemin): string => rtrim($chemin, '/'),
            array_keys($seo->basesCollections())
        );
    }

    /**
     * Diaporama du bandeau, reconstruit depuis le formulaire.
     *
     * L'ordre vient de celui des champs envoyés : déplacer une ligne dans la
     * page suffit donc à la réordonner, sans numéro de rang à tenir à jour.
     * L'état voyage dans un champ caché piloté par l'interrupteur, et non
     * dans une case à cocher — une case décochée n'est pas envoyée, ce qui
     * décalerait les images et leurs états l'un par rapport à l'autre.
     *
     * @return array{pause: int, aleatoire: bool, vues: array<int, array{image: string, actif: bool}>}
     */
    private function diaporama(): array
    {
        $images = (array) ($_POST['diapo_image'] ?? []);
        $etats  = (array) ($_POST['diapo_etat'] ?? []);

        // une vue retirée a quitté la page : elle n'est simplement plus envoyée
        $vues = [];
        $vus  = [];
        foreach ($images as $rang => $image) {
            $image = (string) $image;
            if ($image === '' || isset($vus[$image])) {
                continue;
            }
            $vus[$image] = true;
            $vues[] = ['image' => $image, 'actif' => ((string) ($etats[$rang] ?? '1')) === '1'];
        }

        foreach ((array) ($_POST['diapo_ajout'] ?? []) as $ajout) {
            $ajout = (string) $ajout;
            if ($ajout === '' || isset($vus[$ajout])) {
                continue;
            }
            $vus[$ajout] = true;
            $vues[] = ['image' => $ajout, 'actif' => true];
        }

        return [
            'pause'     => min(30, max(2, (int) ($_POST['diapo_pause'] ?? 6))),
            // L'ordre saisi est conservé même quand le tirage est actif : le
            // décocher doit rendre la suite que la mairie avait rangée.
            'aleatoire' => isset($_POST['diapo_aleatoire']),
            'vues'      => $vues,
        ];
    }

    // ============================================================== accueil

    public function accueil(): string
    {
        $accueil = $this->content->load('pages/accueil');

        return $this->view->render('admin/accueil', [
            'page'    => ['titre' => 'Page d’accueil'],
            'accueil' => $accueil,
            'diapos'  => $accueil['hero']['diaporama']['vues'] ?? [],
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
        $a['hero']['voile']     = min(100, max(0, (int) ($_POST['hero_voile'] ?? 100)));
        $a['hero']['diaporama'] = $this->diaporama();

        // --- bandeau pratique : « picto | libellé | valeur | précision | lien »
        // Les quatre repères posés sous le bandeau : horaires, téléphone,
        // permanence, adresse. C'est ce qu'on cherche en haut d'un site de
        // mairie, avant même les démarches.
        $pratique = [];
        foreach (self::lignes((string) ($_POST['pratique'] ?? '')) as $ligne) {
            $p = array_map('trim', explode('|', $ligne, 5));
            if (($p[1] ?? '') === '') {
                continue;
            }
            $item = ['icone' => $p[0] ?: 'horaires', 'libelle' => $p[1], 'valeur' => $p[2] ?? ''];
            if (($p[3] ?? '') !== '') {
                $item['precision'] = $p[3];
            }
            if (($p[4] ?? '') !== '') {
                $item['lien'] = $p[4];
            }
            $pratique[] = $item;
        }
        $a['pratique'] = $pratique;

        // --- chiffres clés : « valeur | unité | libellé » par ligne
        $indicateurs = [];
        foreach (self::lignes((string) ($_POST['indicateurs'] ?? '')) as $ligne) {
            $p = array_map('trim', explode('|', $ligne, 3));
            if (($p[0] ?? '') === '') {
                continue;
            }
            $indicateurs[] = ['valeur' => $p[0], 'unite' => $p[1] ?? '', 'libelle' => $p[2] ?? ''];
        }
        $a['indicateurs']['titre'] = trim((string) ($_POST['indicateurs_titre'] ?? ''));
        $a['indicateurs']['items'] = $indicateurs;

        // --- les trois blocs à sur-titre, titre et chapô
        foreach (['demarches', 'vie', 'rubriques'] as $bloc) {
            $a[$bloc]['surtitre'] = trim((string) ($_POST[$bloc . '_surtitre'] ?? ''));
            $a[$bloc]['titre']    = trim((string) ($_POST[$bloc . '_titre'] ?? ''));
            $a[$bloc]['texte']    = trim((string) ($_POST[$bloc . '_texte'] ?? ''));
        }

        // --- le bloc « le village » : photo, texte et points numérotés
        $a['village']['surtitre']    = trim((string) ($_POST['village_surtitre'] ?? ''));
        $a['village']['titre']       = trim((string) ($_POST['village_titre'] ?? ''));
        $a['village']['paragraphes'] = self::paragraphes((string) ($_POST['village_texte'] ?? ''));
        $a['village']['image']       = $this->photoFacultative('village_image', (string) ($a['village']['image'] ?? ''));
        $a['village']['image_alt']   = trim((string) ($_POST['village_alt'] ?? ''));

        $points = [];
        foreach (self::lignes((string) ($_POST['village_points'] ?? '')) as $i => $ligne) {
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
        $a['village']['points'] = $points;

        // --- les six cartes de rubrique : « picto | titre | texte | adresse »
        $rubriques = [];
        foreach (self::lignes((string) ($_POST['rubriques_items'] ?? '')) as $ligne) {
            $p = array_map('trim', explode('|', $ligne, 4));
            if (($p[1] ?? '') === '') {
                continue;
            }
            $rubriques[] = [
                'icone' => $p[0] ?: 'document',
                'titre' => $p[1],
                'texte' => $p[2] ?? '',
                'lien'  => ['libelle' => 'Voir la rubrique', 'url' => $p[3] ?? '/'],
            ];
        }
        $a['rubriques']['items'] = $rubriques;

        $this->content->save('pages/accueil', $a);
        Session::flash('succes', 'Page d’accueil enregistrée.');
        return $this->rediriger('/admin/accueil');
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

        foreach (array_keys((array) ($c['implantations'] ?? [])) as $i) {
            $c['implantations'][$i]['nom']   = trim((string) ($_POST['imp_nom_' . $i] ?? ''));
            $c['implantations'][$i]['role']  = trim((string) ($_POST['imp_role_' . $i] ?? ''));
            $c['implantations'][$i]['rue']   = trim((string) ($_POST['imp_rue_' . $i] ?? ''));
            $c['implantations'][$i]['cp']    = trim((string) ($_POST['imp_cp_' . $i] ?? ''));
            $c['implantations'][$i]['ville'] = trim((string) ($_POST['imp_ville_' . $i] ?? ''));
            // Une adresse invalide est écartée plutôt que recopiée : elle
            // finirait dans un src d'iframe, donc dans une requête sortante.
            foreach (['embed', 'lien'] as $champ) {
                $url = trim((string) ($_POST['imp_' . $champ . '_' . $i] ?? ''));
                $c['implantations'][$i]['carte'][$champ] =
                    filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
            }
        }

        $c['formulaire']['titre']   = trim((string) ($_POST['form_titre'] ?? ''));
        $c['formulaire']['texte']   = trim((string) ($_POST['form_texte'] ?? ''));
        $c['formulaire']['mention'] = trim((string) ($_POST['form_mention'] ?? ''));

        $this->content->save('pages/contact', $c);
        Session::flash('succes', 'Page « Contact » enregistrée.');
        return $this->rediriger('/admin/contact');
    }

    // ==================================================== demande en ligne

    public function demande(): string
    {
        return $this->view->render('admin/demande', [
            'page'    => ['titre' => 'Page « Écrire à la mairie »'],
            'demande' => $this->content->load('pages/demande'),
            'medias'  => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    public function demandeEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/demande');
        }
        $d = $this->content->load('pages/demande');

        $d['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));

        $d['hero']['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $d['hero']['titre']    = trim((string) ($_POST['hero_titre'] ?? $d['hero']['titre']));
        $d['hero']['texte']    = trim((string) ($_POST['hero_texte'] ?? ''));
        $d['hero']['image']    = $this->photoFacultative('hero_image', (string) ($d['hero']['image'] ?? ''));

        $d['introduction']['titre'] = trim((string) ($_POST['intro_titre'] ?? ''));
        $d['introduction']['texte'] = trim((string) ($_POST['intro_texte'] ?? ''));

        // Les étapes « et ensuite, il se passe quoi ? » : une par bloc
        // « titre || texte », séparés par une ligne vide.
        $etapes = [];
        foreach (preg_split('/\R{2,}/', trim((string) ($_POST['etapes'] ?? ''))) ?: [] as $bloc) {
            $p = array_map('trim', explode('||', $bloc, 2));
            if (($p[0] ?? '') === '') {
                continue;
            }
            $etapes[] = [
                'numero' => str_pad((string) (count($etapes) + 1), 2, '0', STR_PAD_LEFT),
                'titre'  => preg_replace('/\s+/', ' ', $p[0]) ?? '',
                'texte'  => preg_replace('/\s+/', ' ', $p[1] ?? '') ?? '',
            ];
        }
        $d['etapes'] = $etapes;

        // L'objet est une liste fermée : c'est lui qui décide du service qui
        // traitera la demande, et une formulation libre oblige le secrétariat
        // à deviner.
        $sujets = self::lignes((string) ($_POST['sujets'] ?? ''));
        if ($sujets !== []) {
            $d['sujets'] = $sujets;
        }

        $d['formulaire']['titre']   = trim((string) ($_POST['form_titre'] ?? ''));
        $d['formulaire']['texte']   = trim((string) ($_POST['form_texte'] ?? ''));
        $d['formulaire']['mention'] = trim((string) ($_POST['form_mention'] ?? ''));

        $this->content->save('pages/demande', $d);
        Session::flash('succes', 'Page « Écrire à la mairie » enregistrée.');
        return $this->rediriger('/admin/demande');
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
        $contenus = Contenus::tout();
        $nom = $nom !== null && isset($contenus[$nom]) ? $nom : 'site';

        return $this->view->render('admin/avance', [
            'page'        => ['titre' => 'Éditeur avancé'],
            'contenus'    => Contenus::tout(),
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
        if (!isset(Contenus::tout()[$nom])) {
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
