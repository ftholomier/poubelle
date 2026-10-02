<?php
// Routeur pour le serveur PHP intégré (développement) :
//   php -S localhost:8000 -t public bin/dev-router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/../public' . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/../public/index.php';
