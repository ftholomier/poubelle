<?php
declare(strict_types=1);

/**
 * Table de routage. Chargée par public/index.php avec $router, $view,
 * $content et $config déjà instanciés.
 *
 * Les adresses des pages viennent de Seo : changer un slug dans le
 * back-office change la route, sans toucher à ce fichier.
 *
 * @var App\Core\Router  $router
 * @var App\Core\View    $view
 * @var App\Core\Content $content
 * @var array            $config
 */

use App\Controllers\ApiController;
use App\Controllers\PageController;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Seo;

$parametresSite = new Parametres($config['paths']['data'] . '/admin/parametres.json');
$seo   = new Seo($config['paths']['data'] . '/seo.json', $content);
$pages = new PageController($view, $content, $parametresSite, new Mailer($parametresSite), $seo);
$api   = new ApiController($content);

$view->share('seo', $seo);
$GLOBALS['seo'] = $seo;

/** Chemin courant d'une page, slug éventuellement personnalisé. */
$c = static fn(string $cle): string => $seo->chemin($cle);

// --- pages éditoriales ---------------------------------------------------
$router->get($c('accueil'),          fn() => $pages->accueil());
$router->get($c('boutique'),         fn() => $pages->simple('boutique', 'boutique'));
$router->get($c('galerie'),          fn() => $pages->galerie());
$router->get($c('reglement'),        fn() => $pages->simple('reglement'));
$router->get($c('mentions-legales'), fn() => $pages->simple('mentions-legales'));

// --- hébergements --------------------------------------------------------
$router->get($c('hebergements'),            fn() => $pages->hebergements());
$router->get($c('hebergements') . '/{slug}', fn(array $p) => $pages->hebergement($p['slug']));

// --- pêche ---------------------------------------------------------------
$router->get($c('peche'),            fn() => $pages->peche());
$router->get($c('peche') . '/{slug}', fn(array $p) => $pages->etang($p['slug']));

// --- contact -------------------------------------------------------------
$router->get($c('contact'),  fn() => $pages->contact());
$router->post($c('contact'), fn() => $pages->contactEnvoi());

// --- référencement -------------------------------------------------------
$router->get('/sitemap.xml', function () use ($seo): string {
    header('Content-Type: application/xml; charset=utf-8');
    return $seo->sitemap(base_absolue());
});
$router->get('/robots.txt', function () use ($seo): string {
    header('Content-Type: text/plain; charset=utf-8');
    return $seo->robots(base_absolue());
});

// --- redirections permanentes -------------------------------------------
// anciennes adresses WordPress, plus celles créées par un changement de slug
$redirections = $seo->redirections() + [
    '/hebergements-vacances' => '/hebergements',
    '/lodge-carelie'         => '/hebergements/lodge-carelie',
    '/lodge-lindus'          => '/hebergements/lodge-lindus',
    '/le-gite'               => '/hebergements/le-gite',
    '/letang-fourchu'        => '/peche/etang-fourchu',
    '/letang-florimont'      => '/peche/etang-florimont',
];
foreach ($redirections as $ancienne => $nouvelle) {
    if ($ancienne === '' || $ancienne === $nouvelle) {
        continue;
    }
    $router->get($ancienne, function () use ($nouvelle): string {
        http_response_code(301);
        header('Location: ' . url($nouvelle));
        return '';
    });
}

// --- API JSON ------------------------------------------------------------
$router->get('/api/hebergements',        fn() => $api->collection('hebergements'));
$router->get('/api/hebergements/{slug}', fn(array $p) => $api->item('hebergements', $p['slug']));
$router->get('/api/peche',               fn() => $api->collection('peche'));
$router->get('/api/peche/{slug}',        fn(array $p) => $api->item('peche', $p['slug']));
$router->get('/api/galerie',             fn() => $api->collection('galerie', 'medias'));

// --- back-office ---------------------------------------------------------
require __DIR__ . '/routes-admin.php';

$router->fallback(fn() => $pages->introuvable());
