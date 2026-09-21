<?php
/**
 * Configuration non secrète, versionnée.
 * Les clés d'API vivent dans config/secrets.php (hors dépôt).
 */
declare(strict_types=1);

return [
    'site' => [
        'name'        => 'intermittent.fr',
        'baseline'    => "Offres d'emploi et annuaire de CV pour les intermittents du spectacle",
        'url'         => getenv('SITE_URL') ?: 'https://www.intermittent.fr',
        'email'       => 'contact@le-digital.com',
        'since'       => 2005,
        'timezone'    => 'Europe/Paris',
    ],

    // FR est la langue pivot : les autres sont des traductions en cache.
    'i18n' => [
        'pivot'     => 'fr',
        'languages' => [
            'fr' => ['name' => 'Français',  'locale' => 'fr_FR'],
            'en' => ['name' => 'English',   'locale' => 'en_GB'],
            'es' => ['name' => 'Español',   'locale' => 'es_ES'],
            'de' => ['name' => 'Deutsch',   'locale' => 'de_DE'],
            'it' => ['name' => 'Italiano',  'locale' => 'it_IT'],
            'pt' => ['name' => 'Português', 'locale' => 'pt_PT'],
            'nl' => ['name' => 'Nederlands','locale' => 'nl_NL'],
        ],
    ],

    'paths' => [
        'root'      => dirname(__DIR__),
        'data'      => dirname(__DIR__) . '/data',
        'templates' => dirname(__DIR__) . '/templates',
        'public'    => dirname(__DIR__) . '/public',
    ],

    'storage' => [
        'schema'       => 3,      // version de schéma courante
        'lock_ttl'     => 900,    // verrou d'édition : 15 min
        'keep_backups' => 40,
    ],

    'security' => [
        'session_name'     => 'imtt',
        'login_max_tries'  => 5,
        'login_lock_secs'  => 900,
        'reset_ttl'        => 1800,   // lien de récupération : 30 min
        'argon'            => ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2],
        'hsts'             => true,
        'force_https'      => true,
    ],

    'uploads' => [
        'max_bytes'  => 5 * 1024 * 1024,
        'cv_mimes'   => [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ],
        'image_mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
    ],

    'search' => [
        'per_page'     => 6,     // la maquette montre 6 offres puis un encart in-feed
        'infeed_every' => 6,
    ],

    // Les 7 emplacements de la maquette. `enabled` est piloté depuis le back-office.
    'ads' => [
        'client'     => '',      // ca-pub-... (secrets.php peut l'écraser)
        'slots'      => [
            'home_top'     => ['format' => 'leaderboard',  'label' => 'Bannière haute',      'enabled' => true],
            'home_mid'     => ['format' => 'in-article',   'label' => 'Milieu d\'accueil',   'enabled' => true],
            'list_side'    => ['format' => '300x600',      'label' => 'Colonne de liste',    'enabled' => true],
            'list_infeed'  => ['format' => 'in-feed',      'label' => 'In-feed liste',       'enabled' => true],
            'job_below'    => ['format' => 'in-article',   'label' => 'Sous une offre',      'enabled' => true],
            'profile_side' => ['format' => '300x250',      'label' => 'Colonne de profil',   'enabled' => true],
            'dir_bottom'   => ['format' => 'leaderboard',  'label' => 'Bas d\'annuaire',     'enabled' => true],
        ],
    ],

    'reviews' => [
        'place_id' => '',
        'ttl'      => 43200,   // 12 h
    ],

    'regie' => [
        'model'       => 'gemini-2.5-flash',
        'max_context' => 12,
        'enabled'     => true,
    ],
];
