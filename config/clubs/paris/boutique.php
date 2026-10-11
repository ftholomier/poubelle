<?php
// Boutique de Paris Rétro : reprise des produits du musée historique (mêmes modèles, même mise en page,
// logo du musée), avec les textes et les couleurs transposés. Jamais « PSG » ni le nom officiel du club
// sur un produit (marques déposées) : seulement Paris Rétro, Paris, le Parc, le rouge et le bleu.
return [
    // Couleurs de la charte de Sochaux => charte de Paris Rétro (le blanc, le noir et la crème restent).
    'couleurs' => [
        '#F6C400' => '#E8473B', '#FDC729' => '#E8473B', '#0E1F4D' => '#04224A', '#1E3FA8' => '#1A3F8F', '#1F3FA8' => '#1A3F8F',
    ],
    // Couleurs de textile : « Jaune » devient « Rouge ».
    'textile' => ['Jaune' => ['Rouge', '#C8102E']],
    // Textes des modèles : texte de Sochaux => texte de Paris.
    'textes' => [
        'T-shirt « Jaune et bleu depuis 1928 »' => 'T-shirt « Rouge et bleu depuis 1970 »',
        'Jaune et bleu' => 'Rouge et bleu',
        'depuis 1928' => 'depuis 1970',
        'Un jour Lionceau,' => 'Un jour Parisien,',
        'toujours Lionceau.' => 'toujours Parisien.',
        'Mug « Il fait toujours beau à Bonal »' => 'Mug « Il fait toujours beau au Parc »',
        'Il fait toujours beau à Bonal' => 'Il fait toujours beau au Parc',
        '1928' => '1970',
        'Écharpe « Cent ans de rugissements »' => 'Écharpe « Rouge et bleu pour toujours »',
        'Cent ans de rugissements' => 'Rouge et bleu pour toujours',
        '1928 · Sochaux Rétro · 2028' => '1970 · Paris Rétro · Paris',
        'Poster A3 « Ici, on parle lion couramment »' => 'Poster A3 « Ici, on parle Parc couramment »',
        'Ici, on parle' => 'Ici, on parle',
        'lion couramment.' => 'Parc couramment.',
        'Musée en ligne du FC Sochaux-Montbéliard' => 'Le musée en ligne du football parisien',
        'Tote bag « Café, croissant, Lionceaux »' => 'Tote bag « Café, croissant, Parc des Princes »',
        'Lionceaux.' => 'Parc des Princes.',
        'Sticker « Né pour rugir »' => 'Sticker « Né à Paris »',
        'Né pour rugir' => 'Né à Paris',
        'Jaune et bleu depuis 1928.' => 'Rouge et bleu depuis 1970.',
        'Sochaux Rétro · le musée en ligne du FCSM' => 'Paris Rétro · le musée en ligne',
        'Poster « Ma vie en jaune et bleu »' => 'Poster « Ma vie en bleu & rouge »',
        'Casquette brodée « blason »' => 'Casquette brodée « blason »',
    ],
    // Banque de textes de la boutique (même structure que la collection « boutique-textes »).
    'banque' => [
        ['id' => 'slogans', 'name' => 'Slogans', 'note' => 'Phrases courtes pour le devant des t-shirts, les mugs, les tote bags. Jamais le nom ni le sigle officiels du club (marques déposées). À vérifier sur la base des marques de l’INPI avant impression.', 'items' => [
            'Rouge et bleu depuis 1970.', 'Ici, on parle Parc couramment.', 'Le rouge n’est pas une couleur, c’est une humeur.',
            'Un jour Parisien, toujours Parisien.', 'Mon cœur bat au Parc.', 'Le Parc, ma deuxième maison.',
            'Il fait toujours beau au Parc.', 'Porte de Saint-Cloud, terminus des émotions.', 'Rouge dehors, bleu dedans.',
            'Né à Paris, comme mon club.', 'Paris, c’est aussi une histoire de football.', 'Je ne suis pas têtu, je suis Parisien.',
            'Mon médecin m’a dit d’éviter le stress. J’ai pris un abonnement.', 'Attention : peut parler du Parc pendant des heures.',
            'Café, croissant, Parc des Princes.', 'Le samedi soir, je chante.', 'Supporter à plein temps, philosophe à la mi-temps.',
            'Mes souvenirs sont en rouge et bleu.', 'Dans mes veines ? Du rouge et du bleu.', 'La mémoire a du mordant.',
            'Archiviste du dimanche.', 'Je collectionne les souvenirs, pas les défaites.', 'Rétro un jour, rétro toujours.',
            'Ce maillot a une histoire. Demande-moi.', 'Les légendes ne vieillissent pas.', 'Il y a toujours un match qui raconte ta vie.',
            'Le passé a du talent.', 'Les tribunes, c’est ma famille d’été comme d’hiver.', 'Fidèle au poste depuis le premier coup d’envoi.',
            'Ma passion a un code couleur.', 'Ici, même les souvenirs portent le maillot.', 'Une tribune, des générations.',
            'Paris ne dort jamais, ses supporters non plus.', 'Depuis 1970, le cœur de Paris bat en rouge et bleu.',
        ]],
        ['id' => 'anecdotes', 'name' => 'Anecdotes « Le saviez-vous ? »', 'note' => 'Faits historiques en une phrase, pour le dos des t-shirts, les cartes et les posters. Vérifier chaque fait avant impression.', 'items' => [
            ['Le club naît le 12 août 1970, de la fusion du Paris FC et du Stade Saint-Germain.', '1970 · Histoire du club.'],
            ['Premier trophée en 1982 : la Coupe de France, gagnée aux tirs au but contre Saint-Étienne.', '1982 · Palmarès du club.'],
            ['Premier titre de champion de France en 1986.', '1986 · Palmarès du club.'],
            ['En 1996, à Bruxelles, Paris remporte la Coupe des coupes contre le Rapid de Vienne (1-0).', '1996 · Palmarès européen du club.'],
            ['Le 31 mai 2025, à Munich, Paris gagne sa première Ligue des champions face à l’Inter (5-0).', '2025 · Palmarès européen du club.'],
            ['Le nouveau Parc des Princes est inauguré en 1972.', '1972 · Histoire du stade.'],
        ]],
    ],
];
