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
    private const USAGE = STORAGE_PATH . '/cache/media-usage.json';
    /** Formats acceptés à l'envoi depuis le back-office. */
    public const UPLOAD_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    public const UPLOAD_MAX = 25 * 1024 * 1024;
    private static ?array $items = null;
    private static ?array $usage = null;

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

    /** Légende complète affichable : « Légende – Crédit » (légende anglaise si elle existe). */
    public static function caption(?string $rel, string $fallback = ''): string
    {
        $m = self::get($rel);
        $en = \App\Services\I18n::isEn() ? trim((string) ($m['caption_en'] ?? '')) : '';
        $cap = $en ?: (trim((string) ($m['caption'] ?? '')) ?: $fallback);
        $cred = trim((string) ($m['credit'] ?? ''));
        return $cred !== '' ? ($cap !== '' ? "$cap – $cred" : $cred) : $cap;
    }

    public static function alt(?string $rel, string $fallback = ''): string
    {
        $m = self::get($rel);
        if (\App\Services\I18n::isEn() && ($en = trim((string) ($m['alt_en'] ?? $m['caption_en'] ?? ''))) !== '') {
            return $en;
        }
        return trim((string) ($m['alt'] ?? '')) ?: (trim((string) ($m['caption'] ?? '')) ?: $fallback);
    }

    /**
     * Où chaque média est utilisé : identifiants de fiches (entiers) et contenus
     * éditoriaux (« c:frise », « c:rubriques »…). Calculé avec les statistiques.
     * @return array<string,list<int|string>>
     */
    public static function usage(): array
    {
        return self::$usage ??= (JsonStore::read(self::USAGE, []) ?: []);
    }

    /** @param array<string,list<int|string>> $usage */
    public static function saveUsage(array $usage): void
    {
        JsonStore::write(self::USAGE, $usage);
        self::$usage = $usage;
    }

    /** Médias cités dans un contenu (image à la une, galeries, images insérées dans les textes…). */
    public static function refsIn(mixed $data): array
    {
        $json = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!preg_match_all('#(?:contributions/[a-z0-9-]+/|\d{4}/\d{2}/)[^"\s<>()\\\\?]+?\.(?:jpe?g|png|gif|webp|pdf|svg|tiff?|bmp)#i', $json, $m)) {
            return [];
        }
        $all = self::all();
        return array_values(array_unique(array_filter($m[0], fn ($r) => isset($all[$r]))));
    }

    /** Recalcule l'index d'utilisation (fiches, collections, rubriques). */
    public static function rebuildUsage(): array
    {
        Derived::rebuild();
        return self::usage();
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

    /**
     * Met à jour plusieurs médias en une seule écriture.
     * @param array<string,array> $changes chemin => champs
     */
    public static function putMany(array $changes): int
    {
        $n = 0;
        self::$items = JsonStore::update(self::FILE, function ($all) use ($changes, &$n) {
            $all = $all ?: [];
            foreach ($changes as $rel => $meta) {
                if (isset($all[$rel])) {
                    $all[$rel] = array_merge($all[$rel], $meta);
                    $n++;
                }
            }
            return $all;
        }, []);
        self::cache(self::$items);
        return $n;
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
