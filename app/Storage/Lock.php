<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;

/**
 * Verrou d'édition coopératif : un seul rédacteur par contenu.
 * flock() pour la section critique, fichier d'état pour l'affichage du verrou
 * dans le back-office (« Verrou : vous · 12 min »).
 */
final class Lock
{
    private static function dir(): string
    {
        $dir = Config::path('data') . '/private/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function file(string $resource): string
    {
        return self::dir() . '/' . sha1($resource) . '.lock';
    }

    /** Exécute $fn en exclusivité sur $resource. */
    public static function transaction(string $resource, callable $fn): mixed
    {
        $handle = @fopen(self::file($resource) . '.mutex', 'c+');
        if ($handle === false) {
            return $fn();   // Pas de verrou possible : on n'empêche pas l'écriture.
        }
        try {
            flock($handle, LOCK_EX);
            return $fn();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Prend (ou renouvelle) le verrou éditorial. Renvoie null si un autre le détient. */
    public static function acquire(string $resource, int $userId, string $userName): ?array
    {
        return self::transaction($resource, static function () use ($resource, $userId, $userName): ?array {
            $current = self::inspect($resource);
            if ($current !== null && (int) $current['user_id'] !== $userId) {
                return null;
            }
            $state = [
                'resource'  => $resource,
                'user_id'   => $userId,
                'user_name' => $userName,
                'since'     => $current['since'] ?? time(),
                'touched'   => time(),
            ];
            Json::write(self::file($resource), $state);
            return $state;
        });
    }

    /** Verrou actif, ou null s'il n'existe pas / a expiré. */
    public static function inspect(string $resource): ?array
    {
        $state = Json::read(self::file($resource));
        if ($state === []) {
            return null;
        }
        $ttl = (int) Config::get('storage.lock_ttl', 900);
        if (time() - (int) ($state['touched'] ?? 0) > $ttl) {
            Json::delete(self::file($resource));
            return null;
        }
        $state['age_minutes'] = (int) floor((time() - (int) $state['since']) / 60);
        return $state;
    }

    public static function release(string $resource, int $userId): void
    {
        $state = self::inspect($resource);
        if ($state === null || (int) $state['user_id'] === $userId) {
            Json::delete(self::file($resource));
        }
    }
}
