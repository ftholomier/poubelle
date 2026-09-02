<?php

declare(strict_types=1);

namespace App;

/**
 * Réglages globaux, surchargeables par un config.local.php non versionné.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $values = [
        // Nombre maximum de points renvoyés par l'API pour une forme.
        'shape.max_points'   => 40000,
        // Nombre de points par défaut si la section n'en précise pas.
        'shape.default_points' => 12000,
        // Cache disque des nuages de points (0 = désactivé, utile en développement).
        'cache.enabled'      => true,
        // Durée de vie du cache HTTP envoyé aux navigateurs, en secondes.
        'cache.http_ttl'     => 3600,
        // Affichage des erreurs détaillées dans les réponses API.
        'debug'              => false,
    ];

    public static function boot(): void
    {
        $local = APP_ROOT . '/config.local.php';
        if (is_file($local)) {
            $overrides = require $local;
            if (is_array($overrides)) {
                self::$values = $overrides + self::$values;
            }
        }
        if (getenv('APP_DEBUG') === '1') {
            self::$values['debug'] = true;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$values[$key] = $value;
    }
}
