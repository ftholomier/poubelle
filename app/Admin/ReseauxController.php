<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Content;
use App\Core\Csrf;
use App\Core\Diffusion;
use App\Core\Mediatheque;
use App\Core\Parametres;
use App\Core\Publications;
use App\Core\Reseaux;
use App\Core\Session;
use App\Core\Vignette;
use Throwable;

/**
 * Écran Réseaux sociaux : connexion des comptes, composition, file, journal.
 *
 * La connexion se fait par le dialogue de Meta, aller et retour. Deux points
 * de sécurité y sont tenus, et ils ne sont pas facultatifs :
 *
 *   · le **jeton d'état** tiré au sort à l'aller est vérifié au retour. Sans
 *     lui, une adresse fabriquée par un tiers et cliquée par un administrateur
 *     connecterait la Page de ce tiers sur le site de la commune ;
 *   · l'**adresse de retour** est calculée ici, jamais reçue de la requête.
 *     Une adresse de retour prise dans l'URL est une redirection ouverte.
 *
 * Le secret de l'application n'est jamais réaffiché : le champ est vide, et
 * vide il conserve la valeur enregistrée — comme la clé Gemini et le mot de
 * passe SMTP.
 */
final class ReseauxController
{
    /** Ce qu'on sait reprendre du contenu du site. */
    private const SOURCES = [
        'actualites' => ['nom' => 'Actualité', 'titre' => 'titre', 'texte' => 'resume'],
        'agenda'     => ['nom' => 'Agenda',    'titre' => 'titre', 'texte' => 'texte'],
        'documents'  => ['nom' => 'Document',  'titre' => 'titre', 'texte' => ''],
    ];

    public function __construct(
        private readonly \App\Core\View $view,
        private readonly Reseaux $reseaux,
        private readonly Publications $publications,
        private readonly Diffusion $diffusion,
        private readonly Content $content,
        private readonly Parametres $parametres,
        private readonly Mediatheque $mediatheque,
        private readonly Vignette $vignette,
    ) {
    }

    private function rediriger(string $chemin = '/admin/reseaux'): string
    {
        header('Location: ' . url($chemin), true, 303);
        return '';
    }

    /**
     * L'adresse de retour du dialogue Meta.
     *
     * Calculée, jamais reçue : c'est ce qui empêche d'en faire une redirection
     * ouverte. Elle doit être déclarée à l'identique dans l'application Meta.
     */
    public function adresseRetour(): string
    {
        return absolu(ltrim(url('/admin/reseaux/retour'), '/'));
    }

    // ------------------------------------------------------------------ écran

    public function ecran(): string
    {
        // Dépilage opportuniste : voir App\Core\Publications. Sur mutualisé,
        // le cron n'est pas toujours réglé, et une publication programmée qui
        // ne part jamais sans que rien ne le dise est le pire des cas.
        $depile = $this->depilerSansBruit();

        return $this->view->render('admin/reseaux', [
            'page'       => ['titre' => 'Réseaux sociaux'],
            'reseaux'    => $this->reseaux,
            'retour'     => $this->adresseRetour(),
            'permissions' => Reseaux::PERMISSIONS,
            'manques'    => $this->reseaux->manques(),
            'pages'      => $this->pagesEnAttente(),
            'file'       => $this->publications->file(),
            'journal'    => $this->publications->journal(),
            'retard'     => $this->publications->enRetard(time()),
            'depile'     => $depile,
            'sources'    => $this->sourcesDisponibles(),
            // Appeler cleTache() crée la clé au premier affichage : la mairie
            // ne peut pas régler une tâche planifiée dont l'adresse n'existe
            // pas encore.
            'urlTache'   => absolu(ltrim(url('/taches/reseaux'), '/'))
                          . '?cle=' . rawurlencode($this->reseaux->cleTache()),
            'photos'     => $this->mediatheque->lister(),
            'vignette'   => $this->vignette->possible(),
            'brouillon'  => Session::flashDonnees('reseaux_brouillon') ?? [],
        ], 'admin/layout');
    }

    /**
     * Entre deux dépilages greffés sur l'affichage d'un écran.
     *
     * Sans ce délai, chaque rafraîchissement relançait un appel à Meta. Quand
     * Meta ne répond pas — panne, pare-feu sortant, mutualisé sans accès
     * réseau —, l'écran attendait le délai réseau avant de s'afficher, et
     * c'est justement l'écran où l'on vient voir ce qui ne va pas.
     */
    private const DEPILAGE_REPOS = 300;

    /** @return array{partis: int, echecs: int} */
    private function depilerSansBruit(): array
    {
        $repos = ['partis' => 0, 'echecs' => 0];

        if (time() - $this->publications->dernierDepilage() < self::DEPILAGE_REPOS) {
            return $repos;
        }

        try {
            // Une seule publication par affichage, là où la tâche planifiée en
            // prend vingt : ce dépilage-ci se paie en temps d'attente devant
            // une page blanche. Le reste part à la visite suivante, ou au
            // premier passage du cron.
            $bilan = $this->diffusion->depiler(time(), 1);

            return ['partis' => $bilan['partis'], 'echecs' => $bilan['echecs']];
        } catch (Throwable) {
            // Une file en panne ne doit pas empêcher l'écran de s'afficher.
            return $repos;
        }
    }

    // -------------------------------------------------------------- connexion

    public function application(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $tout = $this->parametres->tout();
        $secret = trim((string) ($_POST['secret'] ?? ''));
        // Champ laissé vide = on conserve le secret déjà enregistré. Il n'est
        // jamais réaffiché, donc il n'est jamais renvoyé par le formulaire.
        if ($secret === '') {
            $secret = (string) ($tout['reseaux']['secret'] ?? '');
        }

        $tout['reseaux'] = array_merge($tout['reseaux'] ?? [], [
            'application' => trim((string) ($_POST['application'] ?? '')),
            'secret'      => $secret,
        ]);

        try {
            $this->parametres->enregistrer($tout);
            Session::flash('succes', 'Application enregistrée.');
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function connexion(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        try {
            header('Location: ' . $this->reseaux->urlConnexion($this->adresseRetour()), true, 303);
            return '';
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            return $this->rediriger();
        }
    }

    /** Retour du dialogue Meta. */
    public function retour(): string
    {
        $erreur = (string) ($_GET['error_description'] ?? $_GET['error'] ?? '');
        if ($erreur !== '') {
            Session::flash('erreur', 'Facebook a refusé la connexion : ' . $erreur);
            return $this->rediriger();
        }

        try {
            $pages = $this->reseaux->pagesDisponibles(
                (string) ($_GET['code'] ?? ''),
                $this->adresseRetour(),
                (string) ($_GET['state'] ?? '')
            );
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            return $this->rediriger();
        }

        if (count($pages) === 1) {
            return $this->retenir($pages[0]);
        }

        // Plusieurs Pages : on laisse choisir plutôt que de deviner. Les
        // jetons sont passés par la session, pas par le formulaire — ils n'ont
        // rien à faire dans du HTML.
        $this->retenirPagesEnAttente($pages);
        return $this->rediriger();
    }

    public function choisirPage(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $choisie = (string) ($_POST['page_id'] ?? '');
        foreach ($this->pagesEnAttente() as $page) {
            if (is_array($page) && ($page['id'] ?? '') === $choisie) {
                $this->oublierPagesEnAttente();
                return $this->retenir($page);
            }
        }

        Session::flash('erreur', 'Ce choix a expiré. Reconnectez-vous à Facebook.');
        return $this->rediriger();
    }

    /* La liste des Pages ne peut PAS passer par flashDonnees() : un flash est
       consommé à la lecture, et c'est justement l'écran qui affiche la liste
       qui la lirait le premier. Le choix arrivait alors sur une session vide
       et échouait toujours — invisible tant qu'un compte ne gère qu'une seule
       Page, puisque retour() la retient sans passer par ici.

       Elle porte des jetons de Page : on l'oublie au choix, à la déconnexion,
       et d'elle-même au bout d'un quart d'heure si l'administrateur s'en va. */
    private const PAGES_DUREE = 900;

    /** @return list<array<string, mixed>> */
    private function pagesEnAttente(): array
    {
        $garde = Session::get('reseaux_pages');
        if (!is_array($garde) || (int) ($garde['expire'] ?? 0) < time()) {
            $this->oublierPagesEnAttente();
            return [];
        }

        return array_values(array_filter((array) ($garde['pages'] ?? []), 'is_array'));
    }

    /** @param list<array{id: string, nom: string, jeton: string}> $pages */
    private function retenirPagesEnAttente(array $pages): void
    {
        Session::set('reseaux_pages', [
            'expire' => time() + self::PAGES_DUREE,
            'pages'  => $pages,
        ]);
    }

    private function oublierPagesEnAttente(): void
    {
        Session::oublier('reseaux_pages');
    }

    /** @param array{id: string, nom: string, jeton: string} $page */
    private function retenir(array $page): string
    {
        try {
            $this->reseaux->retenirPage($page);
            $message = 'Page « ' . $page['nom'] . ' » connectée.';
            $message .= $this->reseaux->instagramPret()
                ? ' Compte Instagram « ' . $this->reseaux->instagramNom() . ' » détecté.'
                : ' Aucun compte Instagram professionnel n’y est rattaché : '
                . 'seule la publication Facebook est possible.';
            Session::flash('succes', $message);
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
        }

        return $this->rediriger();
    }

    public function deconnexion(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $this->reseaux->deconnecter();
        $this->oublierPagesEnAttente();
        Session::flash('succes', 'Comptes déconnectés. L’application reste enregistrée.');

        return $this->rediriger();
    }

    // ------------------------------------------------------------ publication

    public function publier(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $quand = $this->dateDemandee();
        $brouillon = [
            'titre'    => trim((string) ($_POST['titre'] ?? '')),
            'texte'    => trim((string) ($_POST['texte'] ?? '')),
            'surtitre' => trim((string) ($_POST['surtitre'] ?? '')),
            'lien'     => $this->lienValide((string) ($_POST['lien'] ?? '')),
            'image'    => $this->imageValide((string) ($_POST['image'] ?? '')),
            'reseaux'  => (array) ($_POST['reseaux'] ?? []),
            'source'   => trim((string) ($_POST['source'] ?? 'libre')),
            'quand'    => $quand,
        ];

        try {
            $publication = $this->diffusion->preparer($brouillon);
        } catch (Throwable $e) {
            Session::flash('erreur', $e->getMessage());
            Session::flashDonnees('reseaux_brouillon', $brouillon);
            return $this->rediriger();
        }

        // Programmée : elle entre en file et n'est pas envoyée maintenant.
        if ($quand > time() + 60) {
            $this->publications->empiler($publication);
            Session::flash('succes', 'Publication programmée pour le '
                . date('d/m/Y à H\hi', $quand) . '.');
            return $this->rediriger();
        }

        $publication['id'] = 'direct-' . bin2hex(random_bytes(4));
        [$ids, $motifs] = $this->diffusion->envoyer($publication);

        if ($ids === []) {
            $this->publications->journaliser($publication, [], implode(' · ', $motifs), false);
            Session::flash('erreur', 'Rien n’est parti. ' . implode(' · ', $motifs));
            Session::flashDonnees('reseaux_brouillon', $brouillon);
            return $this->rediriger();
        }

        $this->publications->journaliser($publication, $ids, implode(' · ', $motifs), $motifs === []);
        $partis = implode(' et ', array_map(
            static fn(string $r): string => $r === 'facebook' ? 'Facebook' : 'Instagram',
            array_keys($ids)
        ));

        if ($motifs === []) {
            Session::flash('succes', 'Publié sur ' . $partis . '.');
            return $this->rediriger();
        }

        // Un réseau accepté, l'autre refusé : ce qui manque retourne en file
        // plutôt que d'obliger à retaper la publication.
        $repris = $this->diffusion->reprendrePlusTard($publication, $ids);
        Session::flash('erreur',
            'Publié sur ' . $partis . '. En revanche : ' . implode(' · ', $motifs)
            . ($repris === [] ? '' : ' La publication sur '
                . implode(' et ', array_map('ucfirst', $repris))
                . ' est remise en file et sera réessayée.')
        );

        return $this->rediriger();
    }

    public function annuler(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $this->publications->retirer((string) ($_POST['id'] ?? ''));
        Session::flash('succes', 'Publication retirée de la file.');

        return $this->rediriger();
    }

    public function viderJournal(): string
    {
        if (!Csrf::verifier()) {
            return $this->rediriger();
        }

        $this->publications->viderJournal();
        Session::flash('succes', 'Journal vidé.');

        return $this->rediriger();
    }

    // --------------------------------------------------------------- utilités

    /** La date demandée, ou 0 pour « tout de suite ». */
    private function dateDemandee(): int
    {
        if ((string) ($_POST['moment'] ?? 'maintenant') !== 'programme') {
            return 0;
        }
        $date = trim((string) ($_POST['quand'] ?? ''));
        $t = $date === '' ? false : strtotime($date);

        return $t === false ? 0 : (int) $t;
    }

    /**
     * Un lien saisi ne part que s'il est une adresse http(s) complète.
     *
     * Le champ est libre, et ce qu'on y met finit dans une publication
     * publique : un `javascript:` ou un `data:` n'y a rien à faire.
     */
    private function lienValide(string $lien): string
    {
        $lien = trim($lien);
        if ($lien === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $lien) || filter_var($lien, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $lien;
    }

    /**
     * L'image choisie doit exister dans la médiathèque.
     *
     * On ne se contente pas d'un `is_file` : un chemin remontant du POST
     * pourrait désigner n'importe quel fichier du serveur, et cette adresse
     * est ensuite donnée à Meta pour qu'il la télécharge.
     */
    private function imageValide(string $chemin): string
    {
        $chemin = trim($chemin);
        if ($chemin === '' || !preg_match('#^assets/img/[A-Za-z0-9._/-]+$#', $chemin)) {
            return '';
        }
        if (str_contains($chemin, '..')) {
            return '';
        }

        return $this->mediatheque->existe($chemin) ? $chemin : '';
    }

    /**
     * Les contenus du site repris comme point de départ.
     *
     * @return array<string, array{nom: string, items: list<array<string, string>>}>
     */
    private function sourcesDisponibles(): array
    {
        $sources = [];
        foreach (self::SOURCES as $cle => $schema) {
            $items = [];
            foreach ((array) $this->content->get($cle, 'items', []) as $item) {
                if (!is_array($item) || ($item['actif'] ?? true) === false) {
                    continue;
                }
                $titre = trim((string) ($item[$schema['titre']] ?? ''));
                if ($titre === '') {
                    continue;
                }
                $texte = $schema['texte'] === ''
                    ? ''
                    : trim(strip_tags((string) ($item[$schema['texte']] ?? '')));

                $items[] = [
                    'slug'     => (string) ($item['slug'] ?? ''),
                    'titre'    => $titre,
                    'texte'    => mb_substr($texte, 0, 600),
                    'image'    => (string) ($item['image'] ?? ''),
                    'date'     => (string) ($item['date'] ?? ''),
                    'lien'     => $this->lienVersLeSite($cle, $item),
                    'surtitre' => $schema['nom'],
                ];
            }
            if ($items !== []) {
                // Les plus récents d'abord : c'est ce qu'on republie.
                usort($items, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
                $sources[$cle] = ['nom' => $schema['nom'], 'items' => array_slice($items, 0, 30)];
            }
        }

        return $sources;
    }

    /** @param array<string, mixed> $item */
    private function lienVersLeSite(string $cle, array $item): string
    {
        $slug = (string) ($item['slug'] ?? '');

        /* `route()` et non un chemin écrit à la main : le slug d'une page se
           change depuis le back-office, et le site peut vivre dans un
           sous-dossier. « absolu('actualites/…') » ignorait les deux, et le
           lien posté sur Facebook menait alors nulle part. */
        return match ($cle) {
            'actualites' => absolu(ltrim(route('actualites', $slug !== '' ? $slug : null), '/')),
            'agenda'     => absolu(ltrim(route('agenda'), '/')),
            'documents'  => absolu((string) ($item['fichier'] ?? 'documents')),
            default      => absolu(''),
        };
    }
}
