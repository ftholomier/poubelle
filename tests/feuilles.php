<?php
/**
 * Import des feuilles de match (App\Services\FeuillesImport) : jeu de données lisible, compétitions,
 * plan (match absent → à créer, match présent → à comparer), création d'une fiche complète (score,
 * buteurs par équipe, composition, entraîneur, saison), écarts envoyés dans Trouvailles (score, date,
 * affluence) sans toucher la fiche, validation d'une date, relance sans doublon. Les fiches d'essai
 * sont supprimées à la fin. Usage : php tests/feuilles.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Fiches;
use App\Data\Index;
use App\Services\FeuillesImport as F;
use App\Services\Trouvailles as T;

$tmp = sys_get_temp_dir() . '/feuilles-test-' . bin2hex(random_bytes(4));
F::$dir = "$tmp/feuilles";
T::$dir = "$tmp/trouvailles";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

$sheets = F::sheets();
$eq('jeu de données lu (plus de 3 900 feuilles, toutes avec une clé)', [count($sheets) > 3900, count(array_filter($sheets, fn ($s) => strlen($s['key']) === 16)) === count($sheets)], [true, true]);
$eq('compétitions au format du musée', [F::competition(['competition' => 'Championnat de France,D1'])[2], F::competition(['competition' => 'Championnat de France, Ligue 2'])[2], F::competition(['competition' => 'Coupe de France, 8ème tour'])[2], F::competition(['competition' => 'Coupe des Alpes', 'kind' => 'amical'])[0]], ['D1', 'L2', 'CDF', 'Amical']);

$sheet = fn (array $o) => $o + ['kind' => 'officiel', 'file' => 'FM_195253.docx', 'header' => 'Championnat de France, D1 - 20ème journée - 11/01/1953 à Sochaux, stade Auguste Bonal',
    'competition' => 'Championnat de France, D1', 'round' => '20ème journée', 'place' => 'Sochaux', 'stadium' => 'stade Auguste Bonal', 'home' => 'FC Sochaux', 'away' => 'Zzessai Bordeaux', 'sochaux_home' => true,
    'score_home' => 3, 'score_away' => 1, 'half_time' => '1-0', 'score_note' => '', 'spectators' => 8371, 'referee' => 'M. Harzig Edouard',
    'goals_text' => 'Buts : Salzborn(8e,71e),J.J. Marcel(13e) à Sochaux. De Harder(45e) à Bordeaux',
    'scorers' => [['name' => 'Salzborn', 'minutes' => ['8', '71'], 'note' => ''], ['name' => 'J.J. Marcel', 'minutes' => ['13'], 'note' => '']],
    'goals_by_team' => [['team' => 'Sochaux', 'text' => 'Salzborn(8e,71e),J.J. Marcel(13e)'], ['team' => 'Bordeaux', 'text' => 'De Harder(45e)']],
    'lineup' => array_map(fn ($n, $i) => ['name' => $n, 'position' => $i === 0 ? 'G' : ($i < 5 ? 'D' : ($i < 8 ? 'M' : 'A')), 'captain' => false, 'sub' => $i === 10 ? ['Pardo', '70'] : null],
        ['Lorius', 'S. Bravo', 'Barret', 'J. Telléchéa', 'Bruat', 'J.J. Marcel', 'Muro', 'Gardien', 'Salzborn', 'Reignier', 'Zzessai'], range(0, 10)),
    'coach' => 'Gabriel Dormois', 'date' => '1953-01-11'];

$ids = [];
$catsFile = APP_ROOT . '/data/categories.json';
$cats = file_get_contents($catsFile); // les saisons créées pour l'essai sont retirées à la fin
try {
    // Création d'une fiche complète.
    $id = F::create($sheet([]));
    $ids[] = $id;
    $d = Fiches::get($id);
    $m = $d['match'];
    $eq('fiche publiée, saison et titre au format du musée', [$d['status'], $m['season'], $d['title']], ['publie', '1952-1953', 'J20 – Sochaux / Zzessai Bordeaux – D1 – 11/01/1953 – 3-1']);
    $eq('score, affluence, arbitre, mi-temps', [$m['score_raw'], $m['result'], $m['spectators'], $m['referee'], in_array('Mi-temps : 1-0', $m['header_extra'], true)], ['3-1', 'V', 8371, 'M. Harzig Edouard', true]);
    $eq('buts par équipe', array_column($m['goals'], 'team'), ['Sochaux', 'Zzessai Bordeaux']);
    $rows = $m['lineup']['rows'];
    $byName = array_column($rows, null, 'name');
    $eq('composition : buts, remplaçant, entraîneur', [$byName['SALZBORN']['goals'] ?? null, $byName['PARDO']['sub_in'] ?? null, $byName['ZZESSAI']['sub_out'] ?? null, end($rows)['position'], end($rows)['name']], [['8', '71'], '70', '70', 'E', 'DORMOIS Gabriel']);
    $eq('source « feuille de match » sur la fiche', str_contains(json_encode($d['sections'], JSON_UNESCAPED_UNICODE), 'FM_195253.docx'), true);

    // Fiche existante : écarts proposés dans Trouvailles, fiche inchangée.
    $before = json_encode(Fiches::get($id));
    $n = F::compare($sheet(['score_home' => 4, 'date' => '1953-01-12', 'spectators' => 12000]), $id);
    $props = T::props($id)['items'];
    $eq('écarts proposés : score, date, affluence', [$n, array_column($props, 'field')], [3, ['score', 'date', 'affluence']]);
    $eq('origine « feuilles » et source sans lien', [$props[0]['origin'], $props[0]['sources'][0]['url'], str_starts_with($props[0]['sources'][0]['label'], 'Feuille de match 1952-1953')], ['feuilles', '', true]);
    $eq('la fiche n’est pas modifiée par la comparaison', json_encode(Fiches::get($id)) === $before, true);
    $eq('même feuille reproposée : aucun doublon', F::compare($sheet(['score_home' => 4, 'date' => '1953-01-12', 'spectators' => 12000]), $id), 0);
    $date = array_values(array_filter($props, fn ($p) => $p['field'] === 'date'))[0];
    T::accept($id, $date['id'], null, ['name' => 'Essai']);
    $d = Fiches::get($id);
    $eq('date validée : fiche, texte de date et titre mis à jour', [$d['match']['date'], str_contains($d['title'], '12/01/1953')], ['1953-01-12', true]);

    // Plan sur un petit jeu : la fiche existe → à comparer ; l'autre → à créer.
    $file = "$tmp/jeu.json.gz";
    @mkdir($tmp, 0775, true);
    file_put_contents($file, gzencode(json_encode([$sheet(['date' => '1953-01-12']), $sheet(['date' => '1953-02-01', 'away' => 'Zzessai Nancy', 'score_home' => 2, 'score_away' => 2])])));
    F::$data = $file;
    $st = F::plan();
    $eq('plan : 1 à comparer, 1 à créer', [F::summary($st)['a-comparer'], F::summary($st)['a-creer']], [1, 1]);
    $r = F::run(10);
    $collect = function () use (&$ids) {
        foreach (F::state()['items'] as $it) {
            if ($it['status'] === 'fait') {
                $ids[] = (int) $it['fiche'];
            }
        }
    };
    $collect();
    $eq('lot : 1 créée, 1 comparée, plus rien à faire', [$r['created'], $r['compared'], $r['left']], [1, 1, 0]);
    $again = F::run(10)['created'];
    $collect();
    $eq('relance sans doublon', [$again, F::summary(F::plan())['fait']], [0, 1]);
} finally {
    foreach (array_unique($ids) as $x) {
        if (Fiches::get($x)) {
            Fiches::destroy($x, ['name' => 'Essai']);
        }
        exec('rm -rf ' . escapeshellarg(STORAGE_PATH . "/versions/$x"));
    }
    exec('rm -rf ' . escapeshellarg($tmp));
    file_put_contents($catsFile, $cats);
    \App\Data\Categories::forget();
}
$left = array_filter(iterator_to_array(Index::all()), fn ($s) => str_contains((string) ($s['title'] ?? ''), 'Zzessai'));
$eq('fiches d’essai supprimées', count($left), 0);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
