<?php
declare(strict_types=1);

/**
 * Routes du back-office. Incluses depuis app/routes.php.
 *
 * @var App\Core\Router  $router
 * @var App\Core\View    $view
 * @var App\Core\Content $content
 * @var App\Core\Avis    $avis
 * @var App\Core\Seo     $seo
 * @var array            $config
 */

use App\Admin\AdminController;
use App\Admin\ApparenceController;
use App\Admin\AssistantController;
use App\Admin\ContenuController;
use App\Admin\ConseillerController;
use App\Admin\ConversationController;
use App\Admin\AvisController;
use App\Admin\EditionController;
use App\Admin\LangueController;
use App\Admin\MediaController;
use App\Admin\MiseAJourController;
use App\Admin\ParametreController;
use App\Admin\ReseauxController;
use App\Admin\SeoController;
use App\Core\Auth;
use App\Core\Deploiement;
use App\Core\Langues;
use App\Core\Mailer;
use App\Core\Mediatheque;
use App\Core\Parametres;
use App\Core\Verrou;

/* Deux administrateurs peuvent travailler en même temps. Le verrou relève
   l'empreinte des contenus affichés (requêtes GET) et refuse une écriture qui
   effacerait celle d'un autre (requêtes POST).

   Il n'est armé que sur les adresses du back-office. Ce fichier est lu à
   chaque requête — il ne fait que déclarer des routes —, si bien que le
   verrou s'armait aussi sur le site public : Content::load() y notait donc
   une empreinte en session, et OUVRAIT une session pour chaque visiteur. Trois
   conséquences, toutes silencieuses : un cookie posé à qui n'en a pas besoin,
   un fichier de session par visite sur le disque du mutualisé, et surtout le
   « Cache-Control: no-store » que PHP ajoute d'office dès qu'une session
   démarre — soit un site public qu'aucun navigateur n'avait le droit de
   garder, pas même pour le bouton Retour. */
$cheminDemande = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (str_starts_with($cheminDemande, '/admin')) {
    Verrou::armer($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

$auth = new Auth(
    $config['paths']['data'] . '/admin/compte.json',
    dirname($config['paths']['data']) . '/storage/cache/tentatives.json'
);

$mediatheque = new Mediatheque($config['paths']['public'] . '/assets/img/site');
$parametres  = new Parametres($config['paths']['data'] . '/admin/parametres.json');
$mailer      = new Mailer($parametres);
$deploiement = new Deploiement($config['paths']['root'], $parametres);
$languesAdmin = new Langues($config['paths']['data'] . '/langues.json');
$traducteurAdmin = new App\Core\Traducteur($config['paths']['data'] . '/traductions');

// Réseaux sociaux : la diffusion réunit l'accès à Meta, la file d'attente et
// la fabrique d'image. Un seul chemin d'envoi pour l'écran, la case à cocher
// des actualités et la tâche planifiée.
$reseauxMeta = new App\Core\Reseaux($parametres);
$publications = new App\Core\Publications($config['paths']['data'] . '/reseaux');
$vignette = new App\Core\Vignette(
    $config['paths']['public'],
    App\Core\Charte::depuis($parametres),
    (string) $content->get('site', 'nom', 'Mairie')
);
$diffusion = new App\Core\Diffusion($reseauxMeta, $publications, $vignette,
                                     $config['paths']['public'], origine());
$ctrlReseaux = new ReseauxController($view, $reseauxMeta, $publications, $diffusion,
                                     $content, $parametres, $mediatheque, $vignette);

$frequentation = new App\Core\Frequentation($config['paths']['data'] . '/frequentation');
$admin   = new AdminController($view, $content, $auth, $mediatheque,
                               $frequentation, $publications, $conversations,
                               $parametres, $reseauxMeta);
$edition = new EditionController($view, $content, $mediatheque);
$contenu = new ContenuController($view, $content, $mediatheque, $seo, $diffusion, $reseauxMeta);
$media   = new MediaController($view, $content, $mediatheque);
$majour  = new MiseAJourController($view, $deploiement);
$ctrlLangues = new LangueController($view, $content, $languesAdmin, $traducteurAdmin,
                                new App\Core\TraductionAuto(
                                    (string) $parametres->get('traduction.cle_deepl', ''),
                                    (bool) $parametres->get('traduction.gratuits', false)
                                ),
                                $config['paths']['views'], $parametres);
$refer   = new SeoController($view, $content, $seo, $mediatheque);
$reglage = new ParametreController($view, $parametres, $content, $auth, $mailer, $config['paths']['root'], $config['paths']['public']);
$apparence = new ApparenceController($view, $parametres, $content);
$ctrlAvis  = new AvisController($view, $avis, $parametres);
$ctrlIa    = new AssistantController($view, $assistant, $parametres);
$ctrlConv  = new ConversationController($view, $conversations);

/* Le conseiller partage la clé Gemini de l'assistant public mais pas son
   interrupteur : une mairie veut souvent le conseiller d'abord, pour
   préparer son site, avant d'exposer un robot aux visiteurs. */
$conseiller = new App\Core\Conseiller($assistant, $parametres, $content, $seo,
                                      $frequentation, $conversations,
                                      $config['paths']['data']);
$ctrlConseil = new ConseillerController($conseiller);

// compteur du menu : lu une fois, partagé à tous les écrans du back-office
$view->share('nonLues', $conversations->nonLues());
// la pastille est posée par le gabarit du back-office, sur tous les écrans
$view->share('conseiller', $conseiller);

$view->share('auth', $auth);

/**
 * Enveloppe un écran protégé : redirection vers la connexion si besoin.
 */
$protege = static function (callable $handler) use ($auth): callable {
    return static function (array $params = []) use ($handler, $auth) {
        if (!$auth->compteExiste()) {
            header('Location: ' . url('/admin/configuration'), true, 303);
            return '';
        }
        if (!$auth->connecte()) {
            header('Location: ' . url('/admin/connexion'), true, 303);
            return '';
        }
        return $handler($params);
    };
};

// ----- accès --------------------------------------------------------------
$router->get('/admin/configuration',  fn() => $admin->configuration());
$router->post('/admin/configuration', fn() => $admin->configurationEnvoi());
$router->get('/admin/connexion',      fn() => $admin->connexion());
$router->post('/admin/connexion',     fn() => $admin->connexionEnvoi());
$router->post('/admin/deconnexion',   fn() => $admin->deconnexion());

// ----- écrans protégés ----------------------------------------------------
$router->get('/admin',          $protege(fn() => $admin->tableauDeBord()));

$router->get('/admin/site',     $protege(fn() => $edition->site()));
$router->post('/admin/site',    $protege(fn() => $edition->siteEnvoi()));

$router->get('/admin/accueil',  $protege(fn() => $edition->accueil()));
$router->post('/admin/accueil', $protege(fn() => $edition->accueilEnvoi()));

// --- pages du site (éditeur générique de blocs) ---------------------------
$router->get('/admin/pages',         $protege(fn() => $contenu->pages()));
$router->get('/admin/pages/{cle}',   $protege(fn(array $p) => $contenu->page($p['cle'])));
$router->post('/admin/pages/{cle}',  $protege(fn(array $p) => $contenu->pageEnvoi($p['cle'])));
$router->post('/admin/pages/{cle}/bloc', $protege(fn(array $p) => $contenu->pageBloc($p['cle'])));

// --- collections à fiches : démarches et actualités -----------------------
foreach (array_keys(ContenuController::COLLECTIONS) as $nomCollection) {
    $router->get('/admin/' . $nomCollection,          $protege(fn() => $contenu->collection($nomCollection)));
    $router->post('/admin/' . $nomCollection . '/intro', $protege(fn() => $contenu->collectionIntro($nomCollection)));
    $router->post('/admin/' . $nomCollection . '/creer', $protege(fn() => $contenu->ficheCreer($nomCollection)));
    $router->post('/admin/' . $nomCollection . '/{slug}/publication', $protege(fn(array $p) => $contenu->fichePublication($nomCollection, $p['slug'])));
    $router->post('/admin/' . $nomCollection . '/{slug}/ordre',       $protege(fn(array $p) => $contenu->ficheOrdre($nomCollection, $p['slug'])));
    $router->post('/admin/' . $nomCollection . '/{slug}/supprimer',   $protege(fn(array $p) => $contenu->ficheSupprimer($nomCollection, $p['slug'])));
    $router->post('/admin/' . $nomCollection . '/{slug}/bloc',        $protege(fn(array $p) => $contenu->ficheBloc($nomCollection, $p['slug'])));
    $router->get('/admin/' . $nomCollection . '/{slug}',  $protege(fn(array $p) => $contenu->fiche($nomCollection, $p['slug'])));
    $router->post('/admin/' . $nomCollection . '/{slug}', $protege(fn(array $p) => $contenu->ficheEnvoi($nomCollection, $p['slug'])));
}

// --- listes simples : agenda, documents, associations, numéros… -----------
$router->get('/admin/listes/{nom}',        $protege(fn(array $p) => $contenu->liste($p['nom'])));
$router->post('/admin/listes/{nom}',       $protege(fn(array $p) => $contenu->listeEnvoi($p['nom'])));
$router->post('/admin/listes/{nom}/ajout', $protege(fn(array $p) => $contenu->listeAjout($p['nom'])));

// --- conseil municipal ----------------------------------------------------
$router->get('/admin/conseil',  $protege(fn() => $contenu->conseil()));
$router->post('/admin/conseil', $protege(fn() => $contenu->conseilEnvoi()));

// --- demande en ligne et contact ------------------------------------------
$router->get('/admin/demande',  $protege(fn() => $edition->demande()));
$router->post('/admin/demande', $protege(fn() => $edition->demandeEnvoi()));
$router->get('/admin/contact',  $protege(fn() => $edition->contact()));
$router->post('/admin/contact', $protege(fn() => $edition->contactEnvoi()));

// --- médiathèque ----------------------------------------------------------
$router->get('/admin/photos',          $protege(fn() => $media->galerie()));
$router->post('/admin/photos/ajout',   $protege(fn() => $media->ajout()));
$router->post('/admin/photos/rotation', $protege(fn() => $media->rotation()));
$router->post('/admin/photos/retrait', $protege(fn() => $media->retrait()));

// --- apparence et avis ----------------------------------------------------
$router->get('/admin/apparence',  $protege(fn() => $apparence->ecran()));
$router->post('/admin/apparence', $protege(fn() => $apparence->envoi()));

$router->get('/admin/avis',             $protege(fn() => $ctrlAvis->ecran()));
$router->post('/admin/avis',            $protege(fn() => $ctrlAvis->envoi()));
$router->post('/admin/avis/actualiser', $protege(fn() => $ctrlAvis->actualiser()));
$router->post('/admin/avis/rechercher', $protege(fn() => $ctrlAvis->rechercher()));

// --- assistant IA ---------------------------------------------------------
$router->get('/admin/assistant',                  $protege(fn() => $ctrlIa->ecran()));
$router->post('/admin/assistant',                 $protege(fn() => $ctrlIa->envoi()));
$router->post('/admin/assistant/modeles',         $protege(fn() => $ctrlIa->modeles()));
$router->post('/admin/assistant/notes',           $protege(fn() => $ctrlIa->notes()));
$router->post('/admin/assistant/consignes',       $protege(fn() => $ctrlIa->consignes()));
$router->post('/admin/assistant/documents',       $protege(fn() => $ctrlIa->documentAjout()));
$router->post('/admin/assistant/documents/retirer', $protege(fn() => $ctrlIa->documentSuppression()));
$router->post('/admin/assistant/essai',           $protege(fn() => $ctrlIa->essai()));

// --- conseiller du back-office ---------------------------------------------
// Ces trois adresses répondent en JSON à la pastille, jamais une page.
$router->post('/admin/conseiller',        $protege(fn() => $ctrlConseil->question()));
$router->post('/admin/conseiller/bilan',  $protege(fn() => $ctrlConseil->bilan()));
$router->get('/admin/conseiller/bilan',   $protege(fn() => $ctrlConseil->dernierBilan()));

// --- conversations --------------------------------------------------------
$router->get('/admin/conversations',            $protege(fn() => $ctrlConv->ecran()));
$router->post('/admin/conversations/supprimer', $protege(fn() => $ctrlConv->supprimer()));
$router->post('/admin/conversations/vider',     $protege(fn() => $ctrlConv->viderMois()));

// --- réseaux sociaux ------------------------------------------------------
$router->get('/admin/reseaux',                 $protege(fn() => $ctrlReseaux->ecran()));
$router->post('/admin/reseaux/application',    $protege(fn() => $ctrlReseaux->application()));
$router->post('/admin/reseaux/connexion',      $protege(fn() => $ctrlReseaux->connexion()));
$router->get('/admin/reseaux/retour',          $protege(fn() => $ctrlReseaux->retour()));
$router->post('/admin/reseaux/page',           $protege(fn() => $ctrlReseaux->choisirPage()));
$router->post('/admin/reseaux/deconnexion',    $protege(fn() => $ctrlReseaux->deconnexion()));
$router->post('/admin/reseaux/publier',        $protege(fn() => $ctrlReseaux->publier()));
$router->post('/admin/reseaux/annuler',        $protege(fn() => $ctrlReseaux->annuler()));
$router->post('/admin/reseaux/journal/vider',  $protege(fn() => $ctrlReseaux->viderJournal()));

/* Le dépilage de la file, appelé par une tâche planifiée.
 *
 * Cette adresse n'est PAS derrière `$protege`, et c'est voulu : un cron n'a
 * pas de session. Elle est protégée par une clé tirée au sort, comparée à
 * temps constant, et ne rend qu'un compte rendu de deux lignes. Sans clé
 * valable, elle répond 403 et ne dit rien de plus — une adresse qui
 * détaillerait ce qu'elle attend serait un mode d'emploi pour la forcer. */
$router->get('/taches/reseaux', function () use ($reseauxMeta, $diffusion): string {
    header('X-Robots-Tag: noindex, nofollow');
    header('Content-Type: text/plain; charset=utf-8');

    if (!$reseauxMeta->cleTacheValide((string) ($_GET['cle'] ?? ''))) {
        http_response_code(403);
        return "non\n";
    }

    try {
        $bilan = $diffusion->depiler(time(), 20);
    } catch (Throwable $e) {
        http_response_code(500);
        return 'erreur : ' . $e->getMessage() . "\n";
    }

    // « occupe » : un autre dépilage tenait le verrou. Ce n'est pas une
    // erreur — deux crons qui se chevauchent est le cas normal quand un envoi
    // est lent —, et le dire évite qu'on cherche pourquoi rien n'est parti.
    if ($bilan['verrouille'] ?? false) {
        return "occupe\n";
    }

    return sprintf("partis %d\nechecs %d\n", $bilan['partis'], $bilan['echecs']);
});

// --- réglages techniques --------------------------------------------------
$router->get('/admin/parametres',             $protege(fn() => $reglage->ecran()));
$router->post('/admin/parametres/messagerie', $protege(fn() => $reglage->messagerieEnvoi()));
$router->post('/admin/parametres/test',       $protege(fn() => $reglage->test()));
$router->post('/admin/parametres/antispam',   $protege(fn() => $reglage->antispamEnvoi()));
$router->post('/admin/parametres/mesure',     $protege(fn() => $reglage->mesureEnvoi()));
$router->post('/admin/parametres/compte',     $protege(fn() => $reglage->compteEnvoi()));
$router->post('/admin/parametres/droits',     $protege(fn() => $reglage->droitsEnvoi()));

$router->get('/admin/referencement',                     $protege(fn() => $refer->ecran()));
$router->post('/admin/referencement/general',            $protege(fn() => $refer->generalEnvoi()));
$router->post('/admin/referencement/page/{cle}',         $protege(fn(array $p) => $refer->pageEnvoi($p['cle'])));
$router->post('/admin/referencement/fiche/{cle}/{slug}', $protege(fn(array $p) => $refer->ficheEnvoi($p['cle'], $p['slug'])));
$router->post('/admin/referencement/redirection',         $protege(fn() => $refer->redirectionAjout()));
$router->post('/admin/referencement/redirection/retrait', $protege(fn() => $refer->redirectionRetrait()));

$router->get('/admin/langues',                     $protege(fn() => $ctrlLangues->ecran()));
$router->post('/admin/langues/ajouter',            $protege(fn() => $ctrlLangues->ajouter()));
$router->get('/admin/langues/{code}',              $protege(fn(array $p) => $ctrlLangues->traductions($p['code'])));
$router->post('/admin/langues/{code}/publication', $protege(fn(array $p) => $ctrlLangues->basculer($p['code'])));
$router->post('/admin/langues/{code}/supprimer',   $protege(fn(array $p) => $ctrlLangues->supprimer($p['code'])));
$router->post('/admin/langues/{code}/enregistrer', $protege(fn(array $p) => $ctrlLangues->enregistrer($p['code'])));
$router->post('/admin/langues/{code}/auto',        $protege(fn(array $p) => $ctrlLangues->auto($p['code'])));
$router->post('/admin/langues/cle',                $protege(fn() => $ctrlLangues->cleEnvoi()));

$router->get('/admin/mises-a-jour',              $protege(fn() => $majour->ecran()));
$router->post('/admin/mises-a-jour/verifier',    $protege(fn() => $majour->verifier()));
$router->post('/admin/mises-a-jour/appliquer',   $protege(fn() => $majour->appliquer()));
$router->post('/admin/mises-a-jour/sauvegarder', $protege(fn() => $majour->sauvegarder()));
$router->post('/admin/mises-a-jour/restaurer',   $protege(fn() => $majour->restaurer()));

$router->get('/admin/avance',  $protege(fn() => $edition->avance($_GET['nom'] ?? null)));
$router->post('/admin/avance', $protege(fn() => $edition->avanceEnvoi()));
