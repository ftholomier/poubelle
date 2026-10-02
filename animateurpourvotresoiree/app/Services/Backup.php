<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Fs;
use App\Core\Logger;

/**
 * Sauvegardes ZIP des données (storage/data), en option des photos et de la configuration.
 * Les archives restent hors du dossier public (storage/backups) et se téléchargent depuis le back-office.
 */
final class Backup
{
    public const DIR = STORAGE_PATH . '/backups';

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /** Crée une archive et renvoie son nom de fichier. */
    public static function create(bool $withMedia = false, bool $withEnv = false, string $label = 'manuelle'): string
    {
        if (!self::available()) {
            throw new \RuntimeException('L\'extension PHP « zip » est nécessaire aux sauvegardes.');
        }
        @set_time_limit(600);
        Fs::ensureDir(self::DIR, 0700);
        $name = 'apvs-' . date('Ymd-His') . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($label)) . '.zip';
        $path = self::DIR . '/' . $name;
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer l\'archive.');
        }
        $n = self::addDir($zip, STORAGE_PATH . '/data', 'data');
        if ($withMedia && is_dir(PUBLIC_PATH . '/media')) {
            $n += self::addDir($zip, PUBLIC_PATH . '/media', 'media');
        }
        if ($withEnv && is_file(CONFIG_PATH . '/.env')) {
            $zip->addFile(CONFIG_PATH . '/.env', 'config/.env');
            $n++;
        }
        $zip->addFromString('backup.json', (string) json_encode([
            'created_at' => date('c'),
            'version' => APP_VERSION,
            'files' => $n,
            'media' => $withMedia,
            'env' => $withEnv,
            'label' => $label,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();
        @chmod($path, 0600);
        Logger::audit('Sauvegarde créée', ['fichier' => $name, 'fichiers' => $n]);
        return $name;
    }

    private static function addDir(\ZipArchive $zip, string $dir, string $prefix): int
    {
        $n = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || str_ends_with($file->getFilename(), '.lock') || str_ends_with($file->getFilename(), '.tmp')) {
                continue;
            }
            $rel = $prefix . '/' . ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($dir))), '/');
            $zip->addFile($file->getPathname(), $rel);
            $n++;
        }
        return $n;
    }

    /** @return array<int,array{name:string,size:int,date:string,meta:array}> du plus récent au plus ancien */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::DIR . '/*.zip') ?: [] as $f) {
            $meta = [];
            if (self::available()) {
                $zip = new \ZipArchive();
                if ($zip->open($f) === true) {
                    $meta = json_decode((string) $zip->getFromName('backup.json'), true) ?: [];
                    $zip->close();
                }
            }
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'date' => date('c', (int) filemtime($f)), 'meta' => $meta];
        }
        usort($out, static fn ($a, $b) => strcmp($b['date'], $a['date']));
        return $out;
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_\-.]+\.zip$/', $name)) {
            return null;
        }
        $f = self::DIR . '/' . $name;
        return is_file($f) ? $f : null;
    }

    public static function delete(string $name): bool
    {
        $f = self::path($name);
        return $f !== null && @unlink($f);
    }

    /** Ne garde que les $keep sauvegardes automatiques les plus récentes. */
    public static function prune(int $keep): int
    {
        $auto = array_values(array_filter(self::all(), static fn ($b) => str_contains($b['name'], '-auto')));
        $n = 0;
        foreach (array_slice($auto, max(1, $keep)) as $b) {
            if (self::delete($b['name'])) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Restaure les données d'une archive : l'état actuel est d'abord sauvegardé,
     * puis le dossier storage/data est remplacé en une opération (renommage).
     */
    public static function restore(string $name): array
    {
        $f = self::path($name);
        if ($f === null || !self::available()) {
            throw new \RuntimeException('Sauvegarde introuvable.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($f) !== true) {
            throw new \RuntimeException('Archive illisible.');
        }
        $tmp = STORAGE_PATH . '/tmp/restore-' . bin2hex(random_bytes(4));
        Fs::ensureDir($tmp);
        $files = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            // protection contre les chemins malveillants (../, chemins absolus)
            if (!str_starts_with($entry, 'data/') || str_contains($entry, '..') || str_contains($entry, "\0") || str_ends_with($entry, '/')) {
                continue;
            }
            $dest = $tmp . '/' . $entry;
            Fs::ensureDir(dirname($dest));
            $stream = $zip->getStream($entry);
            if ($stream === false) {
                continue;
            }
            $out = fopen($dest, 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);
            $files++;
        }
        $zip->close();
        if ($files === 0 || !is_dir($tmp . '/data')) {
            Fs::rmrf($tmp);
            throw new \RuntimeException('Archive vide ou invalide.');
        }
        $safety = self::create(false, false, 'avant-restauration');
        $old = STORAGE_PATH . '/data.old-' . date('Ymd-His');
        if (!@rename(STORAGE_PATH . '/data', $old) || !@rename($tmp . '/data', STORAGE_PATH . '/data')) {
            if (!is_dir(STORAGE_PATH . '/data') && is_dir($old)) {
                @rename($old, STORAGE_PATH . '/data');
            }
            Fs::rmrf($tmp);
            throw new \RuntimeException('Échec du remplacement des données.');
        }
        Fs::rmrf($tmp);
        Fs::rmrf($old);
        Cache::flush();
        Cache::flush('pages');
        Cache::bump();
        Store::reset();
        Settings::reset();
        Logger::audit('Sauvegarde restaurée', ['fichier' => $name, 'fichiers' => $files, 'sauvegarde_securite' => $safety]);
        return ['files' => $files, 'safety' => $safety];
    }
}
