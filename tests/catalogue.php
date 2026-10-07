<?php
/**
 * Catalogue des archives (App\Services\Catalogue) : catalogue lu, image reconnue par son contenu
 * (quel que soit son nom), rangée une seule fois dans la médiathèque avec légende, crédit, droits,
 * date et rotation, image hors catalogue refusée, rangement dans une galerie, écarter / rétablir.
 * L'état, le média et la galerie d'essai sont retirés à la fin. Usage : php tests/catalogue.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Fiches;
use App\Data\Media;
use App\Services\Catalogue as C;

$tmp = sys_get_temp_dir() . '/catalogue-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
C::$dir = "$tmp/state";
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Catalogue d'essai : une image fabriquée, décrite sous son empreinte.
$img = imagecreatetruecolor(40, 30);
imagefill($img, 0, 0, imagecolorallocate($img, 250, 200, 0));
imagejpeg($img, "$tmp/a.jpg");
$md5 = md5_file("$tmp/a.jpg");
file_put_contents("$tmp/cat.json.gz", gzencode(json_encode(['items' => [[
    'md5' => $md5, 'type' => 'photo', 'rotate' => 90, 'title' => 'Zzessai supporters 1937', 'caption' => 'Supporters sochaliens à la gare.', 'date' => '1937-05-09',
    'rights' => 'club', 'credit' => '', 'quality' => 'bonne', 'persons' => [], 'match' => null, 'lot' => 'dossier_3', 'file' => 'Supporters 37.JPG',
]]])));
C::$data = "$tmp/cat.json.gz";
$id = 0;
$rel = null;
$mediaJson = file_get_contents(Media::FILE); // la médiathèque est remise telle quelle à la fin
try {
    $eq('catalogue lu', [count(C::items()), C::summary()['attente']], [1, 1]);
    copy("$tmp/a.jpg", "$tmp/nom-quelconque.jpg");
    $r = C::receive("$tmp/nom-quelconque.jpg");
    $rel = $r['file'] ?? null;
    $m = $rel ? Media::get($rel) : null;
    $eq('reconnue par son contenu, rangée dans la médiathèque', [$r['status'], (bool) $m], ['nouvelle', true]);
    $eq('légende, crédit, droits, date, rotation', [$m['caption'] ?? null, $m['credit'] ?? null, $m['rights'] ?? null, $m['date_text'] ?? null, $m['edit']['rotate'] ?? null],
        ['Supporters sochaliens à la gare.', 'Archives FCSM / Sochaux Rétro', C::RIGHTS['club'], date_fr('1937-05-09'), 90]);
    $eq('déposée deux fois : pas de doublon', C::receive("$tmp/a.jpg")['status'], 'deja');
    imagefill($img, 0, 0, imagecolorallocate($img, 0, 0, 120));
    imagejpeg($img, "$tmp/b.jpg");
    $eq('image hors catalogue ignorée', C::receive("$tmp/b.jpg")['status'], 'inconnue');

    $doc = Fiches::blank('article');
    $doc['title'] = 'Zzessai catalogue';
    $doc['status'] = 'brouillon';
    $doc['path'] = '/zzessai-catalogue/';
    $doc['slug'] = 'zzessai-catalogue';
    $id = (int) Fiches::save($doc, ['name' => 'Essai'])['id'];
    $eq('rangée dans la fiche choisie, légende corrigée', C::accept($md5, [$id], 'Supporters à la gare de l’Est.'), 1);
    $d = Fiches::get($id);
    $eq('galerie et image principale', [$d['gallery'][0]['image'] ?? null, $d['gallery'][0]['caption'] ?? null, $d['featured_image']], [$rel, 'Supporters à la gare de l’Est.', $rel]);
    $eq('rangée une seule fois même si on recommence', [C::accept($md5, [$id]), count(Fiches::get($id)['gallery'])], [1, 1]);
    $eq('statut « rangée »', C::summary()['range'], 1);
    C::reject($md5);
    $eq('écartée', C::summary()['ecarte'], 1);
    C::restore($md5);
    $eq('remise à ranger', C::summary()['recu'], 1);
} finally {
    if ($id) {
        Fiches::destroy($id, ['name' => 'Essai']);
        exec('rm -rf ' . escapeshellarg(STORAGE_PATH . "/versions/$id"));
    }
    if ($rel) {
        Media::remove($rel);
        @unlink(Media::ORIGINALS . '/' . $rel);
    }
    file_put_contents(Media::FILE, $mediaJson);
    Media::forget();
    exec('rm -rf ' . escapeshellarg($tmp));
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
