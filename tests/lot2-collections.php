<?php
/**
 * Lot 2 — collections : Collections::update (lecture-écriture sous verrou, version seulement si le
 * contenu change), mots ajoutés au dictionnaire en même temps par plusieurs processus (aucun perdu),
 * écran Dictionnaire qui garde un mot ajouté depuis le correcteur pendant qu'il était ouvert.
 * Le dictionnaire et ses versions sont remis dans leur état d'origine à la fin.
 * Usage : php tests/lot2-collections.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Core\Request;
use App\Data\Collections;
use App\Services\Proofreader;

if (($argv[1] ?? '') === '--ajoute') {
    // Processus enfant : attend le top départ puis ajoute son mot.
    while (microtime(true) < (float) $argv[3]) {
        usleep(200);
    }
    Proofreader::addWord($argv[2], null);
    exit(0);
}

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$file = Collections::DIR . '/dictionnaire.json';
$vdir = STORAGE_PATH . '/versions/collections/dictionnaire';
$tmp = sys_get_temp_dir() . '/lot2-collections-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
$had = is_file($file);
if ($had) {
    copy($file, "$tmp/dico.json");
}
if (is_dir($vdir)) {
    exec('cp -a ' . escapeshellarg($vdir) . ' ' . escapeshellarg("$tmp/versions"));
}
$mots = fn () => array_map(fn ($it) => $it['mot'] ?? $it, (array) (JsonStore::read($file, []) ?? []));
try {
    JsonStore::forget($file);
    $versions = fn () => count(JsonStore::read("$vdir/index.json", []) ?: []);
    $v0 = $versions();

    // Collections::update : version créée seulement si le contenu change.
    Collections::update('dictionnaire', fn ($l) => array_merge($l, [['mot' => 'Zzlot2a', 'note' => '']]), null, 'essai');
    JsonStore::forget();
    $eq('update : mot ajouté', in_array('Zzlot2a', $mots(), true), true);
    $eq('update : une version de plus', $versions(), $v0 + 1);
    Collections::update('dictionnaire', fn ($l) => $l, null, 'essai');
    JsonStore::forget();
    $eq('update sans changement : pas de version', $versions(), $v0 + 1);

    // Dix mots ajoutés en même temps depuis le correcteur, par dix processus : aucun perdu.
    $start = microtime(true) + 1.5;
    $procs = [];
    for ($i = 0; $i < 10; $i++) {
        $procs[] = proc_open([PHP_BINARY, __FILE__, '--ajoute', "Zzconc$i", (string) $start], [], $pipes);
    }
    foreach ($procs as $p) {
        proc_close($p);
    }
    JsonStore::forget();
    $have = array_values(array_filter($mots(), fn ($m) => str_starts_with((string) $m, 'Zzconc')));
    sort($have);
    $eq('ajouts simultanés : les dix mots sont là', $have, array_map(fn ($i) => "Zzconc$i", range(0, 9)));
    Proofreader::addWord('zzconc3', null);
    JsonStore::forget();
    $eq('mot déjà connu (casse) : pas de doublon', count(array_filter($mots(), fn ($m) => mb_strtolower((string) $m) === 'zzconc3')), 1);

    // Écran Dictionnaire : ouvert, un mot est ajouté depuis le correcteur, puis l'écran enregistre
    // sa liste (sans ce mot) où il a supprimé « Zzlot2a » : le mot ajouté reste, la suppression vaut.
    $_SESSION = [];
    $_COOKIE['sr_session'] = 'lot2'; // session simulée (pas d'en-têtes en ligne de commande)
    $req = fn (string $m, array $json = []) => new Request($m, '/admin/collection/dictionnaire', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1', 'CONTENT_TYPE' => 'application/json'], json_encode($json));
    @\App\Admin\Collections::edit($req('GET'), 'dictionnaire');
    $seenList = (array) (JsonStore::read($file, []) ?? []);
    Proofreader::addWord('Zzpendant', null);
    $sent = array_values(array_filter($seenList, fn ($it) => ($it['mot'] ?? '') !== 'Zzlot2a'));
    $sent[] = ['mot' => 'Zzecran', 'note' => ''];
    $r = @\App\Admin\Collections::save($req('POST', ['items' => $sent]), 'dictionnaire');
    JsonStore::forget();
    $now = $mots();
    $eq('enregistrement de l’écran accepté', json_decode($r->body, true)['ok'] ?? false, true);
    $eq('mot ajouté pendant que l’écran était ouvert : gardé', in_array('Zzpendant', $now, true), true);
    $eq('mot ajouté à l’écran : enregistré', in_array('Zzecran', $now, true), true);
    $eq('mot supprimé à l’écran : supprimé', in_array('Zzlot2a', $now, true), false);
    // Deuxième enregistrement depuis la même page (liste rafraîchie) : un mot supprimé exprès disparaît.
    $sent2 = array_values(array_filter((array) JsonStore::read($file, []), fn ($it) => ($it['mot'] ?? '') !== 'Zzpendant'));
    @\App\Admin\Collections::save($req('POST', ['items' => $sent2]), 'dictionnaire');
    JsonStore::forget();
    $eq('mot vu puis supprimé à l’écran : supprimé', in_array('Zzpendant', $mots(), true), false);
} finally {
    if ($had) {
        copy("$tmp/dico.json", $file);
    } else {
        @unlink($file);
    }
    exec('rm -rf ' . escapeshellarg($vdir));
    if (is_dir("$tmp/versions")) {
        exec('cp -a ' . escapeshellarg("$tmp/versions") . ' ' . escapeshellarg($vdir));
    }
    exec('rm -rf ' . escapeshellarg($tmp));
    JsonStore::forget();
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
