<?php
declare(strict_types=1);

/**
 * Amorçage de l'application. Chargé par public/index.php, qui est le seul
 * fichier PHP exposé au serveur web.
 */

$config = require dirname(__DIR__) . '/config/config.php';

date_default_timezone_set($config['app']['timezone']);
setlocale(LC_TIME, $config['app']['locale'] . '.UTF-8', $config['app']['locale']);

if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Autoload PSR-4 : App\ -> app/
spl_autoload_register(static function (string $class) use ($config): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $config['paths']['app'] . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/* Les helpers lisent la configuration par $GLOBALS : la poser ici, et non
   dans public/index.php seul, pour que les points d'entrée qui amorcent
   l'application sans passer par le contrôleur frontal — outils, tâches
   planifiées, auditeurs — disposent des mêmes fonctions. */
$GLOBALS['config'] = $config;

require __DIR__ . '/helpers.php';

return $config;
