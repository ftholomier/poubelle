<?php
/**
 * Suppression d'une photo de la médiathèque (ticket) : permise à toute l'équipe (historiens compris),
 * jamais tant que la photo est utilisée quelque part. Photo d'essai créée puis supprimée ; fichier de la
 * médiathèque remis en état. Usage : php tests/medias-suppression.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Medias;
use App\Core\Auth;
use App\Core\Request;
use App\Data\Media;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$saved = (string) file_get_contents(Media::FILE);
(new ReflectionProperty(Auth::class, 'loaded'))->setValue(null, true);
(new ReflectionProperty(Auth::class, 'current'))->setValue(null, ['id' => 'historien-essai', 'name' => 'Historien essai', 'email' => '', 'role' => 'user']);
$del = function (string $rel, bool $force = false) {
    $r = Medias::delete(new Request('POST', '/admin/medias/supprimer', [], ['file' => $rel] + ($force ? ['force' => '1'] : []), [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'], ''));
    return [$r->status, json_decode($r->body, true)];
};
$rel = 'zz-essai/suppression-' . bin2hex(random_bytes(3)) . '.jpg';
$file = Media::ORIGINALS . '/' . $rel;
try {
    // Une photo utilisée (photo principale d'une fiche publiée) : refusée, même en forçant.
    $usedRel = null;
    foreach (Media::usage() as $r => $u) {
        if ($u && Media::get((string) $r)) {
            $usedRel = (string) $r;
            break;
        }
    }
    [$code, $body] = $del($usedRel, true);
    $eq('photo utilisée : suppression refusée (même en forçant), historien', [$code, str_contains((string) ($body['error'] ?? ''), 'encore utilisé')], [409, true]);
    \App\Core\JsonStore::forget();
    Media::forget();
    $eq('photo utilisée : toujours dans la médiathèque', Media::get($usedRel) !== null, true);

    // Une photo inutilisée : supprimée par un historien, fichier effacé.
    @mkdir(dirname($file), 0775, true);
    $im = imagecreatetruecolor(40, 30);
    imagejpeg($im, $file);
    Media::put($rel, ['file' => $rel, 'caption' => 'Essai suppression', 'width' => 40, 'height' => 30]);
    [$code, $body] = $del($rel);
    \App\Core\JsonStore::forget();
    Media::forget();
    $eq('photo inutilisée : supprimée par un historien', [$code, $body['ok'] ?? null, Media::get($rel), is_file($file)], [200, true, null, false]);
} finally {
    @unlink($file);
    @rmdir(dirname($file));
    file_put_contents(Media::FILE, $saved);
    Media::forget();
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
