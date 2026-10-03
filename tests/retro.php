<?php
/**
 * Rétro-Direct (App\Services\RetroDirect) : chronologie d'un match (buts et score, mi-temps,
 * prolongation, tirs au but, remplacements), programme et états, anniversaires proposés,
 * spectateurs et réactions, agenda .ics. Usage : php tests/retro.php (code de sortie 1 en
 * cas d'échec). N'écrit que dans un dossier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Data\Fiches;
use App\Front\Retro;
use App\Services\RetroDirect as R;

$tmp = sys_get_temp_dir() . '/retro-test-' . bin2hex(random_bytes(4));
R::$dir = $tmp;
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$of = fn (array $t, string $type) => array_values(array_filter($t['events'], fn ($e) => $e['type'] === $type));

// Minutes et buteurs.
$eq('minute simple', R::minute('37'), [37, 0]);
$eq('temps additionnel', [R::minute('45+2'), R::minute("90 + 3'")], [[45, 2], [90, 3]]);
$eq('minute illisible', [R::minute('MT'), R::minute(''), R::minute('200')], [null, null, null]);
$g = R::textGoals(['goals' => [['team' => 'Sochaux', 'scorers' => "Sauzée 37' et 61' (s.p)"], ['team' => 'Le Puy', 'scorers' => "Faure 72'"]]], 'Sochaux', 'Le Puy');
$eq('ligne des buteurs', array_map(fn ($x) => [$x['who'], $x['m'][0], $x['side']], $g), [['Sauzée', 37, 0], ['Sauzée', 61, 0], ['Faure', 72, 1]]);

// Sochaux – Le Puy, 28 février 1988 (2-1) : buts des temps forts, mi-temps, remplacement.
$t = R::timeline(Fiches::get(2431));
$goals = $of($t, 'goal');
$eq('buts : minutes, score, camp, buteur', array_map(fn ($x) => [$x['min'], $x['score'], $x['side'], $x['who']], $goals),
    [['37', [1, 0], 0, 'Sauzée'], ['61', [2, 0], 0, 'Sauzée'], ['72', [2, 1], 1, 'Faure']]);
$eq('but à la 37e : 36 min 30 après le coup d’envoi', $goals[0]['t'], 2190);
$eq('mi-temps, reprise après 15 minutes, fin', [$of($t, 'halftime')[0]['t'], $of($t, 'kickoff2')[0]['t'], $t['end'], $of($t, 'fulltime')[0]['score']], [2700, 3600, 6300, [2, 1]]);
$eq('but de la 61e en seconde période', $goals[1]['t'], (int) (3600 + (61 - 45.5) * 60));
$sub = $of($t, 'sub');
$eq('entrée de Jacky Colin (63e), sortie isolée ignorée', [count($sub), $sub[0]['who'], $sub[0]['out'], $sub[0]['min']], [1, 'Jacky Colin', null, '63']);
$eq('nom accentué de la fiche du joueur', str_contains(json_encode($t['events'], JSON_UNESCAPED_UNICODE), 'Franck Sauzee'), false);
$ts = array_column($t['events'], 't');
$sorted = $ts;
sort($sorted);
$eq('événements dans l’ordre, coup d’envoi d’abord', $ts === $sorted && $t['events'][0]['type'] === 'kickoff', true);
$eq('pas de prolongation', [$t['aet'], $t['marks']['extratime'], $t['pens']], [false, null, null]);

// Finale de la Coupe de France 1988 : prolongation et tirs au but (Metz 5-4).
$t = R::timeline(Fiches::get(14188));
$eq('finale 1988 : prolongation, tirs au but', [$t['aet'], $t['pens'], $t['final']], [true, [5, 4], [1, 1]]);
$eq('finale 1988 : ordre des grands moments', array_values(array_filter(array_column($t['events'], 'type'), fn ($x) => !in_array($x, ['action', 'goal', 'sub', 'yellow', 'red'], true))),
    ['kickoff', 'halftime', 'kickoff2', 'fulltime90', 'extratime', 'pens', 'fulltime']);
$eq('finale 1988 : durée (2 × 45, prolongation, tirs au but)', $t['end'] >= (45 + 15 + 45 + 5 + 30 + 3 + 8) * 60, true);
$eq('finale 1988 : 1-1 à la fin', $of($t, 'fulltime')[0]['pens'], [5, 4]);
// Prolongation sans tirs au but : buts de la 119e et 120e après la reprise de la prolongation.
$t = R::timeline(Fiches::get(15930));
$eq('prolongation : buts après la 90e', array_map(fn ($x) => $x['t'] > $t['marks']['extratime'], $of($t, 'goal')), [true, true]);
// Score noté du point de vue de Sochaux (à l'extérieur) : remis dans l'ordre domicile-extérieur.
$t = R::timeline(Fiches::get(15183));
$eq('score retourné (0-4 à l’extérieur)', array_map(fn ($x) => [$x['score'], $x['side']], $of($t, 'goal')), [[[0, 1], 1], [[0, 2], 1], [[0, 3], 1], [[0, 4], 1]]);
// Buts manquants dans les temps forts : ligne des buteurs.
$t = R::timeline(Fiches::get(12529));
$eq('buts manquants repris des buteurs', array_map(fn ($x) => $x['score'], $of($t, 'goal')), [[1, 0], [1, 1]]);
$eq('match rejouable', [R::playable(Fiches::get(2431)), R::playable(['match' => ['highlights' => [['minute' => '10']], 'goals' => []]])], [true, false]);

// Programme : états avenir / direct / terminé, adresse, intérêt.
$s = App\Data\Index::get(2431);
$now = strtotime('2026-10-03 20:37:30');
R::$entries = [
    ['id' => 2431, 'date' => '2026-10-03', 'time' => '20:00', 'intro' => 'La neige à Bonal.'],
    ['id' => 2431, 'date' => '2026-12-24', 'time' => '18:30'],
    ['id' => 14188, 'date' => '2026-06-11', 'time' => '25:00'],
    ['id' => 999999999, 'date' => '2026-10-10', 'time' => '20:00'],
];
$p = R::program($now);
$eq('programme : fiches inconnues écartées, heure invalide → 20 h', array_map(fn ($e) => [$e['id'], $e['date'], $e['time'], $e['state']], $p),
    [[14188, '2026-06-11', '20:00', 'termine'], [2431, '2026-10-03', '20:00', 'direct'], [2431, '2026-12-24', '18:30', 'avenir']]);
$eq('fin du direct = coup de sifflet + après-match', [$p[1]['whistle'] - $p[1]['start'], $p[1]['end'] - $p[1]['whistle']], [6300, R::AFTER]);
$eq('direct du match : celui en cours', R::entryFor(2431, $now)['date'], '2026-10-03');
$eq('après le direct : le suivant', R::entryFor(2431, strtotime('2026-10-04 12:00'))['date'], '2026-12-24');
$eq('bandeau : prochain direct', R::next(strtotime('2026-10-05 12:00'))['date'], '2026-12-24');
$eq('adresse du direct', R::url($s), '/interactif/retro-direct/sochaux-le-puy-division-2-28-02-1988/');
$eq('fiche retrouvée par l’adresse', (R::bySlug('sochaux-le-puy-division-2-28-02-1988')['id'] ?? 0) === 2431 && R::bySlug('../etc') === null, true);
$sug = R::suggestions(90, strtotime('2026-10-01 12:00'), 40);
$eq('anniversaires ronds, dans les 90 jours, dans l’ordre', count(array_filter($sug, fn ($x) => in_array($x['ago'], R::ROUND, true) && $x['date'] >= '2026-10-01' && $x['date'] <= '2026-12-30')) === count($sug) && $sug === array_values($sug), true);
$eq('Sochaux–Marseille du 4 octobre 1986 proposé (40 ans)', (bool) array_filter($sug, fn ($x) => $x['date'] === '2026-10-04' && $x['ago'] === 40 && str_contains($x['s']['title'], 'Marseille')), true);

// Spectateurs connectés, réactions, « J'y étais ! ».
R::presence(2431, '2026-10-03', 'abcdefgh12');
R::presence(2431, '2026-10-03', 'abcdefgh12');
$c = R::presence(2431, '2026-10-03', 'zzzzzzzz99');
$eq('spectateurs : deux navigateurs', [$c['viewers'], $c['peak']], [2, 2]);
$eq('jeton invalide ignoré', R::presence(2431, '2026-10-03', 'BAD TOKEN!')['viewers'], 2);
R::react(2431, '2026-10-03', 'but');
$c = R::react(2431, '2026-10-03', 'but');
R::react(2431, '2026-10-03', 'inconnu');
$eq('réactions', R::stats(2431, '2026-10-03')['reactions'], ['but' => 2, 'bravo' => 0, 'wow' => 0]);
$eq('« J’y étais ! » par match', [R::addEtais(2431), R::addEtais(2431), R::etais(2431), R::etais(14188)], [1, 2, 2, 0]);

// Agenda .ics : directs à venir, lignes de 75 octets, UTC.
R::$entries = [['id' => 2431, 'date' => date('Y-m-d', strtotime('+3 days')), 'time' => '20:30', 'intro' => str_repeat('La neige déblayée dès 8 h ; un vrai match d’hiver, à Bonal. ', 3)]];
$res = Retro::ics(new Request('GET', '/interactif/retro-direct/agenda.ics', [], [], [], [], ''));
$body = $res->body;
$lines = explode("\r\n", rtrim($body, "\r\n"));
$eq('agenda : un événement', substr_count($body, 'BEGIN:VEVENT'), 1);
$eq('agenda : lignes de 75 octets au plus', max(array_map('strlen', $lines)) <= 75, true);
$unfold = str_replace("\r\n ", '', $body);
$eq('agenda : titre et horaire UTC', [str_contains($unfold, 'SUMMARY:Rétro-Direct : Sochaux – Le Puy (1988)'), (bool) preg_match('/DTSTART:\d{8}T\d{6}Z/', $unfold), str_contains($unfold, 'La neige déblayée dès 8 h \; un vrai match d’hiver\, à Bonal.')], [true, true, true]);
$eq('agenda d’un direct inconnu : 404', Retro::ics(new Request('GET', '/interactif/retro-direct/agenda.ics', ['match' => '2431', 'date' => '2001-01-01'], [], [], [], ''))->status, 404);
R::$entries = null;

// Ménage.
$rm = function (string $d) use (&$rm) {
    foreach (glob("$d/*") ?: [] as $f) {
        is_dir($f) ? $rm($f) : unlink($f);
    }
    @rmdir($d);
};
$rm($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
