<?php
/** Statistiques : agrégation (visiteurs, visites, visites d'une page), temps réel, classements, rapport PDF, remise à zéro. */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Services\Stats;
use App\Services\StatsReport;

$fail = 0;
$eq = function (string $what, $got, $want) use (&$fail) {
    $ok = $got === $want;
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'OK   ' : 'ÉCHEC ') . $what . ($ok ? '' : ' : ' . var_export($got, true) . ' au lieu de ' . var_export($want, true)) . "\n";
};
$dir = STORAGE_PATH . '/stats';
$keep = STORAGE_PATH . '/stats-test-' . getmypid();
if (is_dir($dir)) {
    rename($dir, $keep);
}
try {
    @mkdir("$dir/raw", 0775, true);
    $now = time();
    $t = fn (int $ago) => date('H:i:s', $now - $ago);
    // Visiteur A : 3 pages en une visite ; visiteur B : 1 page puis une 2e visite 40 min plus tard.
    $lines = [
        $t(3000) . '|fr|/joueurs/test/|bbbbbbbbbbbb|mobile|google.com',
        $t(2000) . '|fr|/matchs/1983-1984/sochaux-test-02-07-1983/|aaaaaaaaaaaa|ordinateur|',
        $t(1900) . '|en|/matchs/annees-1980/|aaaaaaaaaaaa|ordinateur|',
        $t(1700) . '|fr|/recherche/?q=bonal|aaaaaaaaaaaa|ordinateur|',
        $t(30) . '|fr|/|bbbbbbbbbbbb|mobile|',
    ];
    if ((int) date('G') < 1) {
        echo "(juste après minuit : test du jour ignoré)\n";
    } else {
        file_put_contents("$dir/raw/" . date('Y-m-d') . '.log', implode("\n", $lines) . "\n");
        Stats::aggregate();
        $d = Stats::days()[date('Y-m-d')] ?? [];
        $eq('pages vues', $d['total'] ?? 0, 5);
        $eq('visiteurs uniques', $d['visitors'] ?? 0, 2);
        $eq('visites (30 min d’inactivité ferment une visite)', $d['visits'] ?? 0, 3);
        $eq('visites d’une seule page', $d['bounces'] ?? 0, 2);
        $eq('provenance comptée par visite', $d['ref']['google.com'] ?? 0, 1);
        $live = Stats::live();
        $eq('temps réel : 1 visiteur en ligne (5 dernières minutes)', $live['online'], 1);
        $r = StatsReport::build(date('Y-m-d'), date('Y-m-d'));
        $eq('recherche relevée', array_key_first($r['searches']), 'bonal');
        $eq('décennie des années 1980', isset($r['decades']['1980']), true);
        $eq('rapport PDF', str_starts_with(\App\Pdf\StatsPdf::build($r, 'Aujourd’hui'), '%PDF'), true);
        // Agrégation incrémentale : une ligne de plus ne recompte pas les précédentes.
        file_put_contents("$dir/raw/" . date('Y-m-d') . '.log', $t(5) . "|fr|/|bbbbbbbbbbbb|mobile|\n", FILE_APPEND);
        Stats::aggregate();
        $eq('agrégation incrémentale', Stats::days()[date('Y-m-d')]['total'] ?? 0, 6);
    }
    $eq('décennie d’une saison', StatsReport::decade('/matchs/1953-1954/'), '1950');
    $eq('période « 7 jours »', StatsReport::period('7j')[0], date('Y-m-d', strtotime('-6 days')));
    $eq('dates inversées remises dans l’ordre', StatsReport::period('', '2026-02-01', '2026-01-01')[0], '2026-01-01');
    $old = Stats::reset();
    $eq('remise à zéro', Stats::days(), []);
    $eq('anciennes données gardées', is_dir(STORAGE_PATH . '/' . $old), true);
    exec('rm -rf ' . escapeshellarg(STORAGE_PATH . '/' . $old));
} finally {
    exec('rm -rf ' . escapeshellarg($dir));
    if (is_dir($keep)) {
        rename($keep, $dir);
    }
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
