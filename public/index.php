<?php
declare(strict_types=1);

/**
 * Contrôleur frontal — unique point d'entrée PHP exposé par le serveur web.
 * Tout le reste de l'application vit hors de ce répertoire.
 */

// Serveur de développement intégré : laisser passer les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $fichier = __DIR__ . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    if ($fichier !== __DIR__ . '/' && is_file($fichier)) {
        return false;
    }
}

/*
 * Deux implantations possibles sur un hébergement mutualisé :
 *  - recommandée : la racine web pointe sur public/, l'application est au-dessus
 *  - repli       : tout est dans public_html/, ce fichier est alors à la racine
 */
$amorce = dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file($amorce)) {
    $amorce = __DIR__ . '/app/bootstrap.php';
}
if (!is_file($amorce)) {
    http_response_code(500);
    exit('Installation incomplète : le dossier app/ est introuvable.');
}

$config = require $amorce;
$GLOBALS['config'] = $config;

use App\Core\Content;
use App\Core\Router;
use App\Core\View;

$content = new Content($config['paths']['data'], $config['paths']['modele']);
$view    = new View($config['paths']['views']);

$view->share('config', $config);
$view->share('content', $content);

$router = new Router();
require $config['paths']['app'] . '/routes.php';

try {
    echo $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    error_log(sprintf('[%s] %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

    if ($config['app']['debug']) {
        throw $e;
    }

    http_response_code(500);
    echo $view->render('erreur', ['code' => 500, 'titre' => 'Une erreur est survenue']);
}
