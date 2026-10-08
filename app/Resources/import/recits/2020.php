<?php
/** Grands récits : années 2020 (à relire avant publication). Voir recits.php. */
$sauvetage = [
    ['Communiqué du FC Sochaux après la décision de la DNCG du 11 juillet 2023', 'https://www.fcsochaux.fr/actualites/communique/communique-suite-a-la-decision-de-la-dncg-de-ce-mardi-11-juillet'],
    ['FFF : décisions de la DNCG en appel, 11 juillet 2023', 'https://www.fff.fr/article/10617-dncg-appel-les-decisions-du-11-juillet.html'],
    ['AFP (Soccerway) : « Romain Peugeot renonce à sauver le FC Sochaux »', 'https://fr.soccerway.com/actualites/football-ligue-2-romain-peugeot-renonce-a-sauver-le-fc-sochaux-exclu-de-l2/YgHvMopL'],
    ['AFP (Soccerway) : « Sauvé de la faillite, Sochaux finalement autorisé à jouer en National »', 'https://fr.soccerway.com/actualites/football-ligue-2-sauve-de-la-faillite-sochaux-finalement-autorise-a-jouer-en-national/jJuYvm5K'],
];
$socios = [
    ['macommune.info : « Fin de la levée de fonds mais encore du boulot pour l’association Sociochaux »', 'https://www.macommune.info/fin-de-la-levee-de-fonds-mais-encore-du-boulot-pour-lassociation-sociochaux'],
    ['macommune.info : « Sochaux vit », réaction de l’association Sociochaux', 'https://www.macommune.info/sochaux-vit-reaction-de-lassociation-sociochaux'],
    ['Association Sociochaux (HelloAsso)', 'https://www.helloasso.com/associations/sociochaux'],
];
return [
    [
        'key' => 'saint-etienne-2021', 'year' => 2020.5, 'review' => true,
        'title' => 'L’exploit contre Saint-Étienne 2020 – 2021',
        'image_hint' => ['2021', 'saint-etienne'],
        'intro' => 'Une saison de Ligue 2 solide, une septième place, et un soir de février 2021 où Bonal, vide à cause de la crise sanitaire, voit Sochaux éliminer l’AS Saint-Étienne en Coupe de France.',
        'sections' => [
            ['Une saison de reconstruction', '<p>En 2020-2021, Sochaux termine septième de Ligue 2 sur vingt (12 victoires, 15 nuls, 11 défaites, 45 buts marqués, 37 encaissés). Bedia (12 buts), Weissbeck (10) et Lasme (9) portent l’attaque.</p>'],
            ['Le 11 février 2021', '<p>Après une victoire 1-0 à Nancy au huitième tour, Sochaux reçoit Saint-Étienne, pensionnaire de Ligue 1, en trente-deuxièmes de finale. Les Lionceaux s’imposent 1-0 ({{match:2021-02-11|la fiche du match}}). Le parcours s’arrête au tour suivant à Lyon (5-2), le 6 mars.</p>'],
        ],
    ],
    [
        'key' => 'barrages-2022', 'year' => 2021.5, 'review' => true,
        'title' => 'Si près de la Ligue 1 2021 – 2022',
        'image_hint' => ['2022', 'barrage', 'auxerre'],
        'intro' => 'Cinquième de Ligue 2, Sochaux dispute en mai 2022 les barrages d’accession. Une victoire à Paris, puis une soirée à Auxerre qui se termine aux tirs au but : la Ligue 1 s’éloigne à un penalty près.',
        'sections' => [
            ['Une belle saison', '<p>Avec 19 victoires, 11 nuls et 8 défaites (47 buts marqués, 34 encaissés), Sochaux termine cinquième. Kalulu (10 buts), Mauricio, Weissbeck et Kitala (6 chacun) se partagent les buts. En Coupe de France, Nantes, futur vainqueur, ne passe qu’aux tirs au but à Bonal le 19 décembre 2021 (0-0, 4-5).</p>'],
            ['17 mai : victoire au Paris FC', '<p>Au premier tour des barrages, Sochaux s’impose 2-1 sur le terrain du Paris FC devant 8 000 spectateurs ({{match:2022-05-17|la fiche du match}}).</p>'],
            ['20 mai : la nuit d’Auxerre', '<p>À l’Abbé-Deschamps, devant 17 324 spectateurs, Auxerre et Sochaux ne parviennent pas à se départager (0-0). Aux tirs au but, l’AJA l’emporte 5 à 4 et poursuit vers la Ligue 1 ({{match:2022-05-20|la fiche du match}}).</p>'],
        ],
    ],
    [
        'key' => 'ete-2023-la-chute', 'year' => 2023.4, 'review' => true, 'sources' => $sauvetage,
        'title' => 'L’été où tout a vacillé 2023',
        'image_hint' => ['2023', 'dncg'],
        'intro' => 'Neuvième de Ligue 2 sur le terrain, Sochaux se retrouve à l’été 2023 au bord du gouffre. Faute de garanties financières de son propriétaire, le club est rétrogradé administrativement en National, puis menacé de disparaître.',
        'sections' => [
            ['Une saison normale, puis la DNCG', '<p>Sportivement, 2022-2023 se termine sans drame : neuvième de Ligue 2 (15 victoires, 7 nuls, 16 défaites), avec Sissoko et Doumbia à 13 buts chacun. Mais le propriétaire du club, le groupe chinois Nenking, n’est plus en mesure de garantir le budget, fragilisé par la crise de l’immobilier en Chine.</p>'],
            ['28 juin, 11 juillet : la rétrogradation', '<p>Le 28 juin 2023, la DNCG prononce la rétrogradation administrative du club en National. Le 11 juillet, la commission d’appel la confirme : Nenking n’a pas apporté la trésorerie manquante. On parle d’un trou de 21 millions d’euros. Sans repreneur, le dépôt de bilan menace.</p>'],
            ['Des recours sans issue', '<p>Le club conteste devant le CNOSF, puis devant le tribunal administratif de Paris, qui refuse d’examiner la requête le 3 août faute d’urgence. Un projet de reprise porté par Romain Peugeot ne convainc pas ; il y renonce. À quelques jours de la reprise du championnat, le FC Sochaux-Montbéliard, né en 1928, peut disparaître.</p>'],
        ],
    ],
    [
        'key' => 'sauvetage-2023', 'year' => 2023.6, 'review' => true, 'sources' => array_merge($sauvetage, $socios),
        'title' => 'Le sauvetage : Sochaux vit ! 2023',
        'image_hint' => ['2023', 'sauvetage', 'socios', 'supporters'],
        'intro' => 'En quelques semaines de l’été 2023, des investisseurs régionaux, les collectivités et des milliers de supporters réunis dans l’association Sociochaux sauvent le club. Le FC Sochaux-Montbéliard garde son statut professionnel et repart en National.',
        'sections' => [
            ['Plessis, Wantiez et les investisseurs', '<p>L’ancien président Jean-Claude Plessis et Pierre Wantiez rassemblent un groupe d’investisseurs privés. Les collectivités locales apportent un soutien financier important, et l’association de supporters Sociochaux entre au capital et dans la gouvernance du club. Le projet de relance s’appelle FCSM 2028.</p>'],
            ['La décision qui sauve', '<p>Début août 2023, quelques jours avant la reprise du championnat de National le 11 août, la DNCG rend un avis favorable, aussitôt entériné par le comité exécutif de la FFF : le club garde son statut professionnel pour la saison 2023-2024, avec une masse salariale et des transferts encadrés. Après deux mois de combat, Sochaux vit.</p>'],
            ['Un club qui appartient aussi à ses supporters', '<p>Pour la première fois de son histoire, le FCSM compte ses supporters parmi ses actionnaires. Leur président siège au conseil d’administration de la société qui gère le club. Les historiens retiendront 2023 comme l’année où Sochaux a été sauvé par les siens.</p>'],
        ],
    ],
    [
        'key' => 'sociochaux', 'year' => 2023.7, 'review' => true, 'sources' => $socios,
        'title' => 'Sociochaux, les socios du Lion 2023',
        'image_hint' => ['socios', 'supporters', 'sociochaux'],
        'intro' => 'Ils étaient 300 au printemps, près de 8 000 six semaines plus tard, plus de 11 000 à l’automne : à l’été 2023, l’association Sociochaux devient le visage du sauvetage du FC Sochaux-Montbéliard.',
        'sections' => [
            ['Une levée de fonds populaire', '<p>Pendant l’été 2023, l’association appelle les supporters à devenir socios. En août, elle annonce être passée de 300 à près de 8 000 membres en un mois et demi. Quand la collecte se referme, le 18 septembre 2023, plus de 11 000 souscripteurs ont réuni plus de 770 000 euros.</p>'],
            ['Au capital et au conseil', '<p>Cet argent permet à Sociochaux d’entrer au capital du club et dans ses instances : le président de l’association siège au conseil d’administration de la SASP. Un modèle encore rare dans le football professionnel français.</p>'],
            ['« Sochaux vit »', '<p>À l’annonce de la décision favorable, l’association salue « Sochaux vit » : une victoire collective, celle de milliers de supporters du pays de Montbéliard et d’ailleurs qui ont refusé de voir disparaître leur club.</p>'],
        ],
    ],
    [
        'key' => 'national-2023-2024', 'year' => 2023.8, 'review' => true,
        'title' => 'La renaissance en National 2023 – 2024',
        'image_hint' => ['2024', 'national', 'reims', 'rennes'],
        'intro' => 'Sauvé in extremis, l’effectif reconstruit à la hâte, Sochaux découvre le National. Une huitième place, et une Coupe de France qui remplit Bonal : l’hiver 2024 rappelle que le Lion n’est pas mort.',
        'sections' => [
            ['Une saison pour exister', '<p>En 34 journées, Sochaux compte 12 victoires, 12 nuls et 10 défaites (51 buts marqués, 44 encaissés) : huitième avec 48 points. Hoggas (11 buts) et Zohi (10) mènent l’attaque.</p>'],
            ['La Coupe de France à Bonal', '<p>Après cinq tours régionaux, Sochaux élimine Lorient, club de Ligue 1, le 6 janvier 2024 (2-1, {{match:2024-01-06|la fiche du match}}), puis Reims aux tirs au but le 21 janvier devant 18 754 spectateurs (2-2, 5-4, {{match:2024-01-21|la fiche du match}}). Le 6 février, Rennes met fin au parcours en huitièmes de finale (1-6) devant 19 393 personnes.</p>'],
            ['Le feu vert', '<p>Le 19 juin 2024, la DNCG donne son feu vert, sans restriction, au maintien du club en National. Les finances sont assainies ; le projet peut se tourner vers le terrain.</p>'],
        ],
        'sources' => [['Communiqué du FC Sochaux après la décision de la DNCG du 19 juin 2024', 'https://www.fcsochaux.fr/actualites/communique/communique-suite-a-la-decision-de-la-dncg-de-ce-mercredi-19-juin-2024']],
    ],
    [
        'key' => 'retour-ligue-2-2026', 'year' => 2025.8, 'review' => true,
        'title' => 'Le retour en Ligue 2 2025 – 2026',
        'image_hint' => ['2026', 'montee', 'ligue 2'],
        'intro' => 'Trois ans après l’été du sauvetage, Sochaux retrouve la Ligue 2. Deuxième du National derrière Dijon, l’équipe valide sa montée le 15 mai 2026 devant environ 20 000 spectateurs à Bonal.',
        'sections' => [
            ['Une saison de maturité', '<p>Après une saison 2024-2025 d’apprentissage (9 victoires, 15 nuls, 9 défaites), Sochaux change de dimension en 2025-2026 : 16 victoires, 10 nuls et 6 défaites en championnat, 49 buts marqués et seulement 26 encaissés.</p>'],
            ['15 mai 2026, le nul qui suffit', '<p>Pour la dernière journée, un match nul suffit pour terminer deuxième. Face au Puy, Sochaux fait 2-2 ({{match:2026-05-15|la fiche du match}}) : avec 58 points, derrière Dijon (65), le club monte directement en Ligue 2.</p>'],
            ['De la faillite à la Ligue 2', '<p>Trois saisons après avoir failli disparaître, le FC Sochaux-Montbéliard retrouve le football professionnel de deuxième division, porté par ses investisseurs régionaux, ses collectivités et ses socios. Une remontée qui appartient à tout un pays.</p>'],
        ],
        'sources' => [
            ['France 3 Bourgogne-Franche-Comté : « Trois ans après, le FC Sochaux-Montbéliard remonte en Ligue 2 devant 20 000 personnes »', 'https://france3-regions.franceinfo.fr/bourgogne-franche-comte/doubs/sochaux/trois-ans-apres-le-fc-sochaux-montbeliard-remonte-en-ligue-2-devant-20-000-personnes-3351553.html'],
            ['macommune.info : « Un nul et une montée pour le FC Sochaux qui retrouve la Ligue 2 »', 'https://www.macommune.info/un-nul-et-une-montee-pour-le-fc-sochaux-qui-retrouve-la-ligue-2/'],
        ],
    ],
];
