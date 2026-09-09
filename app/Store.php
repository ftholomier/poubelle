<?php
declare(strict_types=1);

namespace App;

/**
 * Écriture atomique + verrou + versionnage des fichiers JSON de content/.
 *
 * Règle anti-casse : on n'écrit jamais directement sur le fichier servi.
 * Le JSON part dans un .tmp, est relu et validé, puis remplace l'ancien par
 * rename() — opération atomique sur un même volume.
 */
final class Store
{
    public const KEEP_VERSIONS = 30;
    public const EDIT_LOCK_TTL = 600; // 10 minutes

    /** Fichiers régénérables : on n'en garde pas d'historique. */
    private const NO_VERSION = ['reviews.cache.json', 'ai-misses.json'];

    /** Lecture tolérante : jamais d'exception, tableau vide si le fichier est illisible. */
    public static function read(string $relative, array $default = []): array
    {
        $file = self::file($relative);
        if (!is_readable($file)) {
            return $default;
        }
        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            Log::write('content', 'JSON illisible : ' . $relative . ' — ' . json_last_error_msg());
            return $default;
        }
        return $data;
    }

    public static function exists(string $relative): bool
    {
        return is_file(self::file($relative));
    }

    /**
     * Écrit un fichier JSON de content/ : verrou exclusif, version de l'ancien
     * contenu, écriture temporaire validée puis rename atomique.
     */
    public static function write(string $relative, array $data, ?string $by = null): bool
    {
        $file = self::file($relative);
        $dir = \dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            Log::write('content', 'Dossier impossible à créer : ' . $dir);
            return false;
        }

        $data['_schema'] = $data['_schema'] ?? Config::SCHEMA;
        $data['updatedAt'] = (new \DateTimeImmutable())->format(\DATE_ATOM);
        if ($by !== null) {
            $data['updatedBy'] = $by;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            Log::write('content', 'Encodage JSON impossible : ' . $relative);
            return false;
        }

        $lock = self::lockHandle($relative);
        if ($lock === null) {
            return false;
        }

        try {
            if (!\in_array(ltrim($relative, '/'), self::NO_VERSION, true)) {
                self::snapshot($relative, $file);
            }

            $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
            $fh = @fopen($tmp, 'wb');
            if ($fh === false) {
                Log::write('content', 'Fichier temporaire impossible : ' . $tmp);
                return false;
            }
            $ok = fwrite($fh, $json) !== false;
            $ok = $ok && fflush($fh);
            @fsync($fh);
            fclose($fh);

            // Relecture de contrôle avant de publier : un JSON tronqué ne remplace rien.
            if (!$ok || !\is_array(json_decode((string) file_get_contents($tmp), true))) {
                @unlink($tmp);
                Log::write('content', 'Écriture rejetée (JSON de contrôle invalide) : ' . $relative);
                return false;
            }

            @chmod($tmp, 0664);
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
                Log::write('content', 'Rename impossible : ' . $relative);
                return false;
            }
            if (\function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Fusion superficielle puis écriture. */
    public static function patch(string $relative, array $patch, ?string $by = null): bool
    {
        return self::write($relative, array_replace(self::read($relative), $patch), $by);
    }

    // ---------------------------------------------------------------- versions

    public static function versions(string $relative): array
    {
        $dir = self::versionDir($relative);
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.json') ?: [];
        rsort($files, SORT_STRING);
        return array_map(static fn (string $f): array => [
            'id' => basename($f, '.json'),
            'at' => basename($f, '.json'),
            'size' => filesize($f) ?: 0,
        ], $files);
    }

    public static function restore(string $relative, string $versionId, ?string $by = null): bool
    {
        $versionId = preg_replace('/[^0-9A-Za-z_\-]/', '', $versionId) ?? '';
        $src = self::versionDir($relative) . '/' . $versionId . '.json';
        if ($versionId === '' || !is_readable($src)) {
            return false;
        }
        $data = json_decode((string) file_get_contents($src), true);
        if (!\is_array($data)) {
            return false;
        }
        return self::write($relative, $data, $by);
    }

    private static function snapshot(string $relative, string $file): void
    {
        if (!is_readable($file)) {
            return;
        }
        $dir = self::versionDir($relative);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $stamp = (new \DateTimeImmutable())->format('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        @copy($file, $dir . '/' . $stamp . '.json');

        $files = glob($dir . '/*.json') ?: [];
        if (\count($files) > self::KEEP_VERSIONS) {
            sort($files, SORT_STRING);
            foreach (\array_slice($files, 0, \count($files) - self::KEEP_VERSIONS) as $old) {
                @unlink($old);
            }
        }
    }

    // ------------------------------------------------------------------ verrou

    /** Verrou technique d'écriture (flock exclusif sur un fichier jumeau). */
    private static function lockHandle(string $relative)
    {
        $lockFile = Config::storagePath('locks/' . self::slug($relative) . '.lock');
        if (!is_dir(\dirname($lockFile))) {
            @mkdir(\dirname($lockFile), 0775, true);
        }
        $fh = @fopen($lockFile, 'c');
        if ($fh === false) {
            Log::write('content', 'Verrou impossible pour ' . $relative);
            return null;
        }
        $waited = 0;
        while (!flock($fh, LOCK_EX | LOCK_NB)) {
            if ($waited >= 3_000_000) { // 3 s
                fclose($fh);
                Log::write('content', 'Verrou non obtenu (timeout) pour ' . $relative);
                return null;
            }
            usleep(50_000);
            $waited += 50_000;
        }
        return $fh;
    }

    /**
     * Verrou éditorial affiché au back-office : « vous éditez cette page ».
     * Retourne l'email du propriétaire courant, ou null si le verrou est libre.
     */
    public static function editLockOwner(string $key): ?string
    {
        $file = self::editLockFile($key);
        if (!is_readable($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!\is_array($data) || (int) ($data['expires'] ?? 0) < time()) {
            return null;
        }
        return (string) ($data['owner'] ?? '') ?: null;
    }

    /** Prend ou renouvelle le verrou éditorial. false si quelqu'un d'autre le détient. */
    public static function acquireEditLock(string $key, string $owner): bool
    {
        $current = self::editLockOwner($key);
        if ($current !== null && $current !== $owner) {
            return false;
        }
        $file = self::editLockFile($key);
        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0775, true);
        }
        @file_put_contents($file, json_encode([
            'owner' => $owner,
            'expires' => time() + self::EDIT_LOCK_TTL,
        ]), LOCK_EX);
        return true;
    }

    public static function releaseEditLock(string $key, string $owner): void
    {
        if (self::editLockOwner($key) === $owner) {
            @unlink(self::editLockFile($key));
        }
    }

    private static function editLockFile(string $key): string
    {
        return Config::storagePath('locks/edit-' . self::slug($key) . '.lock');
    }

    // ------------------------------------------------------------------ divers

    /** Lecture d'un JSON technique de storage/ (index, caches machine). */
    public static function readStorage(string $relative, array $default = []): array
    {
        $file = Config::storagePath(self::safeRelative($relative));
        if (!is_readable($file)) {
            return $default;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return \is_array($data) ? $data : $default;
    }

    /**
     * Écriture atomique d'un JSON technique de storage/ : même sécurité
     * (fichier temporaire, relecture, rename) mais sans historique.
     */
    public static function writeStorage(string $relative, array $data): bool
    {
        $file = Config::storagePath(self::safeRelative($relative));
        $dir = \dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }
        if (!\is_array(json_decode((string) file_get_contents($tmp), true))) {
            @unlink($tmp);
            Log::write('content', 'Écriture storage rejetée : ' . $relative);
            return false;
        }
        return @rename($tmp, $file);
    }

    private static function file(string $relative): string
    {
        return Config::contentPath(self::safeRelative($relative));
    }

    private static function versionDir(string $relative): string
    {
        return Config::contentPath('_versions/' . self::slug(self::safeRelative($relative)));
    }

    private static function safeRelative(string $relative): string
    {
        $relative = str_replace('\\', '/', trim($relative, '/'));
        $parts = [];
        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = preg_replace('/[^0-9A-Za-z._\-]/', '', $part) ?? '';
        }
        return implode('/', $parts);
    }

    private static function slug(string $relative): string
    {
        return str_replace(['/', '.json'], ['__', ''], self::safeRelative($relative));
    }
}
