<?php
declare(strict_types=1);

namespace App\Core;

/** Limitation de débit par fichier (formulaires, assistant IA, connexion). */
final class RateLimiter
{
    /**
     * @return bool true si l'action est autorisée (et comptabilisée)
     */
    /**
     * Clé enregistrée (empreinte) : une adresse IPv6 compte pour tout son bloc /64, qu'une
     * seule machine peut parcourir à volonté.
     */
    private static function id(string $key): string
    {
        $parts = array_map(function (string $p): string {
            if (str_contains($p, ':') && filter_var($p, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && ($bin = inet_pton($p)) !== false) {
                return (string) inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
            }
            return $p;
        }, explode('|', $key));
        return hash('sha256', implode('|', $parts));
    }

    public static function hit(string $bucket, string $key, int $max, int $windowSeconds): bool
    {
        $file = STORAGE_PATH . '/ratelimit/' . $bucket . '.json';
        $id = self::id($key);
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

    /** Oublie les essais d'une clé (ex. après une connexion réussie). */
    public static function clear(string $bucket, string $key): void
    {
        $id = self::id($key);
        JsonStore::update(STORAGE_PATH . '/ratelimit/' . $bucket . '.json', function ($data) use ($id) {
            $data = is_array($data) ? $data : [];
            unset($data[$id]);
            return $data;
        }, []);
    }

    public static function remaining(string $bucket, string $key, int $max, int $windowSeconds): int
    {
        $data = JsonStore::read(STORAGE_PATH . '/ratelimit/' . $bucket . '.json', []) ?? [];
        $hits = array_filter($data[self::id($key)] ?? [], fn ($t) => $t > time() - $windowSeconds);
        return max(0, $max - count($hits));
    }
}
