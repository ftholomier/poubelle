<?php
// Épopées de départ de la carte (FR / EN), lues par App\Data\Seeds::epopees().

$s = [
    [1937, '1936-1937', 'Finales nationales', 'Colombes', 'Coupe de France', 'Finale à Colombes : Sochaux remporte sa première Coupe de France.', [48.9289, 2.2475], true],
    [1981, '1980-1981', "Coupes d'Europe", 'Alkmaar', 'Demi-finale de Coupe UEFA', "Demi-finale de Coupe UEFA face à l'AZ : le sommet européen du club.", [52.6125, 4.7425], false],
    [1988, '1987-1988', 'Finales nationales', 'Parc des Princes', 'Coupe de France', 'Finale face au FC Metz, au Parc des Princes.', [48.8414, 2.2530], false],
    [2004, '2003-2004', 'Finales nationales', 'Stade de France', 'Coupe de la Ligue', "Finale face à Nantes : Sochaux s'impose aux tirs au but.", [48.9245, 2.3602], true],
    [2007, '2006-2007', 'Finales nationales', 'Stade de France', 'Coupe de France', "Finale face à l'OM : nouvelle victoire aux tirs au but.", [48.9245, 2.3602], true],
];
$en = [
    ['National finals', 'Coupe de France', 'Final at Colombes: Sochaux wins its first Coupe de France.'],
    ['European cups', 'UEFA Cup semi-final', 'UEFA Cup semi-final against AZ: the club’s European peak.'],
    ['National finals', 'Coupe de France', 'Final against FC Metz, at the Parc des Princes.'],
    ['National finals', 'Coupe de la Ligue', 'Final against Nantes: Sochaux wins on penalties.'],
    ['National finals', 'Coupe de France', 'Final against OM: another victory on penalties.'],
];
return [$s, $en];
