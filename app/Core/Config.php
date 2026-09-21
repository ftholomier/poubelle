<?php
declare(strict_types=1);

namespace App\Core;

/** Configuration applicative + secrets, en lecture seule après amorçage. */
final class Config
{
    private static array $data = [];
    private static array $secrets = [];

    public static function boot(array $config, array $secrets): void
    {
        self::$data = $config;
        self::$secrets = $secrets;

        // Les secrets peuvent écraser quelques valeurs de config.
        if (!empty($secrets['adsense_client'])) {
            self::$data['ads']['client'] = $secrets['adsense_client'];
        }
        if (!empty($secrets['places_id'])) {
            self::$data['reviews']['place_id'] = $secrets['places_id'];
        }
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        return arr_get(self::$data, $path, $default);
    }

    /** Un secret absent renvoie '' : l'appelant doit dégrader proprement. */
    public static function secret(string $key, mixed $default = ''): mixed
    {
        return self::$secrets[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        $value = self::$secrets[$key] ?? '';
        return is_array($value) ? $value !== [] : trim((string) $value) !== '';
    }

    public static function path(string $name): string
    {
        return (string) self::get('paths.' . $name);
    }
}
