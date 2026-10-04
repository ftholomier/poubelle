<?php
/**
 * Page d'attente du site de l'association : montrée aux visiteurs tant que le site est fermé.
 * Modifiable dans le back-office (Site de l'association › Page d'attente). Distincte de celle du
 * musée (Éditorial › Page d'attente) : page claire, photo d'archive, ce qui arrive, inscription à
 * la lettre « Ce jour-là », encart du musée en ligne.
 */

return [
    'eyebrow' => 'Association Sochaux Rétro',
    'title' => 'Notre nouveau site arrive',
    'text' => '<p>Sochaux Rétro prépare le site de son association : nos actions, l’agenda des rendez-vous, l’adhésion en ligne et toutes les façons de nous rejoindre.</p><p>Comme à Bonal avant le coup d’envoi, tout se met en place. Encore un peu de patience !</p>',
    'image' => '2024/02/1.-bonal.jpeg',
    'image_caption' => 'La tribune présidentielle de Bonal attend son public',
    'badge' => 'Bientôt',
    'items_title' => 'Ce qui vous attend',
    'items' => [
        ['icon' => '◆', 'title' => 'Nos actions', 'text' => 'Le musée en ligne, les vidéos, les archives, les rencontres et la préparation du centenaire.'],
        ['icon' => '▣', 'title' => 'L’agenda', 'text' => 'Les rendez-vous de l’association et les Rétro-Direct du musée, à ajouter à votre calendrier.'],
        ['icon' => '★', 'title' => 'Adhérer en ligne', 'text' => 'Rejoindre l’association et soutenir ses projets en quelques clics.'],
        ['icon' => '◎', 'title' => 'Devenir bénévole', 'text' => 'Archives, communication, événements : il y a une place pour chacun.'],
    ],
    'countdown' => false,
    'countdown_date' => '',
    'countdown_label' => 'Ouverture dans',
    'newsletter' => true,
    'newsletter_title' => 'Soyez prévenu de l’ouverture',
    'newsletter_text' => 'Inscrivez-vous à « Ce jour-là », la lettre du musée : chaque semaine, les matchs du passé et les nouvelles de l’association.',
    'museum' => true,
    'teaser' => false,
    'contact' => true,
    'social' => true,
];
