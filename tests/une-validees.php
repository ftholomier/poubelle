<?php
/**
 * La Une (grand slider de l'accueil) ne montre que des fiches validées par un historien (ticket) :
 * tirage au hasard et sélection manuelle. Validations et réglage du slider remis en état à la fin.
 * Usage : php tests/une-validees.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Memo;
use App\Data\Index;
use App\Front\Pages;
use App\Services\Validation;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$fresh = function () {
    clearstatcache();
    Memo::forget();
    (new ReflectionProperty(Validation::class, 'memo'))->setValue(null, null);
};

$sliderFile = DATA_PATH . '/collections/slider.json';
$saved = [Validation::FILE => is_file(Validation::FILE) ? (string) file_get_contents(Validation::FILE) : null, $sliderFile => is_file($sliderFile) ? (string) file_get_contents($sliderFile) : null];
$user = ['id' => 'essai-une', 'name' => 'Essai Une', 'email' => ''];
try {
    // Fiches publiées dont la photo est assez grande pour la Une : trois pour l'essai.
    $big = [];
    foreach (Index::published() as $s) {
        if ($s['image'] && !Index::isPlaceholderImage($s['image']) && Pages::slideReady($s['image'], Pages::slideYear($s))) {
            $big[] = (int) $s['id'];
        }
        if (count($big) === 3) {
            break;
        }
    }
    $eq('trois fiches à grande photo pour l’essai', count($big), 3);
    [$a, $b, $c] = $big;
    @unlink($sliderFile);
    \App\Core\JsonStore::write(Validation::FILE, []);
    $fresh();

    // 1. Aucune fiche validée : Une vide (le grand slider n'apparaît pas).
    $eq('aucune fiche validée : tirage vide', Pages::slidePool(), []);
    $eq('aucune fiche validée : pas de diapositive', Pages::slides(5), []);

    // 2. Deux fiches validées : seules elles sont tirées.
    Validation::set($a, $user);
    Validation::set($b, $user);
    $fresh();
    $ids = array_column(Pages::slidePool(), 'id');
    sort($ids);
    $expect = [$a, $b];
    sort($expect);
    $eq('tirage : les deux fiches validées seulement', $ids, $expect);
    $shown = array_column(Pages::slides(5), 'id');
    sort($shown);
    $eq('Une : deux diapositives, toutes validées (pas la fiche non validée)', $shown, $expect);

    // 3. Sélection manuelle : la fiche non validée est écartée.
    \App\Core\JsonStore::write($sliderFile, ['mode' => 'manual', 'ids' => [$c, $a]]);
    \App\Core\JsonStore::forget();
    $fresh();
    $eq('sélection manuelle : la fiche non validée n’est pas montrée', array_column(Pages::slides(5), 'id'), [$a]);
    Validation::set($c, $user);
    $fresh();
    $eq('validée ensuite : elle apparaît, dans l’ordre choisi', array_column(Pages::slides(5), 'id'), [$c, $a]);
} finally {
    foreach ($saved as $f => $content) {
        $content === null ? @unlink($f) : file_put_contents($f, $content);
    }
    \App\Core\JsonStore::forget();
    $fresh();
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
