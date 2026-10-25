<?php
declare(strict_types=1);

namespace LD;

/**
 * Paramètres du site (storage/content/settings.json) avec repli sur les valeurs par défaut.
 */
final class Settings
{
    private static ?array $all = null;

    public static function all(): array
    {
        if (self::$all === null) {
            $defaults = Content::defaults('settings');
            self::$all = Content::merge($defaults, Store::read('content/settings.json', []));
        }
        return self::$all;
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        return array_get(self::all(), $path, $default);
    }

    public static function save(array $settings): void
    {
        Store::write('content/settings.json', $settings);
        self::$all = null;
        PageCache::clear();
    }

    public static function reset(): void
    {
        self::$all = null;
    }

    /** Secret applicatif (signatures HMAC, jetons) généré au premier lancement. */
    public static function appSecret(): string
    {
        $file = Store::path('secrets/app.secret');
        if (!is_file($file)) {
            file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
            @chmod($file, 0600);
        }
        return trim((string) file_get_contents($file));
    }
}
