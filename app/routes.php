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
$router->get('/',            fn() => $pages->accueil());
$router->get('/la-ferme',    fn() => $pages->simple('la-ferme'));
$router->get('/tarifs',      fn() => $pages->simple('tarifs'));
$router->get('/reglement',   fn() => $pages->simple('reglement'));
$router->get('/galerie',     fn() => $pages->galerie());
$router->get('/acces',       fn() => $pages->simple('acces'));

// --- hébergements --------------------------------------------------------
$router->get('/hebergements',        fn() => $pages->hebergements());
$router->get('/hebergements/{slug}', fn(array $p) => $pages->hebergement($p['slug']));

// --- pêche ---------------------------------------------------------------
$router->get('/peche',        fn() => $pages->peche());
$router->get('/peche/{slug}', fn(array $p) => $pages->etang($p['slug']));

// --- contact -------------------------------------------------------------
$router->get('/contact',  fn() => $pages->contact());
$router->post('/contact', fn() => $pages->contactEnvoi());

// --- API JSON (socle du futur back-office) -------------------------------
$router->get('/api/hebergements',        fn() => $api->collection('hebergements'));
$router->get('/api/hebergements/{slug}', fn(array $p) => $api->item('hebergements', $p['slug']));
$router->get('/api/peche',               fn() => $api->collection('peche'));
$router->get('/api/tarifs',              fn() => $api->document('tarifs'));

$router->fallback(fn() => $pages->introuvable());
