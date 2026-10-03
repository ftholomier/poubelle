<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;

/**
 * Sauvegardes ZIP (storage/backups, hors du dossier public) : toutes les données
 * éditoriales (data/), les réglages chiffrés et leur clé, les versions, les boîtes
 * de réception, les dons, la newsletter, les frais d'IA et les textes des fiches audio. Les photos
 * et les voix IA (volumineuses) sont ajoutées le dimanche si l'option est cochée. Les plus anciennes sont supprimées.
 */
final class Backup
{
    public const DIR = STORAGE_PATH . '/backups';

    /** Dossiers et fichiers sauvegardés (relatifs à la racine du projet). */
    private const PATHS = [
        'data',
        'storage/settings.json',
        'storage/secret.key',
        'storage/users.json',
        'storage/versions',
        'storage/inbox',
        'storage/dons',
        'storage/newsletter',
        'storage/votes',
        'storage/counters.json',
        'storage/activity',
        'storage/ia',
        'storage/audio',
    ];

    /** @return array{file:string,size:int,files:int,ms:int}|array{error:string} */
    public static function run(bool $force = false, ?bool $withMedia = null): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return ['error' => 'Extension PHP zip indisponible.'];
        }
        if (!is_dir(self::DIR)) {
            mkdir(self::DIR, 0750, true);
        }
        $withMedia ??= Settings::get('backups.media_weekly', false) && (int) date('N') === 7;
        $t0 = microtime(true);
        $name = 'sauvegarde-' . date('Y-m-d-His') . ($withMedia ? '-photos' : '') . '.zip';
        $file = self::DIR . '/' . $name;
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            return ['error' => 'Impossible de créer l’archive.'];
        }
        $root = APP_ROOT;
        $count = 0;
        $paths = self::PATHS;
        if ($withMedia) {
            $paths[] = 'storage/media';
            $paths[] = 'public/media/audio'; // voix IA des fiches (payées : on les garde)
        }
        foreach ($paths as $rel) {
            $abs = $root . '/' . $rel;
            if (is_file($abs)) {
                $zip->addFile($abs, $rel);
                $count++;
            } elseif (is_dir($abs)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if ($f->isFile() && !str_ends_with($f->getFilename(), '.lock') && !str_ends_with($f->getFilename(), '.tmp')) {
                        $local = $rel . substr($f->getPathname(), strlen($abs));
                        if (str_starts_with($local, 'storage/audio/jobs/')) {
                            continue; // fichiers d'échange temporaires avec Google
                        }
                        $zip->addFile($f->getPathname(), $local);
                        // Les photos sont déjà compressées : stockage sans recompression.
                        if ($withMedia && (str_starts_with($local, 'storage/media') || str_starts_with($local, 'public/media'))) {
                            $zip->setCompressionName($local, \ZipArchive::CM_STORE);
                        }
                        $count++;
                    }
                }
            }
        }
        $zip->setArchiveComment('Sochaux Rétro · ' . date('c') . ' · ' . $count . ' fichiers');
        if (!$zip->close()) {
            @unlink($file);
            return ['error' => 'Écriture de l’archive impossible (espace disque ?).'];
        }
        @chmod($file, 0640);
        self::prune();
        return ['file' => $name, 'size' => (int) filesize($file), 'files' => $count, 'ms' => (int) ((microtime(true) - $t0) * 1000)];
    }

    /** Sauvegardes existantes, plus récentes d'abord. */
    public static function list(): array
    {
        $out = [];
        foreach (glob(self::DIR . '/sauvegarde-*.zip') ?: [] as $f) {
            $out[] = ['file' => basename($f), 'size' => (int) filesize($f), 'at' => date('c', (int) filemtime($f)), 'photos' => str_contains($f, '-photos')];
        }
        usort($out, fn ($a, $b) => strcmp($b['at'], $a['at']));
        return $out;
    }

    public static function path(string $name): ?string
    {
        return preg_match('/^sauvegarde-[\d-]+(-photos)?\.zip$/', $name) && is_file(self::DIR . '/' . $name) ? self::DIR . '/' . $name : null;
    }

    private static function prune(): void
    {
        $keep = max(2, (int) Settings::get('backups.keep', 14));
        $all = self::list();
        $light = array_values(array_filter($all, fn ($b) => !$b['photos']));
        $heavy = array_values(array_filter($all, fn ($b) => $b['photos']));
        foreach (array_slice($light, $keep) as $b) {
            @unlink(self::DIR . '/' . $b['file']);
        }
        foreach (array_slice($heavy, 2) as $b) {
            @unlink(self::DIR . '/' . $b['file']);
        }
    }
}
