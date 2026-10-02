<?php
// Routeur du serveur de développement PHP : fichiers statiques servis tels quels.
$f = __DIR__ . '/../public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_URI'] !== '/' && is_file($f)) {
    return false;
}
require __DIR__ . '/../public/index.php';
