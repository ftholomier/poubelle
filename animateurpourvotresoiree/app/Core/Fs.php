<?php
declare(strict_types=1);

namespace App\Core;

/** Outils fichiers : écriture atomique, JSON, verrous. */
final class Fs
{
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;

    public static function ensureDir(string $dir, int $mode = 0755): void
    {
        if (!is_dir($dir) && !@mkdir($dir, $mode, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le dossier ' . $dir);
        }
    }

    /** Écrit via un fichier temporaire puis rename() : un lecteur ne voit jamais un fichier à moitié écrit. */
    public static function writeAtomic(string $path, string $content, ?int $mode = null): void
    {
        self::ensureDir(dirname($path));
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Écriture impossible : ' . $path);
        }
        if ($mode !== null) {
            @chmod($tmp, $mode);
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Renommage impossible : ' . $path);
        }
        if (function_exists('opcache_invalidate') && str_ends_with($path, '.php')) {
            @opcache_invalidate($path, true);
        }
    }

    public static function readJson(string $path, mixed $default = null): mixed
    {
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        return $data === null && json_last_error() !== JSON_ERROR_NONE ? $default : $data;
    }

    public static function writeJson(string $path, mixed $data, bool $pretty = false): void
    {
        $json = json_encode($data, self::JSON_FLAGS | ($pretty ? JSON_PRETTY_PRINT : 0));
        if ($json === false) {
            throw new \RuntimeException('Encodage JSON impossible pour ' . $path . ' : ' . json_last_error_msg());
        }
        self::writeAtomic($path, $json);
    }

    /** Écrit un tableau sous forme de fichier PHP (mis en cache par OPcache, lecture quasi gratuite). */
    public static function writePhpArray(string $path, array $data): void
    {
        self::writeAtomic($path, "<?php\nreturn " . var_export($data, true) . ";\n");
    }

    public static function readPhpArray(string $path, array $default = []): array
    {
        if (!is_file($path)) {
            return $default;
        }
        $data = include $path;
        return is_array($data) ? $data : $default;
    }

    /**
     * Exécute $fn sous verrou exclusif (flock) sur un fichier de verrou.
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function withLock(string $lockFile, callable $fn, int $timeoutSeconds = 15): mixed
    {
        self::ensureDir(dirname($lockFile));
        $fh = @fopen($lockFile, 'c');
        if ($fh === false) {
            throw new \RuntimeException('Verrou impossible : ' . $lockFile);
        }
        $start = microtime(true);
        while (!flock($fh, LOCK_EX | LOCK_NB)) {
            if (microtime(true) - $start > $timeoutSeconds) {
                fclose($fh);
                throw new \RuntimeException('Délai dépassé pour le verrou ' . basename($lockFile));
            }
            usleep(20000);
        }
        try {
            return $fn();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public static function rmrf(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($path);
    }

    public static function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $size += $f->getSize();
        }
        return $size;
    }

    public static function humanSize(int|float $bytes): string
    {
        $u = ['o', 'Ko', 'Mo', 'Go', 'To'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return number_format($bytes, $i ? 1 : 0, ',', ' ') . ' ' . $u[$i];
    }
}
