<?php
declare(strict_types=1);

namespace LD;

/**
 * Cache HTML des pages publiques (vidé automatiquement à chaque modification dans le back-office).
 */
final class PageCache
{
    public const TOKEN = '__LD_TOKEN__';

    private static function file(string $key): string
    {
        return Store::path('cache/pages/' . hash('sha256', $key) . '.html');
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('performance.page_cache', true) && Http::method() === 'GET';
    }

    public static function get(string $key): ?string
    {
        $f = self::file($key);
        $ttl = (int) Settings::get('performance.cache_ttl', 21600);
        if (is_file($f) && filemtime($f) > time() - $ttl) {
            return (string) file_get_contents($f);
        }
        return null;
    }

    public static function put(string $key, string $html): void
    {
        @file_put_contents(self::file($key), $html, LOCK_EX);
    }

    public static function clear(): void
    {
        foreach (glob(Store::path('cache/pages/*.html')) ?: [] as $f) {
            @unlink($f);
        }
    }

    /** Remplace le marqueur de jeton par un jeton frais au moment de servir la page. */
    public static function hydrate(string $html): string
    {
        return str_replace(self::TOKEN, Guard::token(), $html);
    }
}
