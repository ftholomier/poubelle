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

    /**
     * Chaque compteur est réparti en 256 petits fichiers (selon l'empreinte de la clé) : une
     * requête ne verrouille et ne réécrit que le sien, quel que soit le nombre de visiteurs.
     * Une demande refusée n'écrit rien.
     */
    private static function shard(string $bucket, string $id): string
    {
        return STORAGE_PATH . '/ratelimit/' . $bucket . '/' . substr($id, 0, 2) . '.json';
    }

    /**
     * Lit le fichier sous verrou, applique $fn qui rend [résultat, nouvelles données ou null] ;
     * réécrit seulement si des données sont rendues.
     */
    private static function withShard(string $file, callable $fn): mixed
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            // Stockage indisponible : on laisse passer plutôt que de bloquer le site.
            return $fn([])[0];
        }
        try {
            flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            $data = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
            [$result, $new] = $fn(is_array($data) ? $data : []);
            if ($new !== null) {
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, json_encode($new, JSON_UNESCAPED_SLASHES));
                fflush($fp);
            }
            return $result;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function hit(string $bucket, string $key, int $max, int $windowSeconds): bool
    {
        $id = self::id($key);
        $now = time();
        return (bool) self::withShard(self::shard($bucket, $id), function (array $data) use ($id, $now, $max, $windowSeconds) {
            // Nettoyage des entrées expirées de ce fichier seulement.
            $pruned = false;
            foreach ($data as $k => $hits) {
                $keep = array_values(array_filter((array) $hits, fn ($t) => $t > $now - $windowSeconds));
                $pruned = $pruned || count($keep) !== count((array) $hits);
                if ($keep) {
                    $data[$k] = $keep;
                } else {
                    unset($data[$k]);
                }
            }
            $hits = $data[$id] ?? [];
            if (count($hits) >= $max) {
                return [false, $pruned ? $data : null];
            }
            $hits[] = $now;
            $data[$id] = $hits;
            return [true, $data];
        });
    }

    /** Oublie les essais d'une clé (ex. après une connexion réussie). */
    public static function clear(string $bucket, string $key): void
    {
        $id = self::id($key);
        self::withShard(self::shard($bucket, $id), function (array $data) use ($id) {
            if (!isset($data[$id])) {
                return [null, null];
            }
            unset($data[$id]);
            return [null, $data];
        });
    }

    public static function remaining(string $bucket, string $key, int $max, int $windowSeconds): int
    {
        $id = self::id($key);
        $raw = @file_get_contents(self::shard($bucket, $id));
        $data = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
        $hits = array_filter((array) ($data[$id] ?? []), fn ($t) => $t > time() - $windowSeconds);
        return max(0, $max - count($hits));
    }
}
