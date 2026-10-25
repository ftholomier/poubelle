<?php
/**
 * LE-DIGITAL.com — amorçage de l'application.
 * PHP natif, aucune dépendance, aucune base de données : tout est stocké en JSON dans /storage.
 */
declare(strict_types=1);

define('LD_ROOT', dirname(__DIR__));
define('LD_APP', __DIR__);
define('LD_PUBLIC', LD_ROOT . '/public');
define('LD_STORAGE', LD_ROOT . '/storage');
define('LD_VERSION', '1.0.0');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1 minimum est requis.');
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'LD\\')) {
        return;
    }
    $file = LD_APP . '/lib/' . str_replace('\\', '/', substr($class, 3)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require LD_APP . '/helpers.php';

LD\Store::boot();

$debug = (bool) (LD\Settings::get('debug') ?? false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', LD_STORAGE . '/logs/php-error.log');
