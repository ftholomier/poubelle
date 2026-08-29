<?php
declare(strict_types=1);

/**
 * Fabrique le dossier à transférer par FTP.
 *
 * Un transfert manuel suppose de sélectionner les bons dossiers dans le
 * client FTP à chaque fois. Une seule case cochée de trop — data/ le plus
 * souvent — et le contenu saisi au back-office repart à son état d'origine :
 * les photos du diaporama, les textes, les réalisations. Ce script écarte la
 * question en ne recopiant que le code, à partir de la même liste que celle
 * qu'emploie la mise à jour automatique du back-office.
 *
 * Usage :  php outils/paquet-maj.php [dossier de sortie]
 */

require __DIR__ . '/../app/Core/Permissions.php';
require __DIR__ . '/../app/Core/Parametres.php';
require __DIR__ . '/../app/Core/Deploiement.php';

use App\Core\Deploiement;

$racine = dirname(__DIR__);
$sortie = $argv[1] ?? $racine . '/paquet-maj';

if (is_dir($sortie)) {
    effacer($sortie);
}
if (!mkdir($sortie, 0755, true) && !is_dir($sortie)) {
    fwrite(STDERR, "Impossible de créer $sortie\n");
    exit(1);
}

$copies = 0;
$absents = [];

foreach (Deploiement::cheminsCode() as $chemin) {
    $source = $racine . '/' . $chemin;
    if (!file_exists($source)) {
        $absents[] = $chemin;
        continue;
    }
    $cible = $sortie . '/' . $chemin;
    @mkdir(dirname($cible), 0755, true);
    $copies += copier($source, $cible);
    echo '  ' . $chemin . (is_dir($source) ? '/' : '') . PHP_EOL;
}

echo PHP_EOL . $copies . " fichier(s) dans $sortie" . PHP_EOL;
if ($absents !== []) {
    echo 'Absents du dépôt, ignorés : ' . implode(', ', $absents) . PHP_EOL;
}
echo PHP_EOL
   . "Transférez le CONTENU de ce dossier à la racine du site, en écrasant." . PHP_EOL
   . "data/, storage/ et public/assets/img/site/ n'y sont pas : ils restent" . PHP_EOL
   . "intacts sur le serveur, avec le contenu et les photos du client." . PHP_EOL;

function copier(string $source, string $cible): int
{
    if (is_file($source)) {
        copy($source, $cible);
        return 1;
    }

    $n = 0;
    @mkdir($cible, 0755, true);
    foreach (scandir($source) ?: [] as $entree) {
        if ($entree === '.' || $entree === '..') {
            continue;
        }
        $n += copier($source . '/' . $entree, $cible . '/' . $entree);
    }
    return $n;
}

function effacer(string $chemin): void
{
    if (is_file($chemin) || is_link($chemin)) {
        unlink($chemin);
        return;
    }
    foreach (scandir($chemin) ?: [] as $entree) {
        if ($entree === '.' || $entree === '..') {
            continue;
        }
        effacer($chemin . '/' . $entree);
    }
    rmdir($chemin);
}
