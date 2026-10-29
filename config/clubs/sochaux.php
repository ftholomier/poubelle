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
    // Noms de domaine qui servent ce musée (adresse principale en premier).
    'domains' => ['musee.fcsochauxretro.com', 'www.fcsochauxretro.com', 'fcsochauxretro.com'],
    'domain' => 'musee.fcsochauxretro.com',
    'base_url' => 'https://musee.fcsochauxretro.com',
    'logo_png' => '/assets/img/logo-sochaux-retro.png',
    'logo_svg' => 'app/Resources/shop/logo-sochaux-retro.svg',

    // Le club
    'club' => 'FC Sochaux-Montbéliard',  // nom complet
    'club_de' => 'du FC Sochaux-Montbéliard',
    'club_le' => 'le FC Sochaux-Montbéliard',
    'club_au' => 'au FC Sochaux-Montbéliard',
    // Nom imposé à certains adversaires, quelle que soit la façon de l'écrire (clé de rapprochement => nom) :
    // appliqué à chaque enregistrement d'une fiche de match (équipes et titre).
    'club_display' => ['marseille' => 'Olympique de Marseille', 'rennes' => 'Stade Rennais FC'],
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
    // Carte des épopées : ville d'où partent les routes (coordonnées [lat, lng]).
    'map_home' => ['name' => 'Montbéliard', 'll' => [47.5122, 6.8111]],
    'root_domain' => 'fcsochauxretro.com',
    'vitrine_url' => 'https://www.fcsochauxretro.com',
    // Rubrique « L'équipe » du musée (vignettes des bénévoles), adresse héritée de WordPress.
    'team_path' => '/supporters/l-equipe-de-sochaux-retro/',
    'social_handle' => 'SochauxRetro',
    // Réseaux sociaux du musée (valeurs de départ de Réglages › Réseaux sociaux ; absents : liens masqués).
    'social' => [
        'facebook' => 'https://www.facebook.com/groups/fcsmvintage',
        'instagram' => 'https://instagram.com/sochauxretro',
        'x' => 'https://x.com/SochauxRetro',
        'youtube' => 'https://youtube.com/@SochauxRetro',
    ],
    'contact_email' => 'contact@fcsochauxretro.com',
    'centenary_label' => '14 juin 2028',
    'mascot' => 'Lion',
    'colors' => 'jaune et bleu',
    'colors_en' => 'yellow-and-blue',
    'founded' => '1928',
    'founded_date' => '1928-06-14',
    'founded_label' => '14 juin 1928',
    'centenary_date' => '2028-06-14',
    'stadium' => 'Stade Bonal',
    'stadium_short' => 'Bonal',
    // Le flipper du musée (/interactif/flipper/), un flipper « au stade » : titre, nom peint sous les
    // bumpers, chapeau des bumpers (sans logo), message quand les bumpers s'emballent.
    'flipper' => ['title' => 'Le Flipper du Lion', 'bumpers' => 'Les Lions', 'cap' => 'LION', 'roar' => 'Le Lion rugit !'],
    // Le babyfoot du musée (/interactif/babyfoot/) : titre, sigle du tableau de score, maillot des
    // figurines, couleur de la barre que tient le joueur.
    'babyfoot' => ['title' => 'Babyfoot des Lionceaux', 'short' => 'FCSM',
        'kit' => ['shirt' => '#F6C400', 'sleeves' => '#1F3FA8', 'shorts' => '#1F3FA8', 'socks' => '#1F3FA8'], 'glow' => '#F6C400'],
    'stadium_a' => 'à Bonal',
    'official_site' => 'https://www.fcsochaux.fr',

    // Thème : couleurs de la charte (variables CSS du même nom). « navy » = couleur principale
    // (fonds, textes), « yellow » = couleur d'accent ; les noms sont historiques.
    'theme' => [
        'navy' => '#0E1F4D',
        'navy-2' => '#1A2D63',
        'blue' => '#1F3FA8',
        'yellow' => '#F6C400',
        'cream' => '#F3EDDF',
        'paper' => '#FFFDF6',
        'sand' => '#E8DFC9',
        'muted' => '#3A4A75',
        'mist' => '#C9CFE0',
        'red' => '#D9342B',
        'butter' => '#FFF3C2',
    ],
    'favicon' => '/assets/img/favicon.png',
    'app_icons' => '/assets/img/app',       // 180.png, 192.png, 512.png…
    'logo_400' => '/assets/img/logo-sochaux-retro-400.png',
    'share_image' => '/assets/img/partage-defaut.png',
    'newsletter_header' => '/assets/img/newsletter-entete.gif',

    // Sources de données
    'transfermarkt' => ['id' => 750, 'slug' => 'fc-sochaux-montbeliard'],
    'footballdatabase' => '19-sochaux',
    'worldfootball' => 'fc-sochaux',
    'gallica_query' => 'Sochaux',
];
