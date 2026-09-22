<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Storage\Json;
use App\Storage\Lock;

/**
 * Limitation de débit par IP, sur fichier.
 * Utilisée sur la connexion, le dépôt de CV, le dépôt d'annonce et l'assistant.
 */
final class RateLimit
{
    private static function path(string $bucket): string
    {
        $dir = Config::path('data') . '/private/rate';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $bucket) . '.json';
    }

    private static function key(string $ip): string
    {
        // L'IP n'est jamais stockée en clair : seule une empreinte sert de clé.
        // La clé de l'installation est créée au premier appel, jamais devinable.
        return substr(hash_hmac('sha256', $ip, Secrets::appKey()), 0, 24);
    }

    /**
     * Consomme une tentative. Renvoie le nombre de secondes d'attente restantes,
     * ou 0 si l'action est autorisée.
     */
    public static function hit(string $bucket, string $ip, int $max, int $window): int
    {
        return (int) Lock::transaction('rate:' . $bucket, static function () use ($bucket, $ip, $max, $window): int {
            $file = self::path($bucket);
            $state = Json::read($file);
            $now = time();
            $key = self::key($ip);

            // Purge des fenêtres expirées : le fichier ne grossit pas indéfiniment.
            foreach ($state as $k => $entry) {
                if (($entry['until'] ?? 0) < $now) {
                    unset($state[$k]);
                }
            }

            $entry = $state[$key] ?? ['count' => 0, 'until' => $now + $window];
            if ($entry['until'] < $now) {
                $entry = ['count' => 0, 'until' => $now + $window];
            }
            $entry['count']++;
            $state[$key] = $entry;
            Json::write($file, $state);

            return $entry['count'] > $max ? max(1, (int) $entry['until'] - $now) : 0;
        });
    }

    /** Remet le compteur à zéro (après une connexion réussie). */
    public static function clear(string $bucket, string $ip): void
    {
        Lock::transaction('rate:' . $bucket, static function () use ($bucket, $ip): void {
            $file = self::path($bucket);
            $state = Json::read($file);
            unset($state[self::key($ip)]);
            Json::write($file, $state);
        });
    }

    /** Nombre de tentatives déjà consommées, sans en ajouter une. */
    public static function count(string $bucket, string $ip): int
    {
        $state = Json::read(self::path($bucket));
        $entry = $state[self::key($ip)] ?? null;
        return $entry !== null && ($entry['until'] ?? 0) >= time() ? (int) $entry['count'] : 0;
    }
}
