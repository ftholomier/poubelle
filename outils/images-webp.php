<?php
declare(strict_types=1);

/**
 * Fabrique la variante WebP des photos déjà en place.
 *
 * La médiathèque produit désormais le WebP à l'import (voir
 * Mediatheque::ecrireWebp), mais les photos arrivées avant ne l'ont pas. Ce
 * script rattrape le retard, et ne fait rien de plus : il ne touche jamais au
 * JPEG, qui reste servi aux navigateurs qui ne comprennent pas le WebP.
 *
 * Il est rejouable sans dommage : une variante déjà à jour est laissée
 * telle quelle. « À jour » se juge sur la date du JPEG — une photo remplacée
 * après coup refait donc sa variante, ce qu'une simple présence de fichier ne
 * verrait pas.
 *
 * Une variante PLUS LOURDE que son JPEG est jetée. Mesuré sur les 78 photos
 * de ce site : 28 l'étaient, jusqu'à 42 % de plus — le WebP compresse mal un
 * feuillage ou du gravier. Les servir aurait ralenti le site au nom de
 * l'accélérer, et rien ne l'aurait dit.
 *
 * Usage :
 *     php outils/images-webp.php            # fabrique ce qui manque
 *     php outils/images-webp.php --refaire  # refait tout
 */

$racine = dirname(__DIR__);
$dossier = $racine . '/public/assets/img/site';
$refaire = in_array('--refaire', $argv, true);

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD n’a pas été compilé avec le WebP : rien à faire.\n");
    exit(1);
}
if (!is_dir($dossier)) {
    fwrite(STDERR, "Dossier introuvable : $dossier\n");
    exit(1);
}

$faits = $sautes = $echecs = $inutiles = 0;
$avant = $apres = 0;

foreach (glob($dossier . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE) ?: [] as $jpeg) {
    $webp = preg_replace('/\.jpe?g$/i', '.webp', $jpeg);
    if ($webp === null || $webp === $jpeg) {
        continue;
    }

    if (!$refaire && is_file($webp) && filemtime($webp) >= filemtime($jpeg)) {
        $sautes++;
        continue;
    }

    $image = @imagecreatefromjpeg($jpeg);
    if ($image === false) {
        fwrite(STDERR, 'illisible : ' . basename($jpeg) . "\n");
        $echecs++;
        continue;
    }

    /* 82, la même qualité que le JPEG produit par la médiathèque. Le WebP est
       plus économe à qualité égale : c'est de là que vient le gain, pas d'une
       image plus dégradée. */
    $ok = @imagewebp($image, $webp, 82);
    imagedestroy($image);

    if (!$ok) {
        fwrite(STDERR, 'échec : ' . basename($jpeg) . "\n");
        $echecs++;
        continue;
    }

    clearstatcache(true, $webp);
    if (filesize($webp) >= filesize($jpeg)) {
        unlink($webp);
        $inutiles++;
        continue;
    }

    @chmod($webp, 0644);
    $avant += (int) filesize($jpeg);
    $apres += (int) filesize($webp);
    $faits++;
}

printf("%d gardée(s), %d sans gain donc jetée(s), %d à jour, %d échec(s).\n",
    $faits, $inutiles, $sautes, $echecs);
if ($faits > 0) {
    printf("%s → %s, soit %d %% de moins.\n",
        number_format($avant / 1048576, 2, ',', ' ') . ' Mo',
        number_format($apres / 1048576, 2, ',', ' ') . ' Mo',
        (int) round(100 - $apres / max(1, $avant) * 100));
}

exit($echecs > 0 ? 1 : 0);
