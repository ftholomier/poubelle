<?php
/**
 * Systèmes tactiques (ticket n° 26) : App\Services\Formations (systèmes reconnus, postes qui collent
 * ou non) et Fiche::pitch() (placement ligne par ligne, de l'arrière vers l'avant et de la droite vers
 * la gauche ; placement automatique inchangé sans système). Rien n'est écrit.
 * Usage : php tests/formations.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Front\Fiche;
use App\Services\Formations as F;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// 1. Systèmes : 20, tous à dix joueurs de champ ; anciens textes reconnus.
$eq('20 systèmes', count(F::ALL), 20);
$eq('tous à dix joueurs de champ', array_unique(array_map(fn ($f) => array_sum($f[1]) + array_sum($f[2]), array_values(F::ALL))), [10]);
$eq('clés écrites comme leurs lignes', array_keys(array_filter(F::ALL, fn ($f, $k) => $k !== implode('-', array_merge($f[1], $f[2])), ARRAY_FILTER_USE_BOTH)), []);
$eq('« 442 », « 4.4.2 », « 4 - 4 - 2 »', [F::key('442'), F::key('4.4.2'), F::key('4 - 4 - 2')], ['4-4-2', '4-4-2', '4-4-2']);
$eq('losange, WM, pyramide', [F::key('4-4-2 losange'), F::key('WM'), F::key('la pyramide')], ['4-1-2-1-2', '3-2-2-3', '2-3-5']);
$eq('texte non reconnu ou vide', [F::key('offensif'), F::key(''), F::key('3-4-2')], [null, null, null]);

// 2. L'exemple du ticket : 4-1-3-2.
$row = fn (string $p, string $n) => ['position' => $p, 'name' => $n];
$rows = [$row('G', 'Richert'), $row('D', 'Faty'), $row('D', 'Perquis'), $row('D', 'Bréchet'), $row('D', 'Sauget'),
    $row('M', 'Anin'), $row('M', 'Boudebouz'), $row('M', 'Martin'), $row('M', 'Maurice-Belay'), $row('A', 'Ideye'), $row('A', 'Maïga'), $row('R', 'Pouplin')];
$p = Fiche::pitch($rows, '4-1-3-2');
$at = [];
foreach ($p['players'] as $pl) {
    $at[$pl['name']] = [$pl['x'], $pl['y']];
}
$eq('4-1-3-2 : système affiché', $p['formation'], '4-1-3-2');
$eq('4-1-3-2 : onze titulaires placés', count($p['players']), 11);
$eq('gardien en bas au centre', $at['Richert'], [50.0, 90]);
$eq('défense : Faty à droite … Sauget à gauche', [$at['Faty'][0], $at['Sauget'][0], $at['Faty'][1]], [84.0, 16.0, 72.0]);
$eq('Anin seul au centre, devant la défense', [$at['Anin'][0], $at['Anin'][1] < 72, $at['Anin'][1] > $at['Martin'][1]], [50.0, true, true]);
$eq('milieux offensifs : Boudebouz à droite, Martin au centre, Maurice-Belay à gauche', [$at['Boudebouz'][0], $at['Martin'][0], $at['Maurice-Belay'][0]], [84.0, 50.0, 16.0]);
$eq('attaque : Ideye à droite, Maïga à gauche, resserrés vers l’axe, devant tout le monde', [$at['Ideye'][0], $at['Maïga'][0], $at['Ideye'][1] < $at['Martin'][1]], [65.0, 35.0, true]);
$eq('quatre lignes de champ distinctes', count(array_unique(array_map(fn ($n) => (string) $at[$n][1], ['Faty', 'Anin', 'Boudebouz', 'Ideye']))), 4);

// 3. 4-2-3-1 avec les mêmes postes (5 M, 1 A) ; 4-4-2 losange.
$p3 = Fiche::pitch(array_merge(array_slice($rows, 0, 9), [$row('M', 'Ideye'), $row('A', 'Maïga')]), '4-2-3-1');
$eq('4-2-3-1 : système affiché', $p3['formation'], '4-2-3-1');
$x3 = [];
foreach ($p3['players'] as $pl) {
    $x3[$pl['name']] = $pl['x'];
}
$eq('4-2-3-1 : deux milieux défensifs resserrés vers l’axe', [$x3['Anin'], $x3['Boudebouz']], [65.0, 35.0]);
$y3 = [];
foreach ($p3['players'] as $pl) {
    $y3[$pl['name']] = $pl['y'];
}
$eq('4-2-3-1 : deux milieux défensifs côte à côte, trois offensifs devant, un attaquant tout devant',
    [$y3['Anin'] === $y3['Boudebouz'], $y3['Martin'] === $y3['Ideye'] && $y3['Martin'] < $y3['Anin'], $y3['Maïga'] < $y3['Martin']], [true, true, true]);
$eq('losange : 5 lignes de champ', count(array_unique(array_map(fn ($pl) => (string) $pl['y'], array_filter(Fiche::pitch($rows, '4-1-2-1-2')['players'], fn ($pl) => $pl['name'] !== 'Richert')))) >= 4, true);

// 3 bis. Ticket : Deedson (A) et Demoncy (M) intervertis dans la composition. Avec un système, milieux
// et attaquants suivent l'ordre de la composition (postes mêlés) : les deux joueurs échangent leur place.
$back = [$row('G', 'Gardien'), $row('D', 'D1'), $row('D', 'D2'), $row('D', 'D3'), $row('D', 'D4')];
$at6 = function (array $list, ?string $f) {
    $o = [];
    foreach (Fiche::pitch($list, $f)['players'] as $pl) {
        $o[$pl['name']] = [$pl['x'], $pl['y']];
    }
    return $o;
};
$before = array_merge($back, [$row('M', 'Demoncy'), $row('M', 'Milieu2'), $row('A', 'Deedson'), $row('M', 'Milieu3'), $row('M', 'Milieu4'), $row('A', 'Avant')]);
$after = array_merge($back, [$row('A', 'Deedson'), $row('M', 'Milieu2'), $row('M', 'Demoncy'), $row('M', 'Milieu3'), $row('M', 'Milieu4'), $row('A', 'Avant')]);
$p6 = $at6($before, '4-2-3-1');
$eq('4-2-3-1 : un attaquant listé avant des milieux garde sa place (Deedson à droite des milieux offensifs)', [$p6['Deedson'][0], $p6['Deedson'][1] === $p6['Milieu4'][1], $p6['Avant'][0]], [84.0, true, 50.0]);
$p7 = $at6($after, '4-2-3-1');
$eq('Deedson et Demoncy intervertis : ils échangent leur place sur le terrain', [$p7['Deedson'], $p7['Demoncy']], [$p6['Demoncy'], $p6['Deedson']]);
$eq('les autres joueurs ne bougent pas', [$p7['Milieu2'], $p7['Milieu3'], $p7['Milieu4'], $p7['Avant']], [$p6['Milieu2'], $p6['Milieu3'], $p6['Milieu4'], $p6['Avant']]);
$eq('sans système : chacun reste sur la ligne de son poste (attaquant devant, milieu au milieu)', [$at6($after, null)['Deedson'][1], $at6($after, null)['Demoncy'][1] >= 50], [23, true]);

// 4. Postes qui ne collent pas : placement automatique, message pour l'historien.
$rows3 = $rows;
$rows3[4]['position'] = 'M'; // Sauget milieu : 3 D, 5 M, 2 A
$p4 = Fiche::pitch($rows3, '4-1-3-2');
$eq('postes différents : placement automatique (3-5-2 déduit)', $p4['formation'], '3-5-2');
$eq('postes différents : message', str_contains((string) F::problem('4-1-3-2', $rows3), 'la composition compte 3 D, 5 M et 2 A'), true);
$eq('postes qui collent : pas de message', F::problem('4-1-3-2', $rows), null);
$eq('texte non reconnu : message', str_contains((string) F::problem('offensif', $rows), 'non reconnue'), true);
$eq('pas de système : pas de message', F::problem('', $rows), null);

// 5. Sans système : placement d'avant, inchangé.
$p5 = Fiche::pitch($rows);
$at5 = [];
foreach ($p5['players'] as $pl) {
    $at5[$pl['name']] = [$pl['x'], $pl['y']];
}
$eq('sans système : 4-4-2 déduit des postes', $p5['formation'], '4-4-2');
$eq('sans système : milieux sur une seule ligne (50, les deux du centre un peu reculés)', [$at5['Anin'][1], $at5['Boudebouz'][1], $at5['Martin'][1], $at5['Maurice-Belay'][1]], [50, 53, 53, 50]);
$eq('sans système : défense comme avant, deux attaquants resserrés vers l’axe', [$at5['Faty'], $at5['Ideye'], $at5['Maïga']], [[84.0, 72], [65.0, 23], [35.0, 23]]);

// 6. Noms sur le terrain (ticket) : avec leurs accents, traits d'union et apostrophes ; écrits sans
// accent dans la composition, orthographe de la fiche du joueur reliée (mêmes lettres seulement).
$N = \App\Data\Names::class;
$eq('accents de la composition gardés', [$N::lineupShort('DRAMÉ Boukary'), $N::lineupShort('MAÏGA Modibo'), $N::lineupShort('BRÉCHET Jérémie'), $N::lineupShort('MAïGA Modibo')], ['DRAMÉ', 'MAÏGA', 'BRÉCHET', 'MAÏGA']);
$eq('trait d’union, apostrophe, nom en plusieurs mots', [$N::lineupShort('MAURICE-BELAY Nicolas'), $N::lineupShort("MANAC'H Erwan"), $N::lineupShort('VAN DER BIEST Pierre')], ['MAURICE-BELAY', "MANAC'H", 'VAN DER BIEST']);
$eq('sans accent dans la composition : orthographe de la fiche', [$N::lineupShort('DRAME Boukary', 'Dramé'), $N::lineupShort('MAIGA Modibo', 'Maïga'), $N::lineupShort('BRECHET Jérémy', 'Bréchet')], ['DRAMÉ', 'MAÏGA', 'BRÉCHET']);
$eq('fiche d’un autre nom ou sans accent : composition gardée', [$N::lineupShort('DUPONT Jean', 'Durand'), $N::lineupShort('DRAMÉ Boukary', 'Drame')], ['DUPONT', 'DRAMÉ']);
$eq('rapprochement inchangé (sans accents)', $N::lineupLastName('DRAMÉ Boukary'), 'drame');
$people = [];
foreach (\App\Data\Index::all() as $pid => $x) {
    if (in_array($x['p']['last'] ?? '', ['Dramé', 'Maïga', 'Bréchet'], true)) {
        $people[$x['p']['last']] = (int) $pid;
    }
}
if (count($people) === 3) {
    $short = array_column(Fiche::lineupRows(['id' => 0], [
        ['position' => 'D', 'name' => 'BRECHET Jérémy', 'person_id' => $people['Bréchet']],
        ['position' => 'D', 'name' => 'DRAME Boukary', 'person_id' => $people['Dramé']],
        ['position' => 'A', 'name' => 'MAÏGA Modibo', 'person_id' => null],
    ]), 'short');
    $eq('terrain de la fiche match : BRÉCHET, DRAMÉ, MAÏGA', $short, ['BRÉCHET', 'DRAMÉ', 'MAÏGA']);
}

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
