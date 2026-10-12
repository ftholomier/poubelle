<?php
/**
 * Profil du club Paris Rétro (en préparation). Même structure que sochaux.php.
 * « PSG » dans les textes (usage descriptif) ; jamais dans le nom du musée, le logo, le domaine ni les produits.
 */

return [
    'id' => 'paris',
    'sport' => 'football',

    'site' => 'Paris Rétro',
    'site_lc' => 'Paris rétro',
    'site_de' => 'de Paris Rétro',
    // Noms de domaine qui servent ce musée (adresse principale en premier).
    'domains' => ['paris.fcsochauxretro.com', 'paris.epics.club', 'parisretro.sportia.com', 'parisretro.com', 'www.parisretro.com'],
    'domain' => 'paris.fcsochauxretro.com',
    'base_url' => 'https://paris.fcsochauxretro.com',
    'logo_png' => '/assets/clubs/paris/logo.png',
    'logo_svg' => 'public/assets/clubs/paris/logo.svg',

    'club' => 'Paris Saint-Germain',
    'club_de' => 'du Paris Saint-Germain',
    'club_le' => 'le Paris Saint-Germain',
    'sigle' => 'PSG',
    'sigle_de' => 'du PSG',
    'sigle_le' => 'le PSG',
    'sigle_au' => 'au PSG',
    'team' => 'PSG',
    'team_regex' => '/paris\s*(saint|st|sg)|\bpsg\b/iu',
    'city' => 'Paris',
    'city_a' => 'à Paris',
    'gentile' => 'parisien',
    'gentile_pl' => 'Parisiens',
    'nickname' => 'Parisiens',
    'nickname_sg' => 'Parisien',
    // Mot des titres « Les {mascot}s de la seconde période », « Les {mascot}s en portrait » (Sochaux : Lion).
    'mascot' => 'Parisien',
    // Page « Les chiffres » : rivaux du chiffre « derbys » (identifiants des clubs) et son titre.
    'derbies' => ['marseille'],
    'derby_label' => 'Les Classiques',
    // Page « Les chiffres », « Les enfants du pays » : départements de la région du club.
    'home_region' => [
        'depts' => ['75' => 'Paris', '77' => 'Seine-et-Marne', '78' => 'Yvelines', '91' => 'Essonne', '92' => 'Hauts-de-Seine',
            '93' => 'Seine-Saint-Denis', '94' => 'Val-de-Marne', '95' => 'Val-d’Oise'],
        'main' => '75',
        'unit' => '{nickname} nés en Île-de-France',
        'text' => 'Dont {d} à Paris même, sur les fiches dont le lieu de naissance est connu.',
    ],
    'colors' => 'rouge et bleu',
    'founded' => '1970',
    'founded_date' => '1970-08-12',
    'founded_label' => '12 août 1970',
    'centenary_date' => '2070-08-12',
    'centenary_label' => '12 août 2070',
    'club_short' => 'PSG',
    'stadium' => 'Parc des Princes',
    'stadium_short' => 'Parc',
    'stadium_a' => 'au Parc',
    'stadium_full' => 'Parc des Princes',
    'stadium_full_lc' => 'Parc des Princes',
    'official_site' => '',
    'root_domain' => 'sportia.com',
    'vitrine_url' => '',
    'social_handle' => 'ParisRetro',
    'contact_email' => '',

    'theme' => [
        'navy' => '#04224A',
        'navy-2' => '#0B3265',
        'blue' => '#1E4E9A',
        'yellow' => '#E8473B',
        'cream' => '#F2F0EC',
        'paper' => '#FCFBF8',
        'sand' => '#E4E0D8',
        'muted' => '#3B4A6B',
        'mist' => '#CBD3E2',
        'red' => '#C8102E',
        'butter' => '#FBE3DE',
    ],
    'favicon' => '/assets/clubs/paris/favicon.png',
    'app_icons' => '/assets/clubs/paris/app',
    'logo_400' => '/assets/clubs/paris/logo-400.png',
    'share_image' => '/assets/clubs/paris/logo-400.png',
    'newsletter_header' => '/assets/clubs/paris/logo-400.png',

    'transfermarkt' => ['id' => 583, 'slug' => 'fc-paris-saint-germain'],
    'footballdatabase' => '',
    'worldfootball' => 'paris-saint-germain',
    'gallica_query' => 'Paris Saint-Germain',
];
