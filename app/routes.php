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
use App\Core\Langues;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Seo;
use App\Core\Traducteur;

$parametresSite = new Parametres($config['paths']['data'] . '/admin/parametres.json');
$langues    = new Langues($config['paths']['data'] . '/langues.json');
$traducteur = new Traducteur($config['paths']['data'] . '/traductions');

// La langue se lit dans l'adresse : /en/hebergements est servi en anglais,
// /hebergements en français. Le préfixe est retiré avant le routage, si bien
// qu'une seule table de routes sert toutes les langues.
[$langue, $cheminSansLangue] = $langues->detecter(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
if ($langue !== Langues::SOURCE) {
    $_SERVER['REQUEST_URI'] = $cheminSansLangue;
    $content->traduireEn($langue, $traducteur);
}

$seo = new Seo($config['paths']['data'] . '/seo.json', $content);
$seo->prefixerPar($langues->prefixe($langue));

$pages = new PageController($view, $content, $parametresSite, new Mailer($parametresSite), $seo);
$api   = new ApiController($content);

$view->share('seo', $seo);
$view->share('parametres', $parametresSite);
$view->share('langues', $langues);
$view->share('langue', $langue);
$GLOBALS['seo'] = $seo;
$GLOBALS['langue'] = $langue;
$GLOBALS['traducteur'] = $traducteur;

/** Chemin d'une page sans préfixe de langue : c'est ce que le routeur voit. */
$c = static fn(string $cle): string => $seo->cheminSource($cle);

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
$router->get('/sitemap.xml', function () use ($seo, $langues): string {
    header('Content-Type: application/xml; charset=utf-8');
    return $seo->sitemap(base_absolue(), array_keys($langues->publiees()));
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
        // lien() conserve le préfixe de langue : /en/lodge-carelie doit
        // arriver sur /en/hebergements/lodge-carelie, pas sur le français
        header('Location: ' . lien($nouvelle));
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
