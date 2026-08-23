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
use App\Core\Avis;
use App\Core\Langues;
use App\Core\Mailer;
use App\Core\Parametres;
use App\Core\Seo;
use App\Core\Traducteur;

$parametresSite = new Parametres($config['paths']['data'] . '/admin/parametres.json');
$langues    = new Langues($config['paths']['data'] . '/langues.json');
$traducteur = new Traducteur($config['paths']['data'] . '/traductions');

// La langue se lit dans l'adresse : /en/nos-services est servi en anglais,
// /nos-services en français. Le préfixe est retiré avant le routage, si bien
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

$avis = new Avis($parametresSite, $config['paths']['cache'] . '/avis-google.json');

$pages = new PageController($view, $content, $parametresSite, new Mailer($parametresSite), $seo);
$api   = new ApiController($content);

$view->share('seo', $seo);
$view->share('parametres', $parametresSite);
$view->share('langues', $langues);
$view->share('langue', $langue);
$view->share('avis', $avis);
// Disposition du menu : réglée dans le back-office, lue par l'en-tête et le
// gabarit. Partagée ici pour qu'une page n'ait pas à la redemander.
$view->share('menuStyle', $parametresSite->get('apparence.menu', 'lateral'));
$GLOBALS['seo'] = $seo;
$GLOBALS['langue'] = $langue;
$GLOBALS['traducteur'] = $traducteur;

/** Chemin d'une page sans préfixe de langue : c'est ce que le routeur voit. */
$c = static fn(string $cle): string => $seo->cheminSource($cle);

// --- pages éditoriales ---------------------------------------------------
$router->get($c('accueil'),          fn() => $pages->accueil());
$router->get($c('la-societe'),       fn() => $pages->laSociete());
$router->get($c('nos-valeurs'),      fn() => $pages->valeurs());
$router->get($c('mentions-legales'), fn() => $pages->simple('mentions-legales'));

// --- services ------------------------------------------------------------
$router->get($c('nos-services'),            fn() => $pages->services());
$router->get($c('nos-services') . '/{slug}', fn(array $p) => $pages->service($p['slug']));

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
// anciennes adresses du site précédent, plus celles créées par un changement
// de slug depuis le back-office. Elles sont conservées telles quelles : ce sont
// les adresses déjà référencées et déjà partagées.
$redirections = $seo->redirections() + [
    '/pergola-carport' => '/pergolas-carports',
    '/pergola'         => '/pergolas-carports/pergola-bioclimatique',
    '/carport'         => '/pergolas-carports/carport',
    '/nos-services'    => '/pergolas-carports',
    '/nos-valeurs'     => '/savoir-faire',
    '/a-propos'        => '/la-societe',
    '/accueil'         => '/',
];
foreach ($redirections as $ancienne => $nouvelle) {
    if ($ancienne === '' || $ancienne === $nouvelle) {
        continue;
    }
    $router->get($ancienne, function () use ($nouvelle): string {
        http_response_code(301);
        // lien() conserve le préfixe de langue : /en/a-propos doit arriver
        // sur /en/la-societe, pas sur le français
        header('Location: ' . lien($nouvelle));
        return '';
    });
}

// --- API JSON ------------------------------------------------------------
$router->get('/api/services',        fn() => $api->collection('services'));
$router->get('/api/services/{slug}', fn(array $p) => $api->item('services', $p['slug']));
$router->get('/api/valeurs',         fn() => $api->collection('valeurs'));

// --- back-office ---------------------------------------------------------
require __DIR__ . '/routes-admin.php';

$router->fallback(fn() => $pages->introuvable());
