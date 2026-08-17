<?php
declare(strict_types=1);

/**
 * Table de routage. Chargée par public/index.php avec $router, $view,
 * $content et $config déjà instanciés.
 *
 * @var App\Core\Router  $router
 * @var App\Core\View    $view
 * @var App\Core\Content $content
 * @var array            $config
 */

use App\Controllers\ApiController;
use App\Controllers\PageController;

$pages = new PageController($view, $content);
$api   = new ApiController($content);

// --- pages éditoriales ---------------------------------------------------
$router->get('/',                  fn() => $pages->accueil());
$router->get('/boutique',          fn() => $pages->simple('boutique', 'boutique'));
$router->get('/galerie',           fn() => $pages->galerie());
$router->get('/reglement-general', fn() => $pages->simple('reglement'));
$router->get('/mentions-legales',  fn() => $pages->simple('mentions-legales'));

// --- hébergements --------------------------------------------------------
$router->get('/hebergements',        fn() => $pages->hebergements());
$router->get('/hebergements/{slug}', fn(array $p) => $pages->hebergement($p['slug']));

// --- pêche ---------------------------------------------------------------
$router->get('/peche',        fn() => $pages->peche());
$router->get('/peche/{slug}', fn(array $p) => $pages->etang($p['slug']));

// --- contact -------------------------------------------------------------
$router->get('/contact',  fn() => $pages->contact());
$router->post('/contact', fn() => $pages->contactEnvoi());

// --- redirections depuis les anciennes URL WordPress ---------------------
$redirections = [
    '/hebergements-vacances' => '/hebergements',
    '/lodge-carelie'         => '/hebergements/lodge-carelie',
    '/lodge-lindus'          => '/hebergements/lodge-lindus',
    '/le-gite'               => '/hebergements/le-gite',
    '/letang-fourchu'        => '/peche/etang-fourchu',
    '/letang-florimont'      => '/peche/etang-florimont',
];
foreach ($redirections as $ancienne => $nouvelle) {
    $router->get($ancienne, function () use ($nouvelle): string {
        http_response_code(301);
        header('Location: ' . url($nouvelle));
        return '';
    });
}

// --- API JSON (socle du futur back-office) -------------------------------
$router->get('/api/hebergements',        fn() => $api->collection('hebergements'));
$router->get('/api/hebergements/{slug}', fn(array $p) => $api->item('hebergements', $p['slug']));
$router->get('/api/peche',               fn() => $api->collection('peche'));
$router->get('/api/peche/{slug}',        fn(array $p) => $api->item('peche', $p['slug']));
$router->get('/api/galerie',             fn() => $api->collection('galerie', 'medias'));

// --- back-office ---------------------------------------------------------
require __DIR__ . '/routes-admin.php';

$router->fallback(fn() => $pages->introuvable());
