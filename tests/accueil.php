<?php
/**
 * Grand slider de l'accueil (App\Front\Pages::slides) : le tirage au hasard ne prend que les fiches
 * « À la une » dont la photo est assez grande pour le plein écran ; s'il n'y en a aucune, toutes
 * celles qui ont une vraie photo. Signalement des photos trop petites dans la fiche (onglet
 * « Classement & SEO »). Usage : php tests/accueil.php (code de sortie 1 en cas d'échec).
 * N'écrit que des fichiers de cache.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Memo;
use App\Core\View;
use App\Data\Collections;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Front\Pages;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$size = function (?string $img): array {
    $m = Media::get($img);
    return [(int) ($m['width'] ?? 0), (int) ($m['height'] ?? 0)];
};

// Fiches « À la une » publiées avec une vraie photo, dont trois à la photo trop petite.
$une = array_values(array_filter(Index::published(), fn ($s) => $s['a_la_une'] && $s['image'] && !Index::isPlaceholderImage($s['image'])));
$small = [9410 => 'Louis Kaufmann (40 × 60)', 10715 => 'Yvon Roy (105 × 150)', 10378 => 'Ivan Perisic (502 × 566)'];
$eq('seuil : 1 200 × 600 pixels', Pages::$slideMin, [1200, 600]);
$eq('photo de 40 × 60 : trop petite', [$size(Index::get(9410)['image'] ?? null), Pages::slideReady(Index::get(9410)['image'] ?? null)], [[40, 60], false]);
$big = null;
foreach ($une as $s) {
    if ($size($s['image']) === [1600, 1067]) {
        $big = $s;
        break;
    }
}
$eq('photo de 1 600 × 1 067 : assez grande', $big ? Pages::slideReady($big['image']) : 'aucune photo de cette taille', true);
$eq('sans image ou image inconnue : non', [Pages::slideReady(null), Pages::slideReady(''), Pages::slideReady('2099/01/inconnue.jpg')], [false, false, false]);

// Fiches que le tirage peut montrer.
$pool = Pages::slidePool();
$ok = array_filter($pool, function ($id) {
    $s = Index::get($id);
    return $s && Index::visible($s) && $s['a_la_une'] && !Index::isPlaceholderImage($s['image']) && Pages::slideReady($s['image']);
});
$eq('tirage : des centaines de fiches, toutes « À la une », publiées, à la photo assez grande', [count($pool) > 300, count($ok) === count($pool), count(array_unique($pool)) === count($pool)], [true, true, true]);
$eq('photos trop petites écartées : ' . implode(', ', $small), array_values(array_intersect(array_keys($small), $pool)), []);
$eq('et pourtant bien « À la une » avec une photo', count(array_filter(array_keys($small), fn ($id) => in_array($id, array_column($une, 'id'), true))), 3);
$eq('chaque fiche « À la une » à la photo assez grande est dans le tirage', count(array_filter($une, fn ($s) => Pages::slideReady($s['image']))), count($pool));
Memo::forget();
$eq('liste reprise du cache', Pages::slidePool(), $pool);

// Tirage au hasard (réglage par défaut) : cinq slides, toutes à la photo assez grande.
if ((Collections::get('slider', ['mode' => 'random'])['mode'] ?? 'random') === 'random') {
    $seen = [];
    $bad = 0;
    for ($i = 0; $i < 30; $i++) {
        $slides = Pages::slides(5);
        $bad += count(array_filter($slides, fn ($x) => !Pages::slideReady($x['image'])));
        $seen[] = count($slides) === 5 && count(array_unique(array_column($slides, 'href'))) === 5;
        foreach ($slides as $x) {
            $seen['img'][$x['image']] = true;
        }
    }
    $eq('30 tirages de 5 slides : aucune photo trop petite, jamais deux fois la même fiche', [$bad, count(array_filter(array_slice($seen, 0, 30))) === 30], [0, true]);
    $eq('le tirage varie (plus de 40 photos différentes en 30 tirages)', count($seen['img']) > 40, true);

    // Seuil inatteignable : aucune photo assez grande, le tirage reprend toutes les vraies photos.
    Pages::$slideMin = [100000, 100000];
    Memo::forget();
    $fallback = Pages::slides(3);
    $eq('aucune photo assez grande : liste vide', Pages::slidePool(), []);
    $eq('… et le slider garde 3 slides à vraie photo', [count($fallback), count(array_filter($fallback, fn ($x) => $x['image'] && !Index::isPlaceholderImage($x['image'])))], [3, 3]);
    Pages::$slideMin = [1200, 600];
    Memo::forget();
    $eq('seuil rétabli : même liste qu\'avant', Pages::slidePool(), $pool);
} else {
    echo "(slider en sélection manuelle : tirages au hasard non testés)\n";
}

// Dans la fiche (Classement & SEO) : la case « À la une » signale une image trop petite ou absente.
$seo = fn (array $doc) => View::partial('admin/fiches/_seo', ['doc' => $doc]);
$k = $seo(Fiches::get(9410));
$eq('fiche à la photo de 40 × 60 : signalée, avec sa taille et le minimum', [str_contains($k, 'trop petite pour le grand slider (40 × 60 pixels'), str_contains($k, 'au moins 1 200 × 600')], [true, true]);
$eq('fiche à la photo assez grande : rien à signaler', $big ? str_contains($seo(Fiches::get((int) $big['id'])), 'pas tirée au hasard') : 'aucune', false);
$blank = Fiches::blank('match');
$eq('fiche sans image : « pas tirée au hasard »', str_contains($seo($blank), 'Sans image à la une, la fiche n’est pas tirée au hasard'), true);
$silhouette = null;
foreach (Index::published() as $s) {
    if (Index::isPlaceholderImage($s['image'])) {
        $silhouette = $s['image'];
        break;
    }
}
$eq('silhouette « ? » : choisir une vraie photo (pas « un plus grand scan »)', $silhouette ? [str_contains($h = $seo(['featured_image' => $silhouette] + $blank), 'générique (silhouette « ? »)'), str_contains($h, 'plus grand scan')] : 'aucune silhouette', [true, false]);
$eq('image absente de la médiathèque : taille inconnue', str_contains($seo(['featured_image' => '2099/01/inconnue.jpg'] + $blank), 'absente de la médiathèque (taille inconnue)'), true);

echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
