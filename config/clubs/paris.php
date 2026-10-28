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
    'domains' => ['paris.musee.club', 'paris.fcsochauxretro.com', 'paris.epics.club', 'parisretro.sportia.com', 'parisretro.com', 'www.parisretro.com'],
    'domain' => 'paris.musee.club',
    'base_url' => 'https://paris.musee.club',
    // Anciennes adresses renvoyées (301) vers le domaine principal.
    'redirect_from' => ['paris.fcsochauxretro.com'],
    'logo_png' => '/assets/clubs/paris/logo.png',
    'logo_svg' => 'public/assets/clubs/paris/logo.svg',

    'club' => 'Paris Saint-Germain',
    'club_de' => 'du Paris Saint-Germain',
    'club_le' => 'le Paris Saint-Germain',
    'club_au' => 'au Paris Saint-Germain',
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
    'colors_en' => 'red-and-blue',
    'founded' => '1970',
    'founded_date' => '1970-08-12',
    'founded_label' => '12 août 1970',
    'centenary_date' => '2070-08-12',
    'centenary_label' => '12 août 2070',
    // Centenaire lointain : compte à rebours, page /centenaire/, 100 moments, Onze et album « du centenaire »,
    // objectif des dons et bandeau du back-office coupés (App\Front\Site::centenaryCountdown()).
    'centenaire' => false,
    'club_short' => 'PSG',
    'stadium' => 'Parc des Princes',
    'stadium_short' => 'Parc',
    // Le flipper du musée (/interactif/flipper/) : textes du décor, lettres des cibles, année des couloirs.
    'flipper' => ['title' => 'Le Flipper du Parc', 'letters' => 'PARIS', 'digits' => '1970', 'ramp' => 'Rampe du Parc', 'bumpers' => 'Le Kop',
        'cap' => 'PSG', 'kickback' => 'Camp des Loges', 'roar' => 'Le Parc s’enflamme !'],
    'stadium_a' => 'au Parc',
    'stadium_full' => 'Parc des Princes',
    'stadium_full_lc' => 'Parc des Princes',
    // Carte des épopées : ville d'où partent les routes (coordonnées [lat, lng], Parc des Princes).
    'map_home' => ['name' => 'Paris', 'll' => [48.8414, 2.2530]],
    'official_site' => '',
    'root_domain' => 'sportia.com',
    // Pas (encore) de site d'association : lien « L'association » masqué.
    'vitrine_url' => '',
    // Rubrique « L'équipe » du musée (vignettes des bénévoles).
    'team_path' => '/supporters/l-equipe-du-musee/',
    'social_handle' => 'ParisRetro',
    // Réseaux sociaux du musée : aucun compte pour l'instant (liens masqués, à régler dans Réglages › Réseaux sociaux).
    'social' => [],
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
    // Les chiffres : Buffon arrivé à 40 ans (2018) ; au-delà, une date de naissance est jugée fautive.
    'chiffres_age_arrivee_max' => 41,
    // Chiffres masqués (contrôle d'octobre 2026, scratchpad ref/controle-page-chiffres.md) : données trop
    // lacunaires pour Paris, résultats faux. Entraîneurs absents de la plupart des saisons (Blanc,
    // Ancelotti, Emery, Tuchel, Luis Enrique…), cartons et arbitres relevés sur une minorité de matchs,
    // penaltys arrêtés très incomplets. « pays » rendu le 12/10/2026 : pays de naissance connu pour 521 des 528
    // joueurs ayant disputé un match officiel (98,7 %, lieux complétés d'après Wikipédia, deux sources concordantes).
    'chiffres_masques' => ['jaunes', 'rouges', 'arbitre', 'penalty-arrete'],
];
