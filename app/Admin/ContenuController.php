<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Liste;
use App\Core\Mediatheque;
use App\Core\Seo;
use App\Core\Session;
use App\Core\View;
use RuntimeException;

/**
 * Édition du contenu : les pages à blocs, les collections à fiches et les
 * listes simples.
 *
 * Un écran par page reviendrait à en écrire vingt-huit, et à en écrire un
 * vingt-neuvième le jour où la mairie ajoute une rubrique. Trois écrans
 * génériques suffisent, parce que le contenu du site n'a que trois formes :
 *
 *   · une page  = un bandeau et une suite de blocs (Blocs décrit les blocs) ;
 *   · une collection = des fiches adressables, chacune faite de blocs ;
 *   · une liste = des entrées répétées sans page propre — élus, associations,
 *     numéros utiles, documents, agenda.
 *
 * Ce qui distingue une collection d'une liste : la première a des adresses
 * publiques et donc des slugs, la seconde n'en a pas.
 */
final class ContenuController
{
    /**
     * Les pages éditables, groupées comme le menu du site.
     *
     * La table de Seo dit les slugs ; celle-ci dit l'ordre dans lequel on les
     * présente à la mairie, qui n'est pas l'ordre alphabétique.
     *
     * @var array<string, string[]>
     */
    public const GROUPES = [
        'La mairie'      => ['la-mairie', 'conseil-municipal', 'commissions',
                             'comptes-rendus', 'deliberations', 'budget',
                             'publications', 'urbanisme'],
        'Démarches'      => ['demarches', 'demarches-en-ligne', 'services-etat', 'ccas'],
        'Le village'     => ['le-village', 'histoire', 'salle-camille',
                             'bois-et-forets', 'associations', 'album-photos'],
        'La vie du village' => ['actualites', 'agenda', 'info-a-la-une'],
        'Au quotidien'   => ['au-quotidien', 'dechets', 'vie-scolaire',
                             'intercommunalite', 'liens-utiles', 'numeros-utiles'],
        'Pages de service' => ['mentions-legales', 'confidentialite',
                               'accessibilite', 'plan-du-site'],
    ];

    /**
     * Les collections à fiches : clé publique => intitulés d'écran.
     *
     * @var array<string, array{nom: string, singulier: string, cleSeo: string, titre: string}>
     */
    public const COLLECTIONS = [
        'demarches'  => ['nom' => 'Démarches', 'singulier' => 'démarche',
                         'cleSeo' => 'demarches', 'titre' => 'nom'],
        'actualites' => ['nom' => 'Actualités', 'singulier' => 'actualité',
                         'cleSeo' => 'actualites', 'titre' => 'titre'],
    ];

    /**
     * Les listes simples : fichier => description de ses entrées.
     *
     * `champs` décrit une entrée exactement comme Blocs décrit un bloc, avec
     * les mêmes natures de champ. `sous` nomme la seconde liste du fichier
     * quand il en porte deux (les comités, à côté des commissions).
     *
     * @var array<string, array<string, mixed>>
     */
    public const LISTES = [
        'agenda' => [
            'nom' => 'Agenda', 'singulier' => 'rendez-vous',
            'aide' => 'Les manifestations à venir. Un rendez-vous passe dans « c’est passé » le lendemain de sa date, tout seul.',
            'champs' => [
                'titre' => 'ligne', 'date' => 'date', 'fin' => 'date',
                'heure' => 'ligne', 'lieu' => 'ligne', 'organisateur' => 'ligne',
                'texte' => 'zone',
            ],
        ],
        'documents' => [
            'nom' => 'Documents', 'singulier' => 'document',
            'aide' => 'Les PDF publiés : comptes-rendus, bulletins, documents intercommunaux. La famille décide de la page où le document apparaît.',
            'champs' => [
                'titre' => 'ligne', 'date' => 'date',
                'famille' => 'choix:comptes-rendus|budgets|flash-info|publications',
                'fichier' => 'fichier', 'texte' => 'zone',
            ],
        ],
        'associations' => [
            'nom' => 'Associations', 'singulier' => 'association',
            'aide' => 'Une fiche par association du village, avec ses contacts tels qu’elle les donne.',
            'champs' => [
                'nom' => 'ligne', 'objet' => 'ligne', 'icone' => 'icone',
                'rendez_vous' => 'ligne', 'paragraphes' => 'riche',
                'contacts' => 'items:contacts-assos',
            ],
        ],
        'services-etat' => [
            'nom' => 'Services de l’État', 'singulier' => 'service',
            'aide' => 'Préfecture, DDT, ARS… Ce dont chacun s’occupe, pour éviter un déplacement inutile.',
            'champs' => [
                'nom' => 'ligne', 'sigle' => 'ligne', 'texte' => 'zone',
                'missions' => 'lignes', 'adresse' => 'zone',
                'tel' => 'ligne', 'email' => 'ligne', 'site' => 'ligne',
            ],
        ],
        'numeros' => [
            'nom' => 'Numéros utiles', 'singulier' => 'rubrique',
            'aide' => 'Une rubrique par famille de numéros. Cocher « urgence » compose la rubrique en grand sur fond sombre.',
            'champs' => [
                'nom' => 'ligne', 'texte' => 'zone', 'urgence' => 'case',
                'numeros' => 'items:numeros',
            ],
        ],
        'commissions' => [
            'nom' => 'Commissions & comités', 'singulier' => 'commission',
            'aide' => 'Les commissions communales, puis les structures où la commune siège.',
            'sous' => ['comites' => 'Comités et syndicats'],
            'champs' => [
                'nom' => 'ligne', 'role' => 'zone', 'icone' => 'icone',
                'membres' => 'lignes', 'colonnes' => 'items:colonnes',
            ],
        ],
    ];

    /** Sous-listes propres aux listes simples. */
    public const SOUS_LISTES = [
        'contacts-assos' => ['nom' => 'ligne', 'tel' => 'ligne', 'email' => 'ligne'],
        'numeros'        => ['numero' => 'ligne', 'libelle' => 'ligne',
                             'texte' => 'zone', 'adresse' => 'zone', 'site' => 'ligne'],
        'colonnes'       => ['titre' => 'ligne', 'membres' => 'lignes'],
    ];

    public function __construct(
        private readonly View $view,
        private readonly Content $content,
        private readonly Mediatheque $mediatheque,
        private readonly Seo $seo,
    ) {
    }

    // ============================================================== pages

    public function pages(): string
    {
        $pages = [];
        foreach (self::GROUPES as $groupe => $cles) {
            foreach ($cles as $cle) {
                $pages[$groupe][$cle] = [
                    'nom'     => Seo::PAGES[$cle]['nom'] ?? $cle,
                    'chemin'  => $this->seo->cheminSource($cle),
                    'blocs'   => count($this->contenu($cle)['sections'] ?? []),
                ];
            }
        }

        return $this->view->render('admin/pages', [
            'page'   => ['titre' => 'Pages du site'],
            'pages'  => $pages,
        ], 'admin/layout');
    }

    public function page(string $cle): string
    {
        if (!isset(Seo::PAGES[$cle])) {
            Session::flash('erreur', 'Page inconnue.');
            return $this->rediriger('/admin/pages');
        }

        return $this->view->render('admin/page', [
            'page'    => ['titre' => Seo::PAGES[$cle]['nom']],
            'cle'     => $cle,
            'contenu' => $this->contenu($cle),
            'chemin'  => $this->seo->chemin($cle),
            'medias'  => $this->mediatheque->lister(),
            'documents' => self::documentsDisponibles(),
        ], 'admin/layout');
    }

    public function pageEnvoi(string $cle): string
    {
        if (!Csrf::verifier() || !isset(Seo::PAGES[$cle])) {
            return $this->rediriger('/admin/pages');
        }

        $this->content->save('pages/' . $cle, $this->pageSaisie($cle));
        Session::flash('succes', 'Page « ' . (Seo::PAGES[$cle]['nom']) . ' » enregistrée.');
        return $this->rediriger('/admin/pages/' . $cle);
    }

    /**
     * Ajoute un bloc vide en fin de page, puis rouvre l'écran dessus.
     *
     * L'ajout passe par un enregistrement plutôt que par du JavaScript : le
     * formulaire est déjà rempli quand on clique, et le perdre pour ajouter un
     * bloc serait le meilleur moyen de faire détester l'écran.
     */
    public function pageBloc(string $cle): string
    {
        if (!Csrf::verifier() || !isset(Seo::PAGES[$cle])) {
            return $this->rediriger('/admin/pages');
        }

        $type = (string) ($_POST['type'] ?? 'texte');
        if (!isset(Blocs::TYPES[$type])) {
            $type = 'texte';
        }

        // La page est enregistrée telle qu'elle est saisie, puis le bloc vide
        // s'y ajoute : cliquer « ajouter » ne doit rien faire perdre de ce qui
        // vient d'être tapé, formulaire d'ajout distinct ou non.
        $contenu = $this->pageSaisie($cle);
        $contenu['sections'][] = ['type' => $type, 'titre' => ''];
        $this->content->save('pages/' . $cle, $contenu);

        Session::flash('succes', 'Bloc « ' . Blocs::TYPES[$type]['nom'] . ' » ajouté en fin de page.');
        return $this->rediriger('/admin/pages/' . $cle . '#bloc-' . (count($contenu['sections']) - 1));
    }

    /**
     * La page telle que le formulaire vient de la décrire.
     *
     * Partagée par l'enregistrement et par l'ajout de bloc : les deux
     * reçoivent le même formulaire, et devraient sinon lire les mêmes champs
     * à deux endroits — l'un des deux finirait par en oublier un.
     *
     * @return array<string, mixed>
     */
    private function pageSaisie(string $cle): array
    {
        $contenu = $this->contenu($cle);
        $contenu['titre'] = trim((string) ($_POST['titre'] ?? ($contenu['titre'] ?? '')));
        $contenu['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));
        $contenu['sous_titre'] = trim((string) ($_POST['sous_titre'] ?? ''));

        $hero = $contenu['hero'] ?? [];
        $hero['surtitre'] = trim((string) ($_POST['hero_surtitre'] ?? ''));
        $hero['titre']    = trim((string) ($_POST['hero_titre'] ?? ''));
        $hero['texte']    = trim((string) ($_POST['hero_texte'] ?? ''));
        $hero['image']    = trim((string) ($_POST['hero_image'] ?? ($hero['image'] ?? '')));
        $contenu['hero']  = $hero;

        $contenu['sections'] = self::relireBlocs((array) ($_POST['bloc'] ?? []));

        return $contenu;
    }

    // ========================================================= collections

    public function collection(string $nom): string
    {
        if (!isset(self::COLLECTIONS[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        return $this->view->render('admin/collection', [
            'page'       => ['titre' => self::COLLECTIONS[$nom]['nom']],
            'collection' => $nom,
            'reglages'   => self::COLLECTIONS[$nom],
            'donnees'    => $this->content->load($nom),
        ], 'admin/layout');
    }

    public function ficheCreer(string $nom): string
    {
        if (!Csrf::verifier() || !isset(self::COLLECTIONS[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        $intitule = trim((string) ($_POST['nom'] ?? ''));
        if ($intitule === '') {
            Session::flash('erreur', 'Donnez un titre à la fiche.');
            return $this->rediriger('/admin/' . $nom);
        }

        $donnees = $this->content->load($nom);
        $slug = self::slugUnique($intitule, array_column($donnees['items'] ?? [], 'slug'));

        $fiche = [
            'slug'  => $slug,
            'actif' => false,
            self::COLLECTIONS[$nom]['titre'] => $intitule,
            'resume' => '',
            'image'  => '',
            'meta'   => ['description' => ''],
            'sections' => [],
        ];
        if ($nom === 'demarches') {
            $fiche['famille'] = 'etat-civil';
            $fiche['icone']   = 'document';
        } else {
            $fiche['date'] = date('Y-m-d');
        }

        $donnees['items'][] = $fiche;
        $this->content->save($nom, $donnees);

        Session::flash('succes', 'Fiche créée. Complétez-la, puis publiez-la.');
        return $this->rediriger('/admin/' . $nom . '/' . $slug);
    }

    public function fiche(string $nom, string $slug): string
    {
        $item = isset(self::COLLECTIONS[$nom]) ? $this->content->find($nom, $slug) : null;
        if ($item === null) {
            Session::flash('erreur', 'Fiche introuvable.');
            return $this->rediriger('/admin/' . $nom);
        }

        return $this->view->render('admin/fiche', [
            'page'       => ['titre' => (string) ($item['nom'] ?? $item['titre'] ?? 'Fiche')],
            'collection' => $nom,
            'reglages'   => self::COLLECTIONS[$nom],
            'item'       => $item,
            'medias'     => $this->mediatheque->lister(),
            'documents'  => self::documentsDisponibles(),
        ], 'admin/layout');
    }

    public function ficheEnvoi(string $nom, string $slug): string
    {
        if (!Csrf::verifier() || !isset(self::COLLECTIONS[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        $donnees = $this->content->load($nom);
        $rang = self::rangDe($donnees['items'] ?? [], $slug);
        if ($rang === null) {
            Session::flash('erreur', 'Fiche introuvable.');
            return $this->rediriger('/admin/' . $nom);
        }

        $donnees['items'][$rang] = $this->ficheSaisie($nom, $donnees['items'][$rang]);
        $this->content->save($nom, $donnees);

        Session::flash('succes', 'Fiche enregistrée.');
        return $this->rediriger('/admin/' . $nom . '/' . $slug);
    }

    /**
     * La fiche telle que le formulaire vient de la décrire.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function ficheSaisie(string $nom, array $item): array
    {
        $cleTitre = self::COLLECTIONS[$nom]['titre'];

        $item[$cleTitre] = trim((string) ($_POST['intitule'] ?? $item[$cleTitre]));
        $item['resume']  = trim((string) ($_POST['resume'] ?? ''));
        $item['image']   = trim((string) ($_POST['image'] ?? ''));
        $item['image_alt'] = trim((string) ($_POST['image_alt'] ?? ''));
        $item['meta']['description'] = trim((string) ($_POST['meta_description'] ?? ''));

        if ($nom === 'demarches') {
            $item['famille']  = trim((string) ($_POST['famille'] ?? 'etat-civil'));
            $item['icone']    = trim((string) ($_POST['icone'] ?? 'document'));
            $item['guichet']  = trim((string) ($_POST['guichet'] ?? ''));
            $item['delai']    = trim((string) ($_POST['delai'] ?? ''));
            $item['cout']     = trim((string) ($_POST['cout'] ?? ''));
            $item['validite'] = trim((string) ($_POST['validite'] ?? ''));
            $item['pieces']   = self::lignes((string) ($_POST['pieces'] ?? ''));
            $item['liens']    = self::liensSaisis((array) ($_POST['liens'] ?? []));
        } else {
            // même normalisation que les champs de date des listes : le
            // champ du navigateur rend déjà de l'ISO, mais il ne faut pas
            // qu'une saisie manuelle fausse le tri de la collection
            $item['date'] = Blocs::jour((string) ($_POST['date'] ?? ''));
        }

        $item['sections'] = self::relireBlocs((array) ($_POST['bloc'] ?? []));

        return $item;
    }

    public function ficheBloc(string $nom, string $slug): string
    {
        if (!Csrf::verifier() || !isset(self::COLLECTIONS[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        $type = (string) ($_POST['type'] ?? 'texte');
        if (!isset(Blocs::TYPES[$type])) {
            $type = 'texte';
        }

        $donnees = $this->content->load($nom);
        $rang = self::rangDe($donnees['items'] ?? [], $slug);
        if ($rang === null) {
            return $this->rediriger('/admin/' . $nom);
        }

        $item = $this->ficheSaisie($nom, $donnees['items'][$rang]);
        $item['sections'][] = ['type' => $type, 'titre' => ''];
        $sections = $item['sections'];
        $donnees['items'][$rang] = $item;
        $this->content->save($nom, $donnees);

        Session::flash('succes', 'Bloc ajouté en fin de fiche.');
        return $this->rediriger('/admin/' . $nom . '/' . $slug . '#bloc-' . (count($sections) - 1));
    }

    public function fichePublication(string $nom, string $slug): string
    {
        return $this->operationListe($nom, $slug, 'basculer');
    }

    public function ficheOrdre(string $nom, string $slug): string
    {
        return $this->operationListe($nom, $slug, 'deplacer');
    }

    public function ficheSupprimer(string $nom, string $slug): string
    {
        return $this->operationListe($nom, $slug, 'retirer');
    }

    public function collectionIntro(string $nom): string
    {
        if (!Csrf::verifier() || !isset(self::COLLECTIONS[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        $donnees = $this->content->load($nom);
        $donnees['intro'] = trim((string) ($_POST['intro'] ?? ''));
        $this->content->save($nom, $donnees);

        Session::flash('succes', 'Introduction enregistrée.');
        return $this->rediriger('/admin/' . $nom);
    }

    // ============================================================= listes

    public function liste(string $nom): string
    {
        if (!isset(self::LISTES[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        return $this->view->render('admin/liste', [
            'page'     => ['titre' => self::LISTES[$nom]['nom']],
            'liste'    => $nom,
            'reglages' => self::LISTES[$nom],
            'donnees'  => $this->content->load($nom),
            'medias'   => $this->mediatheque->lister(),
            'documents' => self::documentsDisponibles(),
        ], 'admin/layout');
    }

    public function listeEnvoi(string $nom): string
    {
        if (!Csrf::verifier() || !isset(self::LISTES[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        $reglages = self::LISTES[$nom];
        $donnees  = $this->content->load($nom);

        $donnees['items'] = self::relireEntrees((array) ($_POST['items'] ?? []), $reglages['champs'], $donnees['items'] ?? []);
        foreach ((array) ($reglages['sous'] ?? []) as $cle => $_) {
            $donnees[$cle] = self::relireEntrees((array) ($_POST[$cle] ?? []), $reglages['champs'], $donnees[$cle] ?? []);
        }

        $this->content->save($nom, $donnees);
        Session::flash('succes', $reglages['nom'] . ' : modifications enregistrées.');
        return $this->rediriger('/admin/listes/' . $nom);
    }

    /**
     * Ajoute une entrée vide à une liste, en conservant la saisie en cours.
     */
    public function listeAjout(string $nom): string
    {
        if (!Csrf::verifier() || !isset(self::LISTES[$nom])) {
            return $this->rediriger('/admin/pages');
        }

        $reglages = self::LISTES[$nom];
        $cible    = (string) ($_POST['cible'] ?? 'items');
        if ($cible !== 'items' && !isset($reglages['sous'][$cible])) {
            $cible = 'items';
        }

        $donnees = $this->content->load($nom);
        $donnees['items'] = self::relireEntrees((array) ($_POST['items'] ?? []), $reglages['champs'], $donnees['items'] ?? []);
        foreach ((array) ($reglages['sous'] ?? []) as $cle => $_) {
            $donnees[$cle] = self::relireEntrees((array) ($_POST[$cle] ?? []), $reglages['champs'], $donnees[$cle] ?? []);
        }

        $donnees[$cible][] = ['slug' => self::slugUnique('entree', array_column($donnees[$cible] ?? [], 'slug')), 'actif' => false];
        $this->content->save($nom, $donnees);

        Session::flash('succes', 'Entrée ajoutée en fin de liste. Complétez-la, puis publiez-la.');
        return $this->rediriger('/admin/listes/' . $nom . '#entree-' . $cible . '-' . (count($donnees[$cible]) - 1));
    }

    // ============================================================= conseil

    /**
     * Le conseil municipal a sa forme propre : des groupes hiérarchisés, pas
     * une liste plate. Un maire, trois adjoints et huit conseillers ne se
     * saisissent pas dans le même moule qu'une liste d'associations.
     */
    public function conseil(): string
    {
        return $this->view->render('admin/conseil', [
            'page'    => ['titre' => 'Conseil municipal'],
            'conseil' => $this->content->load('conseil'),
            'medias'  => $this->mediatheque->lister(),
        ], 'admin/layout');
    }

    public function conseilEnvoi(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger('/admin/conseil');
        }

        $conseil = $this->content->load('conseil');
        $conseil['photo']          = trim((string) ($_POST['photo'] ?? ''));
        $conseil['photo_alt']      = trim((string) ($_POST['photo_alt'] ?? ''));
        $conseil['photo_legende']  = trim((string) ($_POST['photo_legende'] ?? ''));

        $groupes = [];
        foreach ((array) ($_POST['groupe'] ?? []) as $brut) {
            if (!is_array($brut)) {
                continue;
            }
            $groupe = [
                'titre'    => trim((string) ($brut['titre'] ?? '')),
                'surtitre' => trim((string) ($brut['surtitre'] ?? '')),
                'texte'    => trim((string) ($brut['texte'] ?? '')),
            ];
            if (trim((string) ($brut['fond'] ?? '')) !== '') {
                $groupe['fond'] = 'sombre';
            }

            $membres = [];
            foreach ((array) ($brut['membres'] ?? []) as $membre) {
                if (!is_array($membre)) {
                    continue;
                }
                $nom = trim((string) ($membre['nom'] ?? ''));
                if ($nom === '') {
                    continue;
                }
                $entree = ['nom' => $nom];
                foreach (['fonction', 'delegation'] as $champ) {
                    $valeur = trim((string) ($membre[$champ] ?? ''));
                    if ($valeur !== '') {
                        $entree[$champ] = $valeur;
                    }
                }
                $membres[] = $entree;
            }
            $groupe['membres'] = $membres;

            if ($groupe['titre'] !== '' || $membres !== []) {
                $groupes[] = $groupe;
            }
        }
        $conseil['groupes'] = $groupes;

        $this->content->save('conseil', $conseil);
        Session::flash('succes', 'Conseil municipal enregistré.');
        return $this->rediriger('/admin/conseil');
    }

    // ============================================================= outils

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function relireBlocs(array $bruts): array
    {
        $blocs = [];
        foreach ($bruts as $brut) {
            if (!is_array($brut)) {
                continue;
            }
            // Une case cochée retire le bloc : plus sûr qu'un bouton qui
            // supprimerait avant que la page n'ait été enregistrée.
            if (($brut['retirer'] ?? '') !== '') {
                continue;
            }
            $bloc = Blocs::relire($brut);
            if ($bloc !== null) {
                $blocs[] = $bloc;
            }
        }
        return $blocs;
    }

    /**
     * Relit les entrées d'une liste simple.
     *
     * Le slug et l'état de publication sont conservés depuis les données
     * existantes : ce sont les deux seules valeurs que le formulaire ne
     * modifie pas, et les perdre casserait les ancres et les liens.
     *
     * @param array<int, mixed> $bruts
     * @param array<string, string> $champs
     * @param array<int, array<string, mixed>> $actuelles
     * @return array<int, array<string, mixed>>
     */
    private static function relireEntrees(array $bruts, array $champs, array $actuelles): array
    {
        $parSlug = [];
        foreach ($actuelles as $entree) {
            if (is_array($entree) && ($entree['slug'] ?? '') !== '') {
                $parSlug[(string) $entree['slug']] = $entree;
            }
        }

        $entrees = [];
        foreach ($bruts as $brut) {
            if (!is_array($brut) || ($brut['retirer'] ?? '') !== '') {
                continue;
            }

            $slug = trim((string) ($brut['slug'] ?? ''));
            $entree = ['slug' => $slug, 'actif' => ($brut['actif'] ?? '') !== ''];

            // Un menu déroulant renvoie toujours sa première option, une case
            // son état : leur présence ne prouve pas qu'on a rempli la ligne.
            // Sans cette distinction, une entrée ajoutée puis laissée vide
            // repartirait avec une famille ou un pictogramme, et se compterait
            // comme saisie.
            $saisie = false;
            foreach ($champs as $champ => $nature) {
                if ($nature === 'case') {
                    $entree[$champ] = ($brut[$champ] ?? '') !== '';
                    continue;
                }
                $valeur = Blocs::relireChamp($nature, $brut[$champ] ?? null, self::SOUS_LISTES);
                if ($valeur === null) {
                    continue;
                }
                $entree[$champ] = $valeur;
                if (!Blocs::estImposee($nature)) {
                    $saisie = true;
                }
            }

            if (!$saisie) {
                continue;
            }
            $entrees[] = $entree;
        }
        return $entrees;
    }


    /**
     * @param array<int, mixed> $bruts
     * @return array<int, array{libelle: string, url: string}>
     */
    private static function liensSaisis(array $bruts): array
    {
        $liens = [];
        foreach ($bruts as $brut) {
            if (!is_array($brut)) {
                continue;
            }
            $url = trim((string) ($brut['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $liens[] = ['libelle' => trim((string) ($brut['libelle'] ?? '')), 'url' => $url];
        }
        return $liens;
    }

    /**
     * @return string[]
     */
    private static function lignes(string $texte): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', trim($texte)) ?: [])));
    }

    /**
     * Les PDF déposés dans public/assets/doc/, pour la liste déroulante.
     *
     * @return string[]
     */
    private static function documentsDisponibles(): array
    {
        $dossier = dirname(__DIR__, 2) . '/public/assets/doc';
        $fichiers = is_dir($dossier) ? (glob($dossier . '/*.pdf') ?: []) : [];
        sort($fichiers);

        return array_map(static fn(string $f): string => 'assets/doc/' . basename($f), $fichiers);
    }

    /**
     * Adresse (slug) libre et lisible, dérivée d'un intitulé.
     *
     * @param string[] $existants
     */
    private static function slugUnique(string $intitule, array $existants): string
    {
        $base = Seo::normaliser($intitule) ?: 'fiche';
        $slug = $base;
        $n = 2;
        while (in_array($slug, $existants, true)) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    /**
     * @param array<int, mixed> $items
     */
    private static function rangDe(array $items, string $slug): ?int
    {
        foreach ($items as $rang => $item) {
            if (is_array($item) && ($item['slug'] ?? null) === $slug) {
                return $rang;
            }
        }
        return null;
    }

    private function operationListe(string $nom, string $slug, string $operation): string
    {
        $retour = '/admin/' . $nom;
        if (!Csrf::verifier() || !isset(self::COLLECTIONS[$nom])) {
            return $this->rediriger($retour);
        }

        $donnees = $this->content->load($nom);
        $items   = $donnees['items'] ?? [];
        $rang    = self::rangDe($items, $slug);
        if ($rang === null) {
            Session::flash('erreur', 'Fiche introuvable.');
            return $this->rediriger($retour);
        }

        if ($operation === 'basculer') {
            [$items, $enLigne] = Liste::basculer($items, $rang);
            Session::flash('succes', $enLigne ? 'Fiche publiée.' : 'Fiche retirée du site.');
        } elseif ($operation === 'deplacer') {
            $items = Liste::deplacer($items, $rang, ($_POST['sens'] ?? 'bas') === 'haut' ? -1 : 1);
        } else {
            // Une suppression accidentelle reste rattrapable : l'enregistrement
            // conserve la version précédente, restaurable depuis l'éditeur avancé.
            $intitule = (string) ($items[$rang]['nom'] ?? $items[$rang]['titre'] ?? $slug);
            $items = Liste::retirer($items, $rang);
            Session::flash('succes', '« ' . $intitule . ' » supprimé. '
                . 'La version précédente reste restaurable depuis l’Éditeur avancé.');
        }

        $donnees['items'] = $items;
        $this->content->save($nom, $donnees);

        return $this->rediriger($retour);
    }

    /**
     * @return array<string, mixed>
     */
    private function contenu(string $cle): array
    {
        try {
            return $this->content->load('pages/' . $cle);
        } catch (RuntimeException) {
            return ['titre' => Seo::PAGES[$cle]['nom'] ?? $cle, 'hero' => [], 'sections' => []];
        }
    }

    private function rediriger(string $chemin): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }
}
