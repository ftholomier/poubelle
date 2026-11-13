<?php
/**
 * Droits des photos (ticket) : photos d'agences, de banques d'images et de journaux repérées par
 * le crédit, la légende, le nom du fichier et les informations cachées du fichier (IPTC, EXIF, XMP),
 * jamais effacées ; avertissement et crédit prérempli à l'envoi ; décision de l'équipe.
 * Médiathèque et relevé des fichiers remis en état. Usage : php tests/droits-photos.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Medias;
use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\Request;
use App\Data\Media;
use App\Services\PhotoRights;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$who = fn (string $rel, array $m, ?array $meta = []) => array_column(PhotoRights::findings($rel, $m, $meta), 'who');

// 1. Ce que dit la médiathèque.
$eq('crédit « AFP »', $who('2024/01/a.jpg', ['credit' => 'AFP']), ['AFP']);
$eq('légende « … photo L’Équipe »', $who('2024/01/a.jpg', ['caption' => 'Pouplin, Contout photo L’Equipe']), ['L’Équipe']);
$eq('légende « l’équipe de Sochaux » : rien', $who('2024/01/a.jpg', ['caption' => 'Photo de l’équipe de Sochaux en 1988, Le Pays de Montbéliard en fête']), []);
$eq('crédit « Le Pays »', $who('2024/01/a.jpg', ['credit' => 'Le Pays']), ['Le Pays']);
$eq('nom de fichier Adobe Stock', $who('2025/03/AdobeStock_123456789.jpeg', []), ['Adobe Stock']);
$eq('nom de fichier « …-photo-AFP.jpg »', $who('2024/01/Sochaux-Rennes-201213-3-photo-AFP.jpg', []), ['AFP']);
$eq('« photo ER » (L’Est Républicain) dans le nom du fichier', $who('2026/08/naidji-photo-er-lionel-vadam-1662154657.jpg', []), ['L’Est Républicain']);
$eq('« Icon Sport via Getty Images » : la banque d’abord', $who('2026/02/839545594.jpg', ['credit' => '(Photo: Olivier Andrivon / Icon Sport via Getty Images)']), ['Getty Images', 'Icon Sport']);
$eq('photo du club créditée : rien', $who('2024/01/equipe-1988.jpg', ['credit' => 'Collection FC Sochaux-Montbéliard', 'caption' => 'L’équipe 1988']), []);
$eq('« Panoramique » n’est pas Panoramic', $who('2024/01/a.jpg', ['caption' => 'Vue panoramique du stade Bonal']), []);

// 2. Informations cachées dans le fichier : IPTC et XMP lus, crédit proposé.
$dir = sys_get_temp_dir() . '/droits-' . bin2hex(random_bytes(3));
mkdir($dir);
$im = imagecreatetruecolor(300, 200);
imagejpeg($im, "$dir/a.jpg");
$iptc = '';
foreach (['2#116' => '© 2019 Getty Images', '2#110' => 'Getty Images', '2#080' => 'Jean Dupont'] as $tag => $v) {
    [$rec, $ds] = explode('#', substr($tag, 0));
    $iptc .= chr(0x1C) . chr((int) $rec) . chr((int) $ds) . pack('n', strlen($v)) . $v;
}
file_put_contents("$dir/iptc.jpg", iptcembed($iptc, "$dir/a.jpg"));
$m = PhotoRights::read("$dir/iptc.jpg");
$eq('IPTC lu (copyright, crédit, auteur)', [$m['copyright'] ?? null, $m['credit'] ?? null, $m['byline'] ?? null], ['© 2019 Getty Images', 'Getty Images', 'Jean Dupont']);
$eq('crédit proposé : auteur / agence', PhotoRights::suggestedCredit($m), 'Jean Dupont / Getty Images');
$eq('IPTC : Getty Images repéré', array_column(PhotoRights::findings('x.jpg', [], $m), 'where')[0] ?? null, 'les informations cachées de la photo (copyright)');
$xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description photoshop:Credit="Shutterstock" xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/" xmpRights:WebStatement="https://www.shutterstock.com/license"><dc:rights><rdf:Alt><rdf:li xml:lang="x-default">Copyright Dmitry K.</rdf:li></rdf:Alt></dc:rights></rdf:Description></rdf:RDF></x:xmpmeta>';
$jpg = (string) file_get_contents("$dir/a.jpg");
$seg = "http://ns.adobe.com/xap/1.0/\0" . $xmp;
file_put_contents("$dir/xmp.jpg", substr($jpg, 0, 2) . "\xFF\xE1" . pack('n', strlen($seg) + 2) . $seg . substr($jpg, 2));
$m = PhotoRights::read("$dir/xmp.jpg");
$eq('XMP lu (crédit, droits, adresse)', [$m['credit'] ?? null, $m['copyright'] ?? null, $m['url'] ?? null], ['Shutterstock', 'Copyright Dmitry K.', 'https://www.shutterstock.com/license']);
$eq('XMP : Shutterstock repéré', $who('x.jpg', [], $m), ['Shutterstock']);
$eq('photo sans informations : rien lu', PhotoRights::read("$dir/a.jpg"), []);

// 3. Envoi depuis le back-office, décision de l'équipe.
$saved = (string) file_get_contents(Media::FILE);
$savedStore = is_file(PhotoRights::FILE) ? (string) file_get_contents(PhotoRights::FILE) : null;
(new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, true);
(new ReflectionProperty(Auth::class, 'current'))->setValue(null, ['id' => 'historien-essai', 'name' => 'Historien essai', 'email' => '', 'role' => 'user']);
$server = ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'];
$rel = null;
try {
    copy("$dir/iptc.jpg", "$dir/envoi.jpg");
    $req = new Request('POST', '/admin/medias/envoi', [], [], ['file' => ['name' => 'zz-essai-droits.jpg', 'tmp_name' => "$dir/envoi.jpg", 'error' => UPLOAD_ERR_OK, 'size' => filesize("$dir/envoi.jpg")]], $server, '');
    $r = json_decode(Medias::upload($req)->body, true);
    $rel = $r['file'] ?? null;
    JsonStore::forget();
    Media::forget();
    $eq('envoi : avertissement Getty Images', str_contains((string) ($r['warning'] ?? ''), 'Getty Images'), true);
    $eq('envoi : crédit et droits préremplis depuis la photo', [Media::get((string) $rel)['credit'] ?? null, Media::get((string) $rel)['rights'] ?? null], ['Jean Dupont / Getty Images', '© 2019 Getty Images']);
    $eq('fichier envoyé intact (informations gardées)', PhotoRights::read(Media::ORIGINALS . '/' . $rel)['copyright'] ?? null, '© 2019 Getty Images');
    $post = fn (array $d) => json_decode(Medias::rights(new Request('POST', '/admin/medias/droits', [], $d, [], $server, ''))->body, true);
    $eq('décision sans raison : refusée', isset($post(['file' => $rel])['error']), true);
    $r = $post(['file' => $rel, 'note' => 'Licence achetée']);
    JsonStore::forget();
    Media::forget();
    $eq('droits vérifiés : qui et pourquoi', [Media::get((string) $rel)['rights_ok']['by'] ?? null, Media::get((string) $rel)['rights_ok']['note'] ?? null], ['Historien essai', 'Licence achetée']);
    $eq('photo vérifiée : plus signalée', array_key_exists((string) $rel, (function () {
        $pending = [];
        foreach (Media::all() as $r => $m) {
            if (empty($m['rights_ok']) && PhotoRights::findings((string) $r, $m)) {
                $pending[(string) $r] = true;
            }
        }
        return $pending;
    })()), false);
    $post(['file' => $rel, 'reset' => 1]);
    JsonStore::forget();
    Media::forget();
    $eq('remise à vérifier', isset(Media::get((string) $rel)['rights_ok']), false);
} finally {
    if ($rel) {
        @unlink(Media::ORIGINALS . '/' . $rel);
        @rmdir(dirname(Media::ORIGINALS . '/' . $rel));
    }
    array_map('unlink', glob("$dir/*") ?: []);
    @rmdir($dir);
    file_put_contents(Media::FILE, $saved);
    // Caches de la médiathèque écrits pendant l'essai (même seconde que la remise en état) : effacés.
    @unlink(STORAGE_PATH . '/cache/media.php');
    array_map('unlink', glob(STORAGE_PATH . '/cache/media/*.php') ?: []);
    @unlink(STORAGE_PATH . '/cache/droits-a-verifier.php');
    if ($savedStore === null) {
        @unlink(PhotoRights::FILE);
    } else {
        file_put_contents(PhotoRights::FILE, $savedStore);
    }
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
