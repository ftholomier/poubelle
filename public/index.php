<?php
/**
 * Contrôleur frontal — racine web du site.
 * Tout le code applicatif et les données vivent en dehors de ce dossier.
 */

declare(strict_types=1);

/*
 * Serveur de développement (php -S) : les fichiers réellement présents
 * (CSS, JS, images) sont servis directement. En production, Apache/Nginx
 * s'en charge et ce bloc n'est jamais exécuté.
 */
if (PHP_SAPI === 'cli-server') {
    $requested = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $candidate = __DIR__ . '/' . ltrim((string) $requested, '/');
    $real      = realpath($candidate);
    if ($real !== false && is_file($real) && str_starts_with($real, __DIR__) && !str_ends_with($real, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

$request = new Request();
Response::forceHttps($request);

$router = new Router();
require APP_PATH . '/routes.php';

$router->dispatch($request);
