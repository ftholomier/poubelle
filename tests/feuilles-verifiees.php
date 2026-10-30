<?php
/**
 * Feuilles de match vérifiées (App\Services\VerifiedSheets) : composition provisoire (la même dans
 * plusieurs matchs) remplacée, composition saisie jamais écrasée (proposition Trouvailles), match
 * absent créé, arbitre et affluence complétés, écarts (score, date, domicile) proposés, « Entré en
 * jeu » sans minute, terrain neutre ; relancer ne refait rien. Fiches d'essai créées puis supprimées.
 * Usage : php tests/feuilles-verifiees.php (code 1 si échec).
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Data\Fiches;
use App\Services\Trouvailles;
use App\Services\VerifiedSheets;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$ids = [];
$row = fn (string $pos, string $name, string $sub = '') => ['position' => $pos, 'name' => $name, 'number' => null, 'extra' => null, 'captain' => false, 'goals' => [], 'own_goals' => [], 'goals_text' => '',
    'sub_in' => null, 'sub_out' => null, 'sub_text' => $sub, 'yellow' => [], 'red' => [], 'cards_text' => '', 'person_id' => null];
$model = [$row('G', 'MODELE Gardien'), $row('D', 'MODELE Un'), $row('D', 'MODELE Deux'), $row('D', 'MODELE Trois'), $row('M', 'MODELE Quatre'), $row('M', 'MODELE Cinq'), $row('M', 'MODELE Six'), $row('A', 'MODELE Sept'), $row('E', 'MODELE Coach')];
$match = function (string $date, string $opp, int $us, int $them, array $rows, string $referee = '', ?int $spect = null) use (&$ids): int {
    $doc = Fiches::blank('match');
    $doc['title'] = 'Essai feuilles ' . $date;
    $doc['status'] = 'publie';
    $m = &$doc['match'];
    $m['date'] = $date;
    $m['season'] = '2025-2026';
    $m['competition'] = 'Championnat';
    $m['home'] = ['name' => 'Sochaux', 'level' => null];
    $m['away'] = ['name' => $opp, 'level' => null];
    $m['sochaux_home'] = true;
    $m['score'] = ['home' => $us, 'away' => $them, 'extra' => null, 'aet' => false, 'pens' => null];
    $m['referee'] = $referee;
    $m['spectators'] = $spect;
    $m['lineup']['rows'] = $rows;
    unset($m);
    $doc['path'] = '/matchs/2025-2026/essai-feuilles-' . $date . '/';
    return $ids[] = (int) Fiches::save($doc, ['name' => 'Essai'])['id'];
};
$p = fn (string $name, string $pos, ?string $in = null, ?string $out = null, array $extra = []) => $extra + ['name' => $name, 'pos' => $pos, 'in' => $in, 'out' => $out, 'goals' => [], 'yellow' => [], 'red' => [], 'cap' => false];
$eleven = fn (string $tag) => [
    $p("GARDIEN $tag", 'G'), $p("ARRIERE Un$tag", 'D'), $p("ARRIERE Deux$tag", 'D', null, '60'), $p("ARRIERE Trois$tag", 'D'), $p("ARRIERE Quatre$tag", 'D'),
    $p("MILIEU Un$tag", 'M', null, null, ['goals' => ['12'], 'cap' => true]), $p("MILIEU Deux$tag", 'M', null, null, ['yellow' => ['33']]), $p("MILIEU Trois$tag", 'M'),
    $p("AVANT Un$tag", 'A'), $p("AVANT Deux$tag", 'A'), $p("AVANT Trois$tag", 'A'), $p("REMPLACANT Entre$tag", 'R', '60'), $p("REMPLACANT Banc$tag", 'R'),
];
$sheet = fn (string $date, string $opp, int $us, int $them, string $tag, array $extra = []) => $extra + [
    'date' => $date, 'kind' => 'officiel', 'competition' => 'Championnat de France, Ligue 2', 'round' => '1ère journée', 'home' => 'FC Sochaux', 'away' => $opp, 'sochaux_home' => true,
    'score_home' => $us, 'score_away' => $them, 'half_time' => '1-0', 'score_note' => '', 'place' => 'Sochaux', 'stadium' => 'Stade Auguste Bonal', 'spectators' => 1234,
    'referee' => 'Albert Dupontel', 'confidence' => 'haute', 'coaches' => ['ESSAI Entraîneur'], 'players' => $eleven($tag),
    'goals_sochaux' => 'Un' . $tag . " 12'", 'goals_opponent' => $them ? "Adverse 50'" : '', 'sources' => ['transfermarkt.fr', 'footballdatabase.eu'],
];
$file = STORAGE_PATH . '/essai-feuilles-verifiees.json';

try {
    $p1 = $match('2026-06-03', 'Essai Alpha', 1, 0, $model, 'A. Dupontel');
    $p2 = $match('2026-06-10', 'Essai Bravo', 1, 0, $model);
    $p3 = $match('2026-06-17', 'Essai Charlie', 1, 0, $model);
    $own = [$row('G', 'SAISI Gardien'), $row('D', 'SAISI Un'), $row('D', 'SAISI Deux'), $row('D', 'SAISI Trois'), $row('M', 'SAISI Quatre'), $row('M', 'SAISI Cinq'), $row('M', 'SAISI Six'), $row('A', 'SAISI Sept')];
    $c1 = $match('2026-06-24', 'Essai Delta', 1, 0, $own, 'Albert Dupontel', 900);
    file_put_contents($file, json_encode(['saison' => '2025-2026', 'matchs' => [
        $sheet('2026-06-03', 'Essai Alpha FC', 1, 0, 'A'),
        $sheet('2026-06-10', 'Essai Bravo', 2, 0, 'B'),
        $sheet('2026-06-24', 'Essai Delta', 1, 0, 'D'),
        $sheet('2026-06-27', 'Essai Echo', 1, 1, 'E', ['kind' => 'amical', 'competition' => 'Amical', 'round' => '', 'neutral' => true, 'confidence' => 'moyenne']),
        $sheet('2026-06-18', 'Essai Charlie', 1, 0, 'C', ['home' => 'Essai Charlie', 'away' => 'FC Sochaux', 'sochaux_home' => false, 'score_home' => 0, 'score_away' => 1]),
    ]], JSON_UNESCAPED_UNICODE));
    // Joueur entré sans minute connue (amical)
    $data = json_decode((string) file_get_contents($file), true);
    $data['matchs'][3]['players'][] = $p('ENTRE Sansminute', 'R', null, null, ['entered' => true]);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));

    $r = VerifiedSheets::import($file);
    $eq('compte rendu', $r['message'], 'feuilles de match 2025-2026 : 1 match(s) créé(s), 3 composition(s) provisoire(s) remplacée(s), 1 fiche(s) complétée(s), 5 proposition(s) dans Trouvailles');
    $d1 = Fiches::fresh($p1)['match'];
    $eq('provisoire remplacée : titulaires, remplaçants entrés puis restés sur le banc, entraîneur', array_map(fn ($x) => $x['position'] . ' ' . $x['name'], $d1['lineup']['rows']), [
        'G GARDIEN A', 'D ARRIERE UnA', 'D ARRIERE DeuxA', 'D ARRIERE TroisA', 'D ARRIERE QuatreA', 'M MILIEU UnA', 'M MILIEU DeuxA', 'M MILIEU TroisA', 'A AVANT UnA', 'A AVANT DeuxA', 'A AVANT TroisA',
        'R REMPLACANT EntreA', 'R REMPLACANT BancA', 'E ESSAI Entraîneur']);
    $byName = array_column($d1['lineup']['rows'], null, 'name');
    $eq('but, brassard, carton, sortie, entrée', [$byName['MILIEU UnA']['goals'], $byName['MILIEU UnA']['captain'], $byName['MILIEU DeuxA']['yellow'], $byName['ARRIERE DeuxA']['sub_out'], $byName['REMPLACANT EntreA']['sub_in'], $byName['REMPLACANT BancA']['sub_in']], [['12'], true, ['33'], '60', '60', null]);
    $eq('buteurs des deux équipes', [$d1['goals'][0]['scorers'] ?? null, $d1['goals_text']], ["UnA 12'", "UnA 12' pour " . \App\Core\Club::team()]);
    $eq('arbitre noté par son initiale : nom complet', $d1['referee'], 'Albert Dupontel');
    $eq('affluence vide : complétée', [$d1['spectators'], $d1['spectators_text']], [1234, "1\u{202F}234 spectateurs"]);
    $eq('mi-temps dans l’en-tête', $d1['header_extra'], ['Mi-temps : 1-0']);
    $eq('section des sources', end(Fiches::fresh($p1)['sections'])['title'], 'Sources de la feuille de match');
    $props = fn (int $id) => array_map(fn ($x) => $x['field'] . ' : ' . $x['value'], (array) (\App\Core\JsonStore::read(Trouvailles::$dir . "/props/$id.json", [])['items'] ?? []));
    $eq('score différent : proposé, pas changé', [$props($p2), Fiches::fresh($p2)['match']['score']['home']], [['score : 2-0'], 1]);
    $dc = Fiches::fresh($c1)['match'];
    $eq('composition saisie : jamais écrasée', $dc['lineup']['rows'][0]['name'], 'SAISI Gardien');
    $pc = $props($c1);
    $eq('composition saisie : feuille vérifiée proposée', str_starts_with($pc[0] ?? '', 'feuille : G · GARDIEN D'), true);
    $eq('affluence déjà saisie et différente : proposée, rien d’écrasé', [$pc[1] ?? null, $dc['spectators']], ['affluence : 1234', 900]);
    $eq('composition saisie : mi-temps ajoutée à l’en-tête (fiche complétée)', $dc['header_extra'], ['Mi-temps : 1-0']);
    $eq('date et domicile différents : proposés', $props($p3), ['date : 18/06/2026', 'info : Selon les sources de la feuille vérifiée, ' . \App\Core\Club::team() . ' jouait à l’extérieur (Essai Charlie – FC Sochaux, Stade Auguste Bonal).']);
    $new = $r['created'][0] ?? 0;
    $ids[] = $new;
    $dn = Fiches::fresh($new);
    $eq('match absent : créé, publié, dans sa saison', [$dn['status'], $dn['match']['date'], $dn['match']['season'], in_array('2025-2026', $dn['categories'], true)], ['publie', '2026-06-27', '2025-2026', true]);
    $en = array_column($dn['match']['lineup']['rows'], null, 'name')['ENTRE Sansminute'] ?? [];
    $eq('entré sans minute connue : « Entré en jeu »', [array_key_exists('sub_in', $en) ? $en['sub_in'] : 'x', $en['sub_text'] ?? ''], [null, 'Entré en jeu']);
    $eq('confiance moyenne signalée dans les sources', str_contains((string) end($dn['sections'])['html'], 'à confirmer'), true);
    $eq('relancer : rien de plus', VerifiedSheets::import($file)['message'], 'feuilles de match 2025-2026 : 0 match(s) créé(s), 0 composition(s) provisoire(s) remplacée(s), 0 fiche(s) complétée(s), 0 proposition(s) dans Trouvailles');

    // Fiche sans composition : la feuille l'ajoute, avec le titre et les colonnes du tableau, et sa note.
    $v1 = $match('2026-07-01', 'Essai Foxtrot', 1, 1, [], 'M. Dupontel');
    $doc = Fiches::fresh($v1);
    $doc['match']['lineup'] = null;
    Fiches::save($doc, ['name' => 'Essai']);
    file_put_contents($file, json_encode(['saison' => '2025-2026', 'matchs' => [$sheet('2026-07-01', 'Essai Foxtrot', 1, 1, 'F', ['note' => 'Remplaçants restés sur le banc : inconnus.'])]], JSON_UNESCAPED_UNICODE));
    $eq('composition manquante : compte rendu', VerifiedSheets::import($file)['message'], 'feuilles de match 2025-2026 : 0 match(s) créé(s), 1 composition(s) ajoutée(s), 0 composition(s) provisoire(s) remplacée(s), 0 fiche(s) complétée(s), 0 proposition(s) dans Trouvailles');
    $dv = Fiches::fresh($v1);
    $eq('composition manquante : ajoutée avec titre et colonnes', [count($dv['match']['lineup']['rows']), $dv['match']['lineup']['title'], $dv['match']['lineup']['headers']],
        [14, 'Composition ' . \App\Core\Club::team(), ['Postes', 'Nom et prénom', 'Buts', 'Remp.', 'Cartons']]);
    $eq('composition manquante : « M. Dupontel » reçoit son prénom', $dv['match']['referee'], 'Albert Dupontel');
    $eq('note de la feuille dans les sources', str_contains((string) end($dv['sections'])['html'], '<p>Remplaçants restés sur le banc : inconnus.</p>'), true);

    // Feuille « existing » sans fiche correspondante : rien n'est créé (jamais de fiche en double).
    file_put_contents($file, json_encode(['saison' => '2025-2026', 'matchs' => [$sheet('2026-07-20', 'Essai Golf', 2, 2, 'G', ['existing' => true])]], JSON_UNESCAPED_UNICODE));
    $rg = VerifiedSheets::import($file);
    $eq('feuille « existing » sans fiche : rien de créé', [$rg['created'], $rg['message']], [[], 'feuilles de match 2025-2026 : 0 match(s) créé(s), 0 composition(s) provisoire(s) remplacée(s), 0 fiche(s) complétée(s), 0 proposition(s) dans Trouvailles, 1 fiche(s) introuvable(s) (rien créé)']);
    $ids = array_merge($ids, $rg['created']);

    // Feuille livrée pour Sochaux–Nîmes du 21 février 1993 : onze titulaires, deux entrées, et la
    // fiche du musée retrouvée (lecture seule ; la correction est appliquée par la tâche « corrections »).
    $real = json_decode((string) file_get_contents(APP_ROOT . '/app/Resources/import/feuilles-1992-1993.json'), true);
    $s9 = $real['matchs'][0] ?? [];
    $eq('feuille 1992-1993 : onze titulaires et deux remplaçants entrés, fiche existante seulement', [count(array_filter($s9['players'], fn ($x) => $x['pos'] !== 'R')), count(array_filter($s9['players'], fn ($x) => $x['pos'] === 'R' && $x['in'] !== null)), $s9['existing'] ?? false], [11, 2, true]);
    $found = \App\Services\FeuillesImport::nearest($s9, \App\Services\FeuillesImport::byDate());
    $fm = $found ? (Fiches::get($found)['match'] ?? []) : [];
    if (\App\Core\Club::isDefault() && is_file(DATA_PATH . '/fiches/15603.json')) {
        $eq('feuille 1992-1993 : la fiche du match est retrouvée', [$fm['date'] ?? null, $fm['away']['name'] ?? null], ['1993-02-21', 'Nîmes']);
    }
} finally {
    @unlink($file);
    foreach (array_reverse(array_filter($ids)) as $id) {
        Fiches::destroy($id, ['name' => 'Essai']);
        @unlink(Trouvailles::$dir . "/props/$id.json");
    }
    \App\Data\Index::rebuild();
    \App\Services\Search::rebuild();
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
