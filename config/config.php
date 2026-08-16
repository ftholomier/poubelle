<?php
declare(strict_types=1);

/**
 * Configuration du site. Hors /public : jamais servi par le serveur web.
 * Les valeurs sensibles (SMTP, clés) passeront par des variables
 * d'environnement, pas par ce fichier.
 */
return [
    'app' => [
        'name'     => 'Étang Fourchu',
        'env'      => getenv('APP_ENV') ?: 'production',
        'debug'    => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        'base_url' => rtrim(getenv('APP_BASE_URL') ?: '', '/'),
        'locale'   => 'fr_FR',
        'timezone' => 'Europe/Paris',
    ],

    'paths' => [
        'root'   => dirname(__DIR__),
        'app'    => dirname(__DIR__) . '/app',
        'data'   => dirname(__DIR__) . '/data',
        'views'  => dirname(__DIR__) . '/views',
        'public' => dirname(__DIR__) . '/public',
        'cache'  => dirname(__DIR__) . '/storage/cache',
    ],

    // Renseigné depuis le site source dès que l'accès réseau est ouvert.
    'reservation' => [
        'url'    => null,   // moteur de réservation externe
        'label'  => 'Réserver',
        'target' => '_blank',
    ],
];
