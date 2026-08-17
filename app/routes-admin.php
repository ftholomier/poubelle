<?php
declare(strict_types=1);

/**
 * Routes du back-office. Incluses depuis app/routes.php.
 *
 * @var App\Core\Router  $router
 * @var App\Core\View    $view
 * @var App\Core\Content $content
 * @var array            $config
 */

use App\Admin\AdminController;
use App\Admin\EditionController;
use App\Admin\MediaController;
use App\Admin\MiseAJourController;
use App\Admin\ParametreController;
use App\Admin\PhotoController;
use App\Admin\SeoController;
use App\Core\Auth;
use App\Core\Deploiement;
use App\Core\Mailer;
use App\Core\Mediatheque;
use App\Core\Parametres;
use App\Core\Seo;

$auth = new Auth(
    $config['paths']['data'] . '/admin/compte.json',
    dirname($config['paths']['data']) . '/storage/cache/tentatives.json'
);

$mediatheque = new Mediatheque($config['paths']['public'] . '/assets/img/site');
$parametres  = new Parametres($config['paths']['data'] . '/admin/parametres.json');
$mailer      = new Mailer($parametres);
$deploiement = new Deploiement($config['paths']['root'], $parametres);

$admin   = new AdminController($view, $content, $auth);
$edition = new EditionController($view, $content, $mediatheque);
$media   = new MediaController($view, $content, $mediatheque);
$photo   = new PhotoController($view, $content, $mediatheque);
$majour  = new MiseAJourController($view, $deploiement);
$refer   = new SeoController($view, $content, $seo, $mediatheque);
$reglage = new ParametreController($view, $parametres, $content, $auth, $mailer, $config['paths']['root'], $config['paths']['public']);

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
$router->get('/admin',            $protege(fn() => $admin->tableauDeBord()));
$router->get('/admin/site',       $protege(fn() => $edition->site()));
$router->post('/admin/site',      $protege(fn() => $edition->siteEnvoi()));
$router->get('/admin/accueil',    $protege(fn() => $edition->accueil()));
$router->post('/admin/accueil',   $protege(fn() => $edition->accueilEnvoi()));

// diaporama du bandeau d'accueil
$router->post('/admin/accueil/hero/ajout',                $protege(fn() => $edition->heroAjout()));
$router->post('/admin/accueil/hero/classer',               $protege(fn() => $edition->heroClasser()));
$router->post('/admin/accueil/hero/{rang}/publication',   $protege(fn(array $p) => $edition->heroPublication((int) $p['rang'])));
$router->post('/admin/accueil/hero/{rang}/ordre',         $protege(fn(array $p) => $edition->heroOrdre((int) $p['rang'])));
$router->post('/admin/accueil/hero/{rang}/supprimer',     $protege(fn(array $p) => $edition->heroSupprimer((int) $p['rang'])));

$router->get('/admin/hebergements',                $protege(fn() => $edition->hebergements()));
$router->post('/admin/hebergements/creer',         $protege(fn() => $edition->hebergementCreer()));
$router->post('/admin/hebergements/{slug}/publication', $protege(fn(array $p) => $edition->hebergementPublication($p['slug'])));
$router->post('/admin/hebergements/{slug}/supprimer', $protege(fn(array $p) => $edition->hebergementSupprimer($p['slug'])));
$router->get('/admin/hebergements/{slug}/photos',  $protege(fn(array $p) => $photo->hebergement($p['slug'])));
$router->post('/admin/hebergements/{slug}/photos', $protege(fn(array $p) => $photo->envoi('hebergements', $p['slug'])));
$router->get('/admin/hebergements/{slug}',         $protege(fn(array $p) => $edition->hebergement($p['slug'])));
$router->post('/admin/hebergements/{slug}',        $protege(fn(array $p) => $edition->hebergementEnvoi($p['slug'])));

$router->get('/admin/peche',                $protege(fn() => $edition->peche()));
$router->post('/admin/peche/regles',        $protege(fn() => $edition->pecheReglesEnvoi()));
$router->get('/admin/peche/{slug}/photos',  $protege(fn(array $p) => $photo->etang($p['slug'])));
$router->post('/admin/peche/{slug}/photos', $protege(fn(array $p) => $photo->envoi('peche', $p['slug'])));
$router->get('/admin/peche/{slug}',         $protege(fn(array $p) => $edition->etang($p['slug'])));
$router->post('/admin/peche/{slug}',        $protege(fn(array $p) => $edition->etangEnvoi($p['slug'])));

$router->get('/admin/boutique',   $protege(fn() => $edition->boutique()));
$router->post('/admin/boutique',  $protege(fn() => $edition->boutiqueEnvoi()));
$router->post('/admin/boutique/creer',                  $protege(fn() => $edition->boutiqueCreer()));
$router->post('/admin/boutique/{rang}/publication',     $protege(fn(array $p) => $edition->boutiquePublication((int) $p['rang'])));
$router->post('/admin/boutique/{rang}/ordre',           $protege(fn(array $p) => $edition->boutiqueOrdre((int) $p['rang'])));
$router->post('/admin/boutique/{rang}/photo',           $protege(fn(array $p) => $edition->boutiquePhoto((int) $p['rang'])));
$router->post('/admin/boutique/{rang}/supprimer',       $protege(fn(array $p) => $edition->boutiqueSupprimer((int) $p['rang'])));
$router->get('/admin/reglement',  $protege(fn() => $edition->reglement()));
$router->post('/admin/reglement', $protege(fn() => $edition->reglementEnvoi()));

$router->get('/admin/galerie',          $protege(fn() => $media->galerie()));
$router->post('/admin/galerie/ajout',   $protege(fn() => $media->ajout()));
$router->post('/admin/galerie/publication', $protege(fn() => $media->publication()));
$router->post('/admin/galerie/categorie',   $protege(fn() => $media->categorie()));
$router->post('/admin/galerie/ordre',       $protege(fn() => $media->ordre()));
$router->post('/admin/galerie/retrait', $protege(fn() => $media->retrait()));

$router->get('/admin/parametres',            $protege(fn() => $reglage->ecran()));
$router->post('/admin/parametres/messagerie', $protege(fn() => $reglage->messagerieEnvoi()));
$router->post('/admin/parametres/test',       $protege(fn() => $reglage->test()));
$router->post('/admin/parametres/mesure',      $protege(fn() => $reglage->mesureEnvoi()));
$router->post('/admin/parametres/compte',     $protege(fn() => $reglage->compteEnvoi()));
$router->post('/admin/parametres/droits',     $protege(fn() => $reglage->droitsEnvoi()));

$router->get('/admin/referencement',  $protege(fn() => $refer->ecran()));
$router->post('/admin/referencement/general', $protege(fn() => $refer->generalEnvoi()));
$router->post('/admin/referencement/page/{cle}', $protege(fn(array $p) => $refer->pageEnvoi($p['cle'])));
$router->post('/admin/referencement/fiche/{cle}/{slug}', $protege(fn(array $p) => $refer->ficheEnvoi($p['cle'], $p['slug'])));
$router->post('/admin/referencement/redirection',         $protege(fn() => $refer->redirectionAjout()));
$router->post('/admin/referencement/redirection/retrait', $protege(fn() => $refer->redirectionRetrait()));

$router->get('/admin/mises-a-jour',             $protege(fn() => $majour->ecran()));
$router->post('/admin/mises-a-jour/verifier',    $protege(fn() => $majour->verifier()));
$router->post('/admin/mises-a-jour/appliquer',   $protege(fn() => $majour->appliquer()));
$router->post('/admin/mises-a-jour/sauvegarder', $protege(fn() => $majour->sauvegarder()));
$router->post('/admin/mises-a-jour/restaurer',   $protege(fn() => $majour->restaurer()));

$router->get('/admin/avance',  $protege(fn() => $edition->avance($_GET['nom'] ?? null)));
$router->post('/admin/avance', $protege(fn() => $edition->avanceEnvoi()));
