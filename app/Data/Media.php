<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/**
 * Médiathèque : métadonnées des fichiers (data/media.json), fichiers originaux
 * dans storage/media/originals (hors /public).
 */
final class Media
{
    public const FILE = DATA_PATH . '/media.json';
    public const ORIGINALS = STORAGE_PATH . '/media/originals';
    private const CACHE = STORAGE_PATH . '/cache/media.php';
    private static ?array $items = null;

    /** @return array<string,array> par chemin relatif */
    public static function all(): array
    {
        if (self::$items === null) {
            $useCache = is_file(self::CACHE) && is_file(self::FILE) && filemtime(self::CACHE) >= filemtime(self::FILE);
            self::$items = $useCache ? (include self::CACHE) : null;
            if (!is_array(self::$items)) {
                self::$items = JsonStore::read(self::FILE, []) ?? [];
                self::cache(self::$items);
            }
        }
        return self::$items;
    }

    public static function get(?string $rel): ?array
    {
        return $rel ? (self::all()[$rel] ?? null) : null;
    }

    public static function file(string $rel): ?string
    {
        $rel = ltrim(str_replace(['..', "\0"], '', $rel), '/');
        $f = self::ORIGINALS . '/' . $rel;
        return is_file($f) ? $f : null;
    }

    /** Légende complète affichable : « Légende – Crédit ». */
    public static function caption(?string $rel, string $fallback = ''): string
    {
        $m = self::get($rel);
        $cap = trim((string) ($m['caption'] ?? '')) ?: $fallback;
        $cred = trim((string) ($m['credit'] ?? ''));
        return $cred !== '' ? ($cap !== '' ? "$cap – $cred" : $cred) : $cap;
    }

    public static function alt(?string $rel, string $fallback = ''): string
    {
        $m = self::get($rel);
        return trim((string) ($m['alt'] ?? '')) ?: (trim((string) ($m['caption'] ?? '')) ?: $fallback);
    }

    public static function put(string $rel, array $meta, ?array $user = null): void
    {
        self::$items = JsonStore::update(self::FILE, function ($all) use ($rel, $meta) {
            $all = $all ?: [];
            $all[$rel] = array_merge($all[$rel] ?? ['file' => $rel], $meta);
            return $all;
        }, []);
        self::cache(self::$items);
        Activity::log($user, 'a modifié le média', ['title' => $rel, 'path' => '']);
    }

    public static function remove(string $rel): void
    {
        self::$items = JsonStore::update(self::FILE, function ($all) use ($rel) {
            unset($all[$rel]);
            return $all;
        }, []);
        self::cache(self::$items);
    }

    private static function cache(array $items): void
    {
        $dir = dirname(self::CACHE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = self::CACHE . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, '<?php return ' . var_export($items, true) . ";\n", LOCK_EX);
        rename($tmp, self::CACHE);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::CACHE, true);
        }
    }
}
