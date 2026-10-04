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

// Hébergement mal réglé (version de PHP, extension indispensable absente) : un message clair
// plutôt qu'une erreur 500 muette. Liste complète : back-office › Tâches planifiées › Serveur.
(function (): void {
    $missing = array_values(array_filter(['mbstring', 'intl', 'dom', 'sodium', 'ctype'], fn (string $ext) => !extension_loaded($ext)));
    if (PHP_VERSION_ID >= 80300 && !$missing && defined('PASSWORD_ARGON2ID')) {
        return;
    }
    $what = array_filter([
        PHP_VERSION_ID < 80300 ? 'PHP ' . PHP_VERSION . ' au lieu de PHP 8.3' : '',
        $missing ? 'extension' . (count($missing) > 1 ? 's' : '') . ' PHP absente' . (count($missing) > 1 ? 's' : '') . ' : ' . implode(', ', $missing) : '',
        !defined('PASSWORD_ARGON2ID') ? 'chiffrement des mots de passe Argon2 indisponible (extension sodium)' : '',
    ]);
    $msg = 'Configuration du serveur à compléter : ' . implode(' ; ', $what) . '. Réglage dans cPanel › Sélectionner une version de PHP (version 8.3, onglet Extensions).';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Retry-After: 300');
    header('X-Robots-Tag: noindex');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sochaux Rétro</title></head>'
        . '<body style="margin:0;padding:40px 24px;background:#0E1F4D;color:#F3EDDF;font:20px/1.5 Georgia,serif"><main style="max-width:640px;margin:auto">'
        . '<h1 style="font:900 44px/1 Arial Narrow,sans-serif;text-transform:uppercase">Réglage du serveur en cours</h1>'
        . '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></main></body></html>';
    exit;
})();

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
        echo \App\Core\View::render('errors/500');
    }
});
