<?php
/**
 * Équipe de départ (Site de l'association › Équipe). Aucun nom n'est inventé : un membre sans
 * nom n'est pas affiché sur le site. Les pôles décrivent les missions des bénévoles.
 */

return [
    'members' => [
        ['role' => 'Président ou présidente', 'name' => '', 'pole' => 'bureau', 'photo' => null, 'text' => ''],
        ['role' => 'Vice-président ou vice-présidente', 'name' => '', 'pole' => 'bureau', 'photo' => null, 'text' => ''],
        ['role' => 'Secrétaire', 'name' => '', 'pole' => 'bureau', 'photo' => null, 'text' => ''],
        ['role' => 'Trésorier ou trésorière', 'name' => '', 'pole' => 'bureau', 'photo' => null, 'text' => ''],
        ['role' => 'Responsable du musée en ligne', 'name' => '', 'pole' => 'musee', 'photo' => null, 'text' => ''],
        ['role' => 'Coordination des historiens', 'name' => '', 'pole' => 'musee', 'photo' => null, 'text' => ''],
        ['role' => 'Responsable vidéos et réseaux sociaux', 'name' => '', 'pole' => 'communication', 'photo' => null, 'text' => ''],
        ['role' => 'Responsable des archives', 'name' => '', 'pole' => 'archives', 'photo' => null, 'text' => ''],
    ],
    'poles' => [
        ['key' => 'bureau', 'icon' => '★', 'title' => 'Le bureau', 'text' => 'Il anime l’association, gère son budget et la représente auprès des partenaires.'],
        ['key' => 'musee', 'icon' => '◆', 'title' => 'Musée et historiens', 'text' => 'Rédiger les fiches, vérifier les dates, les scores et les compositions, sources à l’appui.'],
        ['key' => 'archives', 'icon' => '▤', 'title' => 'Archives et numérisation', 'text' => 'Collecter, scanner, restaurer et légender photos, affiches, programmes et coupures de presse.'],
        ['key' => 'communication', 'icon' => '▶', 'title' => 'Vidéos et réseaux', 'text' => 'Monter les vidéos, animer la communauté et faire connaître le musée.'],
        ['key' => 'evenements', 'icon' => '◎', 'title' => 'Événements et rencontres', 'text' => 'Organiser expositions, projections, Après-midi Bonal et rendez-vous du centenaire.'],
    ],
];
