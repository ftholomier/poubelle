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
        return array_map(fn ($x, $e) => ['q' => $x[0], 'a' => $x[1], 'c' => $x[2], 'fact' => $x[3], 'q_en' => $e[0], 'a_en' => $e[1], 'fact_en' => $e[2], 'validated' => false], $q, $en);
    }

    public static function frise(): array
    {
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
        return array_map(fn ($x, $t) => ['year' => $x[0], 'title' => $x[1], 'text' => $x[2], 'title_en' => $t[0], 'text_en' => $t[1], 'tone' => $x[3], 'image' => $x[4] ?? null, 'href' => null, 'validated' => false], $e, $en);
    }

    public static function maillots(): array
    {
        // Photos d'équipe de chaque époque, à remplacer au besoin par des maillots photographiés de face.
        $img = [
            1930 => '2024/11/1932-1er-match-zyro-image6-768x470-1.jpg',
            1950 => '2024/12/312_001.jpg',
            1970 => '2026/06/FC-SOCHAUX-MONTBELIARD-1970-71.jpg',
            1980 => '2026/06/fc-sochaux-1978-79.jpg',
            2000 => '2026/06/le-onze-de-depart-de-sochaux-non-vous-ne-revez-pas-mickael-isabey-n-est-pas-retenu-il-n-est-meme-pas-sur-la-feuille-de-match-photo-alexandre-marchi-1589306142.jpg',
            2020 => '2026/05/FCSM-LPF43-2025-2026-1-Michael-Desprez.jpg',
        ];
        return array_map(fn ($y) => ['era' => (string) $y, 'label' => 'Années ' . ($y < 2000 ? substr((string) $y, 2) : $y), 'image' => $img[$y], 'text' => ''], array_keys($img));
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
        $en = [
            ['National finals', 'Coupe de France', 'Final at Colombes: Sochaux wins its first Coupe de France.'],
            ['European cups', 'UEFA Cup semi-final', 'UEFA Cup semi-final against AZ: the club’s European peak.'],
            ['National finals', 'Coupe de France', 'Final against FC Metz, at the Parc des Princes.'],
            ['National finals', 'Coupe de la Ligue', 'Final against Nantes: Sochaux wins on penalties.'],
            ['National finals', 'Coupe de France', 'Final against OM: another victory on penalties.'],
        ];
        return array_map(fn ($x, $e) => ['y' => $x[0], 's' => $x[1], 'type' => $x[2], 't' => $x[3], 'h' => $x[4], 'p' => $x[5], 'type_en' => $e[0], 'h_en' => $e[1], 'p_en' => $e[2], 'll' => $x[6], 'win' => $x[7], 'href' => null, 'validated' => false], $s, $en);
    }

    public static function lieux(): array
    {
        return [
            ['n' => 'Stade Auguste-Bonal', 't' => 'Stade', 't_en' => 'Stadium', 'll' => [47.5122, 6.8111], 'd' => "L'antre du Lion, à Montbéliard.", 'd_en' => 'The Lion’s den, in Montbéliard.', 'href' => '/infrastructures/le-stade/', 'validated' => true],
            ['n' => "Musée de l'Aventure Peugeot", 'n_en' => 'Peugeot Adventure Museum', 't' => 'Patrimoine', 't_en' => 'Heritage', 'll' => [47.5137, 6.8282], 'd' => "L'histoire de la marque au lion, voisine du club.", 'd_en' => 'The story of the lion brand, the club’s neighbour.', 'href' => null, 'validated' => false],
            ['n' => 'Site Peugeot de Sochaux', 'n_en' => 'Peugeot Sochaux plant', 't' => 'Patrimoine', 't_en' => 'Heritage', 'll' => [47.5065, 6.8395], 'd' => 'Le berceau industriel du club.', 'd_en' => 'The club’s industrial birthplace.', 'href' => null, 'validated' => false],
        ];
    }
}
