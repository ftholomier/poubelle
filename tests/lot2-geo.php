<?php
/**
 * Lot 2 — géolocalisation des stades (Geo::run) : les recherches se font hors verrou puis seules les
 * positions trouvées sont reportées, stade par stade, dans la liste relue (une correction faite à la
 * main pendant ce temps reste) ; un stade introuvable est retenu (geo_fail_at) et laissé de côté
 * pendant Geo::RETRY_DAYS jours ; un service muet ne marque rien. Nominatim est simulé.
 * data/collections/stades.json, geo.json et les versions sont remis en état à la fin.
 * Usage : php tests/lot2-geo.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Data\Collections;
use App\Services\Geo;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$files = [Collections::DIR . '/stades.json', Collections::DIR . '/geo.json'];
$vdir = STORAGE_PATH . '/versions/collections/stades';
$tmp = sys_get_temp_dir() . '/lot2-geo-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
$saved = [];
foreach ($files as $i => $f) {
    $saved[$i] = is_file($f) ? file_get_contents($f) : null;
}
if (is_dir($vdir)) {
    exec('cp -a ' . escapeshellarg($vdir) . ' ' . escapeshellarg("$tmp/versions"));
}
try {
    // Liste d'essai : A et B introuvables par le répertoire, C corrigé à la main pendant la tâche.
    $list = [
        ['id' => 'zz-a', 'name' => 'Stade Zz Alpha', 'aliases' => [], 'city' => '', 'department' => '', 'country' => '', 'lat' => null, 'lng' => null, 'auto' => true],
        ['id' => 'zz-b', 'name' => 'Stade Zz Beta', 'aliases' => [], 'city' => 'Ailleurs', 'department' => '', 'country' => '', 'lat' => null, 'lng' => null, 'auto' => true],
        ['id' => 'zz-c', 'name' => 'Stade Zz Gamma', 'aliases' => [], 'city' => '', 'department' => '', 'country' => '', 'lat' => null, 'lng' => null, 'auto' => true],
        ['id' => 'zz-d', 'name' => 'Stade Zz Delta', 'aliases' => [], 'city' => '', 'department' => '', 'country' => '', 'lat' => null, 'lng' => null, 'auto' => true],
    ];
    Collections::save('stades', $list, null, 'essai');
    $asked = [];
    Geo::$lookup = function (string $q, string $kind) use (&$asked) {
        if ($kind !== 'stadium') {
            return false; // lieux de naissance : service « muet », rien n'est mémorisé
        }
        $asked[] = $q;
        if (str_contains($q, 'Alpha')) {
            // Pendant la recherche, un historien corrige deux stades à la main.
            Collections::update('stades', function ($l) {
                foreach ($l as &$s) {
                    if ($s['id'] === 'zz-c') {
                        $s['lat'] = 45.0;
                        $s['lng'] = 5.0;
                        $s['geo_manual'] = true;
                        $s['auto'] = false;
                    }
                    if ($s['id'] === 'zz-b') {
                        $s['name'] = 'Stade Zz Beta (corrigé)';
                        $s['auto'] = false;
                    }
                }
                unset($s);
                return $l;
            }, ['name' => 'Historien'], 'correction');
            return ['lat' => 47.1, 'lng' => 6.1, 'cc' => 'FR', 'city' => 'Alphaville', 'source' => 'nominatim'];
        }
        if (str_contains($q, 'Gamma')) {
            return ['lat' => 1.0, 'lng' => 1.0, 'cc' => 'FR', 'city' => 'Faux', 'source' => 'nominatim'];
        }
        if (str_contains($q, 'Delta')) {
            return false; // service muet
        }
        return null; // Beta : introuvable
    };
    $r = Geo::run(10, true);
    JsonStore::forget();
    $by = array_column(Collections::get('stades', []), null, 'id');
    $eq('trouvé : position et ville', [$by['zz-a']['lat'], $by['zz-a']['lng'], $by['zz-a']['city']], [47.1, 6.1, 'Alphaville']);
    $eq('corrigé à la main pendant la tâche : position gardée', [(float) $by['zz-c']['lat'], (float) $by['zz-c']['lng'], $by['zz-c']['auto']], [45.0, 5.0, false]);
    $eq('autre correction pendant la tâche : gardée', [$by['zz-b']['name'], $by['zz-b']['auto']], ['Stade Zz Beta (corrigé)', false]);
    $eq('introuvable : échec retenu', !empty($by['zz-b']['geo_fail_at']), true);
    $eq('service muet : rien de retenu', isset($by['zz-d']['geo_fail_at']), false);
    $eq('compte rendu', [$r['stades'], $r['restants'] >= 2], [1, true]);
    $eq('aucun autre stade de la liste touché', count($by), 4);

    // Deuxième passage : B (échec récent) est laissé de côté, D est retenté.
    $asked = [];
    Geo::run(10, true);
    $eq('échec récent : pas retenté, les suivants passent', [in_array(true, array_map(fn ($q) => str_contains($q, 'Beta'), $asked), true), in_array(true, array_map(fn ($q) => str_contains($q, 'Delta'), $asked), true)], [false, true]);
    // Échec ancien : retenté.
    Collections::update('stades', function ($l) {
        foreach ($l as &$s) {
            if ($s['id'] === 'zz-b') {
                $s['geo_fail_at'] = date('c', time() - (Geo::RETRY_DAYS + 1) * 86400);
            }
        }
        unset($s);
        return $l;
    });
    $asked = [];
    Geo::run(10, true);
    $eq('échec ancien : retenté', in_array(true, array_map(fn ($q) => str_contains($q, 'Beta'), $asked), true), true);
} finally {
    Geo::$lookup = null;
    foreach ($files as $i => $f) {
        $saved[$i] === null ? @unlink($f) : file_put_contents($f, $saved[$i]);
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
