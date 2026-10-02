<?php
declare(strict_types=1);

namespace App\Data;

/**
 * Contenus de départ des outils interactifs (quiz, frise, maillots, épopées, lieux).
 * Rédigés à partir de faits établis — la plupart tirés du site lui-même — et
 * marqués « à valider » : les historiens les relisent dans le back-office.
 */
final class Seeds
{
    public static function quiz(): array
    {
        $q = [
            ['En quelle année le FC Sochaux est-il fondé ?', ['1919', '1928', '1932', '1945'], 1, 'Le club voit le jour le 20 mai 1928 : son centenaire sera célébré le 20 mai 2028.'],
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
        return array_map(fn ($x) => ['q' => $x[0], 'a' => $x[1], 'c' => $x[2], 'fact' => $x[3], 'validated' => false], $q);
    }

    public static function frise(): array
    {
        $e = [
            [1928, 'Fondation du club', 'Le FC Sochaux voit le jour le 20 mai 1928.', 'n'],
            [1931, 'Ouverture du stade de la Forge', 'Le futur stade Auguste-Bonal accueille ses premiers matchs.', ''],
            [1935, 'Premier titre de champion de France', '', 'y'],
            [1937, 'Première Coupe de France', '', 'y'],
            [1938, 'Second titre de champion de France', '', 'y'],
            [1974, 'Le premier centre de formation de France', '', ''],
            [1981, 'Demi-finale de la Coupe UEFA', "Face à l'AZ Alkmaar, le sommet européen du club.", 'y'],
            [1983, 'Coupe Gambardella', 'Victoire 1-0 face à Lens en finale, à Lille.', ''],
            [1988, 'Finale de la Coupe de France', 'Face au FC Metz, au Parc des Princes. Saison record : 147 buts.', ''],
            [2000, 'Un nouveau stade Bonal', 'Le stade est entièrement reconstruit.', ''],
            [2004, 'Coupe de la Ligue', 'Victoire face à Nantes aux tirs au but.', 'y'],
            [2007, 'Deuxième Coupe de France', "Victoire face à l'OM au Stade de France, aux tirs au but.", 'y'],
            [2028, 'Le centenaire', 'Rendez-vous le 20 mai 2028.', 'n'],
        ];
        return array_map(fn ($x) => ['year' => $x[0], 'title' => $x[1], 'text' => $x[2], 'tone' => $x[3], 'image' => null, 'href' => null, 'validated' => false], $e);
    }

    public static function maillots(): array
    {
        return array_map(fn ($y) => ['era' => (string) $y, 'label' => 'Années ' . ($y < 2000 ? substr((string) $y, 2) : $y), 'image' => null, 'text' => ''], [1930, 1950, 1970, 1980, 2000, 2020]);
    }

    public static function epopees(): array
    {
        $s = [
            [1937, '1936-1937', 'Finales nationales', 'Colombes', 'Coupe de France', 'Finale à Colombes : Sochaux remporte sa première Coupe de France.', [48.9289, 2.2475], true],
            [1981, '1980-1981', "Coupes d'Europe", 'Alkmaar', 'Demi-finale de Coupe UEFA', "Demi-finale de Coupe UEFA face à l'AZ : le sommet européen du club.", [52.6125, 4.7425], false],
            [1988, '1987-1988', 'Finales nationales', 'Parc des Princes', 'Coupe de France', 'Finale face au FC Metz, au Parc des Princes.', [48.8414, 2.2530], false],
            [2004, '2003-2004', 'Finales nationales', 'Stade de France', 'Coupe de la Ligue', "Finale face à Nantes : Sochaux s'impose aux tirs au but.", [48.9245, 2.3602], true],
            [2007, '2006-2007', 'Finales nationales', 'Stade de France', 'Coupe de France', "Finale face à l'OM : nouvelle victoire aux tirs au but.", [48.9245, 2.3602], true],
        ];
        return array_map(fn ($x) => ['y' => $x[0], 's' => $x[1], 'type' => $x[2], 't' => $x[3], 'h' => $x[4], 'p' => $x[5], 'll' => $x[6], 'win' => $x[7], 'href' => null, 'validated' => false], $s);
    }

    public static function lieux(): array
    {
        return [
            ['n' => 'Stade Auguste-Bonal', 't' => 'Stade', 'll' => [47.5122, 6.8111], 'd' => "L'antre du Lion, à Montbéliard.", 'href' => '/infrastructures/le-stade/', 'validated' => true],
            ['n' => "Musée de l'Aventure Peugeot", 't' => 'Patrimoine', 'll' => [47.5137, 6.8282], 'd' => "L'histoire de la marque au lion, voisine du club.", 'href' => null, 'validated' => false],
            ['n' => 'Site Peugeot de Sochaux', 't' => 'Patrimoine', 'll' => [47.5065, 6.8395], 'd' => 'Le berceau industriel du club.', 'href' => null, 'validated' => false],
        ];
    }
}
