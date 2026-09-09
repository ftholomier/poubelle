<?php
/**
 * Routeur du serveur intégré de PHP (développement uniquement) :
 *   php -S localhost:8000 -t public bin/router.php
 * Les fichiers réels sont servis tels quels, le reste va au contrôleur frontal.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . $path;

if ($path !== '/' && is_file($file)) {
    if (str_ends_with($file, '.php')) {
        require $file;
        return true;
    }
    return false; // fichier statique : le serveur intégré s'en charge
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    require rtrim($file, '/') . '/index.php';
    return true;
}
require __DIR__ . '/../public/index.php';
return true;
