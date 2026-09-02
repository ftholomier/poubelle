<?php

declare(strict_types=1);

/**
 * Contrôleur frontal. Tout passe par ici : pages et API.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Config;
use App\Content;
use App\Http\Api;
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

$router = new Router();
Api::register($router);

$router->get('/', static function (): void {
    $sections = Content::sections();

    // Le descripteur de forme de chaque section est déposé dans la page :
    // le front sait immédiatement quoi dessiner, sans requête préalable.
    $shapes = [];
    foreach ($sections as $section) {
        $id = (string) $section['id'];
        $shapes[$id] = [
            'id'       => $id,
            'type'     => $section['shape']['type'],
            'count'    => $section['shape']['count'],
            'spin'     => (float) ($section['shape']['spin'] ?? 0),
            'spinAxis' => $section['shape']['spinAxis'] ?? 'y',
            'label'    => $section['shape']['label'] ?? null,
            'shapeUrl' => '/api/shape/' . rawurlencode($id),
        ];
        // Une forme textuelle est tracée par le navigateur : il lui faut la recette complète.
        if ($section['shape']['type'] === 'text') {
            $shapes[$id] += [
                'text'  => $section['shape']['text'] ?? '',
                'font'  => $section['shape']['font'] ?? '900 220px Montserrat, sans-serif',
                'depth' => (float) ($section['shape']['depth'] ?? 0.08),
                'scale' => (float) ($section['shape']['scale'] ?? 1.0),
                'seed'  => (int) ($section['shape']['seed'] ?? 1337),
            ];
        }
    }

    View::render('home', [
        'site'       => Content::site(),
        'sections'   => $sections,
        'shapesData' => $shapes,
    ]);
});

$router->get('/labo', static function (): void {
    View::render('lab', ['site' => Content::site()], 'layout-bare');
});

$router->get('/health', static function (): void {
    Response::json([
        'status' => 'ok',
        'php'    => PHP_VERSION,
        'gd'     => extension_loaded('gd'),
        'cache'  => is_writable(APP_CACHE),
    ]);
});

$router->fallback(static function (array $params): void {
    $path = (string) ($params['path'] ?? '/');
    if (str_starts_with($path, '/api/')) {
        Response::error('Route inconnue : ' . $path, 404);
        return;
    }
    http_response_code(404);
    View::render('error', [
        'site'    => Content::site(),
        'code'    => 404,
        'message' => 'Cette page n\'existe pas.',
    ]);
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
    } else {
        http_response_code(500);
        View::render('error', [
            'site'    => Content::site(),
            'code'    => 500,
            'message' => Config::get('debug', false) ? $e->getMessage() : 'Une erreur est survenue.',
        ]);
    }
}
