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
use App\Admin\ConversationController;
use App\Admin\AvisController;
use App\Admin\EditionController;
use App\Admin\LangueController;
use App\Admin\MediaController;
use App\Admin\MiseAJourController;
use App\Admin\ParametreController;
use App\Admin\SeoController;
use App\Core\Auth;
use App\Core\Deploiement;
use App\Core\Langues;
use App\Core\Mailer;
use App\Core\Mediatheque;
use App\Core\Parametres;

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

$admin   = new AdminController($view, $content, $auth, $mediatheque);
$edition = new EditionController($view, $content, $mediatheque);
$contenu = new ContenuController($view, $content, $mediatheque, $seo);
$media   = new MediaController($view, $content, $mediatheque);
$majour  = new MiseAJourController($view, $deploiement);
$ctrlLangues = new LangueController($view, $content, $languesAdmin, $traducteurAdmin,
                                new App\Core\TraductionAuto((string) $parametres->get('traduction.cle_deepl', '')),
                                $config['paths']['views'], $parametres);
$refer   = new SeoController($view, $content, $seo, $mediatheque);
$reglage = new ParametreController($view, $parametres, $content, $auth, $mailer, $config['paths']['root'], $config['paths']['public']);
$apparence = new ApparenceController($view, $parametres);
$ctrlAvis  = new AvisController($view, $avis, $parametres);
$ctrlIa    = new AssistantController($view, $assistant, $parametres);
$ctrlConv  = new ConversationController($view, $conversations);
// compteur du menu : lu une fois, partagé à tous les écrans du back-office
$view->share('nonLues', $conversations->nonLues());

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
$router->post('/admin/assistant/documents',       $protege(fn() => $ctrlIa->documentAjout()));
$router->post('/admin/assistant/documents/retirer', $protege(fn() => $ctrlIa->documentSuppression()));
$router->post('/admin/assistant/essai',           $protege(fn() => $ctrlIa->essai()));

// --- conversations --------------------------------------------------------
$router->get('/admin/conversations',            $protege(fn() => $ctrlConv->ecran()));
$router->post('/admin/conversations/supprimer', $protege(fn() => $ctrlConv->supprimer()));
$router->post('/admin/conversations/vider',     $protege(fn() => $ctrlConv->viderMois()));

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
