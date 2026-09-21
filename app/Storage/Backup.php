<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;

/**
 * Snapshot zip horodaté avant chaque publication, restaurable depuis le back-office.
 * Les fichiers déposés (CV, photos) ne sont pas embarqués : seuls les JSON le sont.
 */
final class Backup
{
    private static function dir(): string
    {
        $dir = Config::path('data') . '/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Crée un snapshot. Renvoie son nom de fichier, ou null si ZipArchive manque. */
    public static function snapshot(string $reason = 'manual', ?int $userId = null): ?string
    {
        if (!class_exists(\ZipArchive::class)) {
            Audit::log('backup.skipped', ['reason' => 'ext-zip absente'], $userId);
            return null;
        }

        $name = sprintf('snapshot-%s-%s.zip', date('Ymd-His'), slugify($reason, 24));
        $target = self::dir() . '/' . $name;

        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Audit::log('backup.failed', ['file' => $name], $userId);
            return null;
        }

        $base = Config::path('data');
        foreach (['content', 'jobs', 'cv', 'employers', 'users', 'index'] as $folder) {
            self::addFolder($zip, $base . '/' . $folder, $folder);
        }
        $zip->addFromString('manifest.json', (string) json_encode([
            'created_at' => date('c'),
            'reason'     => $reason,
            'user'       => $userId,
            'schema'     => Schema::version(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        Audit::log('backup.created', ['file' => $name, 'reason' => $reason], $userId);
        self::prune();

        return $name;
    }

    /** @return array<int, array{name:string,size:int,created_at:string,reason:string}> */
    public static function listAll(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/snapshot-*.zip') ?: [] as $file) {
            $out[] = [
                'name'       => basename($file),
                'size'       => (int) filesize($file),
                'created_at' => date('c', (int) filemtime($file)),
                'reason'     => self::reasonOf(basename($file)),
            ];
        }
        usort($out, static fn(array $a, array $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    /**
     * Restaure un snapshot. L'état courant est lui-même sauvegardé avant écrasement,
     * pour qu'une restauration reste réversible.
     */
    public static function restore(string $name, ?int $userId = null): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return false;
        }
        $file = self::dir() . '/' . basename($name);
        if (!is_file($file)) {
            return false;
        }

        self::snapshot('avant-restauration', $userId);

        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            return false;
        }
        $ok = $zip->extractTo(Config::path('data'));
        $zip->close();

        if ($ok) {
            Index::rebuildAll();
            Audit::log('backup.restored', ['file' => basename($name)], $userId);
        }
        return $ok;
    }

    public static function delete(string $name): bool
    {
        $file = self::dir() . '/' . basename($name);
        return !is_file($file) || @unlink($file);
    }

    private static function addFolder(\ZipArchive $zip, string $absolute, string $relative): void
    {
        if (!is_dir($absolute)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $entry) {
            /** @var \SplFileInfo $entry */
            if (!$entry->isFile() || $entry->getExtension() !== 'json') {
                continue;
            }
            $zip->addFile(
                $entry->getPathname(),
                $relative . '/' . ltrim(str_replace($absolute, '', $entry->getPathname()), '/'),
            );
        }
    }

    /** Ne garde que les N snapshots les plus récents. */
    private static function prune(): void
    {
        $keep = (int) Config::get('storage.keep_backups', 40);
        $files = self::listAll();
        foreach (array_slice($files, $keep) as $old) {
            self::delete($old['name']);
        }
    }

    private static function reasonOf(string $name): string
    {
        return preg_match('/^snapshot-\d{8}-\d{6}-(.+)\.zip$/', $name, $m) ? str_replace('-', ' ', $m[1]) : '—';
    }
}
