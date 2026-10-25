<?php
declare(strict_types=1);

namespace LD;

/**
 * Limitation de débit par IP (fenêtre glissante, stockée en fichiers).
 */
final class RateLimit
{
    public static function hit(string $bucket, int $max, int $windowSeconds, ?string $who = null): bool
    {
        $who ??= Http::ip();
        $key = hash('sha256', $bucket . '|' . $who . '|' . Settings::appSecret());
        $file = Store::path('cache/rl/' . substr($key, 0, 32) . '.json');
        $now = time();
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return true;
        }
        try {
            flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            $hits = json_decode($raw ?: '[]', true);
            $hits = array_values(array_filter(is_array($hits) ? $hits : [], static fn($t) => $t > $now - $windowSeconds));
            if (count($hits) >= $max) {
                return false;
            }
            $hits[] = $now;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($hits));
            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function enforce(string $bucket, int $max, int $windowSeconds): void
    {
        if (!self::hit($bucket, $max, $windowSeconds)) {
            Http::fail('Doucement 🙂 Trop de demandes en peu de temps. Réessaie dans quelques minutes.', 429);
        }
    }

    /** Ménage occasionnel des fichiers expirés. */
    public static function gc(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        foreach (glob(Store::path('cache/rl/*.json')) ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
