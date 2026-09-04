<?php
declare(strict_types=1);

/**
 * Configuration du site. Hors /public : jamais servi par le serveur web.
 * Les valeurs sensibles (SMTP, clés) passeront par des variables
 * d'environnement, pas par ce fichier.
 */
return [
    'app' => [
        'name'     => 'Mairie d’Angeot',
        'env'      => getenv('APP_ENV') ?: 'production',
        'debug'    => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        'base_url' => rtrim(getenv('APP_BASE_URL') ?: '', '/'),
        'locale'   => 'fr_FR',
        'timezone' => 'Europe/Paris',
    ],

    'paths' => (static function (): array {
        // config/ se trouve toujours directement sous la racine du projet
        $racine = dirname(__DIR__);
        // implantation à plat (tout dans public_html) : pas de sous-dossier public/
        $web = is_dir($racine . '/public') ? $racine . '/public' : $racine;

        return [
            'root'   => $racine,
            'app'    => $racine . '/app',
            'data'   => $racine . '/data',
            // contenu livré avec le code, recopié dans data/ à la première lecture
            'modele' => $racine . '/data-modele',
            'views'  => $racine . '/views',
            'public' => $web,
            'cache'  => $racine . '/storage/cache',
        ];
    })(),
];
