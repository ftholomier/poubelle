<?php
declare(strict_types=1);

/*
 * Migration de l'ancienne base en ligne de commande.
 *
 *   php bin/import.php chemin/vers/dump.sql            import complet
 *   php bin/import.php chemin/vers/dump.sql --photos   + rapatriement des photos des pros actifs
 *   php bin/import.php --photos-only [--all]           photos seules (--all : y compris anciens membres)
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Import\Importer;

$args = array_slice($argv, 1);
$dump = null;
foreach ($args as $a) {
    if (!str_starts_with($a, '--')) {
        $dump = $a;
    }
}
$withPhotos = in_array('--photos', $args, true) || in_array('--photos-only', $args, true);
$photosOnly = in_array('--photos-only', $args, true);
$all = in_array('--all', $args, true);

$t0 = microtime(true);
if (!$photosOnly) {
    if ($dump) {
        if (!is_file($dump)) {
            fwrite(STDERR, "Fichier introuvable : $dump\n");
            exit(1);
        }
        App\Core\Fs::ensureDir(Importer::DIR);
        $dest = Importer::DIR . '/' . basename($dump);
        if (realpath($dump) !== realpath($dest)) {
            copy($dump, $dest);
            touch($dest);
        }
    }
    foreach (Importer::STEPS as $step => [$label]) {
        $offset = 0;
        do {
            $t = microtime(true);
            $res = Importer::run($step, $offset);
            printf("[%s] %s — %s (%.1fs)\n", $step, $label, $res['message'], microtime(true) - $t);
            $offset = $res['offset'];
        } while (!$res['done']);
    }
}
if ($withPhotos) {
    $offset = 0;
    do {
        $res = Importer::photos($offset, 20, !$all);
        printf("[photos] %s\n", $res['message']);
        $offset = $res['offset'];
    } while (!$res['done']);
}
printf("Terminé en %.1fs\n", microtime(true) - $t0);
