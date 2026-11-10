<?php
/**
 * Bandes unies incrustées dans les photos (ticket : bandeau de Sochaux–Stade Rennais FC avec des bandes
 * blanches sur les côtés malgré le « cover ») : Images::bands() les repère, Images::open() les rogne
 * (retouche « trim », Media::editOf()), un recadrage de l'historien l'emporte. Images fabriquées,
 * rien n'est écrit. Usage : php tests/bandes.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Data\Media;
use App\Services\Images;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
// « Photo » : bruit coloré (aucune ligne unie), avec des bandes éventuelles.
$photo = function (int $w, int $h, array $bands = [0, 0, 0, 0], array $color = [255, 255, 255]): \GdImage {
    mt_srand(42);
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y += 2) {
        for ($x = 0; $x < $w; $x += 2) {
            imagefilledrectangle($im, $x, $y, $x + 1, $y + 1, imagecolorallocate($im, mt_rand(20, 230), mt_rand(60, 200), mt_rand(10, 220)));
        }
    }
    $c = imagecolorallocate($im, ...$color);
    [$l, $t, $r, $b] = $bands;
    $l && imagefilledrectangle($im, 0, 0, $l - 1, $h - 1, $c);
    $r && imagefilledrectangle($im, $w - $r, 0, $w - 1, $h - 1, $c);
    $t && imagefilledrectangle($im, 0, 0, $w - 1, $t - 1, $c);
    $b && imagefilledrectangle($im, 0, $h - $b, $w - 1, $h - 1, $c);
    return $im;
};

$eq('bandes blanches à gauche et à droite (comme Sochaux–Rennes)', Images::bands($photo(1000, 500, [64, 0, 65, 0])), [0.064, 0.0, 0.065, 0.0]);
$eq('bandes noires en haut et en bas', Images::bands($photo(800, 600, [0, 60, 0, 60], [0, 0, 0])), [0.0, 0.1, 0.0, 0.1]);
$eq('photo sans bande : rien', Images::bands($photo(800, 500)), null);
$eq('liseré de 2 px : ignoré', Images::bands($photo(800, 500, [2, 0, 2, 0])), null);
$flat = imagecreatetruecolor(600, 400);
imagefilledrectangle($flat, 0, 0, 599, 399, imagecolorallocate($flat, 120, 170, 230));
$eq('image presque unie (ciel) : jamais rognée', Images::bands($flat), null);

// Rognage à la fabrication des déclinaisons ; recadrage de l'historien prioritaire.
$tmp = sys_get_temp_dir() . '/bandes-' . bin2hex(random_bytes(3)) . '.png';
imagepng($photo(1000, 500, [64, 0, 65, 0]), $tmp);
$im = Images::open($tmp, ['trim' => [0.064, 0.0, 0.065, 0.0]]);
$eq('photo rognée : plus de bande, toute la hauteur', [imagesx($im), imagesy($im), Images::bands($im)], [871, 500, null]);
@unlink($tmp);
$eq('bandes notées : rognées', Media::editOf(['trim' => [0.06, 0, 0.06, 0]]), ['trim' => [0.06, 0, 0.06, 0]]);
$eq('recadrage choisi par l’historien : il l’emporte', Media::editOf(['trim' => [0.06, 0, 0.06, 0], 'edit' => ['crop' => [0.1, 0.1, 0.5, 0.5]]]), ['crop' => [0.1, 0.1, 0.5, 0.5]]);
$eq('aucune bande, aucune retouche', [Media::editOf(['trim' => []]), Media::editOf(null)], [null, null]);

echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
