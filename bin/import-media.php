<?php
declare(strict_types=1);

/**
 * Reprise des médias d'un site existant dans la photothèque.
 *
 *   php bin/import-media.php docs/le-signal-import.json
 *   php bin/import-media.php docs/le-signal-import.json --from=/chemin/des/originaux
 *   php bin/import-media.php docs/le-signal-import.json --force
 *
 * Le manifeste dit, pour chaque fichier, ce qu'on en fait :
 *   import  → converti en WebP (maître + dérivés 1600/800/400) et enregistré
 *             dans la photothèque avec ses textes alternatifs FR/EN ;
 *   audio   → copié tel quel dans /public/media ;
 *   asset   → copié dans /public/assets/img s'il n'y est pas déjà ;
 *   exclude → ignoré, avec la raison écrite dans le manifeste.
 *
 * Sans --from, les fichiers sont téléchargés depuis leur adresse d'origine.
 * Avec --from, ils sont lus dans un dossier qui reproduit l'arborescence
 * wp-content/uploads (2025/04/…). Sans --force, une photo déjà présente dans
 * la photothèque n'est pas reconvertie : la commande se relance sans risque.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Config;
use App\Http;
use App\Media;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$args = array_slice($argv, 1);
$manifestFile = '';
$from = '';
$force = false;
foreach ($args as $arg) {
    if ($arg === '--force') {
        $force = true;
    } elseif (str_starts_with($arg, '--from=')) {
        $from = rtrim(substr($arg, 7), '/');
    } elseif ($manifestFile === '') {
        $manifestFile = $arg;
    }
}
if ($manifestFile === '' || !is_readable($manifestFile)) {
    fwrite(STDERR, "Usage : php bin/import-media.php <manifeste.json> [--from=dossier] [--force]\n");
    exit(1);
}
$manifest = json_decode((string) file_get_contents($manifestFile), true);
$items = is_array($manifest['medias'] ?? null) ? $manifest['medias'] : [];
if ($items === []) {
    fwrite(STDERR, "Manifeste vide ou illisible.\n");
    exit(1);
}

$by = 'import-' . (string) (parse_url((string) ($items[0]['url'] ?? ''), PHP_URL_HOST) ?: 'manifeste');
$known = [];
foreach (Media::all() as $media) {
    $known[(string) ($media['path'] ?? '')] = true;
}

/** Copie locale du fichier d'origine : dossier fourni, sinon téléchargement. */
$fetch = static function (array $item) use ($from): ?string {
    if ($from !== '') {
        $file = $from . '/' . ltrim((string) $item['source'], '/');
        return is_readable($file) ? $file : null;
    }
    $response = Http::request('GET', (string) $item['url'], ['timeout' => 60]);
    if (!$response['ok'] || $response['body'] === '') {
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'media');
    if ($tmp === false || file_put_contents($tmp, $response['body']) === false) {
        return null;
    }
    return $tmp;
};

$entries = [];
$counts = ['import' => 0, 'skip' => 0, 'audio' => 0, 'asset' => 0, 'exclude' => 0, 'error' => 0];

foreach ($items as $item) {
    $action = (string) ($item['action'] ?? '');
    $target = (string) ($item['target'] ?? '');
    $label = (string) ($item['source'] ?? $item['url'] ?? '?');

    if ($action === 'exclude') {
        $counts['exclude']++;
        echo "· écarté   {$label} — " . (string) ($item['reason'] ?? '') . "\n";
        continue;
    }

    if ($action === 'asset' || $action === 'audio') {
        $dest = Config::publicPath(ltrim($target, '/'));
        if (is_file($dest) && !$force) {
            $counts[$action]++;
            echo "· présent  {$target}\n";
            continue;
        }
        $file = $fetch($item);
        if ($file === null || !@copy($file, $dest)) {
            $counts['error']++;
            echo "✗ échec    {$label}\n";
            continue;
        }
        $counts[$action]++;
        echo "+ copié    {$target}\n";
        continue;
    }

    if ($action !== 'import') {
        continue;
    }
    if (isset($known[$target]) && !$force && is_file(Config::publicPath(ltrim($target, '/')))) {
        $counts['skip']++;
        echo "· présent  {$target}\n";
        continue;
    }

    $file = $fetch($item);
    if ($file === null) {
        $counts['error']++;
        echo "✗ introuvable {$label}\n";
        continue;
    }
    $converted = Media::convert($file, pathinfo($target, PATHINFO_FILENAME));
    if ($from === '') {
        @unlink($file);
    }
    if (!$converted['ok'] || $converted['path'] !== $target) {
        $counts['error']++;
        echo "✗ conversion {$label} : " . ($converted['error'] ?: 'nom de destination inattendu') . "\n";
        continue;
    }

    $uploaded = (string) ($item['uploadedAt'] ?? '');
    $at = $uploaded !== '' && strtotime($uploaded) !== false
        ? (new DateTimeImmutable($uploaded))->format(DATE_ATOM)
        : (new DateTimeImmutable())->format(DATE_ATOM);

    $entries[] = [
        'path' => $converted['path'],
        'name' => basename((string) ($item['source'] ?? $target)),
        'alt' => (string) ($item['alt'] ?? ''),
        'caption' => '',
        'site' => Config::SITES[0],
        'group' => (string) ($item['group'] ?? ''),
        'source' => (string) ($item['url'] ?? ''),
        'width' => $converted['width'],
        'height' => $converted['height'],
        'bytes' => $converted['bytes'],
        'derivatives' => $converted['derivatives'],
        'at' => $at,
        'by' => $by,
        'i18n' => ['en' => ['alt' => (string) ($item['alt_en'] ?? ''), 'caption' => '']],
    ];
    $counts['import']++;
    echo "+ importé  {$converted['path']} ({$converted['width']}×{$converted['height']}, "
        . count($converted['derivatives']) . " dérivé(s))\n";
}

if ($entries !== []) {
    Media::register($entries, $by);
}

echo "\n" . $counts['import'] . ' importée(s), ' . $counts['skip'] . ' déjà présente(s), '
    . $counts['audio'] . ' audio, ' . $counts['asset'] . ' asset(s), '
    . $counts['exclude'] . ' écartée(s), ' . $counts['error'] . " erreur(s).\n";
exit($counts['error'] > 0 ? 1 : 0);
