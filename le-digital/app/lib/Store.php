<?php
declare(strict_types=1);

namespace LD;

/**
 * Stockage JSON sans base de données : écritures atomiques + verrous fichiers.
 */
final class Store
{
    private static array $cache = [];

    /** Dossiers créés au premier lancement. */
    private const DIRS = [
        'content', 'data', 'data/voices', 'secrets', 'cache', 'cache/pages', 'cache/rl',
        'cache/ai', 'cache/models', 'logs', 'sessions',
    ];

    public static function boot(): void
    {
        if (is_file(LD_STORAGE . '/.booted')) {
            return;
        }
        foreach (self::DIRS as $d) {
            $p = LD_STORAGE . '/' . $d;
            if (!is_dir($p)) {
                @mkdir($p, 0775, true);
            }
        }
        @file_put_contents(LD_STORAGE . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        // Contenus par défaut (repris de la maquette) copiés si absents.
        foreach (glob(LD_APP . '/defaults/content/*.json') ?: [] as $f) {
            $dest = LD_STORAGE . '/content/' . basename($f);
            if (!is_file($dest)) {
                copy($f, $dest);
            }
        }
        foreach (glob(LD_APP . '/defaults/data/*.json') ?: [] as $f) {
            $dest = LD_STORAGE . '/data/' . basename($f);
            if (!is_file($dest)) {
                copy($f, $dest);
            }
        }
        @touch(LD_STORAGE . '/.booted');
    }

    public static function path(string $rel): string
    {
        $rel = ltrim(str_replace(['..', '\\'], ['', '/'], $rel), '/');
        return LD_STORAGE . '/' . $rel;
    }

    public static function read(string $rel, mixed $default = []): mixed
    {
        if (array_key_exists($rel, self::$cache)) {
            return self::$cache[$rel];
        }
        $file = self::path($rel);
        if (!is_file($file)) {
            return $default;
        }
        $raw = file_get_contents($file);
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data)) {
            return $default;
        }
        return self::$cache[$rel] = $data;
    }

    public static function write(string $rel, mixed $data): void
    {
        $file = self::path($rel);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \RuntimeException('Encodage JSON impossible pour ' . $rel);
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Écriture impossible : ' . $rel);
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException('Écriture impossible : ' . $rel);
        }
        self::$cache[$rel] = $data;
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    /**
     * Lecture-modification-écriture sous verrou exclusif (évite les pertes en cas d'accès concurrents).
     */
    public static function update(string $rel, callable $fn, mixed $default = []): mixed
    {
        $lock = fopen(self::path($rel) . '.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Verrou impossible : ' . $rel);
        }
        try {
            flock($lock, LOCK_EX);
            unset(self::$cache[$rel]);
            $data = self::read($rel, $default);
            $result = $fn($data);
            self::write($rel, $data);
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function delete(string $rel): void
    {
        $file = self::path($rel);
        if (is_file($file)) {
            unlink($file);
        }
        unset(self::$cache[$rel]);
    }

    public static function forget(?string $rel = null): void
    {
        if ($rel === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$rel]);
        }
    }

    public static function appendLog(string $name, array $entry): void
    {
        $entry = ['at' => date('c')] + $entry;
        @file_put_contents(
            self::path('logs/' . $name . '.log'),
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
