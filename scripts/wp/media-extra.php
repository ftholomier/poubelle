<?php
/**
 * Télécharge les images citées par les fiches mais absentes de la médiathèque
 * WordPress (liste établie par import.php : storage/import/media-extra.json).
 *
 * Usage : php scripts/wp/media-extra.php [--reessayer]
 * (les fichiers de /wp-content/uploads/ ne sont pas protégés par le mot de passe du front ;
 * WP_PASSWORD n'est utilisé que s'il est défini).
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$list = read_json(IMPORT_DIR . '/media-extra.json') ?: [];
// --reessayer : reprend aussi les fichiers en échec lors de l'aspiration (media-errors.json),
// par exemple les images retouchées dans WordPress dont la taille annoncée n'est plus la bonne.
if (in_array('--reessayer', $argv, true)) {
    foreach (read_json(IMPORT_DIR . '/media-errors.json') ?: [] as $msg) {
        if (preg_match('#(https?://\S+/wp-content/uploads/(\S+?))\s*:#', (string) $msg, $m)) {
            $list[rawurldecode($m[2])] = $m[1];
        }
    }
}
$base = ROOT . '/storage/media/originals';
$todo = array_filter($list, fn ($url, $rel) => safe_media_rel((string) $rel) && !is_file("$base/$rel"), ARRAY_FILTER_USE_BOTH);
out(count($list) . ' image(s) hors médiathèque, ' . count($todo) . ' à télécharger');
if (!$todo) {
    exit(0);
}
if (getenv('WP_PASSWORD')) {
    login();
}
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
        if (!media_file_ok($part, $rel)) {
            throw new RuntimeException('fichier reçu illisible');
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
