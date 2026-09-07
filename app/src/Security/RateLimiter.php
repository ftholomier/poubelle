<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\JsonStore;

/**
 * Limitation de débit sur fichier JSON (anti brute-force / anti-spam / quota IA).
 */
final class RateLimiter
{
    public static function hit(string $bucket, string $identifier, int $max, int $window): bool
    {
        $file = self::file($bucket);
        $now  = time();
        $key  = hash('sha256', $identifier);
        $allowed = true;

        JsonStore::mutate($file, static function (array $data) use ($key, $now, $max, $window, &$allowed): array {
            $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];

            // Purge des fenêtres expirées (empêche le fichier de grossir).
            foreach ($entries as $k => $entry) {
                if (!is_array($entry) || ($entry['expires'] ?? 0) < $now) {
                    unset($entries[$k]);
                }
            }

            $entry = $entries[$key] ?? ['count' => 0, 'expires' => $now + $window];
            if (($entry['expires'] ?? 0) < $now) {
                $entry = ['count' => 0, 'expires' => $now + $window];
            }
            $entry['count'] = (int) $entry['count'] + 1;
            $allowed = $entry['count'] <= $max;
            $entries[$key] = $entry;

            return ['entries' => $entries];
        }, ['entries' => []]);

        return $allowed;
    }

    public static function remaining(string $bucket, string $identifier, int $max): int
    {
        $data = JsonStore::read(self::file($bucket), ['entries' => []], true);
        $entry = $data['entries'][hash('sha256', $identifier)] ?? null;
        if (!is_array($entry) || ($entry['expires'] ?? 0) < time()) {
            return $max;
        }
        return max(0, $max - (int) $entry['count']);
    }

    public static function clear(string $bucket, string $identifier): void
    {
        JsonStore::mutate(self::file($bucket), static function (array $data) use ($identifier): array {
            $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
            unset($entries[hash('sha256', $identifier)]);
            return ['entries' => $entries];
        }, ['entries' => []]);
    }

    private static function file(string $bucket): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '', $bucket) ?: 'default';
        return Config::get('paths.runtime', DATA_PATH . '/runtime') . '/rate-' . $safe . '.json';
    }
}
