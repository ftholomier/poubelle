<?php
/**
 * Téléchargement parallèle des médias (en complément de « fetch.php media »).
 * Chaque travailleur traite une tranche de la médiathèque, en partant de la fin :
 *   php scripts/wp/media-worker.php 0 3   (tranche 0 sur 3)
 * Les fichiers déjà présents avec la bonne taille sont ignorés.
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$k = (int) ($argv[1] ?? 0);
$n = max(1, (int) ($argv[2] ?? 1));
$media = read_json(IMPORT_DIR . '/rest/media.json');
$base = ROOT . '/storage/media/originals';
$errors = [];
$bytes = 0;
$done = 0;
for ($i = count($media) - 1; $i >= 0; $i--) {
    if ($i % $n !== $k) {
        continue;
    }
    $m = $media[$i];
    $url = $m['source_url'];
    $rel = preg_replace('#^.*/wp-content/uploads/#', '', $url);
    $file = "$base/$rel";
    $expected = $m['media_details']['filesize'] ?? null;
    if (is_file($file) && ($expected === null || filesize($file) === $expected)) {
        continue;
    }
    $part = "$file.w$k.part";
    try {
        ensure_dir(dirname($file));
        $fp = fopen($part, 'wb');
        $r = http($url, ['timeout' => 600, 'curl' => [CURLOPT_FILE => $fp, CURLOPT_RETURNTRANSFER => false]]);
        fclose($fp);
        if ($r['code'] !== 200) {
            throw new RuntimeException("HTTP {$r['code']}");
        }
        $size = filesize($part);
        if ($expected !== null && $size !== $expected) {
            throw new RuntimeException("taille $size au lieu de $expected");
        }
        if (!is_file($file) || ($expected !== null && filesize($file) !== $expected)) {
            rename($part, $file);
        } else {
            @unlink($part);
        }
        $bytes += $size;
        $done++;
    } catch (Throwable $e) {
        @unlink($part);
        $errors[$m['id']] = $url . ' : ' . $e->getMessage();
    }
    if ($done % 100 === 0 && $done > 0) {
        out(sprintf('travailleur %d/%d : %d fichiers (%.1f Mo), %d erreurs', $k, $n, $done, $bytes / 1e6, count($errors)));
    }
    usleep(150000);
}
write_json(IMPORT_DIR . "/media-errors-w$k.json", $errors);
out(sprintf('travailleur %d/%d terminé : %d fichiers, %d erreurs', $k, $n, $done, count($errors)));
