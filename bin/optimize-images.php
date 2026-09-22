#!/usr/bin/env php
<?php
/**
 * Ramène les images déjà téléversées à une taille raisonnable et produit
 * leurs vignettes. Réexécutable : une image déjà traitée n'est pas retouchée.
 *
 *   php bin/optimize-images.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Image;

if (!Image::available()) {
    fwrite(STDERR, "L'extension GD est absente : rien à faire." . PHP_EOL);
    exit(1);
}

$result = Image::optimizeAll();
printf("%d image(s) traitée(s), %s Ko économisés.%s",
    $result['files'], number_format($result['saved'] / 1024, 0, ',', ' '), PHP_EOL);
