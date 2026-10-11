<?php
// Frise chronologique de départ (événements FR / EN), lue par App\Data\Seeds::frise().

$e = [
    [1928, 'Fondation du club', 'Le FC Sochaux voit le jour le 14 juin 1928.', 'n', '2024/11/1928-FC-Sochaux3b1-1536x992-1.jpg'],
    [1931, 'Ouverture du stade de la Forge', 'Le futur stade Auguste-Bonal accueille ses premiers matchs.', '', '2025/03/Tribune-terminee-1931-Bis-1.jpg'],
    [1935, 'Premier titre de champion de France', '', 'y', '2024/12/5-79.jpg'],
    [1937, 'Première Coupe de France', '', 'y', '2025/03/Phanphare-Peugeot-coupe-de-France-37-a-Paris.jpg'],
    [1938, 'Second titre de champion de France', '', 'y', '2024/12/906_001.jpg'],
    [1974, 'Le premier centre de formation de France', '', '', '2024/05/programme-.png'],
    [1981, 'Demi-finale de la Coupe UEFA', "Face à l'AZ Alkmaar, le sommet européen du club.", 'y', '2024/11/inbound6599480284613850309.jpg'],
    [1983, 'Coupe Gambardella', 'Victoire 1-0 face à Lens en finale, à Lille.', '', '2025/03/le-fc-sochaux-vainqueur-de-sa-premiere-coupe-gambardella-en-1983-conter-lens-grace-a-un-but-de-stephane-p.jpg'],
    [1988, 'Finale de la Coupe de France', 'Face au FC Metz, au Parc des Princes. Saison record : 147 buts.', '', '2024/04/equipe_finale_cdf_88_2.jpg'],
    [2000, 'Un nouveau stade Bonal', 'Le stade est entièrement reconstruit.', '', '2025/11/519640823_1128725095737251_737815365501537839_n.jpg'],
    [2004, 'Coupe de la Ligue', 'Victoire face à Nantes aux tirs au but.', 'y', '2025/01/photos-il-y-a-quinze-ans-sochaux-remportait-la-coupe-de-la-ligue-au-stade-de-france-1554725436.jpg'],
    [2007, 'Deuxième Coupe de France', "Victoire face à l'OM au Stade de France, aux tirs au but.", 'y', '2026/06/le-onze-de-depart-de-sochaux-non-vous-ne-revez-pas-mickael-isabey-n-est-pas-retenu-il-n-est-meme-pas-sur-la-feuille-de-match-photo-alexandre-marchi-1589306142.jpg'],
    [2028, 'Le centenaire', 'Rendez-vous le 14 juin 2028.', 'n', '2025/03/le-deplacement-des-supporters-de-reims-a-sochaux-sera-encadre-1705584005.jpg'],
];
$en = [
    ['The club is founded', 'FC Sochaux is born on 14 June 1928.'],
    ['The Stade de la Forge opens', 'The future Stade Auguste-Bonal hosts its first matches.'],
    ['First French league title', ''],
    ['First Coupe de France', ''],
    ['Second French league title', ''],
    ['France’s first youth academy', ''],
    ['UEFA Cup semi-final', 'Against AZ Alkmaar, the club’s European peak.'],
    ['Coupe Gambardella', '1-0 win against Lens in the final, in Lille.'],
    ['Coupe de France final', 'Against FC Metz, at the Parc des Princes. Record season: 147 goals.'],
    ['A new Stade Bonal', 'The stadium is completely rebuilt.'],
    ['Coupe de la Ligue', 'Victory against Nantes on penalties.'],
    ['Second Coupe de France', 'Victory against OM at the Stade de France, on penalties.'],
    ['The centenary', 'See you on 14 June 2028.'],
];
return [$e, $en];
