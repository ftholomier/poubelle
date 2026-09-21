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
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        // Deux valeurs de configuration sont en réalité des identifiants
        // modifiables depuis le back-office : on les lit au moment de l'appel.
        if ($path === 'ads.client') {
            return self::secret('adsense_client', arr_get(self::$data, $path, $default));
        }
        if ($path === 'reviews.place_id') {
            return self::secret('places_id', arr_get(self::$data, $path, $default));
        }
        return arr_get(self::$data, $path, $default);
    }

    /**
     * Un secret absent renvoie '' : l'appelant doit dégrader proprement.
     *
     * Deux sources : le magasin piloté depuis le back-office l'emporte sur
     * config/secrets.php, ce qui permet de démarrer avec un fichier posé à la
     * main puis de basculer sur l'interface sans rien casser.
     */
    public static function secret(string $key, mixed $default = ''): mixed
    {
        if (\App\Services\Secrets::has($key)) {
            return \App\Services\Secrets::get($key, $default);
        }
        return self::$secrets[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        if (\App\Services\Secrets::has($key)) {
            return true;
        }
        $value = self::$secrets[$key] ?? '';
        return is_array($value) ? $value !== [] : trim((string) $value) !== '';
    }

    public static function path(string $name): string
    {
        return (string) self::get('paths.' . $name);
    }
}
