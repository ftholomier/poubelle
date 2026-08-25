<?php
declare(strict_types=1);

/**
 * Point d'entrée unique — Suisse Immo Recrutement.
 * Serveur intégré PHP : php -S localhost:8000 -t public public/index.php
 */

// Le serveur intégré sert directement les fichiers existants.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . '/' . ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '', '/');
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();

// ---------------------------------------------------------------- vitrine
$router->get('/', [SiteController::class, 'home']);
$router->get('/le-reseau', [SiteController::class, 'network']);
$router->get('/le-metier', [SiteController::class, 'job']);
$router->get('/candidater', [SiteController::class, 'apply']);
$router->get('/merci', [SiteController::class, 'thanks']);
$router->get('/actualites', [SiteController::class, 'news']);
$router->get('/actualites/{slug}', [SiteController::class, 'article']);
$router->get('/contact', [SiteController::class, 'contact']);
$router->get('/mentions-legales', [SiteController::class, 'legal']);
$router->get('/politique-de-confidentialite', [SiteController::class, 'privacy']);
$router->get('/sitemap.xml', [SiteController::class, 'sitemap']);

// Redirections depuis les anciennes URL WordPress.
$legacy = [
    '/devenir-agent-immobilier-bourgogne-franche-comte-doubs' => '/le-reseau',
    '/agent-immobilier-independant-bourgogne-franche-comte' => '/le-metier',
    '/recrutement-candidat-agent-immobilier-bourgogne-franche-comte' => '/candidater',
    '/suisseimmo-recrutement-agent-immobilier-belfort' => '/candidater',
    '/accompagnement-formation-agent-immobilier-bourgogne-franche-comte' => '/contact',
    '/recrutement-agent-immobilier-besancon-belfort-montbeliard-doubs' => '/mentions-legales',
];
foreach ($legacy as $from => $to) {
    $router->get($from, static function () use ($to): void {
        header('Location: ' . url(ltrim($to, '/')), true, 301);
        exit;
    });
}

// -------------------------------------------------------------------- API
$router->get('/api/content', [ApiController::class, 'content']);
$router->get('/api/posts', [ApiController::class, 'posts']);
$router->post('/api/simulate', [ApiController::class, 'simulate']);
$router->post('/api/track', [ApiController::class, 'track']);
$router->post('/api/apply/step', [ApiController::class, 'applyStep']);
$router->post('/api/apply', [ApiController::class, 'apply']);
$router->post('/api/lead', [ApiController::class, 'lead']);
$router->post('/api/bot/chat', [ApiController::class, 'botChat']);

// ------------------------------------------------------------ back-office
$router->any('/admin/login', [AdminController::class, 'login']);
$router->get('/admin/logout', [AdminController::class, 'logout']);
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/candidatures', [AdminController::class, 'applicationsList']);
$router->get('/admin/candidatures/export', [AdminController::class, 'applicationsExport']);
$router->any('/admin/candidatures/{id}', [AdminController::class, 'applicationShow']);
$router->post('/admin/candidatures/{id}/supprimer', [AdminController::class, 'applicationDelete']);
$router->get('/admin/candidatures/{id}/cv', [AdminController::class, 'cv']);
$router->get('/admin/messages', [AdminController::class, 'leads']);
$router->post('/admin/messages/{id}/supprimer', [AdminController::class, 'leadDelete']);
$router->any('/admin/contenu', [AdminController::class, 'contentEdit']);
$router->any('/admin/contenu/{section}', [AdminController::class, 'contentEdit']);
$router->get('/admin/actualites', [AdminController::class, 'posts']);
$router->any('/admin/actualites/{id}', [AdminController::class, 'postEdit']);
$router->post('/admin/actualites/{id}/supprimer', [AdminController::class, 'postDelete']);
$router->any('/admin/reglages', [AdminController::class, 'settings']);
$router->any('/admin/utilisateurs', [AdminController::class, 'users']);
$router->any('/admin/bot', [AdminController::class, 'bot']);
$router->post('/admin/bot/modeles', [AdminController::class, 'botModels']);
$router->post('/admin/bot/test', [AdminController::class, 'botTest']);
$router->post('/admin/bot/documents', [AdminController::class, 'botDocumentAdd']);
$router->post('/admin/bot/documents/{id}/supprimer', [AdminController::class, 'botDocumentDelete']);
$router->get('/admin/emails', [AdminController::class, 'mails']);

$router->fallback(static function (array $p): void {
    if (str_starts_with((string) ($p['path'] ?? ''), '/api/')) {
        json_out(['ok' => false, 'error' => 'Route inconnue.'], 404);
    }
    SiteController::notFound();
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
