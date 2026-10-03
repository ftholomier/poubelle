<?php
/**
 * « xx » de l'ancien site (information inconnue) : jamais affichés, la phrase est réécrite
 * sans eux ou la ligne disparaît. Cas relevés dans les fiches (naissance, fiche d'identité,
 * références de match, temps forts, réactions, chiffre clé…) et cas limites.
 * Usage : php tests/inconnu.php (code de sortie 1 en cas d'échec). Ne modifie rien.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Front\Unknown;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// ---------------------------------------------------------------- lignes (fiche d'identité, naissance, décès)
foreach ([
    'né le xx à xx' => '',
    'Né le XX à XX' => '',
    'Décédé le xx à xx' => 'Décédé',
    'né le 12 mai 1920 à xx' => 'né le 12 mai 1920',
    'né le 1er mai 1920 à XX' => 'né le 1er mai 1920',
    'né le xx à xx (Angleterre)' => 'né en Angleterre',
    'Décédé le xx à xx (Hongrie)' => 'Décédé en Hongrie',
    'né le 3 mai 1920 à xx (Italie)' => 'né le 3 mai 1920 en Italie',
    'né le 12 mai 1920 à xx (Écosse ?)' => 'né le 12 mai 1920 en Écosse ?',
    'né le xx à xx (Pays-Bas)' => 'né aux Pays-Bas',
    'né le xx à (Nigéria)' => 'né au Nigéria',
    'né le xx à Aulnoye (59)' => 'né à Aulnoye (59)',
    'né le xx/xx/1925 à Aulnoye (59)' => 'né en 1925 à Aulnoye (59)',
    'né le xx/09/1990 à Sochaux' => 'né en septembre 1990 à Sochaux',
    'né le xx xx 1940 à Belfort' => 'né en 1940 à Belfort',
    'né le xx juillet 1940' => 'né en juillet 1940',
    'né le xx 1907 à Delme-en-Moselle (57)' => 'né en 1907 à Delme-en-Moselle (57)',
    'né le xx au Brésil' => 'né au Brésil',
    'né le xx en Algérie ?' => 'né en Algérie ?',
    'né xx en Ecosse ?' => 'né en Ecosse ?',
    'né en 1909 à xx (Hongrie)' => 'né en 1909 en Hongrie',
    'né le (années 20) à xx' => 'né dans les années 20',
    'né le xx à Montbéliard ?' => 'né à Montbéliard ?',
    'xx (Italie ?)' => 'Italie ?',
    '1mxx' => '',
    '1m xx' => '',
    'xx kg' => '',
    'juillet 19xx' => '',
    'xx' => '',
    'milieu de terrain' => 'milieu de terrain',
] as $in => $out) {
    $eq("ligne « $in »", Unknown::line($in), $out);
}

// ---------------------------------------------------------------- références de match (fiche d'identité)
foreach ([
    'Sochaux - xx du xx/xx/2025 : x-x' => '',
    'xx - Sochaux du xx/xx/2025 : 0-1' => '',
    'Sochaux - xx du xx/xx/xxxx : x-x' => '',
    'xx - Sochaux du xx/08/2025 : x-x' => '',
    'Sochaux - du xx/xx/1951 :' => '',
    'Sochaux - Strasbourg du xx/09/1990 :' => 'Sochaux - Strasbourg en septembre 1990',
    'Sochaux - Rodez-Aveyron du 12/08/2023 : x-x' => 'Sochaux - Rodez-Aveyron du 12/08/2023',
    'Sochaux - SC Fives du 06/07/2002 : 5-2 - But à la xx et à la 89\'' => 'Sochaux - SC Fives du 06/07/2002 : 5-2 - But à la 89\'',
    'Sochaux - Nancy du xx/xx/1975 : 2-1' => 'Sochaux - Nancy en 1975 : 2-1',
    'Sochaux du xx/xx/1951 :' => 'Sochaux en 1951',
    'Sochaux du xx/xx/1946 : x-x - But à la xx\'' => 'Sochaux en 1946',
    'US Belfort - Sochaux du 17/12/1939 : 1-2 a.p' => 'US Belfort - Sochaux du 17/12/1939 : 1-2 a.p',
] as $in => $out) {
    $eq("match « $in »", Unknown::line($in), $out);
}

// ---------------------------------------------------------------- en anglais (fiche traduite)
$eq('anglais : né sans date ni lieu', Unknown::line('born on xx in xx'), '');
$eq('anglais : date incomplète', Unknown::line('born on xx/xx/1925 in Aulnoye'), 'born in 1925 in Aulnoye');
$eq('anglais : pays connu', Unknown::line('Died on xx in xx (Hungary)'), 'Died in Hungary');
$eq('anglais : match, mois connu', Unknown::line('Sochaux - Metz on xx/09/1990: x-x'), 'Sochaux - Metz in September 1990');

// ---------------------------------------------------------------- textes suivis
$eq('temps fort : minute inconnue retirée', Unknown::text('Frei perce. xx’ : Omar Daf dégage le ballon.'), 'Frei perce. Omar Daf dégage le ballon.');
$eq('ligne « Arbitre : xx » : rien', Unknown::text('Arbitre : xx'), '');
$eq('ligne « Arbitre : M. xx » : rien', Unknown::text('Arbitre : M. xx'), '');
$eq('espaces de bord gardés (texte entre deux balises)', Unknown::text(' né le xx à xx, puis '), ' né, puis ');
$eq('« (xx’) » ne laisse pas « ( ) »', Unknown::text('But de Dupont (xx’) et de Martin'), 'But de Dupont et de Martin');
$eq('verbe « a » gardé', Unknown::text('Il a xx sélections'), 'Il a sélections');
$eq('rang inconnu : « à la xxè place »', Unknown::has('Sochaux termine à la xxè place du tournoi.'), true);
$eq('« XXe siècle », « XXL », Maxxsport : pas des marques', [Unknown::has('au début du XXe siècle'), Unknown::has('en taille XXL'), Unknown::has('photo Maxxsport'), Unknown::has('https://youtu.be/abXXcd12')], [false, false, false, false]);
$eq('texte sans marque : inchangé (même objet)', Unknown::text('Rien à signaler.'), 'Rien à signaler.');

// ---------------------------------------------------------------- texte riche
$eq('bloc réduit à rien : retiré', Unknown::html("<h4><strong>né le xx à xx</strong></h4>\n<p>Poste : milieu</p>"), "\n<p>Poste : milieu</p>");
$eq('bloc réduit à son étiquette : retiré', Unknown::html('<p><strong>Arbitre :</strong> xx</p><p>Texte</p>'), '<p>Texte</p>');
$eq('liste vidée : retirée, texte rendu vide', Unknown::html("<ul>\n<li>xxx</li>\n</ul>"), '');
$eq('balise de lien gardée, mot avant la balise gardé', Unknown::html('<p>né le xx à xx, puis <a href="/x/">rejoint</a> Sochaux</p>'), '<p>né, puis <a href="/x/">rejoint</a> Sochaux</p>');
$eq('attribut contenant « > » jamais réécrit', Unknown::html('<p><a href="/x" title="a>xx &quot; style=&quot;color:red&quot;">lien</a> xx</p>'), '<p><a href="/x" title="a>xx &quot; style=&quot;color:red&quot;">lien</a></p>');
$eq('« & » d’un texte nettoyé : un seul échappement', Unknown::html('<p>Tom &amp; Jerry xx’ : but</p>'), '<p>Tom &amp; Jerry but</p>');
$eq('texte riche sans marque : inchangé', Unknown::html('<p>Rien <b>ici</b></p>'), '<p>Rien <b>ici</b></p>');

// ---------------------------------------------------------------- fiche entière
$doc = [
    'id' => 1, 'type' => 'match', 'title' => 'Sochaux - Toulon', 'intro' => '', 'sections' => [['title' => 'Réactions', 'html' => "<ul>\n<li>xxx</li>\n</ul>"]],
    'key_figure' => ['number' => '2', 'text' => 'buts et 2 passes décisives avec Sochaux en xx matches.'],
    'match' => [
        'referee' => 'M. xx', 'stadium' => 'Sochaux termine à la xxè place du tournoi.', 'spectators_text' => 'xx spectateurs',
        'goals_text' => "Touzghar 1', Andriatsima 21' et 43' pour Sochaux ; xx pour la Sélection de Franche-Comté",
        'goals' => [['team' => 'Sochaux', 'scorers' => "Dupont 12', xx 45'"], ['team' => 'la Sélection', 'scorers' => 'xx']],
        'highlights' => [['minute' => 'xx', 'text' => 'Tir de Santos.', 'goal' => false], ['minute' => '12', 'text' => 'xx’ :', 'goal' => false], ['minute' => '30', 'text' => 'But ! xx’ : reprise', 'goal' => true]],
        'reactions' => [['who' => '', 'text' => 'xxx'], ['who' => 'Coach', 'text' => 'Tom & Jerry : xx’ : bien joué']],
        'breves' => ['Brève sans marque', 'xx'],
    ],
];
$d = Unknown::doc($doc);
$eq('chiffre clé partiellement rempli : « en xx matches » retiré', $d['key_figure']['text'] ?? null, 'buts et 2 passes décisives avec Sochaux.');
$eq('chiffre clé jamais rempli : retiré', Unknown::doc(['key_figure' => ['number' => '425', 'text' => 'Kévin a joué xx matches (12 buts) pour Sochaux.']] + $doc)['key_figure'], null);
$eq('arbitre, stade, spectateurs inconnus : vides', [$d['match']['referee'], $d['match']['stadium'], $d['match']['spectators_text']], ['', '', '']);
$eq('buteurs inconnus d’une équipe retirés', $d['match']['goals_text'], "Touzghar 1', Andriatsima 21' et 43' pour Sochaux");
$eq('buteur inconnu retiré, les autres gardés', array_column($d['match']['goals'], 'scorers'), ["Dupont 12'"]);
$eq('temps forts : minute inconnue vidée, action vide retirée (hors but)', array_map(fn ($h) => [$h['minute'], $h['text']], $d['match']['highlights']), [['', 'Tir de Santos.'], ['30', 'But ! reprise']]);
$eq('réactions : vide retirée, « & » d’un texte simple non échappé', $d['match']['reactions'], [['text' => 'Tom & Jerry : bien joué', 'who' => 'Coach']]);
$eq('brèves : vide retirée', $d['match']['breves'], ['Brève sans marque']);
$eq('section réduite à une liste vide : texte vide (section masquée)', $d['sections'][0]['html'], '');
$p = Unknown::doc(['id' => 2, 'type' => 'personne', 'title' => 'X', 'personne' => [
    'subtitle' => 'né xx en Ecosse ?', 'height' => '1mxx',
    'birth' => ['text' => 'né le xx/xx/1925 à Aulnoye (59)', 'date' => ['text' => 'xx/xx/1925', 'iso' => '1925', 'precision' => 'year'], 'place' => ['text' => 'xx (Hongrie)', 'city' => 'xx']],
    'death' => ['text' => 'Décédé le xx à xx', 'date' => ['text' => 'xx', 'iso' => null, 'precision' => null], 'place' => ['text' => 'xx', 'city' => 'xx']],
    'fiche' => [['label' => '', 'value' => 'né le xx à xx'], ['label' => 'Taille', 'value' => '1mxx'], ['label' => 'Poste', 'value' => 'milieu'], ['label' => 'Premier match joué', 'value' => 'Sochaux - xx du xx/xx/2025 : x-x']],
    'first_match' => 'Sochaux - xx du xx/xx/2025 : x-x',
]]);
$eq('personne : sous-titre, naissance, lieu, décès', [$p['personne']['subtitle'], $p['personne']['birth']['text'], $p['personne']['birth']['place']['text'], $p['personne']['birth']['place']['city'], $p['personne']['death']['text'], $p['personne']['birth']['date']['text'], $p['personne']['death']['date']['text']],
    ['né en Ecosse ?', 'né en 1925 à Aulnoye (59)', 'Hongrie', '', 'Décédé', '1925', '']);
$eq('personne : lignes de la fiche d’identité sans rien d’utile retirées', $p['personne']['fiche'], [['label' => 'Poste', 'value' => 'milieu']]);
$eq('personne : premier match du modèle jamais rempli, taille inconnue', [$p['personne']['first_match'], $p['personne']['height']], ['', '']);
$hl = Unknown::doc(['id' => 4, 'type' => 'personne', 'title' => 'Y', 'personne' => ['first_match' => 'Sochaux - Saint-Etienne du 08/08/2026 : x-x', 'first_goal' => "Sochaux du xx/xx/1946 : x-x - But à la xx'", 'last_match' => 'Sochaux - Nancy du xx/05/1975 : 2-1']]);
$eq('matchs marquants : référence complète gardée (score inconnu retiré), modèle vide ou date incomplète retirés', [$hl['personne']['first_match'], $hl['personne']['first_goal'], $hl['personne']['last_match']], ['Sochaux - Saint-Etienne du 08/08/2026', '', '']);
$eq('phrase : point final, sauf après « ? »', [sentence('Né en Italie ?'), sentence('Né en 1925'), sentence('Né en 1925.'), sentence('')], ['Né en Italie ?', 'Né en 1925.', 'Né en 1925.', '']);
$clean = ['id' => 3, 'type' => 'article', 'title' => 'Sans marque', 'intro' => '<p>Texte</p>', 'sections' => [], 'legacy' => ['header_html' => 'né le xx']];
$eq('fiche sans marque (hors ancien en-tête) : inchangée', Unknown::doc($clean), $clean);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
