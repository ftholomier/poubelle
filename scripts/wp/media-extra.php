<?php
/**
 * Télécharge les images citées par les fiches mais absentes de la médiathèque
 * WordPress (liste établie par import.php : storage/import/media-extra.json).
 *
 * Usage : WP_PASSWORD=... php scripts/wp/media-extra.php
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$list = read_json(IMPORT_DIR . '/media-extra.json') ?: [];
$base = ROOT . '/storage/media/originals';
$todo = array_filter($list, fn ($url, $rel) => !is_file("$base/$rel"), ARRAY_FILTER_USE_BOTH);
out(count($list) . ' image(s) hors médiathèque, ' . count($todo) . ' à télécharger');
if (!$todo) {
    exit(0);
}
login();
$ok = 0;
$errors = [];
foreach ($todo as $rel => $url) {
    $file = "$base/$rel";
    ensure_dir(dirname($file));
    $part = "$file.part";
    $fp = fopen($part, 'wb');
    try {
        $r = http($url, ['timeout' => 300, 'curl' => [CURLOPT_FILE => $fp, CURLOPT_RETURNTRANSFER => false]]);
        fclose($fp);
        if ($r['code'] !== 200 || filesize($part) < 100) {
            throw new RuntimeException('HTTP ' . $r['code']);
        }
        rename($part, $file);
        $ok++;
        out("ok $rel");
    } catch (Throwable $e) {
        @fclose($fp);
        @unlink($part);
        $errors[$rel] = $url . ' : ' . $e->getMessage();
        out("échec $rel : " . $e->getMessage());
    }
    usleep(200000);
}
write_json(IMPORT_DIR . '/media-extra-errors.json', $errors);
out("$ok téléchargée(s), " . count($errors) . ' échec(s)');
