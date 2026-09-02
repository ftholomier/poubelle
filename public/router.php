<?php
/**
 * Routeur du serveur de développement intégré à PHP.
 * Les fichiers statiques existants sont servis tels quels, le reste va au contrôleur frontal.
 *
 * Usage : php -S localhost:8000 -t public public/router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file) && !str_ends_with($path, '.php')) {
    return false;
}

require __DIR__ . '/index.php';
