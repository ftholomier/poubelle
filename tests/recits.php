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
    $eq('récits aux clés uniques, au moins 2 chapitres chacun, dans l’ordre des années', [count(array_unique(array_column($all, 'key'))), count(array_filter($all, fn ($r) => count($r['sections']) >= 2)), array_column($all, 'year') === array_values(array_map(fn ($x) => $x, (function ($y) { sort($y); return $y; })(array_column($all, 'year'))))], [count($all), count($all), true]);
    $eq('lien vers un match absent : texte seul', G::links('{{match:1899-01-01|la fiche}}'), 'la fiche');
    $n = G::create();
    $made = array_filter(array_column(G::status(), 'fiche'));
    $eq('rubrique « Grands récits » et récits créés', [(bool) Categories::get(G::ROOT), count($made)], [true, count($all)]);
    $relire = Fiches::get((int) G::existing('sauvetage-2023'));
    $eq('nouveau récit « à relire », sources de presse en lien', [$relire['status'], str_contains(json_encode($relire['sections']), 'fff.fr')], ['relire', true]);
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
