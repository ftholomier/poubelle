<?php
/**
 * Textes de départ des pages du site de l'association (modifiables dans le back-office :
 * Site de l'association › Pages). Chaque page : « _label » (nom dans le back-office), titres,
 * accroches, textes (HTML simple), images de la médiathèque, listes.
 * « a_verifier » : liste des points inventés faute d'information, à confirmer par l'association.
 */

return [
    'accueil' => [
        '_label' => 'Accueil',
        'hero_eyebrow' => 'Association Sochaux Rétro',
        'hero_title' => 'Faire vivre la mémoire du FC Sochaux-Montbéliard',
        'hero_lead' => 'Depuis 1928, le club a écrit une histoire immense. Nous la rassemblons, la préservons et la partageons avec tous les Lionceaux, d’ici et d’ailleurs.',
        'hero_image' => '2024/01/1.-Supporter.jpeg',
        'intro_title' => 'Une passion, une mission',
        'intro_text' => '<p>Sochaux Rétro, c’est d’abord une communauté de supporters passionnés par l’histoire du FCSM. Photos, affiches, programmes, coupures de presse, vidéos, souvenirs : nous collectons tout ce qui raconte près d’un siècle de jaune et de bleu.</p><p>Notre ambition : que cette mémoire ne se perde pas, et qu’elle reste accessible gratuitement, à tous, jusqu’au centenaire du club en 2028 et bien au-delà.</p>',
        'museum_title' => 'Le musée en ligne du FCSM',
        'museum_text' => 'Des milliers de fiches de matchs, de joueurs, d’entraîneurs, de dirigeants et de supporters, des photos d’archives, des vidéos et des jeux : notre grand projet est ouvert à tous, gratuitement.',
        'centenary_title' => 'Objectif centenaire',
        'centenary_text' => 'Le FC Sochaux-Montbéliard aura 100 ans. Nous préparons ce rendez-vous avec vous : un moment d’histoire chaque semaine, le Onze de légende élu par le public, et des archives sauvées pour les générations futures.',
        'support_title' => 'Rejoignez l’aventure',
        'support_text' => 'Adhérer, donner un peu de temps, confier une archive ou soutenir nos projets : chacun peut aider à écrire la suite.',
        'newsletter_title' => 'Ce jour-là, dans votre boîte mail',
        'newsletter_text' => 'Chaque semaine, les matchs du passé, le compte à rebours du centenaire et les nouvelles du musée.',
    ],

    'association' => [
        '_label' => 'Qui sommes-nous',
        'title' => 'Qui sommes-nous ?',
        'lead' => 'Une association de passionnés, au service de la mémoire du FC Sochaux-Montbéliard et de tous ceux qui l’aiment.',
        'image' => '2025/01/FC-SOCHAUX-1977-78.jpg',
        'story_title' => 'Notre histoire',
        'story' => '<p>Tout est parti d’une évidence partagée par des milliers de supporters : l’histoire du FC Sochaux-Montbéliard est trop belle pour rester dispersée dans les greniers, les albums de famille et les mémoires.</p><p>Autour d’un groupe et d’une page sur les réseaux sociaux, Sochaux Rétro a d’abord rassemblé des photos, des vidéos et des souvenirs. La communauté a grandi, jusqu’à compter aujourd’hui plus de 11 000 passionnés, et plus de 1 400 vidéos ont été publiées sur notre chaîne YouTube.</p><p>Pour aller plus loin, l’association a lancé un projet à la hauteur du club : un véritable musée en ligne, gratuit, qui retrace match après match, joueur après joueur, près d’un siècle d’histoire sochalienne.</p>',
        'missions_title' => 'Nos missions',
        'missions' => [
            ['icon' => '◎', 'title' => 'Rassembler', 'text' => 'Collecter photos, documents, objets, vidéos et témoignages auprès des supporters, des anciens joueurs et de leurs familles.'],
            ['icon' => '▣', 'title' => 'Préserver', 'text' => 'Numériser, restaurer, légender et classer chaque pièce pour qu’elle traverse le temps.'],
            ['icon' => '→', 'title' => 'Partager', 'text' => 'Rendre cette mémoire accessible à tous, gratuitement : musée en ligne, vidéos, réseaux sociaux, rencontres.'],
            ['icon' => '★', 'title' => 'Transmettre', 'text' => 'Faire découvrir l’histoire du club aux plus jeunes et faire revivre leurs souvenirs aux plus anciens.'],
        ],
        'values_title' => 'Ce qui nous anime',
        'values' => [
            ['title' => 'La passion', 'text' => 'Nous sommes des supporters avant tout. Le jaune et le bleu nous rassemblent.'],
            ['title' => 'La rigueur', 'text' => 'Chaque date, chaque score, chaque nom est vérifié par nos historiens bénévoles, sources à l’appui.'],
            ['title' => 'Le partage', 'text' => 'Tout ce que nous rassemblons est mis gratuitement à la disposition de tous.'],
            ['title' => 'Le respect', 'text' => 'Des droits des auteurs, des familles et des donateurs, comme de toutes les époques du club.'],
        ],
        'independence' => '<p>Sochaux Rétro est une association indépendante, animée par des bénévoles. Elle n’est pas liée au FC Sochaux-Montbéliard, dont elle partage simplement la passion.</p>',
        'a_verifier' => ['Le récit des débuts de l’association (dates, fondateurs, étapes) est à compléter avec la vraie histoire.', 'La phrase sur l’indépendance vis-à-vis du club est à confirmer.'],
    ],

    'equipe' => [
        '_label' => 'L’équipe',
        'title' => 'L’équipe',
        'lead' => 'Des bénévoles passionnés, chacun avec ses talents : historiens, collectionneurs, vidéastes, rédacteurs, photographes…',
        'empty_text' => '<p>Les membres du bureau et les responsables de pôles se présenteront très bientôt ici.</p>',
        'poles_title' => 'Nos pôles de bénévoles',
        'join_title' => 'Envie de nous rejoindre ?',
        'join_text' => 'Il n’est pas nécessaire d’être un spécialiste : il suffit d’aimer le club et d’avoir un peu de temps à partager.',
    ],

    'documents' => [
        '_label' => 'Statuts et documents',
        'title' => 'Statuts et documents',
        'lead' => 'Les textes qui régissent l’association et les comptes rendus de sa vie démocratique.',
        'empty_text' => '<p>Les statuts et les comptes rendus d’assemblée générale seront bientôt téléchargeables ici. En attendant, ils sont disponibles sur simple demande.</p>',
        'governance' => '<p>Sochaux Rétro est une association régie par la loi du 1er juillet 1901. Elle est administrée par un bureau élu par l’assemblée générale des adhérents, qui se réunit au moins une fois par an pour approuver les comptes et décider des grandes orientations.</p>',
        'a_verifier' => ['La description de la gouvernance (bureau, assemblée générale annuelle) est à confirmer avec les statuts.'],
    ],

    'actions' => [
        '_label' => 'Nos actions (accueil de la rubrique)',
        'title' => 'Nos actions',
        'lead' => 'Du musée en ligne aux après-midi souvenirs, tout ce que fait l’association pour la mémoire du club.',
    ],

    'actualites' => [
        '_label' => 'Actualités (liste)',
        'title' => 'Actualités',
        'lead' => 'La vie de l’association, les nouveautés du musée et les grands rendez-vous du centenaire.',
        'empty_text' => 'Les premières actualités arrivent très bientôt.',
    ],

    'agenda' => [
        '_label' => 'Agenda (liste)',
        'title' => 'Agenda',
        'lead' => 'Rencontres, rendez-vous en ligne et grands moments à venir.',
        'empty_text' => 'Aucun rendez-vous programmé pour le moment : revenez bientôt, ou abonnez-vous à la newsletter pour être prévenu.',
    ],

    'soutenir' => [
        '_label' => 'Nous soutenir',
        'title' => 'Nous soutenir',
        'lead' => 'L’association vit grâce à ses adhérents, ses bénévoles et ses donateurs. Chaque geste compte pour sauver un siècle d’histoire.',
        'image' => '2024/10/IMG_20221102_151814_edit_352676538919621-scaled.jpg',
        'why_title' => 'Pourquoi nous soutenir ?',
        'why' => '<p>Numériser une affiche, restaurer une photo d’équipe, héberger le musée, produire des vidéos, organiser des rencontres : tout cela a un coût et demande du temps. Votre soutien nous permet d’aller plus vite et plus loin, à l’approche du centenaire.</p>',
    ],

    'adherer' => [
        '_label' => 'Adhérer',
        'title' => 'Adhérer à l’association',
        'lead' => 'Devenez membre de Sochaux Rétro : vous soutenez nos projets et vous participez à la vie de l’association.',
        'benefits_title' => 'Être adhérent, c’est…',
        'benefits' => [
            ['title' => 'Soutenir le projet', 'text' => 'Votre cotisation finance la numérisation des archives et le fonctionnement du musée en ligne.'],
            ['title' => 'Avoir voix au chapitre', 'text' => 'Vous êtes invité à l’assemblée générale et vous votez les grandes orientations.'],
            ['title' => 'Être aux premières loges', 'text' => 'Vous êtes informé en avant-première des nouveautés, rencontres et projets du centenaire.'],
            ['title' => 'Rejoindre une famille', 'text' => 'Celle des passionnés qui font vivre la mémoire du club, toutes générations confondues.'],
        ],
        'validity' => 'L’adhésion est valable pour l’année civile en cours.',
        'paper_title' => 'Préférez-vous le papier ?',
        'paper_text' => '<p>Imprimez le bulletin d’adhésion, remplissez-le et envoyez-le avec votre règlement par chèque à l’ordre de Sochaux Rétro à l’adresse de l’association.</p>',
        'a_verifier' => ['Les tarifs d’adhésion (Site de l’association › Adhésions) sont des exemples à valider.', 'La durée de validité de l’adhésion (année civile) est à confirmer.', 'Les avantages des adhérents (assemblée générale, avant-premières) sont à confirmer.'],
    ],

    'benevolat' => [
        '_label' => 'Devenir bénévole',
        'title' => 'Devenir bénévole',
        'lead' => 'Historien dans l’âme, as du scanner, monteur vidéo, plume alerte ou simple passionné : il y a une place pour vous.',
        'image' => '2025/03/le-fc-sochaux-vainqueur-de-sa-premiere-coupe-gambardella-en-1983-conter-lens-grace-a-un-but-de-stephane-p.jpg',
        'intro' => '<p>Le musée en ligne et toutes nos actions reposent sur des bénévoles. Chacun donne le temps qu’il peut, d’où il veut : beaucoup de missions se font depuis chez soi.</p>',
        'form_title' => 'Proposer votre aide',
        'form_text' => 'Dites-nous ce qui vous plaît et le temps dont vous disposez : un membre de l’équipe vous recontacte.',
    ],

    'partenaires' => [
        '_label' => 'Partenaires',
        'title' => 'Partenaires',
        'lead' => 'Ils soutiennent la mémoire du FC Sochaux-Montbéliard à nos côtés.',
        'empty_text' => '<p>Nos premiers partenaires seront bientôt présentés ici. Et si c’était vous ?</p>',
        'become_title' => 'Devenir partenaire',
        'become_text' => '<p>Entreprise de la région, collectivité, média, club de supporters, collectionneur ou institution culturelle : associez votre nom à un projet de patrimoine populaire, suivi par une large communauté et tourné vers le centenaire du club.</p>',
        'offers' => [
            ['title' => 'Mécénat', 'text' => 'Soutenez la numérisation d’une collection ou l’organisation d’un événement. Visibilité sur le site et nos réseaux.'],
            ['title' => 'Partenariat de contenu', 'text' => 'Archives, photos, témoignages : enrichissons ensemble le musée en ligne, avec mention de la source.'],
            ['title' => 'Événements', 'text' => 'Exposition, rencontre, projection : construisons un rendez-vous autour de l’histoire du club.'],
        ],
        'a_verifier' => ['Les formules de partenariat proposées (mécénat, contenus, événements) sont à valider.'],
    ],

    'presse' => [
        '_label' => 'Presse',
        'title' => 'Espace presse',
        'lead' => 'Journalistes, blogueurs, créateurs : tout ce qu’il faut pour parler de Sochaux Rétro.',
        'about_title' => 'L’association en bref',
        'about' => '<p>Sochaux Rétro est une association de supporters passionnés qui rassemble, préserve et partage l’histoire du FC Sochaux-Montbéliard. Sa communauté réunit plus de 11 000 passionnés sur les réseaux sociaux et sa chaîne YouTube compte plus de 1 400 vidéos. À l’approche du centenaire du club, elle a créé un musée en ligne gratuit qui retrace près d’un siècle d’histoire sochalienne.</p>',
        'contact_text' => 'Pour une interview, des visuels ou une information : écrivez-nous, nous répondons rapidement.',
        'empty_text' => 'Les articles consacrés à l’association seront rassemblés ici.',
    ],

    'contact' => [
        '_label' => 'Contact',
        'title' => 'Contact',
        'lead' => 'Une question, une idée, une archive à proposer, un partenariat ? Écrivez-nous : un bénévole vous répond.',
    ],
];
