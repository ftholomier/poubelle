<?php
/**
 * Grands récits (App\Services\GrandsRecits) : textes livrés valides, rubrique créée, récits créés une
 * seule fois, mise en page « récit illustré », liens vers les matchs. Les fiches et la rubrique d'essai
 * sont retirées à la fin. Usage : php tests/recits.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Categories;
use App\Data\Fiches;
use App\Front\Recit;
use App\Services\GrandsRecits as G;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$catsFile = Categories::FILE;
$cats = file_get_contents($catsFile);
$already = array_filter(array_column(G::status(), 'fiche'));
$made = [];
try {
    $all = G::all();
    $eq('6 récits, clés uniques, chaque récit a au moins 3 chapitres', [count($all), count(array_unique(array_column($all, 'key'))), count(array_filter($all, fn ($r) => count($r['sections']) >= 3))], [6, 6, 6]);
    $eq('lien vers un match absent : texte seul', G::links('{{match:1899-01-01|la fiche}}'), 'la fiche');
    $n = G::create();
    $made = array_filter(array_column(G::status(), 'fiche'));
    $eq('rubrique « Grands récits » et récits créés', [(bool) Categories::get(G::ROOT), count($made)], [true, 6]);
    $eq('relancer ne recrée rien', G::create(), 0);
    $doc = Fiches::get((int) G::existing('epopee-uefa-1981'));
    $eq('publié dans la rubrique, mise en page récit', [$doc['status'], $doc['categories'], Recit::applies($doc)], ['publie', [G::ROOT], true]);
    $r = Recit::build($doc);
    $eq('chapitres numérotés, époque tirée du titre', [count(array_filter($r['chapters'], fn ($c) => $c['n'] > 0)) >= 5, $r['era']], [true, '1980 – 1981']);
} finally {
    if (!$already) {
        foreach ($made as $id) {
            Fiches::destroy((int) $id, ['name' => 'Essai']);
            exec('rm -rf ' . escapeshellarg(STORAGE_PATH . "/versions/$id"));
        }
        file_put_contents($catsFile, $cats);
        Categories::forget();
    }
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
