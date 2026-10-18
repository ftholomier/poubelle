<?php
/**
 * Lot 2 — écritures simultanées : boîte à idées des moments (une fiche rattachée à une idée pendant
 * que d'autres la modifient ou changent son état n'est pas effacée), Trouvailles (une proposition
 * validée deux fois en même temps n'est envoyée qu'une fois dans la fiche ; un envoi qui échoue la
 * remet en attente). Plusieurs processus PHP partent au même instant. Fichiers d'essai dans un
 * dossier temporaire ; la fiche d'essai est supprimée à la fin. Usage : php tests/lot2-concurrence.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Services\MomentIdeas as Ideas;
use App\Services\Trouvailles as T;

$wait = function (float $at) {
    while (microtime(true) < $at) {
        usleep(100);
    }
};
// Processus enfants.
if (($argv[1] ?? '') === '--idee') {
    Ideas::$file = $argv[2];
    $wait((float) $argv[5]);
    if ($argv[3] === 'lier') {
        Ideas::linkFiche($argv[4], (int) $argv[6], ['name' => 'Rédactrice']);
    } elseif ($argv[3] === 'etat') {
        try {
            Ideas::setState($argv[4], 'retenue', '', ['name' => 'Autre']);
        } catch (\InvalidArgumentException) {
        }
    } else {
        Ideas::edit($argv[4], ['why' => 'Pourquoi ' . $argv[3]], ['name' => 'Autre']);
    }
    exit(0);
}
if (($argv[1] ?? '') === '--trouvaille') {
    T::$dir = $argv[2];
    $wait((float) $argv[5]);
    try {
        T::accept((int) $argv[3], $argv[4], null, null);
        echo 'envoye';
    } catch (\RuntimeException $e) {
        echo $e->getMessage();
    }
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
$run = function (array $jobs): array {
    $procs = [];
    foreach ($jobs as $args) {
        $pre = (string) ini_get('auto_prepend_file');
        $procs[] = [proc_open(array_merge([PHP_BINARY], $pre !== '' ? ['-d', "auto_prepend_file=$pre"] : [], [__FILE__], $args), [1 => ['pipe', 'w']], $pipes), $pipes[1]];
    }
    $out = [];
    foreach ($procs as [$p, $o]) {
        $out[] = trim((string) stream_get_contents($o));
        fclose($o);
        proc_close($p);
    }
    return $out;
};
$tmp = sys_get_temp_dir() . '/lot2-concurrence-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
$id = 0;
try {
    $doc = Fiches::blank('article');
    $doc['title'] = 'Zzessai lot2 concurrence';
    $doc['sections'] = [['title' => 'Sources', 'html' => '<p>Essai.</p>']];
    $id = (int) Fiches::save($doc, null, 'essai')['id'];
    \App\Data\Index::put(Fiches::get($id));

    // Boîte à idées : 12 modifications et changements d'état pendant que l'idée reçoit sa fiche.
    Ideas::$file = "$tmp/idees.json";
    $idea = Ideas::add(['title' => 'Zz idée', 'year' => 1950, 'why' => 'Départ', 'theme' => 'autre'], ['name' => 'Essai']);
    $start = microtime(true) + 1.5;
    $jobs = [['--idee', Ideas::$file, 'lier', $idea['id'], (string) $start, (string) $id]];
    for ($i = 0; $i < 12; $i++) {
        $jobs[] = ['--idee', Ideas::$file, $i % 3 ? "e$i" : 'etat', $idea['id'], (string) $start, '0'];
    }
    $run($jobs);
    JsonStore::forget();
    $got = Ideas::get($idea['id']);
    $eq('idée : fiche rattachée gardée', [(int) ($got['fiche'] ?? 0), $got['state']], [$id, 'redigee']);
    $eq('idée : modifications appliquées', str_starts_with((string) $got['why'], 'Pourquoi '), true);
    // Dans le même processus : une idée rédigée ne repasse pas « retenue ».
    try {
        Ideas::setState($idea['id'], 'proposee', '', null);
        $eq('idée rédigée : changement d’état refusé', false, true);
    } catch (\InvalidArgumentException) {
        $eq('idée rédigée : changement d’état refusé', true, true);
    }
    try {
        Ideas::edit('inconnue', ['why' => 'x'], null);
        $eq('idée inconnue : refus', false, true);
    } catch (\InvalidArgumentException $e) {
        $eq('idée inconnue : refus', $e->getMessage(), 'Idée introuvable.');
    }

    // Trouvailles : la même proposition validée par 6 clics simultanés.
    T::$dir = "$tmp/trouvailles";
    $props = T::$dir . "/props/$id.json";
    $item = fn (string $iid, string $value) => ['id' => $iid, 'field' => 'info', 'value' => $value, 'sources' => [['label' => 'Essai', 'url' => '', 'kind' => 'web']], 'origin' => 'web', 'status' => 'attente', 'at' => date('c')];
    JsonStore::write($props, ['id' => $id, 'at' => date('c'), 'items' => [$item('it1', 'Zzinfo unique'), $item('it2', 'Zzinfo deux')]]);
    $start = microtime(true) + 1.5;
    $out = $run(array_fill(0, 6, ['--trouvaille', T::$dir, (string) $id, 'it1', (string) $start]));
    JsonStore::forget();
    $html = implode('', array_column(Fiches::fresh($id)['sections'], 'html'));
    $eq('une seule application dans la fiche', substr_count($html, 'Zzinfo unique'), 1);
    $eq('les autres clics : « déjà traitée »', [count(array_keys($out, 'envoye', true)), count(array_keys($out, 'Proposition déjà traitée.', true))], [1, 5]);
    $eq('proposition envoyée', array_column(T::props($id)['items'], 'status', 'id')['it1'], 'envoye');
    // Échec de l'envoi (fiche disparue) : la proposition retourne en attente.
    $copy = Fiches::fresh($id);
    JsonStore::delete(Fiches::path($id));
    try {
        T::accept($id, 'it2', null, null);
        $eq('fiche introuvable : erreur', false, true);
    } catch (\RuntimeException $e) {
        $eq('fiche introuvable : erreur', $e->getMessage(), 'Fiche introuvable.');
    }
    JsonStore::forget();
    $eq('échec : remise en attente', array_column(T::props($id)['items'], 'status', 'id')['it2'], 'attente');
    JsonStore::write(Fiches::path($id), $copy);
    // Réservation oubliée (processus interrompu) : reprise après le délai.
    JsonStore::update($props, function ($p) {
        $p['items'][1]['status'] = 'en-cours';
        $p['items'][1]['busy_at'] = date('c', time() - 3600);
        return $p;
    });
    T::accept($id, 'it2', null, null);
    JsonStore::forget();
    $eq('réservation ancienne : reprise', array_column(T::props($id)['items'], 'status', 'id')['it2'], 'envoye');
} finally {
    if ($id) {
        Fiches::destroy($id, null);
        exec('rm -rf ' . escapeshellarg(Fiches::VERSIONS . "/$id"));
    }
    exec('rm -rf ' . escapeshellarg($tmp));
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
