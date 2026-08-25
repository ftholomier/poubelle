<?php
declare(strict_types=1);

/**
 * Configuration globale de l'application.
 * Aucune base de données : tout est stocké en JSON dans /data.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', APP_ROOT . '/app');
define('DATA_DIR', APP_ROOT . '/data');
define('UPLOAD_DIR', DATA_DIR . '/uploads');
define('PUBLIC_DIR', APP_ROOT . '/public');
define('VIEW_DIR', APP_DIR . '/Views');

// Fuseau / locale
date_default_timezone_set('Europe/Paris');
setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');

// Debug : passer à false en production.
define('APP_DEBUG', getenv('APP_DEBUG') === '1');

// Durée de vie de la session admin (secondes)
define('ADMIN_SESSION_TTL', 60 * 60 * 8);

// Limites d'upload de CV
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED', ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}
