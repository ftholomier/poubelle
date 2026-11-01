<?php
/**
 * Légendes et crédits des photos, médiathèque ↔ galeries des fiches (App\Services\CaptionSync) :
 * découpage légende + crédit de la médiathèque repris dans une fiche qui avait encore l'ancienne
 * légende (ticket : ASSE-FCSM-2010-2011-6-ER.jpg), légende et crédit saisis dans une fiche repris
 * dans la médiathèque (FCSM-SR-1970-1971), texte propre à une fiche jamais écrasé, deux fiches en
 * désaccord laissées telles quelles, rattrapage. data/media.json et l'index d'utilisation des
 * médias sont remis dans leur état d'origine, les fiches d'essai supprimées.
 * Usage : php tests/legendes-photos.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Medias as Admin;
use App\Core\JsonStore;
use App\Core\Request;
use App\Data\Fiches;
use App\Data\Media;
use App\Services\CaptionSync as CS;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Règles.
$eq('ancienne légende = légende + « Photo » + crédit', CS::isSplitOf('Maïga, Monsoreau, Janot Photo L’Est républicain', 'Maïga, Monsoreau, Janot', "L'Est Républicain"), true);
$eq('avec retour à la ligne', CS::isSplitOf("Maïga, Monsoreau, Janot\nPhoto L’Est républicain", 'Maïga, Monsoreau, Janot', 'L’Est républicain'), true);
$eq('crédit seul', CS::isSplitOf('Photo L’Est républicain', '', 'L’Est républicain'), true);
$eq('crédit en tête', CS::isSplitOf('©PHOTOPQR/L’ALSACE ; Joie des Sochaliens', 'Joie des Sochaliens', 'PHOTOPQR/L’ALSACE'), true);
$eq('autre texte : pas un découpage', CS::isSplitOf('Maïga et Perquis Photo L’Est républicain', 'Maïga, Monsoreau, Janot', 'L’Est républicain'), false);

$user = ['id' => 'legendes-essai', 'name' => 'Essai légendes', 'email' => ''];
$file = Media::FILE;
$backup = file_get_contents($file);
$usageBackup = is_file(Media::USAGE) ? file_get_contents(Media::USAGE) : null;
$req = fn (array $json) => new Request('POST', '/admin/medias', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1', 'CONTENT_TYPE' => 'application/json'], json_encode($json));
$ids = [];
$gal = fn (int $id, string $rel) => array_values(array_filter(Fiches::fresh($id)['gallery'] ?? [], fn ($g) => $g['image'] === $rel))[0] ?? null;
$pair = fn (?array $g) => [$g['caption'] ?? null, $g['credit'] ?? null];
try {
    $r = [];
    foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'] as $k) {
        $r[$k] = "zz-legendes/$k.jpg";
    }
    $old = 'Maïga, Monsoreau, Janot Photo L’Est républicain';
    Media::put($r['a'], ['file' => $r['a'], 'caption' => $old, 'credit' => '']);                 // découpée ensuite dans la médiathèque
    Media::put($r['b'], ['file' => $r['b'], 'caption' => 'Perquis', 'credit' => 'L’Est républicain']); // déjà découpée (rattrapage)
    Media::put($r['c'], ['file' => $r['c'], 'caption' => '', 'credit' => '']);                    // vide : la fiche la remplit
    Media::put($r['d'], ['file' => $r['d'], 'caption' => '', 'credit' => '']);                    // vide : remplie à l'enregistrement de la fiche
    Media::put($r['h'], ['file' => $r['h'], 'caption' => '', 'credit' => '']);                    // vide (rattrapage)
    Media::put($r['i'], ['file' => $r['i'], 'caption' => 'Archive de Christian Pacek', 'credit' => '']); // ancien texte de la fiche
    Media::put($r['e'], ['file' => $r['e'], 'caption' => '', 'credit' => '']);                    // deux fiches en désaccord
    Media::put($r['f'], ['file' => $r['f'], 'caption' => 'Le stade', 'credit' => 'X']);           // légende propre à une fiche
    Media::put($r['g'], ['file' => $r['g'], 'caption' => 'Ancienne', 'credit' => '']);           // corrigée dans une fiche

    $mk = function (string $title, array $gallery) use (&$ids) {
        $d = Fiches::blank('article');
        $d['title'] = $title;
        $d['status'] = 'relire';
        $d['gallery'] = $gallery;
        return $ids[] = (int) Fiches::save($d, null, 'essai')['id'];
    };
    $f1 = $mk('Zzessai légendes 1', [
        ['image' => $r['a'], 'caption' => $old, 'credit' => '', 'caption_raw' => $old],
        ['image' => $r['b'], 'caption' => 'Perquis Photo L’Est républicain', 'credit' => '', 'caption_raw' => 'Perquis Photo L’Est républicain'],
        ['image' => $r['e'], 'caption' => 'Version 1', 'credit' => '', 'caption_raw' => ''],
        ['image' => $r['f'], 'caption' => 'Propre à la fiche', 'credit' => 'X', 'caption_raw' => ''],
        ['image' => $r['g'], 'caption' => 'Ancienne', 'credit' => '', 'caption_raw' => ''],
    ]);
    $f2 = $mk('Zzessai légendes 2', [
        ['image' => $r['c'], 'caption' => '', 'credit' => '', 'caption_raw' => ''],
        ['image' => $r['d'], 'caption' => 'Christian Pacek', 'credit' => 'Archives FCSM', 'caption_raw' => ''],
        ['image' => $r['g'], 'caption' => 'Ancienne', 'credit' => '', 'caption_raw' => ''],
    ]);
    $f3 = $mk('Zzessai légendes 3', [
        ['image' => $r['e'], 'caption' => 'Version 2', 'credit' => '', 'caption_raw' => ''],
        ['image' => $r['h'], 'caption' => 'Bernard Genghini', 'credit' => 'Collection privée', 'caption_raw' => ''],
        ['image' => $r['i'], 'caption' => 'Archive de Christian Pacek', 'credit' => '', 'caption_raw' => ''],
    ]);
    // Fiche corrigée avant ce report (ticket, fiche 23088) : la médiathèque a gardé l'ancien texte.
    $d3 = Fiches::fresh($f3);
    $d3['gallery'][2]['caption'] = 'Christian Pacek à l’entraînement';
    $d3['gallery'][2]['credit'] = 'Collection Christian Pacek';
    Fiches::save($d3, $user, 'essai');
    $usage = (array) (JsonStore::read(Media::USAGE, []) ?: []);
    foreach ([$r['a'] => [$f1], $r['b'] => [$f1], $r['c'] => [$f2], $r['d'] => [$f2], $r['e'] => [$f1, $f3], $r['f'] => [$f1], $r['g'] => [$f1, $f2], $r['h'] => [$f3], $r['i'] => [$f3]] as $rel => $list) {
        $usage[$rel] = $list;
    }
    Media::saveUsage($usage);

    // 1. Médiathèque → fiches : le crédit séparé de la légende dans la médiathèque (ticket, fiche 3503).
    $_SESSION = [];
    $res = json_decode((string) @Admin::save($req(['file' => $r['a'], 'caption' => 'Maïga, Monsoreau, Janot', 'credit' => 'L’Est républicain']))->body, true);
    $eq('médiathèque → fiche : légende et crédit séparés repris', $pair($gal($f1, $r['a'])), ['Maïga, Monsoreau, Janot', 'L’Est républicain']);
    $eq('médiathèque → fiche : signalé à l’historien', str_contains((string) ($res['message'] ?? ''), 'repris dans 1 fiche'), true);
    // Légende propre à une fiche : gardée ; seul le crédit, identique, suit.
    @Admin::save($req(['file' => $r['f'], 'caption' => 'Le stade Bonal', 'credit' => 'Y']));
    $eq('légende propre à la fiche gardée, crédit identique suivi', $pair($gal($f1, $r['f'])), ['Propre à la fiche', 'Y']);

    // 2. Fiche → médiathèque : photo ajoutée avec légende et crédit (ticket, fiche 23088).
    $before = Fiches::fresh($f2);
    $after = $before;
    $after['gallery'][0]['caption'] = 'Christian Pacek à la lutte';
    $after['gallery'][0]['credit'] = 'L’Est républicain';
    $saved = Fiches::save($after, $user, 'essai');
    $done = CS::fromFiche($before, $saved, $user);
    Media::forget();
    $eq('fiche → médiathèque : légende et crédit repris', [$done, $pair(Media::get($r['c']))], [[$r['c'], $r['d']], ['Christian Pacek à la lutte', 'L’Est républicain']]);
    $eq('fiche → médiathèque : média vide d’une autre photo de la fiche complété', $pair(Media::get($r['d'])), ['Christian Pacek', 'Archives FCSM']);
    // Légende corrigée dans une fiche : la médiathèque (même texte) suit, puis l'autre fiche (même texte).
    $before = Fiches::fresh($f2);
    $after = $before;
    $after['gallery'][array_search($r['g'], array_column($after['gallery'], 'image'), true)]['caption'] = 'Corrigée';
    $saved = Fiches::save($after, $user, 'essai');
    CS::fromFiche($before, $saved, $user);
    Media::forget();
    $eq('légende corrigée : médiathèque mise à jour', Media::get($r['g'])['caption'], 'Corrigée');
    $eq('légende corrigée : l’autre fiche (même texte) aussi', $gal($f1, $r['g'])['caption'] ?? null, 'Corrigée');
    // Un champ vidé dans la fiche n'efface rien dans la médiathèque.
    $before = Fiches::fresh($f2);
    $after = $before;
    $after['gallery'][0]['credit'] = '';
    $saved = Fiches::save($after, $user, 'essai');
    CS::fromFiche($before, $saved, $user);
    Media::forget();
    $eq('crédit vidé dans la fiche : médiathèque gardée', Media::get($r['c'])['credit'], 'L’Est républicain');

    // 3. Rattrapage (production : médiathèque déjà découpée, fiches pas encore à jour).
    $res = CS::catchUp([$f1, $f2, $f3]);
    Media::forget();
    $eq('rattrapage : ancienne légende de la fiche découpée', $pair($gal($f1, $r['b'])), ['Perquis', 'L’Est républicain']);
    $eq('rattrapage : médiathèque vide remplie d’après la fiche', $pair(Media::get($r['h'])), ['Bernard Genghini', 'Collection privée']);
    $eq('rattrapage : ancien texte de la fiche resté dans la médiathèque → texte corrigé de la fiche', $pair(Media::get($r['i'])), ['Christian Pacek à l’entraînement', 'Collection Christian Pacek']);
    $eq('rattrapage : deux fiches en désaccord, médiathèque laissée vide', $pair(Media::get($r['e'])), ['', '']);
    $eq('rattrapage : légende propre à la fiche jamais écrasée', $gal($f1, $r['f'])['caption'] ?? null, 'Propre à la fiche');
    $eq('rattrapage : rejoué, plus rien à faire', [CS::catchUp([$f1, $f2, $f3])['media'], CS::catchUp([$f1, $f2, $f3])['fiches']], [0, []]);
} finally {
    foreach ($ids as $i) {
        Fiches::destroy($i, $user);
        exec('rm -rf ' . escapeshellarg(Fiches::VERSIONS . "/$i"));
    }
    file_put_contents($file, $backup);
    JsonStore::forget($file);
    if ($usageBackup !== null) {
        file_put_contents(Media::USAGE, $usageBackup);
        JsonStore::forget(Media::USAGE);
    }
    Media::forget();
    Media::all();
}
$eq('médiathèque remise en état', file_get_contents($file) === $backup, true);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
