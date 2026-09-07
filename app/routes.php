<?php
/**
 * Déclaration des routes (front public, API, back-office).
 * @var App\Http\Router $router
 */

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\MediaController;
use App\Controllers\SiteController;

$langPattern = implode('|', array_map(
    'preg_quote',
    App\Core\Config::arr('i18n.available', ['fr', 'en'])
));

/* ---------------------------------------------------------------- */
/* API publique (JSON)                                              */
/* ---------------------------------------------------------------- */
$router->post('/api/contact',  [ApiController::class, 'contact']);
$router->post('/api/lead',     [ApiController::class, 'lead']);
$router->post('/api/chat',     [ApiController::class, 'chat']);
$router->get('/api/reviews',   [ApiController::class, 'reviews']);
$router->get('/api/search',    [ApiController::class, 'search']);
$router->get('/api/health',    [ApiController::class, 'health']);

/* ---------------------------------------------------------------- */
/* Fichiers téléversés (servis hors racine web, avec contrôle)      */
/* ---------------------------------------------------------------- */
$router->get('/media/img/{file:[A-Za-z0-9._\-]+}', [MediaController::class, 'image']);
$router->get('/media/doc/{file:[A-Za-z0-9._\-]+}', [MediaController::class, 'document']);

/* ---------------------------------------------------------------- */
/* Authentification back-office                                     */
/* ---------------------------------------------------------------- */
$router->any('/admin/login',           [AuthController::class, 'login']);
$router->any('/admin/logout',          [AuthController::class, 'logout']);
$router->any('/admin/mot-de-passe-oublie', [AuthController::class, 'forgot']);
$router->any('/admin/reinitialisation',    [AuthController::class, 'reset']);
$router->any('/admin/installation',     [AuthController::class, 'setup']);

/* ---------------------------------------------------------------- */
/* Back-office                                                      */
/* ---------------------------------------------------------------- */
$router->any('/admin',                  [AdminController::class, 'dashboard']);
$router->any('/admin/pages',            [AdminController::class, 'pages']);
$router->any('/admin/pages/nouvelle',   [AdminController::class, 'pageCreate']);
$router->any('/admin/pages/{slug:[a-z0-9\-]+}', [AdminController::class, 'pageEdit']);
$router->any('/admin/reglages',         [AdminController::class, 'settings']);
$router->any('/admin/apparence',        [AdminController::class, 'appearance']);
$router->any('/admin/traductions',      [AdminController::class, 'translations']);
$router->any('/admin/avis',             [AdminController::class, 'reviews']);
$router->any('/admin/demandes',         [AdminController::class, 'leads']);
$router->any('/admin/medias',           [AdminController::class, 'media']);
$router->any('/admin/documents',        [AdminController::class, 'documents']);
$router->any('/admin/assistant',        [AdminController::class, 'assistant']);
$router->any('/admin/utilisateurs',     [AdminController::class, 'users']);
$router->any('/admin/maintenance',      [AdminController::class, 'maintenance']);
$router->post('/admin/api/{action:[a-z\-]+}', [AdminController::class, 'ajax']);

/* ---------------------------------------------------------------- */
/* Redirections des anciennes adresses WordPress (référencement)    */
/* ---------------------------------------------------------------- */
$legacy = [
    'romain-lemaire-conseil-accompagnement-entreprise-strategie-financiere' => '/qui-suis-je',
    'expert-creation-holding-strategie-financiere-romain-lemaire'           => '/accompagnement',
    'romain-lemaire-expert-comptable-finance-le-comptable-a-lunettes'       => '/contact',
    'expert-en-strategie-financiere-romaine-lemaire'                        => '/mentions-legales',
    'prevention-des-defaillances-lutte-contre-lexercice-illegal-et-les-fraudes-2024' => '/prevention-des-defaillances-2024',
    'livre-blanc-romain-lemaire-ensemble-pour-agir-belles-vues-finances'    => '/ensemble-pour-agir-2023',
    'entreprendre-2022-belles-vues-finances'                                => '/entreprendre-2022',
    'editions-legislatives-2022-belles-vues-finances'                       => '/editions-legislatives-2022',
    'article-forbes-transmission-entreprise-belles-vues-finances'           => '/forbes-2023',
    'challenges-14-decembre-2023-evolutis-conseil'                          => '/challenges-2023',
];
foreach ($legacy as $old => $new) {
    $router->get('/' . $old, static function () use ($new): void {
        App\Http\Response::redirect($new, 301);
    });
}

/* ---------------------------------------------------------------- */
/* Site public                                                      */
/* ---------------------------------------------------------------- */
$router->get('/sitemap.xml', [SiteController::class, 'sitemap']);
$router->get('/robots.txt',  [SiteController::class, 'robots']);

$router->get('/', [SiteController::class, 'home']);
$router->get('/{lang:' . $langPattern . '}', [SiteController::class, 'home']);
$router->get('/{lang:' . $langPattern . '}/{slug:[a-z0-9\-]+}', [SiteController::class, 'page']);
$router->get('/{slug:[a-z0-9\-]+}', [SiteController::class, 'page']);

$router->fallback([SiteController::class, 'notFound']);
