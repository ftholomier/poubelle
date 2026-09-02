<?php

declare(strict_types=1);

/**
 * Contrôleur frontal. Tout passe par ici : pages publiques, API, back-office.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Admin\AdminController;
use App\Config;
use App\Content;
use App\Http\Api;
use App\Http\DiagnosticController;
use App\Http\PageController;
use App\Http\Response;
use App\Http\Router;
use App\View;

Config::boot();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Les fichiers statiques portent une version dans leur URL et peuvent donc être
// gardés longtemps. Le document HTML, lui, transporte la carte des versions :
// s'il était mis en cache, le navigateur continuerait de réclamer d'anciens
// modules après une mise à jour. Il est donc systématiquement revalidé.
// Les réponses de l'API posent ensuite leur propre en-tête, qui remplace celui-ci.
if (!headers_sent()) {
    header('Cache-Control: no-cache');
}

$router = new Router();

// L'ordre compte : « /{slug} » avalerait sinon /api, /admin et /health.
Api::register($router);
AdminController::register($router);
DiagnosticController::register($router);

$router->get('/health', static function (): void {
    Response::json([
        'status' => 'ok',
        'php'    => PHP_VERSION,
        'gd'     => extension_loaded('gd'),
        'cache'  => is_writable(APP_CACHE),
        'pages'  => count(Content::pages()),
    ]);
});

PageController::register($router);

$router->fallback(static function (array $params): void {
    $path = (string) ($params['path'] ?? '/');
    if (str_starts_with($path, '/api/')) {
        Response::error('Route inconnue : ' . $path, 404);
        return;
    }
    PageController::notFound($path);
});

try {
    $router->dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $_SERVER['REQUEST_URI'] ?? '/'
    );
} catch (Throwable $e) {
    error_log('[particules] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
        Response::error('Erreur interne', 500, $e->getMessage());
        exit;
    }

    http_response_code(500);
    try {
        View::render('error', [
            'site'       => Content::site(),
            'page'       => ['title' => 'Erreur', 'slug' => 'erreur'],
            'navigation' => Content::navigation(),
            'shapesData' => [],
            'code'       => 500,
            'message'    => Config::get('debug', false) ? $e->getMessage() : 'Une erreur est survenue.',
        ]);
    } catch (Throwable) {
        // Le contenu lui-même est en cause : on répond en texte brut plutôt
        // que de laisser une page blanche.
        header('Content-Type: text/plain; charset=utf-8');
        echo "Erreur interne.\n";
    }
}
