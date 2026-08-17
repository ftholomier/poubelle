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
use App\Admin\ParametreController;
use App\Admin\PhotoController;
use App\Core\Auth;
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

$admin   = new AdminController($view, $content, $auth);
$edition = new EditionController($view, $content);
$media   = new MediaController($view, $content, $mediatheque);
$photo   = new PhotoController($view, $content, $mediatheque);
$reglage = new ParametreController($view, $parametres, $auth, $mailer, $config['paths']['root'], $config['paths']['public']);

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

$router->get('/admin/hebergements',                $protege(fn() => $edition->hebergements()));
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
$router->get('/admin/reglement',  $protege(fn() => $edition->reglement()));
$router->post('/admin/reglement', $protege(fn() => $edition->reglementEnvoi()));

$router->get('/admin/galerie',          $protege(fn() => $media->galerie()));
$router->post('/admin/galerie/ajout',   $protege(fn() => $media->ajout()));
$router->post('/admin/galerie/retrait', $protege(fn() => $media->retrait()));

$router->get('/admin/parametres',            $protege(fn() => $reglage->ecran()));
$router->post('/admin/parametres/messagerie', $protege(fn() => $reglage->messagerieEnvoi()));
$router->post('/admin/parametres/test',       $protege(fn() => $reglage->test()));
$router->post('/admin/parametres/compte',     $protege(fn() => $reglage->compteEnvoi()));

$router->get('/admin/avance',  $protege(fn() => $edition->avance($_GET['nom'] ?? null)));
$router->post('/admin/avance', $protege(fn() => $edition->avanceEnvoi()));
