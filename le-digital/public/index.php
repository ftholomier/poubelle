<?php
/**
 * LE-DIGITAL.com — point d'entrée unique (front controller).
 * Seul le dossier /public est exposé au web ; le code (app/) et les données (storage/) restent hors de portée.
 */
declare(strict_types=1);

// Serveur de développement PHP (php -S) : laisse passer les fichiers statiques existants.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use LD\Controllers\Admin;
use LD\Controllers\Api;
use LD\Controllers\Front;
use LD\Http;
use LD\PageCache;
use LD\Router;
use LD\Settings;

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: microphone=(self), camera=(), geolocation=()');
if (Http::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000');
}

$path = Http::path();
$method = Http::method();

// Redirections 301 configurées dans le back-office (ex. anciennes URL WordPress).
foreach ((array) Settings::get('redirects', []) as $r) {
    $from = '/' . trim((string) ($r['from'] ?? ''), '/');
    if (($r['from'] ?? '') !== '' && strcasecmp($from, $path) === 0 && !empty($r['to'])) {
        Http::redirect((string) $r['to'], 301);
    }
}

// Cache HTML : pages publiques servies sans recalcul.
$isPublic = !str_starts_with($path, '/admin') && !str_starts_with($path, '/api/');
if ($isPublic && $method === 'GET' && PageCache::enabled() && !LD\Auth::peek()) {
    $cached = PageCache::get(Front::cacheKey());
    if ($cached !== null) {
        header('X-LD-Cache: HIT');
        Front::output($cached);
    }
}

$router = new Router();

// Pages publiques
$router->get('/', [Front::class, 'home']);
$router->get('/sur-mesure-ia', [Front::class, 'surMesure']);
$router->get('/formation', [Front::class, 'formation']);
$router->get('/accompagnement', [Front::class, 'accompagnement']);
$router->get('/mes-solutions', [Front::class, 'solutions']);
$router->get('/qui-suis-je', [Front::class, 'qui']);
$router->get('/avis', [Front::class, 'avis']);
$router->get('/blog', [Front::class, 'blog']);
$router->get('/blog/{slug}', [Front::class, 'article']);
$router->get('/on-prend-rdv', [Front::class, 'rdv']);
$router->get('/zones/{slug}', [Front::class, 'zone']);
$router->get('/mentions-legales', [Front::class, 'mentions']);
$router->get('/accessibilite', static fn() => Http::redirect('/mentions-legales#accessibilite', 301));
$router->any('/rdv/annuler/{id}/{token}', [Front::class, 'cancelBooking']);
$router->get('/rdv/agenda.ics', [Front::class, 'icsFeed']);
$router->get('/sitemap.xml', [Front::class, 'sitemap']);
$router->get('/robots.txt', [Front::class, 'robots']);
$router->get('/cron/{key}', [Front::class, 'cron']);

// API publique
$router->post('/api/contact', [Api::class, 'contact']);
$router->post('/api/rappel', [Api::class, 'callback']);
$router->get('/api/rdv/creneaux', [Api::class, 'slots']);
$router->post('/api/rdv', [Api::class, 'book']);
$router->post('/api/vocal', [Api::class, 'voice']);
$router->post('/api/ia/chat', [Api::class, 'chat']);
$router->post('/api/ia/brief', [Api::class, 'brief']);
$router->post('/api/ia/resume-vocal', [Api::class, 'voiceSummary']);
$router->post('/api/ia/devis', [Api::class, 'devis']);
$router->post('/api/ia/analyse', [Api::class, 'analyse']);
$router->post('/api/ia/explique', [Api::class, 'explain']);

// Back-office
Admin::routes($router);

try {
    if (!$router->dispatch($method === 'HEAD' ? 'GET' : $method, $path)) {
        if (str_starts_with($path, '/api/')) {
            Http::fail('Introuvable', 404);
        }
        Front::notFound();
    }
} catch (Throwable $e) {
    error_log('[LD] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (str_starts_with($path, '/api/') || str_starts_with($path, '/admin/api/')) {
        Http::fail(Settings::get('debug') ? $e->getMessage() : 'Erreur interne, réessaie dans un instant.', 500);
    }
    http_response_code(500);
    echo Settings::get('debug') ? '<pre>' . e((string) $e) . '</pre>' : '<!doctype html><meta charset="utf-8"><title>Erreur</title><p style="font-family:sans-serif;padding:40px">Oups, une erreur est survenue. Réessaie dans un instant.</p>';
}
