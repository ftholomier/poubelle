<?php
/** Contrôle qualité remis à zéro sur une fiche : alertes mises de côté, même raison écartée, autre raison signalée, réaffichage. */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Quality;
use App\Data\Derived;
use App\Data\Fiches;
use App\Services\QualityAck as A;

$fail = 0;
$eq = function (string $what, $got, $want) use (&$fail) {
    $ok = $got === $want;
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'OK   ' : 'ÉCHEC ') . $what . ($ok ? '' : ' : ' . var_export($got, true) . ' au lieu de ' . var_export($want, true)) . "\n";
};
$keep = is_file(A::FILE) ? file_get_contents(A::FILE) : null;
try {
    $id = null;
    foreach (Derived::part('quality') as $a) {
        if (!empty($a['id']) && Fiches::get((int) $a['id'])) {
            $id = (int) $a['id'];
            $code = (string) $a['code'];
            $msg = (string) $a['msg'];
            break;
        }
    }
    if (!$id) {
        echo "Aucune alerte de fiche à tester.\n";
        exit(0);
    }
    $doc = Fiches::get($id);
    $before = array_filter(Quality::forDoc($doc), fn ($c) => $c[0] !== 'ok');
    $eq('la fiche a des alertes', count($before) > 0, true);
    $alerts = array_map(fn ($c) => [(string) ($c[2] ?? ''), (string) $c[1]], $before);
    A::acknowledge($id, $alerts, 'Test');
    $after = array_filter(Quality::forDoc($doc), fn ($c) => $c[0] !== 'ok');
    $eq('plus aucune alerte sur la fiche', count($after), 0);
    $inAll = false;
    foreach (Quality::all() as $items) {
        foreach ($items as $i) {
            $inAll = $inAll || ((int) ($i['id'] ?? 0) === $id && $i['code'] === $code);
        }
    }
    $eq('écran Qualité : alerte écartée', $inAll, false);
    $eq('même raison, autre nombre : toujours écartée', A::acked($id, $code, preg_replace('/\d+/', '99', $msg) ?? $msg), true);
    $eq('autre raison : signalée', A::acked($id, $code, 'Une raison toute nouvelle'), false);
    $eq('autre fiche : non concernée', A::acked($id + 999999, $code, $msg), false);
    A::reopen($id);
    $eq('réaffichage : les alertes reviennent', count(array_filter(Quality::forDoc($doc), fn ($c) => $c[0] !== 'ok')), count($before));
} finally {
    $keep === null ? @unlink(A::FILE) : file_put_contents(A::FILE, $keep);
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
