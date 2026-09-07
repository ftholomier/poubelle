<?php
/**
 * Le Comptable à Lunettes — bootstrap applicatif.
 * PHP natif, sans dépendance externe, sans base de données.
 */

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1 ou supérieur est requis.');
}

define('APP_START', microtime(true));
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('DATA_PATH', ROOT_PATH . '/data');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('VIEW_PATH', APP_PATH . '/views');

/* ------------------------------------------------------------------ */
/* Autoloader PSR-4 minimaliste                                        */
/* ------------------------------------------------------------------ */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

/* ------------------------------------------------------------------ */
/* Configuration (.env optionnel, jamais versionné)                    */
/* ------------------------------------------------------------------ */
require_once APP_PATH . '/src/Core/Env.php';
App\Core\Env::load(ROOT_PATH . '/.env');

require_once APP_PATH . '/helpers.php';

$config = require APP_PATH . '/config.php';
App\Core\Config::hydrate($config);

/* ------------------------------------------------------------------ */
/* Erreurs : jamais d'affichage brut en production                     */
/* ------------------------------------------------------------------ */
$debug = App\Core\Config::bool('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

set_exception_handler(static function (Throwable $e): void {
    App\Core\Logger::error('Uncaught exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile() . ':' . $e->getLine(),
        'trace'   => $e->getTraceAsString(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (App\Core\Config::bool('app.debug', false)) {
        echo '<pre style="padding:24px;font:14px/1.6 monospace">'
            . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8')
            . '</pre>';
        return;
    }
    $tpl = VIEW_PATH . '/front/error.php';
    if (is_file($tpl)) {
        $code = 500;
        include $tpl;
    } else {
        echo 'Une erreur est survenue.';
    }
});

/* ------------------------------------------------------------------ */
/* Fuseau + encodage                                                   */
/* ------------------------------------------------------------------ */
date_default_timezone_set(App\Core\Config::get('app.timezone', 'Europe/Paris'));
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
setlocale(LC_ALL, 'fr_FR.UTF-8', 'fr_FR', 'French');

/* ------------------------------------------------------------------ */
/* Garde-fous d'installation : arborescence de données garantie        */
/* ------------------------------------------------------------------ */
App\Core\Installer::ensureFilesystem();
