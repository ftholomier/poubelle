<?php
/**
 * Photos du terrain (App\Services\PitchPhotos, ticket : pastilles sans photo pour les joueurs dont la
 * photo principale est en largeur) : réponse du détecteur retenue seulement si sûre (un seul visage),
 * carré recadré autour de la tête, vignette fabriquée, essais photo par photo, pastille affichée sur
 * le terrain ; image jointe à la requête Gemini. Détecteur simulé, photo d'essai fabriquée puis
 * supprimée ; aucune fiche ni aucun média modifié. Usage : php tests/pastilles.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Media;
use App\Services\PitchPhotos as P;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// 1. Réponse du détecteur : un seul visage, déclaré sûr, cadre valable.
$eq('un seul visage sûr : retenu', P::accept(['faces' => 1, 'sure' => true, 'box_2d' => [100, 400, 300, 550]]), [0.1, 0.4, 0.3, 0.55]);
$eq('deux visages : jamais', P::accept(['faces' => 2, 'sure' => true, 'box_2d' => [100, 400, 300, 550]]), null);
$eq('pas sûr : jamais', P::accept(['faces' => 1, 'sure' => false, 'box_2d' => [100, 400, 300, 550]]), null);
$eq('sans cadre, cadre inversé ou démesuré : jamais', [P::accept(['faces' => 1, 'sure' => true]), P::accept(['faces' => 1, 'sure' => true, 'box_2d' => [300, 550, 100, 400]]), P::accept(['faces' => 1, 'sure' => true, 'box_2d' => [0, 0, 1000, 1000]])], [null, null, null]);
$eq('réponse illisible : jamais', [P::accept(null), P::accept('oui')], [null, null]);

// 2. Carré autour du visage : tête et épaules, gardé dans la photo ; trop petit : rien.
$eq('visage de 100 px dans une photo de 800 × 500 : carré de 190 px centré sous le visage', P::square([0.2, 0.4375, 0.4, 0.5625], 800, 500), ['x' => 305, 'y' => 67, 'side' => 190]);
$eq('visage collé au bord : carré gardé dans la photo', P::square([0.0, 0.0, 0.3, 0.15], 800, 500)['x'] ?? null, 0);
$eq('visage minuscule (photo d’équipe) : rien', P::square([0.5, 0.5, 0.52, 0.51], 800, 500), null);

// 3. Image jointe à la requête Gemini, avant le texte.
$body = \App\Services\Gemini::requestBody([['role' => 'user', 'text' => 'Qui ?', 'images' => [['mime' => 'image/webp', 'data' => 'QUJD']]]], null);
$eq('requête Gemini : image puis texte', $body['contents'][0]['parts'], [['inline_data' => ['mime_type' => 'image/webp', 'data' => 'QUJD']], ['text' => 'Qui ?']]);

// 4. Une personne : photo en largeur fabriquée (visage simulé), essais photo par photo, vignette, pastille.
$rel = 'zz-essai/pastille-' . bin2hex(random_bytes(3)) . '.jpg';
$rel2 = 'zz-essai/pastille2-' . bin2hex(random_bytes(3)) . '.jpg';
$src = Media::ORIGINALS . '/' . $rel;
$made = [];
$saved = is_file(P::FILE) ? (string) file_get_contents(P::FILE) : null;
try {
    @mkdir(dirname($src), 0775, true);
    foreach ([$rel, $rel2] as $r) {
        $im = imagecreatetruecolor(900, 560);
        imagefilledrectangle($im, 0, 0, 899, 559, imagecolorallocate($im, 30, 120, 40));
        imagefilledellipse($im, 450, 160, 110, 140, imagecolorallocate($im, 230, 190, 160)); // « visage »
        imagejpeg($im, Media::ORIGINALS . '/' . $r, 90);
        imagedestroy($im);
        $made[] = Media::ORIGINALS . '/' . $r;
    }
    $doc = ['id' => 999999, 'title' => 'Joueur Essai', 'featured_image' => $rel, 'gallery' => [['image' => $rel2], ['image' => $rel]], 'personne' => []];
    $eq('photos essayées : principale puis galerie, sans doublon', P::candidates($doc), [$rel, $rel2]);
    $seen = [];
    // Photo principale : deux visages (pas sûr) ; galerie : un seul visage net.
    P::$detect = function (string $file, array $d, string $caption) use (&$seen, $rel) {
        $seen[] = $file;
        return count($seen) === 1 ? P::accept(['faces' => 2, 'sure' => true, 'box_2d' => [160, 440, 410, 560]]) : P::accept(['faces' => 1, 'sure' => true, 'box_2d' => [160, 440, 410, 560]]);
    };
    $r = P::forPerson($doc);
    $eq('photo principale douteuse écartée, photo de la galerie retenue', [count($seen), $r['img'] ?? null], [2, $rel2]);
    $out = MEDIA_PATH . '/pastilles/' . ($r['file'] ?? 'x');
    $made[] = $out;
    $eq('vignette carrée de 200 px fabriquée', is_file($out) ? array_slice(getimagesize($out), 0, 2) : null, [200, 200]);
    $eq('carré autour du visage (côté 1,9 × la hauteur du visage)', $r['box']['side'] ?? null, 266);
    \App\Core\JsonStore::write(P::FILE, [999999 => $r + ['sig' => P::sig($doc), 'at' => date('c')]]);
    P::forget();
    $eq('adresse de la pastille', P::url(999999), MEDIA_URL . '/pastilles/' . $r['file']);
    $eq('aucun visage sûr : rien', [P::url(123456789), (function () use ($doc) { P::$detect = fn () => null; return P::forPerson($doc)['file']; })()], [null, null]);
    $eq('photo principale en largeur : pas un portrait', P::isPortrait($rel), false);
} finally {
    P::$detect = null;
    foreach ($made as $f) {
        @unlink($f);
    }
    foreach ([$rel, $rel2] as $r) {
        foreach (glob(MEDIA_PATH . '/*/' . $r . '.webp') ?: [] as $f) {
            @unlink($f);
        }
    }
    @rmdir(dirname($src));
    $saved === null ? @unlink(P::FILE) : file_put_contents(P::FILE, $saved);
    P::forget();
}

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
