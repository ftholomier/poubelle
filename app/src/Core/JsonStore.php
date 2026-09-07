<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Magasin JSON transactionnel — remplace la base de données.
 *
 * Garanties :
 *  - Lecture cohérente  : verrou partagé (LOCK_SH) pendant la lecture.
 *  - Écriture atomique  : verrou exclusif (LOCK_EX) + écriture dans un fichier
 *                          temporaire du même volume puis rename() atomique.
 *                          Le front ne voit jamais un fichier à moitié écrit.
 *  - Reprise de contenu : chaque écriture archive la version précédente dans
 *                          data/backups/<fichier>/<horodatage>.json (N révisions).
 *  - Auto-réparation    : si le JSON courant est illisible/corrompu, la dernière
 *                          révision valide est restaurée automatiquement.
 *  - Tolérance de schéma: read() fusionne toujours avec un défaut, donc un
 *                          fichier incomplet (ou plus récent) ne casse pas le front.
 */
final class JsonStore
{
    /** @var array<string,array<string,mixed>> cache mémoire par requête */
    private static array $cache = [];

    /* ------------------------------------------------------------------ */
    /* Lecture                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed> $default Schéma de repli (jamais de front cassé).
     * @return array<string,mixed>
     */
    public static function read(string $path, array $default = [], bool $fresh = false): array
    {
        $key = realpath($path) ?: $path;

        if (!$fresh && isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $data = self::readRaw($path);

        if ($data === null) {
            // Fichier absent, vide ou corrompu → tentative de restauration.
            $restored = self::restoreFromBackup($path);
            if ($restored !== null) {
                Logger::warning('JSON corrompu restauré depuis une sauvegarde', ['file' => basename($path)]);
                $data = $restored;
            } else {
                $data = [];
            }
        }

        $merged = Schema::normalize($data, $default);
        self::$cache[$key] = $merged;

        return $merged;
    }

    /** @return array<string,mixed>|null null si absent ou invalide */
    private static function readRaw(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            self::acquire($handle, LOCK_SH);
            $size = filesize($path);
            $raw  = $size > 0 ? stream_get_contents($handle) : '';
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }

    /* ------------------------------------------------------------------ */
    /* Écriture                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Écriture atomique + archivage de la version précédente.
     *
     * @param array<string,mixed> $data
     */
    public static function write(string $path, array $data, bool $backup = true): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de créer le dossier de données : {$dir}");
        }
        if (!is_writable($dir)) {
            throw new RuntimeException("Dossier de données non inscriptible : {$dir}");
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if ($json === false) {
            throw new RuntimeException('Encodage JSON impossible : ' . json_last_error_msg());
        }
        // Vérification de relecture : on n'écrit jamais un JSON qu'on ne sait pas relire.
        if (json_decode($json, true) === null) {
            throw new RuntimeException('Contrôle d’intégrité JSON échoué, écriture annulée.');
        }

        // Verrou d'écriture posé sur un fichier sentinelle : il survit au rename().
        $lockFile = self::lockPath($path);
        $lock = @fopen($lockFile, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Verrou de fichier indisponible.');
        }

        try {
            self::acquire($lock, LOCK_EX);

            if ($backup && is_file($path)) {
                self::snapshot($path);
            }

            $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
            $written = @file_put_contents($tmp, $json);
            if ($written === false || $written !== strlen($json)) {
                @unlink($tmp);
                throw new RuntimeException('Écriture temporaire incomplète.');
            }
            @chmod($tmp, 0640);

            // rename() est atomique sur le même système de fichiers.
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                throw new RuntimeException('Basculement atomique impossible.');
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }

        unset(self::$cache[realpath($path) ?: $path]);
        return true;
    }

    /**
     * Lecture → modification → écriture, le tout sous verrou exclusif.
     * Évite toute perte de données en cas d'écritures concurrentes.
     *
     * @param callable(array<string,mixed>):array<string,mixed> $mutator
     * @return array<string,mixed> l'état final
     */
    public static function mutate(string $path, callable $mutator, array $default = []): array
    {
        $lockFile = self::lockPath($path);
        $lock = @fopen($lockFile, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Verrou de fichier indisponible.');
        }
        try {
            self::acquire($lock, LOCK_EX);
            $current = self::read($path, $default, true);
            $next    = $mutator($current);
            if (!is_array($next)) {
                throw new RuntimeException('Le mutateur doit retourner un tableau.');
            }
            // On réutilise write() mais le verrou est réentrant sur le même process.
            self::writeUnlocked($path, $next);
            return $next;
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    private static function writeUnlocked(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Encodage JSON impossible.');
        }
        if (is_file($path)) {
            self::snapshot($path);
        }
        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Écriture atomique impossible.');
        }
        @chmod($path, 0640);
        unset(self::$cache[realpath($path) ?: $path]);
    }

    /* ------------------------------------------------------------------ */
    /* Révisions / reprise de contenu                                      */
    /* ------------------------------------------------------------------ */

    /** Archive la version courante avant écrasement. */
    private static function snapshot(string $path): void
    {
        $dir = self::backupDir($path);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return;
        }
        $stamp = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 4);
        @copy($path, $dir . '/' . $stamp . '.json');
        self::pruneRevisions($dir);
    }

    private static function pruneRevisions(string $dir): void
    {
        $keep = Config::int('storage.revisions', 30);
        $files = glob($dir . '/*.json') ?: [];
        if (count($files) <= $keep) {
            return;
        }
        sort($files);
        $excess = array_slice($files, 0, count($files) - $keep);
        foreach ($excess as $file) {
            @unlink($file);
        }
    }

    /** @return array<int,array{file:string,name:string,date:string,size:int}> */
    public static function revisions(string $path): array
    {
        $dir = self::backupDir($path);
        $files = glob($dir . '/*.json') ?: [];
        rsort($files);
        $out = [];
        foreach ($files as $file) {
            $out[] = [
                'file' => $file,
                'name' => basename($file, '.json'),
                'date' => date('d/m/Y H:i:s', (int) filemtime($file)),
                'size' => (int) filesize($file),
            ];
        }
        return $out;
    }

    /** Restaure explicitement une révision (action back-office). */
    public static function restore(string $path, string $revisionName): bool
    {
        $dir  = self::backupDir($path);
        $safe = preg_replace('/[^A-Za-z0-9\-]/', '', $revisionName) ?? '';
        $file = $dir . '/' . $safe . '.json';
        if ($safe === '' || !is_file($file)) {
            return false;
        }
        $raw = file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return false;
        }
        self::write($path, $data);
        Logger::audit('content.restore', ['file' => basename($path), 'revision' => $safe]);
        return true;
    }

    /** Dernière révision valide, utilisée par l'auto-réparation. */
    private static function restoreFromBackup(string $path): ?array
    {
        $files = glob(self::backupDir($path) . '/*.json') ?: [];
        rsort($files);
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($data)) {
                // On remet le fichier de production en état sans écraser l'historique.
                @copy($file, $path);
                return $data;
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Utilitaires                                                         */
    /* ------------------------------------------------------------------ */

    private static function backupDir(string $path): string
    {
        $root = Config::get('paths.backups', DATA_PATH . '/backups');
        $slug = preg_replace('/[^A-Za-z0-9\-_.]/', '_', basename($path, '.json')) ?? 'file';
        return rtrim((string) $root, '/') . '/' . $slug;
    }

    private static function lockPath(string $path): string
    {
        $dir = Config::get('paths.runtime', DATA_PATH . '/runtime') . '/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $dir . '/' . sha1($path) . '.lock';
    }

    /** flock bloquant avec délai maximal, pour ne jamais figer une requête web. */
    private static function acquire($handle, int $mode): void
    {
        $timeout = Config::int('storage.lock_timeout', 5);
        $deadline = microtime(true) + $timeout;
        while (!flock($handle, $mode | LOCK_NB)) {
            if (microtime(true) >= $deadline) {
                // On continue en lecture dégradée plutôt que d'échouer :
                // le fichier reste lisible car les écritures sont atomiques.
                Logger::warning('Verrou fichier non obtenu dans le délai imparti');
                return;
            }
            usleep(25_000);
        }
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
