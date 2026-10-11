<?php
/**
 * Profil du club : tout ce qui change d'un musée à l'autre (noms, dates, lieux, sources).
 * Lu par App\Core\Club. Les formes « du / au / à » sont écrites en entier, le français
 * ne se devinant pas (« du FCSM », « de l'OM », « du Paris Saint-Germain »).
 */

return [
    'id' => 'sochaux',
    'sport' => 'football',

    // Le musée
    'site' => 'Sochaux Rétro',           // nom du musée
    'site_lc' => 'Sochaux rétro',        // variante en minuscule (logo, en-tête)
    'site_de' => 'de Sochaux Rétro',
    'domain' => 'musee.fcsochauxretro.com',
    'base_url' => 'https://musee.fcsochauxretro.com',
    'logo_png' => '/assets/img/logo-sochaux-retro.png',
    'logo_svg' => 'app/Resources/shop/logo-sochaux-retro.svg',

    // Le club
    'club' => 'FC Sochaux-Montbéliard',  // nom complet
    'club_de' => 'du FC Sochaux-Montbéliard',
    'club_le' => 'le FC Sochaux-Montbéliard',
    'sigle' => 'FCSM',
    'sigle_de' => 'du FCSM',
    'sigle_le' => 'le FCSM',
    'sigle_au' => 'au FCSM',
    'team' => 'Sochaux',                 // nom de l'équipe dans les fiches de match
    'team_regex' => '/sochaux/iu',       // reconnaît l'équipe dans un nom d'équipe
    'city' => 'Sochaux',
    'city_a' => 'à Sochaux',
    'gentile' => 'sochalien',            // adjectif
    'gentile_pl' => 'Sochaliens',
    'nickname' => 'Lionceaux',           // surnom des joueurs
    'nickname_sg' => 'Lionceau',
    'club_short' => 'FC Sochaux',
    'stadium_full' => 'Stade Auguste-Bonal',
    'stadium_full_lc' => 'stade Auguste-Bonal',
    'root_domain' => 'fcsochauxretro.com',
    'vitrine_url' => 'https://www.fcsochauxretro.com',
    'social_handle' => 'SochauxRetro',
    'contact_email' => 'contact@fcsochauxretro.com',
    'centenary_label' => '14 juin 2028',
    'mascot' => 'Lion',
    'colors' => 'jaune et bleu',
    'founded' => '1928',
    'founded_date' => '1928-06-14',
    'founded_label' => '14 juin 1928',
    'centenary_date' => '2028-06-14',
    'stadium' => 'Stade Bonal',
    'stadium_short' => 'Bonal',
    'stadium_a' => 'à Bonal',
    'official_site' => 'https://www.fcsochaux.fr',

    // Sources de données
    'transfermarkt' => ['id' => 750, 'slug' => 'fc-sochaux-montbeliard'],
    'footballdatabase' => '19-sochaux',
    'worldfootball' => 'fc-sochaux',
    'gallica_query' => 'Sochaux',
];
