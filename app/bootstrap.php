<?php
declare(strict_types=1);

/**
 * Amorçage commun : autoload, erreurs, en-têtes de base.
 * Aucune dépendance externe, aucun gestionnaire de paquets.
 */

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, 4));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use App\Config;
use App\Log;

mb_internal_encoding('UTF-8');
date_default_timezone_set(Config::get('APP_TIMEZONE') ?? 'Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');

error_reporting(E_ALL);
ini_set('display_errors', Config::isDebug() ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', Config::storagePath('logs/php.log'));

set_exception_handler(static function (\Throwable $e): void {
    Log::write('error', $e::class . ' : ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (Config::isDebug()) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES) . '</pre>';
        return;
    }
    $view = Config::path('app/views/pages/error.php');
    if (is_file($view)) {
        $code = 500;
        require $view;
    } else {
        echo 'Une erreur est survenue.';
    }
});

// En-têtes de sécurité posés même si le serveur ne lit pas le .htaccess.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header_remove('X-Powered-By');
}
