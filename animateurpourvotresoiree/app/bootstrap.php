<?php
declare(strict_types=1);

/*
 * Animateur Pour Votre Soirée — amorçage de l'application.
 * Tout le code vit hors de /public : seul public/index.php est exposé.
 */

define('APVS_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('CONFIG_PATH', BASE_PATH . '/config');
define('APP_VERSION', '1.0.0');

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Paris');
ini_set('default_charset', 'UTF-8');

App\Core\Env::boot(CONFIG_PATH . '/.env');
App\Core\ErrorHandler::register();
