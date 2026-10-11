<?php
// Quiz de départ du musée (questions FR / EN), lu par App\Data\Seeds::quiz().

$q = [
    ['En quelle année le FC Sochaux est-il fondé ?', ['1919', '1928', '1932', '1945'], 1, 'Le club voit le jour le 14 juin 1928 : son centenaire sera célébré le 14 juin 2028.'],
    ['Comment surnomme-t-on les joueurs sochaliens ?', ['Les Aiglons', 'Les Canaris', 'Les Lionceaux', 'Les Dogues'], 2, "Le lion est l'emblème du club… et de la région."],
    ['Combien de titres de champion de France le club compte-t-il ?', ['Aucun', 'Un', 'Deux', 'Trois'], 2, 'Deux titres, en 1935 et 1938.'],
    ['Quel est le nom du stade du FCSM ?', ['Stade Auguste-Bonal', 'Stade de la Meinau', 'Stade Bollaert', "Stade de l'Abbé-Deschamps"], 0, 'Le stade Auguste-Bonal, à Montbéliard.'],
    ['Quel trophée Sochaux remporte-t-il en 2004 ?', ['Coupe de France', 'Coupe de la Ligue', 'Trophée des champions', 'Coupe Intertoto'], 1, 'La Coupe de la Ligue 2004, face à Nantes.'],
    ['Contre qui Sochaux gagne-t-il la finale de la Coupe de France 2007 ?', ['Lyon', 'Nantes', 'Marseille', 'Lens'], 2, "Victoire face à l'OM au Stade de France, aux tirs au but."],
    ['En quelle année Sochaux remporte-t-il sa première Coupe de France ?', ['1937', '1947', '1959', '1971'], 0, 'En 1937 : la première Coupe de France du club.'],
    ['En quelle année le stade de la Forge, futur stade Bonal, voit-il le jour ?', ['1928', '1931', '1946', '1962'], 1, 'Le stade de la Forge, devenu stade Auguste-Bonal, ouvre en 1931.'],
    ['Le centre de formation sochalien, l’un des premiers de France, est créé en…', ['1958', '1966', '1974', '1990'], 2, 'Le premier centre de formation de France ouvre à Sochaux en 1974.'],
    ['Combien de buts Sochaux marque-t-il lors de la saison record 1987-1988 ?', ['97', '112', '128', '147'], 3, '147 buts en une seule saison : un record !'],
    ['Contre quelle équipe Sochaux remporte-t-il la Coupe Gambardella 1983 ?', ['Lens', 'Nantes', 'Auxerre', 'Metz'], 0, 'Le 27 mai 1983, à Lille, Sochaux bat Lens 1-0.'],
    ['Comment s’appelle la mascotte du club ?', ['Le Sochalion', 'Lionel', 'Le Roi Lion', 'Bonalou'], 0, 'Le Sochalion égaye les tribunes de Bonal.'],
    ['Quelles sont les couleurs du FC Sochaux-Montbéliard ?', ['Rouge et blanc', 'Jaune et bleu', 'Vert et blanc', 'Bleu et blanc'], 1, 'Le jaune et le bleu, couleurs du club depuis ses débuts.'],
    ['Contre quel club Sochaux joue-t-il la demi-finale de la Coupe UEFA 1980-1981 ?', ['Ipswich Town', 'AZ Alkmaar', 'Hambourg', 'Real Madrid'], 1, "Le sommet européen du club : une demi-finale face à l'AZ Alkmaar."],
    ['Contre quel adversaire Sochaux dispute-t-il la finale de la Coupe de France 1988 ?', ['Metz', 'Monaco', 'Bordeaux', 'Marseille'], 0, 'La finale face au FC Metz se joue au Parc des Princes.'],
];
$en = [
    ['In which year was FC Sochaux founded?', ['1919', '1928', '1932', '1945'], 'The club was founded on 14 June 1928: its centenary will be celebrated on 14 June 2028.'],
    ['What nickname is given to Sochaux players?', ['Les Aiglons', 'Les Canaris', 'Les Lionceaux', 'Les Dogues'], 'The lion is the emblem of the club… and of the region.'],
    ['How many French league titles has the club won?', ['None', 'One', 'Two', 'Three'], 'Two titles, in 1935 and 1938.'],
    ['What is the name of FCSM’s stadium?', ['Stade Auguste-Bonal', 'Stade de la Meinau', 'Stade Bollaert', "Stade de l'Abbé-Deschamps"], 'Stade Auguste-Bonal, in Montbéliard.'],
    ['Which trophy did Sochaux win in 2004?', ['Coupe de France', 'Coupe de la Ligue', 'Trophée des champions', 'Intertoto Cup'], 'The 2004 Coupe de la Ligue, against Nantes.'],
    ['Against whom did Sochaux win the 2007 Coupe de France final?', ['Lyon', 'Nantes', 'Marseille', 'Lens'], 'Victory against OM at the Stade de France, on penalties.'],
    ['In which year did Sochaux win its first Coupe de France?', ['1937', '1947', '1959', '1971'], 'In 1937: the club’s first Coupe de France.'],
    ['In which year did the Stade de la Forge, the future Stade Bonal, open?', ['1928', '1931', '1946', '1962'], 'The Stade de la Forge, later renamed Stade Auguste-Bonal, opened in 1931.'],
    ['Sochaux’s youth academy, one of the first in France, was created in…', ['1958', '1966', '1974', '1990'], 'France’s first youth academy opened in Sochaux in 1974.'],
    ['How many goals did Sochaux score in the record 1987-1988 season?', ['97', '112', '128', '147'], '147 goals in a single season: a record!'],
    ['Against which team did Sochaux win the 1983 Coupe Gambardella?', ['Lens', 'Nantes', 'Auxerre', 'Metz'], 'On 27 May 1983, in Lille, Sochaux beat Lens 1-0.'],
    ['What is the name of the club mascot?', ['Le Sochalion', 'Lionel', 'The Lion King', 'Bonalou'], 'Le Sochalion livens up the stands at Bonal.'],
    ['What are FC Sochaux-Montbéliard’s colours?', ['Red and white', 'Yellow and blue', 'Green and white', 'Blue and white'], 'Yellow and blue, the club’s colours since the very beginning.'],
    ['Against which club did Sochaux play the 1980-1981 UEFA Cup semi-final?', ['Ipswich Town', 'AZ Alkmaar', 'Hamburg', 'Real Madrid'], 'The club’s European peak: a semi-final against AZ Alkmaar.'],
    ['Against which opponent did Sochaux play the 1988 Coupe de France final?', ['Metz', 'Monaco', 'Bordeaux', 'Marseille'], 'The final against FC Metz was played at the Parc des Princes.'],
];
return [$q, $en];
