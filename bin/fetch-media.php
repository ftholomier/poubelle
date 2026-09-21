#!/usr/bin/env php
<?php
/**
 * Récupère les fichiers référencés par les JSON (CV, photos, logos) depuis
 * l'ancien site et les range dans data/uploads/.
 *
 * S'exécute depuis n'importe où : machine locale, serveur, ou cette session
 * une fois le domaine autorisé en sortie réseau.
 *
 *   php bin/fetch-media.php                 # tout ce qui manque
 *   php bin/fetch-media.php --only=cv       # cv | photo | logo
 *   php bin/fetch-media.php --dry-run
 *   php bin/fetch-media.php --base=http://www.intermittent.fr
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Domain\CvRepository;
use App\Domain\EmployerRepository;
use App\Storage\Index;

$options = getopt('', ['only::', 'dry-run', 'base::', 'sleep::']);
$only    = $options['only'] ?? '';
$dryRun  = isset($options['dry-run']);
$base    = rtrim((string) ($options['base'] ?? 'http://www.intermittent.fr'), '/');
$pause   = (int) ($options['sleep'] ?? 150);   // ms entre deux requêtes

$uploads = Config::path('data') . '/uploads';
$targets = [];

/** Empile un téléchargement si le fichier local manque encore. */
$queue = static function (string $kind, string $url, string $localPath) use (&$targets, $uploads): void {
    if ($url === '' || $localPath === '' || is_file($uploads . '/' . $localPath)) {
        return;
    }
    $targets[] = ['kind' => $kind, 'url' => $url, 'path' => $localPath];
};

foreach (CvRepository::all() as $cv) {
    if (($cv['file']['path'] ?? '') === '' && ($cv['file']['legacy_url'] ?? '') !== '') {
        $queue('cv', (string) $cv['file']['legacy_url'], 'cv/' . $cv['id'] . '.' . pathinfo(
            parse_url((string) $cv['file']['legacy_url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'pdf');
    }
    if (($cv['photo']['path'] ?? '') === '' && ($cv['photo']['legacy_url'] ?? '') !== '') {
        $queue('photo', (string) $cv['photo']['legacy_url'], 'photo/' . $cv['id'] . '.' . (pathinfo(
            parse_url((string) $cv['photo']['legacy_url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg'));
    }
}
foreach (EmployerRepository::all() as $employer) {
    if (($employer['logo']['path'] ?? '') === '' && ($employer['logo']['legacy_url'] ?? '') !== '') {
        $queue('logo', (string) $employer['logo']['legacy_url'], 'logo/' . $employer['id'] . '.' . (pathinfo(
            parse_url((string) $employer['logo']['legacy_url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'png'));
    }
}

if ($only !== '') {
    $targets = array_values(array_filter($targets, static fn(array $t) => $t['kind'] === $only));
}

printf("%d fichier(s) à récupérer depuis %s%s\n", count($targets), $base, $dryRun ? '  [simulation]' : '');
if ($targets === []) {
    exit(0);
}

$done = $failed = 0;
foreach ($targets as $i => $target) {
    // Les JSON portent l'URL absolue de l'ancien site : on la rebase sur --base.
    $path = parse_url($target['url'], PHP_URL_PATH) ?: '';
    $url  = $base . $path;
    $dest = $uploads . '/' . $target['path'];

    printf("[%3d/%3d] %-6s %s\n", $i + 1, count($targets), $target['kind'], basename($path));
    if ($dryRun) {
        continue;
    }

    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0775, true);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => false,   // le certificat de l'ancien site est cassé
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'intermittent.fr migration/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && is_string($body) && $body !== '') {
        file_put_contents($dest, $body);
        $done++;
    } else {
        fwrite(STDERR, sprintf("   échec (HTTP %d) %s\n", $code, $url));
        $failed++;
    }
    usleep($pause * 1000);
}

printf("\nTerminé : %d récupéré(s), %d échec(s).\n", $done, $failed);
if ($done > 0) {
    Index::rebuildAll();
    echo "Index reconstruit. Lancez `php bin/link-media.php` pour rattacher les fichiers aux fiches.\n";
}
