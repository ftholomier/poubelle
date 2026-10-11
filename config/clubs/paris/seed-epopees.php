<?php
// Épopées de départ de la carte (FR / EN), lues par App\Data\Seeds::epopees().

$s = [
    [1982, '1981-1982', 'Finales nationales', 'Parc des Princes', 'Coupe de France', 'Finale face à Saint-Étienne : premier trophée du club, aux tirs au but.', [48.8414, 2.2530], true],
    [1993, '1992-1993', "Coupes d'Europe", 'Turin', 'Demi-finale de Coupe UEFA', 'Après avoir renversé le Real Madrid, Paris tombe face à la Juventus.', [45.1096, 7.6413], false],
    [1994, '1993-1994', "Coupes d'Europe", 'Londres', 'Demi-finale de Coupe des coupes', 'Arsenal arrête la route parisienne en demi-finale.', [51.5580, -0.1030], false],
    [1995, '1994-1995', "Coupes d'Europe", 'Milan', 'Demi-finale de Ligue des champions', 'Après Barcelone en quart, le Milan AC barre la route de la finale.', [45.4781, 9.1240], false],
    [1996, '1995-1996', "Coupes d'Europe", 'Bruxelles', 'Coupe des coupes', 'Finale au stade Roi-Baudouin : Paris bat le Rapid de Vienne 1-0 et gagne sa première coupe d’Europe.', [50.8954, 4.3341], true],
    [1997, '1996-1997', "Coupes d'Europe", 'Rotterdam', 'Coupe des coupes', 'Finale au Kuip : défaite 1-0 face au FC Barcelone.', [51.8939, 4.5231], false],
    [1998, '1997-1998', 'Finales nationales', 'Stade de France', 'Coupe de France', 'Finale face à Lens, dans le stade tout neuf de Saint-Denis.', [48.9245, 2.3602], true],
    [2020, '2019-2020', "Coupes d'Europe", 'Lisbonne', 'Ligue des champions', 'Première finale de C1, au stade de la Luz, perdue 1-0 face au Bayern Munich.', [38.7527, -9.1847], false],
    [2025, '2024-2025', "Coupes d'Europe", 'Munich', 'Ligue des champions', 'Finale face à l’Inter : victoire 5-0, la première Ligue des champions du club.', [48.2188, 11.6247], true],
    [2025, '2024-2025', 'Compétitions mondiales', 'East Rutherford', 'Coupe du monde des clubs', 'Finale au MetLife Stadium, perdue 3-0 face à Chelsea.', [40.8135, -74.0745], false],
    [2025, '2025-2026', "Coupes d'Europe", 'Udine', "Supercoupe de l'UEFA", 'Mené 2-0 par Tottenham, Paris égalise puis gagne aux tirs au but.', [46.0816, 13.2000], true],
    [2025, '2025-2026', 'Compétitions mondiales', 'Al-Rayyan', 'Coupe intercontinentale', 'Finale au Qatar face à Flamengo, gagnée aux tirs au but.', [25.3297, 51.3422], true],
    [2026, '2025-2026', "Coupes d'Europe", 'Budapest', 'Ligue des champions', 'Finale à la Puskás Aréna face à Arsenal : 1-1, puis victoire aux tirs au but.', [47.5031, 19.0981], true],
    [2026, '2026-2027', "Coupes d'Europe", 'Salzbourg', "Supercoupe de l'UEFA", 'Victoire 2-1 face à Aston Villa : le trophée est conservé.', [47.8163, 12.9984], true],
];
$en = [
    ['National finals', 'Coupe de France', 'Final against Saint-Étienne: the club’s first trophy, on penalties.'],
    ['European cups', 'UEFA Cup semi-final', 'After turning over Real Madrid, Paris fall to Juventus.'],
    ['European cups', 'Cup Winners’ Cup semi-final', 'Arsenal end the Parisian run in the semi-final.'],
    ['European cups', 'Champions League semi-final', 'After Barcelona in the quarter-final, AC Milan block the way to the final.'],
    ['European cups', 'Cup Winners’ Cup', 'Final at the King Baudouin Stadium: Paris beat Rapid Vienna 1-0 for their first European trophy.'],
    ['European cups', 'Cup Winners’ Cup', 'Final at De Kuip: a 1-0 defeat against FC Barcelona.'],
    ['National finals', 'Coupe de France', 'Final against Lens, in the brand-new stadium in Saint-Denis.'],
    ['European cups', 'Champions League', 'First European Cup final, at the Estádio da Luz, lost 1-0 to Bayern Munich.'],
    ['European cups', 'Champions League', 'Final against Inter: a 5-0 win and the club’s first Champions League.'],
    ['World competitions', 'Club World Cup', 'Final at MetLife Stadium, lost 3-0 to Chelsea.'],
    ['European cups', 'UEFA Super Cup', 'Two goals down against Tottenham, Paris level and win on penalties.'],
    ['World competitions', 'Intercontinental Cup', 'Final in Qatar against Flamengo, won on penalties.'],
    ['European cups', 'Champions League', 'Final at the Puskás Aréna against Arsenal: 1-1, then a penalty shoot-out win.'],
    ['European cups', 'UEFA Super Cup', '2-1 win against Aston Villa: the trophy is retained.'],
];
return [$s, $en];
