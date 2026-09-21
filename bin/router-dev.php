<?php
/**
 * Routeur pour le serveur intégré de PHP, en développement uniquement :
 *   php -S 127.0.0.1:8080 -t public bin/router-dev.php
 * En production, Apache/nginx sert /public et .htaccess fait le même travail.
 */
$file = __DIR__ . '/../public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($file) && !str_ends_with($file, '.php')) {
    return false;   // le serveur intégré sert le fichier tel quel
}
require __DIR__ . '/../public/index.php';
