<?php
declare(strict_types=1);

/**
 * Contenus d'origine du Signal : réglages, catalogue, pages FR/EN, avis.
 *
 * Usage : php bin/seed.php [--force]
 * Sans --force, les fichiers déjà présents dans content/ ne sont pas touchés :
 * la commande complète une installation sans jamais écraser une saisie faite
 * au back-office. Avec --force, tout est remis dans l'état de la reprise du
 * site le-signal.com (les versions précédentes restent restaurables : chaque
 * écriture passe par Store et garde l'ancien fichier dans content/_versions).
 *
 * Les fichiers de référence sont dans bin/seed/, au format exact de content/.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Store;

$force = \in_array('--force', $argv ?? [], true);
$source = __DIR__ . '/seed';

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), '.json')) {
        $files[] = ltrim(str_replace('\\', '/', substr($file->getPathname(), \strlen($source))), '/');
    }
}
sort($files);

foreach ($files as $relative) {
    if (!$force && Store::exists($relative)) {
        echo "· {$relative} (déjà présent)\n";
        continue;
    }
    $data = json_decode((string) file_get_contents($source . '/' . $relative), true);
    if (!\is_array($data)) {
        echo "✗ {$relative} : JSON illisible\n";
        continue;
    }
    unset($data['updatedAt'], $data['updatedBy']);
    Store::write($relative, $data, 'seed');
    echo ($force ? '↻' : '+') . " {$relative}\n";
}

if (!Store::exists('requests.json')) {
    Store::write('requests.json', ['requests' => []], 'seed');
    echo "+ requests.json\n";
}

echo "\nContenus d'origine en place. Photos : php bin/import-media.php docs/le-signal-import.json\n";
