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
        // Lien partenaire présent dans le pied de page du site d'origine.
        'partner_url' => 'https://www.genius-cv.com',
    ],

    // FR est la langue pivot : les autres sont des traductions en cache.
    'i18n' => [
        'pivot'     => 'fr',
        // Les annonces sont traduites : c'est le contenu qu'un visiteur
        // étranger vient chercher. Les CV ne le sont pas par défaut — ce sont
        // des textes personnels, et un CV se lit d'ordinaire dans sa langue.
        'translate_jobs' => true,
        'translate_cv'   => false,
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

        // Seuls ces rôles ouvrent le back-office. Les comptes repris de
        // WordPress (candidate, employer) n'y ont aucun accès.
        'staff_roles'      => ['admin'],

        /**
         * Proxys autorisés à réécrire l'adresse du visiteur et le protocole.
         * Vide = on ne croit que REMOTE_ADDR, ce qui est le bon réglage sur un
         * hébergement mutualisé classique. Derrière un CDN, y placer ses plages
         * (« 173.245.48.0/20 », « 2400:cb00::/32 »…) sans quoi les limiteurs de
         * débit compteraient toutes les visites sur la même empreinte.
         */
        'trusted_proxies'  => [],
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

    /**
     * Durée de vie d'une annonce. Une offre de spectacle se périme vite : une
     * liste pleine d'annonces de 2017 dessert autant le visiteur que le
     * référencement, et Google for Jobs exige une date de fin.
     */
    'jobs' => [
        'lifetime_days'      => 30,   // CDD d'usage, cachets, missions courtes
        'lifetime_days_long' => 45,   // CDI et CDI intermittent
        'archive_after_days' => 180,  // au-delà, la fiche répond 410
    ],

    /**
     * Modération des dépôts publics.
     *   auto   — publication immédiate, sauf soupçon de spam
     *   always — tout passe par la file d'attente
     *   never  — publication immédiate quoi qu'il arrive (déconseillé)
     */
    'moderation' => [
        'mode'      => 'auto',
        'min_secs'  => 4,      // un formulaire rempli plus vite est un robot
        'max_links' => 2,      // au-delà, l'annonce part en modération
    ],

    /** Mise en relation : candidature à une offre, message à un candidat. */
    'contact' => [
        'max_per_hour'   => 5,
        'message_max'    => 4000,
        'attachment_max' => 3 * 1024 * 1024,
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

    /**
     * Offres externes greffées dans les résultats, comme le faisait l'extension
     * Indeed du site WordPress. Réglages repris de l'ancienne installation.
     */
    'sources' => [
        'enabled'   => true,
        'cache_ttl' => 3600,    // 1 h : un agrégateur ne bouge pas plus vite
        'before'    => 5,       // offres externes affichées avant les annonces du site
        'after'     => 25,      // et après
        'country'   => 'fr',
        'location'  => 'France',

        // Les 30 métiers ciblés par l'ancien site. Sert de requête par défaut
        // quand le visiteur n'a pas saisi de mot-clé.
        'query' => 'superviseur or accessoiriste or animateur or assistant or reportage or plateau or '
                 . 'radio or television or sonorisateur or bruiteur or technicien or machiniste or '
                 . 'monteur or coiffeur or cadreur or costume or cinema or producteur or mixeur or '
                 . 'habilleur or maquilleur or costumier or eclairagiste or realisateur or regisseur or '
                 . 'decorateur or intermittent or spectacle or production audiovisuelle',

        'france_travail' => [
            // Domaine « L » du ROME : spectacle, cinéma et audiovisuel.
            // Vider ce tableau pour chercher uniquement sur les mots-clés.
            'rome' => [
                'L1101', 'L1103', 'L1201', 'L1202', 'L1203', 'L1204',
                'L1301', 'L1302', 'L1303', 'L1304',
                'L1501', 'L1502', 'L1503', 'L1504', 'L1505',
                'L1506', 'L1507', 'L1508', 'L1509', 'L1510',
            ],
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
