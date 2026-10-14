<?php
/**
 * Soirées entre amis (quiz du club-house créé par un supporter) : classement « Entre amis » séparé du
 * championnat, au plus 3 parties comptées par jour et par joueur. Usage : php tests/quiz-amis.php
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Services\QuizChampionship as C;
use App\Services\QuizLive as Q;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got) . ' attendu ' . json_encode($exp)) . "\n";
};
$tmp = sys_get_temp_dir() . '/quiz-amis-' . bin2hex(random_bytes(4));
mkdir($tmp);
C::$file = $tmp . '/championnat.json';
Q::$dir = $tmp . '/parties';
$d = ['players' => [], 'seasons' => []];
foreach (['c1', 'c2', 'c3'] as $c) {
    $d['players'][$c] = ['pseudo' => "P$c", 'ok' => true, 'since' => date('Y-m-d')];
}
JsonStore::write(C::$file, $d);
$play = function (string $league) {
    $g = Q::create(3, 15, 'fr', 'mix', 'supporter', false, $league);
    foreach (['c1', 'c2', 'c3'] as $c) {
        Q::join($g['code'], "P$c", $c);
    }
    for ($i = 0; $i < 20; $i++) {
        JsonStore::update(Q::$dir . '/' . $g['code'] . '.json', function ($x) { $x['t0'] -= 5000; return $x; });
        if ((Q::next($g['code'])['phase'] ?? '') === 'end') {
            break;
        }
    }
    return Q::get($g['code']);
};
$counted = [];
for ($k = 0; $k < 4; $k++) {
    $g = $play('amis');
    $counted[] = $g['counted'] ?? null;
    $eq("partie $k : ligue gardée", $g['league'], 'amis');
}
$eq('3 parties comptées par jour, la 4e non', $counted, [true, true, true, false]);
$s = C::season();
$eq('classement entre amis : 3 parties chacun', array_map(fn ($r) => $r['games'], C::ranking(C::key($s, 'amis'))), [3, 3, 3]);
$eq('championnat du club-house intact', C::ranking($s), []);
$eq('saisons séparées', [C::seasons('amis'), C::seasons()], [[$s], []]);
$g = $play('club');
$eq('partie du club-house : comptée au championnat', [$g['counted'] ?? null, count(C::ranking($s))], [true, 3]);
exec('rm -rf ' . escapeshellarg($tmp));
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
