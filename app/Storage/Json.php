<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;

/**
 * Lecture/écriture de fichiers JSON.
 * Toute écriture passe par un temporaire puis rename() : jamais d'écriture en place,
 * donc jamais de fichier à moitié écrit servi au visiteur.
 */
final class Json
{
    /** Lecture tolérante : fichier absent ou corrompu -> valeur de repli. */
    public static function read(string $path, array $default = []): array
    {
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Audit::log('storage.corrupt', ['path' => self::relative($path)]);
            return $default;
        }
        return $data;
    }

    /** Écriture atomique. Renvoie false sans lever d'exception si le disque refuse. */
    public static function write(string $path, array $data): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            Audit::log('storage.mkdir_failed', ['path' => self::relative($dir)]);
            return false;
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($json === false) {
            Audit::log('storage.encode_failed', ['path' => self::relative($path)]);
            return false;
        }

        $tmp = $dir . '/.' . basename($path) . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0664);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            Audit::log('storage.rename_failed', ['path' => self::relative($path)]);
            return false;
        }
        return true;
    }

    public static function delete(string $path): bool
    {
        return !is_file($path) || @unlink($path);
    }

    /** Liste les fichiers .json d'un dossier, triés par nom. */
    public static function listFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob(rtrim($dir, '/') . '/*.json') ?: [];
        sort($files, SORT_NATURAL);
        return $files;
    }

    private static function relative(string $path): string
    {
        return str_replace(Config::path('root') . '/', '', $path);
    }
}
