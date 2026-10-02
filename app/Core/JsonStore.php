<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Lecture / écriture de fichiers JSON sans base de données.
 *
 * - Écriture atomique : fichier temporaire puis rename(), donc jamais de
 *   fichier à moitié écrit en cas de coupure.
 * - Verrou exclusif (flock) pendant les mises à jour, pour que deux
 *   historiens qui enregistrent en même temps ne s'écrasent pas.
 * - Cache mémoire par requête.
 */
final class JsonStore
{
    /** @var array<string,mixed> */
    private static array $cache = [];

    public static function read(string $path, mixed $default = null): mixed
    {
        if (array_key_exists($path, self::$cache)) {
            return self::$cache[$path];
        }
        if (!is_file($path)) {
            return $default;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Lecture impossible : $path");
        }
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("JSON invalide dans $path : " . json_last_error_msg());
        }
        return self::$cache[$path] = $data;
    }

    public static function write(string $path, mixed $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Dossier impossible à créer : $dir");
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException("Écriture impossible : $tmp");
        }
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Remplacement impossible : $path");
        }
        self::$cache[$path] = $data;
    }

    /**
     * Lecture-modification-écriture sous verrou exclusif.
     *
     * @param callable(mixed):mixed $fn reçoit la valeur actuelle, renvoie la nouvelle
     */
    public static function update(string $path, callable $fn, mixed $default = null): mixed
    {
        $lock = self::lock($path);
        try {
            unset(self::$cache[$path]);
            $new = $fn(self::read($path, $default));
            self::write($path, $new);
            return $new;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function delete(string $path): void
    {
        unset(self::$cache[$path]);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** Ajoute une ligne JSON à un journal (une entrée par ligne). */
    public static function append(string $path, array $entry): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @return list<array> lignes d'un journal JSON, les plus récentes en premier */
    public static function readLines(string $path, int $limit = 0): array
    {
        if (!is_file($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_reverse($lines);
        if ($limit > 0) {
            $lines = array_slice($lines, 0, $limit);
        }
        return array_values(array_filter(array_map(fn ($l) => json_decode($l, true), $lines)));
    }

    /** @return resource */
    private static function lock(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fp = fopen($path . '.lock', 'c');
        if (!$fp || !flock($fp, LOCK_EX)) {
            throw new RuntimeException("Verrou impossible : $path");
        }
        return $fp;
    }

    public static function forget(?string $path = null): void
    {
        if ($path === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$path]);
        }
    }
}
