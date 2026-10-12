<?php
// Accueil : images par défaut des tuiles et des encarts. Photos libres de droits livrées avec le code
// (config/clubs/paris/photos.php, crédit auteur + licence) tant que le musée n'a pas ses propres médias.
// « reserves » sans image : premier objet publié de la collection.
return [
    'reserves' => [
        'photos' => 'club/parc-des-princes-match-1933.webp',
        'supporters' => 'club/tribune-boulogne-2008.webp',
    ],
    // Fond flouté du mur du vestiaire.
    'vestiaire' => 'club/parc-des-princes-tribune-borelli-2013.webp',
    'teasers' => [
        'quiz' => 'club/parc-des-princes-psg-nancy-2012.webp',
        'frise' => 'club/parc-des-princes-pelouse-1972.webp',
        'contribuer' => 'club/parc-des-princes-france-pays-bas-1936.webp',
    ],
    // Cases restées vides de l'accueil (« Ce jour-là », « Il y a N ans », photo du jour, grand slider) :
    // voir App\Front\Pages::fillEmpty().
    'cases' => [
        'photos' => [
            'club/parc-des-princes-exterieur-2017.webp',
            'club/parc-des-princes-tribune-auteuil-2013.webp',
            'club/parc-des-princes-pelouse-1972.webp',
            'club/tribune-boulogne-2008.webp',
            'club/parc-des-princes-euro-2016.webp',
            'club/parc-des-princes-match-1933.webp',
            'club/avenue-du-parc-des-princes-2021.webp',
            'club/parc-des-princes-psg-nancy-2012.webp',
            'club/stade-georges-lefevre-2026.webp',
            'club/parc-des-princes-tribune-borelli-2013.webp',
            'club/parc-des-princes-pelouse-2018.webp',
            'club/camp-des-loges-2022.webp',
        ],
        'slider' => [
            ['image' => 'club/parc-des-princes-exterieur-2017.webp', 'kind' => 'Le stade', 'title' => 'Le Parc des Princes', 'text' => 'Porte de Saint-Cloud, la maison du club depuis 1974.', 'href' => '/saisons/', 'focus' => '50% 40%'],
            ['image' => 'club/parc-des-princes-pelouse-1972.webp', 'kind' => 'Archives', 'title' => 'Le nouveau Parc, 1972', 'text' => 'Mars 1972 : on pose la pelouse du stade de Roger Taillibert.', 'href' => '/interactif/frise/', 'focus' => '50% 50%'],
            ['image' => 'club/parc-des-princes-psg-nancy-2012.webp', 'kind' => 'Ambiance', 'title' => 'Un soir au Parc', 'text' => 'Matchs, saisons, joueurs : toute l’histoire du club.', 'href' => '/matchs/', 'focus' => '50% 50%'],
            ['image' => 'club/stade-georges-lefevre-2026.webp', 'kind' => 'Les origines', 'title' => 'Saint-Germain-en-Laye', 'text' => 'Le stade Georges-Lefèvre, terrain du Stade saint-germanois, l’un des deux clubs fondateurs.', 'href' => '/interactif/frise/#1970', 'focus' => '50% 50%'],
        ],
    ],
];
