<?php
/**
 * Compositions reliées aux fiches des joueurs (App\Services\LineupLinks, tâche « liens-compositions ») :
 * numéro de fiche posé quand le nom est sûr, prénom ajouté seulement s'il manque, homonymes départagés
 * par l'initiale, rien de relié en cas de doute ; relancer ne refait rien. Fiches d'essai créées puis
 * supprimées. Usage : php tests/liens-compositions.php (code 1 si échec).
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Data\Fiches;
use App\Services\LineupLinks;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$ids = [];
$person = function (string $first, string $last, string $born, string $arrival) use (&$ids): int {
    $doc = Fiches::blank('personne');
    $doc['title'] = trim($first . ' ' . $last);
    $doc['status'] = 'publie';
    $doc['personne']['first_name'] = $first;
    $doc['personne']['last_name'] = $last;
    $doc['personne']['birth'] = ['date' => ['iso' => $born, 'precision' => 'day', 'text' => '']];
    $doc['personne']['arrival'] = ['iso' => $arrival, 'precision' => 'year', 'text' => ''];
    $doc['path'] = '/joueurs/essai-' . strtolower($last) . '-' . strtolower(preg_replace('/\W/u', '', $first) ?: 'x') . '/';
    return $ids[] = (int) Fiches::save($doc, ['name' => 'Essai'])['id'];
};
$match = function (string $date, array $rows) use (&$ids): int {
    $doc = Fiches::blank('match');
    $doc['title'] = 'Essai compositions ' . $date;
    $doc['status'] = 'publie';
    $doc['match']['date'] = $date;
    $doc['match']['lineup']['rows'] = array_map(fn ($r) => ['position' => $r[1] ?? 'M', 'name' => $r[0], 'person_id' => $r[2] ?? null], $rows);
    $doc['path'] = '/matchs/essai-compositions-' . $date . '/';
    return $ids[] = (int) Fiches::save($doc, ['name' => 'Essai'])['id'];
};
$rows = fn (int $id) => array_map(fn ($r) => [$r['name'], $r['person_id'] ?? null], Fiches::fresh($id)['match']['lineup']['rows']);

try {
    $q = $person('Zéphyrin', 'Quabzil', '1990-04-02', '2010');
    $vb = $person('Bartholomé', 'Vrognard', '1991-01-10', '2011');
    $vy = $person('Yannick', 'Vrognard', '1993-06-20', '2011');
    $x = $person('?', 'Xurplat', '1989-02-02', '2010');
    $m1 = $match('2012-09-15', [
        ['QUABZIL'], ['QUABZIL Z'], ['QUABZIL Zephirin'], ['QUABZIL Marcel'],
        ['VROGNARD B'], ['VROGNARD Y'], ['VROGNARD'], ['VROGNARD', 'D', $vy],
        ["QUABZIL (A l'essai)", 'R'], ['XURPLAT'], ['2e mi-temps'],
    ]);
    $m2 = $match('1950-03-12', [['QUABZIL']]);
    $out = LineupLinks::run(30, [$m1, $m2]);
    $r = $rows($m1);
    $eq('nom seul : relié, prénom ajouté', $r[0], ['QUABZIL Zéphyrin', $q]);
    $eq('initiale : relié, prénom à la place de l’initiale', $r[1], ['QUABZIL Zéphyrin', $q]);
    $eq('prénom à une faute près (même initiale) : relié, nom de la composition gardé', $r[2], ['QUABZIL Zephirin', $q]);
    $eq('autre prénom : pas relié', $r[3], ['QUABZIL Marcel', null]);
    $eq('homonymes : départagés par l’initiale (B)', $r[4], ['VROGNARD Bartholomé', $vb]);
    $eq('homonymes : départagés par l’initiale (Y)', $r[5], ['VROGNARD Yannick', $vy]);
    $eq('homonymes sans initiale : rien de relié', $r[6], ['VROGNARD', null]);
    $eq('lien déjà posé : gardé, prénom ajouté', $r[7], ['VROGNARD Yannick', $vy]);
    $eq('mention « à l’essai » gardée', $r[8], ["QUABZIL Zéphyrin (A l'essai)", $q]);
    $eq('prénom inconnu (« ? ») : relié, nom gardé', $r[9], ['XURPLAT', $x]);
    $eq('ligne parasite : rien', $r[10], ['2e mi-temps', null]);
    $eq('âge impossible (né en 1990, match de 1950) : pas relié', $rows($m2)[0], ['QUABZIL', null]);
    $eq('compte rendu', $out, '8 ligne(s) de composition reliée(s) dans 1 fiche(s)');
    $eq('relancer : rien de nouveau', LineupLinks::run(30, [$m1, $m2]), 'rien de nouveau');
    $eq('version de la fiche signée par la tâche', (string) (Fiches::versions($m1)[0]['by'] ?? ''), 'Liens des compositions');
} finally {
    foreach (array_reverse($ids) as $id) {
        Fiches::destroy($id, ['name' => 'Essai']);
    }
    \App\Data\Index::rebuild();
    \App\Services\Search::rebuild();
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
