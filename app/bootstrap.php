<?php
/**
 * Démarrage de l'application : chemins, chargement automatique des classes,
 * gestion des erreurs et réglages.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', APP_ROOT . '/app');
define('DATA_PATH', APP_ROOT . '/data');
define('STORAGE_PATH', APP_ROOT . '/storage');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('TEMPLATES_PATH', APP_ROOT . '/templates');

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP_DIR . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require APP_DIR . '/helpers.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');

$debug = \App\Core\Settings::get('general.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-' . date('Y-m') . '.log');

set_exception_handler(function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $e . "\n");
        exit(1);
    }
    http_response_code(500);
    if ($debug) {
        echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        echo \App\Core\View::render('errors/500', [], 'layout');
    }
});
