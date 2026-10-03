<?php
/**
 * Télécharge depuis l'ancien site les fichiers de la médiathèque (data/media.json) absents de
 * storage/media/originals. À lancer sur le serveur à l'installation, tant que l'ancien site
 * WordPress est en ligne. Reprenable : relancer la commande reprend là où elle s'est arrêtée,
 * aucun fichier présent n'est écrasé.
 *
 * Usage : php scripts/wp/media-sync.php [--depuis=<dossier>] [--verifier]
 *   --depuis    dossier « wp-content/uploads » de l'ancien site sur le même hébergement :
 *               les fichiers y sont copiés directement (bien plus rapide), les autres sont
 *               téléchargés.
 *   --verifier  contrôle aussi l'empreinte (sha1) des fichiers déjà présents et retélécharge
 *               ceux qui sont abîmés.
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

$media = read_json(ROOT . '/data/media.json') ?: [];
$base = ROOT . '/storage/media/originals';
$verify = in_array('--verifier', $argv, true);
$from = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--depuis=')) {
        $from = rtrim(substr($a, 9), '/');
        if (!is_dir($from)) {
            fwrite(STDERR, "Dossier introuvable : $from\n");
            exit(1);
        }
    }
}

$todo = [];
foreach ($media as $rel => $m) {
    $rel = (string) $rel;
    $file = "$base/$rel";
    if (!is_file($file)) {
        $todo[$rel] = $m;
    } elseif ($verify && !empty($m['sha1']) && sha1_file($file) !== $m['sha1']) {
        $todo[$rel] = $m;
    }
}
out(count($media) . ' médias, ' . count($todo) . ' à télécharger');

$ok = 0;
$errors = [];
$warnings = [];
$n = 0;
foreach ($todo as $rel => $m) {
    $n++;
    $url = wp_url() . '/wp-content/uploads/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
    $file = "$base/$rel";
    ensure_dir(dirname($file));
    $part = "$file.part";
    $local = $from !== null && is_file("$from/$rel") ? "$from/$rel" : null;
    $fp = $local ? null : fopen($part, 'wb');
    try {
        if ($local) {
            if (!copy($local, $part)) {
                throw new RuntimeException("copie impossible depuis $local");
            }
        } else {
            $r = http($url, ['timeout' => 300, 'curl' => [CURLOPT_FILE => $fp, CURLOPT_RETURNTRANSFER => false]], 3);
            fclose($fp);
            if ($r['code'] !== 200 || filesize($part) < 100) {
                throw new RuntimeException('HTTP ' . $r['code']);
            }
        }
        if (!preg_match('/\.pdf$/i', $rel) && !@getimagesize($part)) {
            throw new RuntimeException('fichier reçu illisible');
        }
        if (!empty($m['sha1']) && sha1_file($part) !== $m['sha1']) {
            $warnings[$rel] = 'différent du fichier de la reprise (conservé)';
        }
        rename($part, $file);
        $ok++;
    } catch (Throwable $e) {
        if (is_resource($fp)) {
            fclose($fp);
        }
        @unlink($part);
        $errors[$rel] = $url . ' : ' . $e->getMessage();
    }
    if ($n % 100 === 0) {
        out("$n / " . count($todo) . " ($ok téléchargés, " . count($errors) . ' échecs)');
    }
}
if ($errors || $warnings) {
    write_json(IMPORT_DIR . '/media-sync-erreurs.json', ['echecs' => $errors, 'avertissements' => $warnings]);
    out('Détail : storage/import/media-sync-erreurs.json');
}
out("$ok téléchargé(s), " . count($errors) . ' échec(s), ' . count($warnings) . ' avertissement(s)');
exit($errors ? 1 : 0);
