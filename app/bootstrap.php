<?php
/**
 * Amorçage commun au front public, à l'API et au back-office.
 * Aucune dépendance Composer : autoload PSR-4 maison sur app/.
 */
declare(strict_types=1);

define('APP_START', microtime(true));
define('ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require ROOT . '/app/Support/helpers.php';

$config  = require ROOT . '/config/config.php';
$secrets = is_file(ROOT . '/config/secrets.php') ? require ROOT . '/config/secrets.php' : [];

App\Core\Config::boot($config, is_array($secrets) ? $secrets : []);

date_default_timezone_set(App\Core\Config::get('site.timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');

// Les erreurs ne fuient jamais vers le visiteur : elles partent au journal.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', App\Core\Config::get('paths.data') . '/logs/php-error.log');
error_reporting(E_ALL);

App\Storage\Schema::migrate();
