<?php
declare(strict_types=1);

/**
 * Dérive les variantes du logo à partir du fichier source fourni par le
 * client (monogramme « CV » surmontant le mot-symbole « CABINET VILLARD »).
 *
 * Produit :
 *   logo-trehant.png          logo complet détouré, marges égalisées
 *   logo-trehant-clair.png    mot-symbole en ivoire, pour les fonds sombres
 *   embleme-trehant.png       monogramme seul, pour les usages compacts
 *   favicon-512.png           monogramme sur fond orange
 *
 * Usage : php tools/logos.php <source.png>
 *
 * Le script est conservé dans le dépôt pour pouvoir régénérer les variantes
 * si le client fournit un jour un logo retouché : refaire ce détourage à la
 * main est long et le résultat serait difficile à reproduire à l'identique.
 */

$source = $argv[1] ?? null;
if ($source === null || !is_file($source)) {
    fwrite(STDERR, "Usage : php tools/logos.php <logo-source.png>\n");
    exit(1);
}

$destination = dirname(__DIR__) . '/public/assets/img/logo';
if (!is_dir($destination) && !mkdir($destination, 0755, true)) {
    fwrite(STDERR, "Impossible de créer {$destination}\n");
    exit(1);
}

$src = imagecreatefrompng($source);
if ($src === false) {
    fwrite(STDERR, "PNG illisible : {$source}\n");
    exit(1);
}

$largeur = imagesx($src);
$hauteur = imagesy($src);

/** Pixel suffisamment opaque pour compter comme du dessin. */
$opaque = static function (GdImage $im, int $x, int $y): bool {
    return ((imagecolorat($im, $x, $y) >> 24) & 0x7F) < 100;
};

// --- repérage du contenu et de la coupure monogramme / mot-symbole --------
$lignes = [];
for ($y = 0; $y < $hauteur; $y++) {
    $n = 0;
    for ($x = 0; $x < $largeur; $x++) {
        if ($opaque($src, $x, $y)) {
            $n++;
        }
    }
    $lignes[$y] = $n;
}

$pleines = array_keys(array_filter($lignes));
$hautContenu = (int) min($pleines);
$basContenu  = (int) max($pleines);

// La plus longue suite de lignes vides à l'intérieur du contenu sépare le
// monogramme du mot-symbole. La repérer plutôt que la coder en dur permet de
// rejouer le script sur un logo redessiné.
$meilleurDebut = $meilleurLong = $debut = $long = 0;
for ($y = $hautContenu; $y <= $basContenu; $y++) {
    if ($lignes[$y] === 0) {
        if ($long === 0) {
            $debut = $y;
        }
        $long++;
        if ($long > $meilleurLong) {
            $meilleurLong = $long;
            $meilleurDebut = $debut;
        }
    } else {
        $long = 0;
    }
}
$coupure = $meilleurLong > 0 ? $meilleurDebut + intdiv($meilleurLong, 2) : $basContenu;

$colonnes = [];
for ($x = 0; $x < $largeur; $x++) {
    for ($y = $hautContenu; $y <= $basContenu; $y++) {
        if ($opaque($src, $x, $y)) {
            $colonnes[] = $x;
            break;
        }
    }
}
$gaucheContenu = (int) min($colonnes);
$droiteContenu = (int) max($colonnes);

printf(
    "Contenu x %d..%d, y %d..%d — coupure monogramme/texte à y=%d\n",
    $gaucheContenu,
    $droiteContenu,
    $hautContenu,
    $basContenu,
    $coupure
);

/** Copie détourée d'une bande, avec une marge proportionnelle. */
$decouper = static function (GdImage $im, int $x1, int $y1, int $x2, int $y2, float $marge = 0.04): GdImage {
    $l = $x2 - $x1 + 1;
    $h = $y2 - $y1 + 1;
    $m = (int) round(max($l, $h) * $marge);

    $out = imagecreatetruecolor($l + 2 * $m, $h + 2 * $m);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagealphablending($out, true);
    imagecopy($out, $im, $m, $m, $x1, $y1, $l, $h);

    return $out;
};

// --- logo complet ---------------------------------------------------------
$complet = $decouper($src, $gaucheContenu, $hautContenu, $droiteContenu, $basContenu);
imagepng($complet, $destination . '/logo-trehant.png', 9);
printf("logo-trehant.png %dx%d\n", imagesx($complet), imagesy($complet));

// --- emblème seul ---------------------------------------------------------
// Le monogramme est plus étroit que le mot-symbole : on relève ses propres
// bornes horizontales, sinon il resterait décentré dans un cadre trop large.
$colMono = [];
for ($x = 0; $x < $largeur; $x++) {
    for ($y = $hautContenu; $y < $coupure; $y++) {
        if ($opaque($src, $x, $y)) {
            $colMono[] = $x;
            break;
        }
    }
}
$embleme = $decouper($src, (int) min($colMono), $hautContenu, (int) max($colMono), $coupure - 1, 0.05);
imagepng($embleme, $destination . '/embleme-trehant.png', 9);
printf("embleme-trehant.png %dx%d\n", imagesx($embleme), imagesy($embleme));

// --- variante claire ------------------------------------------------------
// Sur fond sombre, le mot-symbole orange tombe à 3:1 : illisible en petit.
// Le monogramme, lui, garde son dégradé — c'est la signature de la marque.
$clair = imagecreatetruecolor(imagesx($complet), imagesy($complet));
imagealphablending($clair, false);
imagesavealpha($clair, true);
imagecopy($clair, $complet, 0, 0, 0, 0, imagesx($complet), imagesy($complet));

$decalage = $coupure - $hautContenu + (int) round(max(
    $droiteContenu - $gaucheContenu + 1,
    $basContenu - $hautContenu + 1
) * 0.04);

for ($y = $decalage; $y < imagesy($clair); $y++) {
    for ($x = 0; $x < imagesx($clair); $x++) {
        $rgba = imagecolorat($clair, $x, $y);
        $alpha = ($rgba >> 24) & 0x7F;
        if ($alpha >= 127) {
            continue;
        }
        // L'ivoire remplace la couleur, l'opacité d'origine est conservée :
        // c'est elle qui porte le lissage des contours des lettres.
        imagesetpixel($clair, $x, $y, imagecolorallocatealpha($clair, 253, 250, 252, $alpha));
    }
}
imagepng($clair, $destination . '/logo-trehant-clair.png', 9);
printf("logo-trehant-clair.png %dx%d\n", imagesx($clair), imagesy($clair));

// --- favicon --------------------------------------------------------------
$taille = 512;
$favicon = imagecreatetruecolor($taille, $taille);
imagealphablending($favicon, false);
imagesavealpha($favicon, true);
imagefill($favicon, 0, 0, imagecolorallocatealpha($favicon, 255, 255, 255, 0));
imagealphablending($favicon, true);

$marge = (int) round($taille * 0.12);
$dispo = $taille - 2 * $marge;
$ratio = min($dispo / imagesx($embleme), $dispo / imagesy($embleme));
$le = (int) round(imagesx($embleme) * $ratio);
$he = (int) round(imagesy($embleme) * $ratio);
imagecopyresampled(
    $favicon,
    $embleme,
    intdiv($taille - $le, 2),
    intdiv($taille - $he, 2),
    0,
    0,
    $le,
    $he,
    imagesx($embleme),
    imagesy($embleme)
);
imagepng($favicon, $destination . '/favicon-512.png', 9);
printf("favicon-512.png %dx%d\n", $taille, $taille);

// --- déclinaisons de largeur pour le site --------------------------------
foreach ([400, 800] as $l) {
    $h = (int) round(imagesy($complet) * $l / imagesx($complet));
    $petit = imagecreatetruecolor($l, $h);
    imagealphablending($petit, false);
    imagesavealpha($petit, true);
    imagefill($petit, 0, 0, imagecolorallocatealpha($petit, 255, 255, 255, 127));
    imagealphablending($petit, true);
    imagecopyresampled($petit, $complet, 0, 0, 0, 0, $l, $h, imagesx($complet), imagesy($complet));
    imagepng($petit, $destination . '/logo-trehant@' . $l . '.png', 9);
    printf("logo-trehant@%d.png %dx%d\n", $l, $l, $h);
}

echo "Terminé.\n";
