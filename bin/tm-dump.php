<?php
/**
 * Relevé hors ligne des compositions Transfermarkt (le serveur en ligne est bloqué par le site) :
 * php bin/tm-dump.php 1970 2025 → app/Resources/compos/tm-AAAA.json (une saison par fichier).
 */
require __DIR__ . '/../app/bootstrap.php';
use App\Services\Compos;

[$from, $to] = [(int) ($argv[1] ?? 1928), (int) ($argv[2] ?? (int) date('Y') - 1)];
$rs = new ReflectionMethod(Compos::class, 'tmSeason');
$rp = new ReflectionMethod(Compos::class, 'page');
foreach ([$rs, $rp] as $r) { $r->setAccessible(true); }
for ($sid = $from; $sid <= $to; $sid++) {
    $out = APP_ROOT . '/app/Resources/compos/tm-' . $sid . '.json';
    $data = is_file($out) ? (array) json_decode((string) file_get_contents($out), true) : [];
    try {
        $games = $rs->invoke(null, $sid);
    } catch (Throwable $e) {
        fwrite(STDERR, "$sid : $e\n");
        continue;
    }
    foreach ($games as $g) {
        if (isset($data[$g['date'] . '#' . $g['id']])) {
            continue;
        }
        try {
            $html = $rp->invoke(null, 'https://www.transfermarkt.fr/spielbericht/index/spielbericht/' . $g['id'], 'tm-m-' . $g['id'], 86400 * 365);
            $p = Compos::tmParse($html);
            $data[$g['date'] . '#' . $g['id']] = ['id' => $g['id'], 'date' => $g['date'], 'coach' => $p['coach'] ?? '', 'players' => array_map(fn ($x) => array_intersect_key($x, array_flip(['name', 'pos', 'in', 'out', 'goals', 'yellow', 'red', 'num'])), $p['players'] ?? [])];
        } catch (Throwable $e) {
            fwrite(STDERR, "$sid {$g['id']} : " . $e->getMessage() . "\n");
            continue;
        }
        ksort($data);
        file_put_contents($out, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    echo "$sid : " . count($games) . " matchs, " . count($data) . " relevés\n";
}
