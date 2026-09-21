#!/usr/bin/env php
<?php
/**
 * Récupère depuis la Wayback Machine les fichiers que l'export ne contenait
 * pas : photos de candidats, logos d'employeurs, CV.
 *
 * L'ancien site n'étant plus joignable en HTTPS, archive.org est souvent le
 * seul endroit où ces fichiers existent encore. Le script interroge l'API CDX
 * pour savoir ce qui est archivé, puis ne télécharge que ce qui manque.
 *
 *   php bin/import-wayback.php                 # tout ce qui manque
 *   php bin/import-wayback.php --only=photo    # photo | logo | cv
 *   php bin/import-wayback.php --dry-run
 *   php bin/import-wayback.php --retries=5
 *
 * Réexécutable : un fichier déjà présent n'est pas retéléchargé.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Domain\CvRepository;
use App\Domain\EmployerRepository;
use App\Services\Knowledge;
use App\Storage\Index;

$options  = getopt('', ['only::', 'dry-run', 'retries::', 'host::']);
$only     = (string) ($options['only'] ?? '');
$dryRun   = isset($options['dry-run']);
$retries  = max(1, (int) ($options['retries'] ?? 4));
$host     = (string) ($options['host'] ?? 'intermittent.fr');

const CDX = 'https://web.archive.org/cdx/search/cdx';
const WB  = 'https://web.archive.org/web/';

/** Requête HTTP avec réessais : le tunnel vers archive.org coupe souvent. */
function fetch(string $url, int $retries): string
{
    for ($i = 1; $i <= $retries; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_ENCODING       => '',      // gère gzip
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; intermittent.fr migration)',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && is_string($body) && $body !== '') {
            return $body;
        }
        if ($i < $retries) {
            sleep($i * 2);
        }
    }
    return '';
}

/**
 * Inventaire de ce qui est archivé sous /wp-content/uploads/.
 * @return array<string, array{url:string, timestamp:string}> chemin relatif => snapshot
 */
function inventory(string $host, int $retries): array
{
    $out = [];
    foreach ([$host, 'www.' . $host] as $variant) {
        $url = CDX . '?' . http_build_query([
            'url'       => $variant . '/wp-content/uploads',
            'matchType' => 'prefix',
            'output'    => 'json',
            'fl'        => 'original,timestamp,statuscode',
            'collapse'  => 'urlkey',
            'limit'     => 20000,
        ]);
        $body = fetch($url, $retries);
        if ($body === '') {
            continue;
        }
        $rows = json_decode($body, true);
        if (!is_array($rows) || count($rows) < 2) {
            continue;
        }
        foreach (array_slice($rows, 1) as $row) {
            [$original, $timestamp, $status] = array_pad((array) $row, 3, '');
            if ((string) $status !== '200' || !str_contains((string) $original, '/uploads/')) {
                continue;
            }
            $relative = explode('/uploads/', (string) $original, 2)[1];
            // On garde le snapshot le plus récent d'un même fichier.
            if (!isset($out[$relative]) || $timestamp > $out[$relative]['timestamp']) {
                $out[$relative] = ['url' => (string) $original, 'timestamp' => (string) $timestamp];
            }
        }
    }
    return $out;
}

/** Chemin relatif d'une URL de l'ancien site, ou '' si ce n'est pas un upload. */
function relativeOf(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    return str_contains($path, '/uploads/') ? explode('/uploads/', $path, 2)[1] : '';
}

echo "→ Inventaire de ce qui est archivé\n";
$archive = inventory($host, $retries);
if ($archive === []) {
    fwrite(STDERR, "API CDX injoignable. Réessayez plus tard, ou augmentez --retries.\n");
    exit(1);
}
printf("   %d fichier(s) archivés\n", count($archive));

/* ------------------------------------------- ce qu'il nous manque vraiment */

$wanted = [];   // [kind, id, relatif, applique]

foreach (CvRepository::all() as $cv) {
    if (($cv['photo']['path'] ?? '') === '' && ($cv['photo']['legacy_url'] ?? '') !== '') {
        $wanted[] = ['photo', (string) $cv['id'], relativeOf((string) $cv['photo']['legacy_url'])];
    }
    if (($cv['file']['path'] ?? '') === '' && ($cv['file']['legacy_url'] ?? '') !== '') {
        $wanted[] = ['cv', (string) $cv['id'], relativeOf((string) $cv['file']['legacy_url'])];
    }
}
foreach (EmployerRepository::all() as $employer) {
    if (($employer['logo']['path'] ?? '') === '' && ($employer['logo']['legacy_url'] ?? '') !== '') {
        $wanted[] = ['logo', (string) $employer['id'], relativeOf((string) $employer['logo']['legacy_url'])];
    }
}

if ($only !== '') {
    $wanted = array_values(array_filter($wanted, static fn(array $w) => $w[0] === $only));
}

$available = array_values(array_filter($wanted, static fn(array $w) => $w[2] !== '' && isset($archive[$w[2]])));

$byKind = [];
foreach ($wanted as [$kind]) {
    $byKind[$kind]['manquants'] = ($byKind[$kind]['manquants'] ?? 0) + 1;
}
foreach ($available as [$kind]) {
    $byKind[$kind]['archivés'] = ($byKind[$kind]['archivés'] ?? 0) + 1;
}

echo "\n→ Ce qui manque au site, et ce qu'archive.org en a\n";
foreach ($byKind as $kind => $counts) {
    printf("   %-6s %4d manquant(s), %4d archivé(s)\n",
        $kind, $counts['manquants'] ?? 0, $counts['archivés'] ?? 0);
}

if ($available === []) {
    echo "\nRien à récupérer.\n";
    exit(0);
}
if ($dryRun) {
    printf("\n[simulation] %d fichier(s) seraient récupérés.\n", count($available));
    exit(0);
}

/* ------------------------------------------------------------ récupération */

echo "\n→ Téléchargement\n";
$uploads = Config::path('data') . '/uploads';
$done = $failed = 0;
$patchCv = $patchEmployer = [];

foreach ($available as $i => [$kind, $id, $relative]) {
    $snapshot = $archive[$relative];
    $extension = strtolower(pathinfo(parse_url($snapshot['url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    $extension = preg_match('/^[a-z0-9]{2,5}$/', $extension) ? $extension : 'bin';

    $target = $uploads . '/' . $kind . '/' . $id . '.' . $extension;
    $already = is_file($target) && filesize($target) > 0;

    if (!$already) {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        // `id_` demande le fichier brut, sans la surcouche d'archive.org.
        $body = fetch(WB . $snapshot['timestamp'] . 'id_/' . $snapshot['url'], $retries);
        printf("   [%3d/%3d] %-6s %-14s %s\n", $i + 1, count($available), $kind, $id, basename($relative));

        if ($body === '') {
            $failed++;
            continue;
        }
        file_put_contents($target, $body);
    }

    // Un fichier non conforme ne doit pas être rattaché à une fiche.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($target) ?: '';
    $expected = $kind === 'cv'
        ? ['application/pdf', 'application/msword',
           'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($mime, $expected, true)) {
        unlink($target);
        $failed++;
        continue;
    }

    $stored = $kind . '/' . $id . '.' . $extension;
    if ($kind === 'logo') {
        $patchEmployer[$id] = $stored;
    } else {
        $patchCv[$id][$kind] = $stored;
    }
    $done++;
    if (!$already) {
        usleep(250000);   // on ne martèle pas archive.org
    }
}

/* ------------------------------------------------------- mise à jour fiches */

foreach ($patchCv as $id => $paths) {
    $cv = CvRepository::find($id);
    if ($cv === null) {
        continue;
    }
    if (isset($paths['photo'])) {
        $cv['photo']['path'] = $paths['photo'];
    }
    if (isset($paths['cv'])) {
        $cv['file']['path'] = $paths['cv'];
        $cv['file']['size'] = (int) @filesize($uploads . '/' . $paths['cv']);
    }
    CvRepository::save($cv);
}
foreach ($patchEmployer as $id => $path) {
    $employer = EmployerRepository::find($id);
    if ($employer !== null) {
        $employer['logo']['path'] = $path;
        EmployerRepository::save($employer);
    }
}

Index::rebuildAll();
Knowledge::rebuild();

printf("\nTerminé : %d fichier(s) récupérés, %d échec(s).\n", $done, $failed);
