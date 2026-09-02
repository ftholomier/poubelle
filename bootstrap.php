<?php
/**
 * Amorçage de l'application : constantes de chemin et autoload PSR-4 maison.
 * Aucune dépendance Composer : PHP natif uniquement.
 */

declare(strict_types=1);

define('APP_ROOT', __DIR__);
define('APP_SRC', APP_ROOT . '/src');
define('APP_CONTENT', APP_ROOT . '/content');
define('APP_VIEWS', APP_ROOT . '/views');
define('APP_PUBLIC', APP_ROOT . '/public');
define('APP_CACHE', APP_ROOT . '/var/cache');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_SRC . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (!is_dir(APP_CACHE)) {
    @mkdir(APP_CACHE, 0775, true);
}
