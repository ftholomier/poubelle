<?php
declare(strict_types=1);

namespace App\Core;

/** Limitation de débit par fichier (formulaires, assistant IA, connexion). */
final class RateLimiter
{
    /**
     * @return bool true si l'action est autorisée (et comptabilisée)
     */
    public static function hit(string $bucket, string $key, int $max, int $windowSeconds): bool
    {
        $file = STORAGE_PATH . '/ratelimit/' . $bucket . '.json';
        $id = hash('sha256', $key);
        $now = time();
        $allowed = false;
        JsonStore::update($file, function ($data) use ($id, $now, $max, $windowSeconds, &$allowed) {
            $data = is_array($data) ? $data : [];
            // Nettoyage des entrées expirées.
            foreach ($data as $k => $hits) {
                $data[$k] = array_values(array_filter($hits, fn ($t) => $t > $now - $windowSeconds));
                if (!$data[$k]) {
                    unset($data[$k]);
                }
            }
            $hits = $data[$id] ?? [];
            if (count($hits) < $max) {
                $hits[] = $now;
                $allowed = true;
            }
            $data[$id] = $hits;
            return $data;
        }, []);
        return $allowed;
    }

    public static function remaining(string $bucket, string $key, int $max, int $windowSeconds): int
    {
        $data = JsonStore::read(STORAGE_PATH . '/ratelimit/' . $bucket . '.json', []) ?? [];
        $hits = array_filter($data[hash('sha256', $key)] ?? [], fn ($t) => $t > time() - $windowSeconds);
        return max(0, $max - count($hits));
    }
}
