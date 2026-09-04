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

        /* APP_DATA déplace le contenu vivant ailleurs que dans data/.

           Il n'a qu'un usage, et c'est un usage sérieux : permettre à un
           auditeur de partir d'un contenu neuf sans effacer celui de la
           machine. Le premier script à en avoir eu besoin, aller-retour.py,
           faisait le ménage dans data/ — ce qui détruisait le contenu d'un
           poste de développement et écrasait, au passage, la configuration
           qu'un autre auditeur venait d'y écrire. Une variable
           d'environnement coûte trois lignes et ferme les deux problèmes. */
        $donnees = getenv('APP_DATA');

        return [
            'root'   => $racine,
            'app'    => $racine . '/app',
            'data'   => is_string($donnees) && $donnees !== '' ? rtrim($donnees, '/') : $racine . '/data',
            // contenu livré avec le code, recopié dans data/ à la première lecture
            'modele' => $racine . '/data-modele',
            'views'  => $racine . '/views',
            'public' => $web,
            'cache'  => $racine . '/storage/cache',
        ];
    })(),
];
