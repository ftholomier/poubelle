<?php
declare(strict_types=1);

// Point d'entrée unique : tout le code applicatif est hors du dossier public.
if (PHP_VERSION_ID < 80200) {
    // message clair plutôt qu'une page blanche si l'hébergement utilise une ancienne version de PHP
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Version de PHP trop ancienne</title><p style="font:17px/1.6 sans-serif;max-width:640px;margin:60px auto;padding:0 20px">'
        . 'Ce site a besoin de <strong>PHP 8.2 ou plus récent</strong> (version actuelle : ' . PHP_VERSION . ').<br>'
        . 'Choisissez PHP 8.3 dans l\'espace client de votre hébergement (réglages PHP du site), puis rechargez cette page.</p>';
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\App::run();
