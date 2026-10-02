<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cache fichier. Les valeurs sont écrites en PHP (var_export) : OPcache les garde en mémoire.
 * Un numéro de version global invalide d'un coup le cache de pages après toute modification.
 */
final class Cache
{
    private static array $memo = [];
    private static ?int $version = null;

    private static function dir(string $bucket = 'data'): string
    {
        return STORAGE_PATH . '/cache/' . $bucket;
    }

    private static function file(string $key, string $bucket = 'data'): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '_', substr($key, 0, 60));
        return self::dir($bucket) . '/' . $safe . '-' . substr(sha1($key), 0, 12) . '.php';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $f = self::file($key);
        if (!is_file($f)) {
            return $default;
        }
        $data = @include $f;
        if (!is_array($data) || !array_key_exists('v', $data)) {
            return $default;
        }
        if ($data['e'] !== 0 && $data['e'] < time()) {
            @unlink($f);
            return $default;
        }
        return self::$memo[$key] = $data['v'];
    }

    public static function set(string $key, mixed $value, int $ttl = 0): void
    {
        self::$memo[$key] = $value;
        try {
            Fs::writeAtomic(self::file($key), "<?php\nreturn " . var_export(['e' => $ttl > 0 ? time() + $ttl : 0, 'v' => $value], true) . ";\n");
        } catch (\Throwable) {
            // le cache est facultatif
        }
    }

    /** @template T @param callable():T $fn @return T */
    public static function remember(string $key, int $ttl, callable $fn): mixed
    {
        $miss = new \stdClass();
        $v = self::get($key, $miss);
        if ($v !== $miss) {
            return $v;
        }
        $v = $fn();
        self::set($key, $v, $ttl);
        return $v;
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
        @unlink(self::file($key));
    }

    public static function flush(string $bucket = 'data'): int
    {
        self::$memo = [];
        $n = 0;
        foreach (glob(self::dir($bucket) . '/*') ?: [] as $f) {
            if (is_file($f) && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------- version globale

    public static function version(): int
    {
        if (self::$version === null) {
            $f = STORAGE_PATH . '/cache/version.txt';
            self::$version = is_file($f) ? (int) file_get_contents($f) : 1;
        }
        return self::$version;
    }

    /** À appeler après toute modification visible publiquement. */
    public static function bump(): void
    {
        self::$version = self::version() + 1;
        self::$memo = [];
        try {
            Fs::writeAtomic(STORAGE_PATH . '/cache/version.txt', (string) self::$version);
        } catch (\Throwable) {
        }
    }

    // --------------------------------------------------------- cache de pages

    private static function pageFile(string $key): string
    {
        return self::dir('pages') . '/v' . self::version() . '-' . sha1($key) . '.php';
    }

    public static function pageGet(string $key): ?array
    {
        $f = self::pageFile($key);
        if (!is_file($f)) {
            return null;
        }
        $data = @include $f;
        if (!is_array($data) || ($data['e'] ?? 0) < time()) {
            return null;
        }
        return $data['v'];
    }

    public static function pagePut(string $key, array $payload, int $ttl): void
    {
        try {
            Fs::writeAtomic(self::pageFile($key), "<?php\nreturn " . var_export(['e' => time() + $ttl, 'v' => $payload], true) . ";\n");
        } catch (\Throwable) {
        }
    }

    /** Supprime les pages en cache d'anciennes versions ou trop vieilles. */
    public static function prunePages(int $maxAge = 86400): int
    {
        $n = 0;
        $prefix = 'v' . self::version() . '-';
        foreach (glob(self::dir('pages') . '/*.php') ?: [] as $f) {
            if (!str_starts_with(basename($f), $prefix) || filemtime($f) < time() - $maxAge) {
                if (@unlink($f)) {
                    $n++;
                }
            }
        }
        return $n;
    }
}
