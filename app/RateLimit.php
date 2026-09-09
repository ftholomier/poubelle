<?php
declare(strict_types=1);

namespace App;

/** Compteurs par IP dans storage/locks (aucune base de données). */
final class RateLimit
{
    /** true si l'action est permise, false si le quota est dépassé. */
    public static function allow(string $bucket, int $max, int $windowSeconds, ?string $key = null): bool
    {
        $key ??= self::ip();
        $file = Config::storagePath('locks/rl-' . preg_replace('/[^a-z0-9_\-]/i', '', $bucket) . '-' . substr(sha1($key), 0, 16) . '.json');
        if (!is_dir(\dirname($file))) {
            @mkdir(\dirname($file), 0775, true);
        }

        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return true; // On ne bloque pas le visiteur si le disque refuse le compteur.
        }
        try {
            if (!flock($fh, LOCK_EX)) {
                return true;
            }
            $raw = stream_get_contents($fh) ?: '';
            $data = json_decode($raw, true);
            $now = time();
            if (!\is_array($data) || (int) ($data['start'] ?? 0) + $windowSeconds < $now) {
                $data = ['start' => $now, 'count' => 0];
            }
            $data['count'] = (int) $data['count'] + 1;

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode($data));
            fflush($fh);

            return $data['count'] <= $max;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $trusted = Config::get('TRUSTED_PROXY');
        if ($trusted && $ip === $trusted && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($forwarded[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /** Nettoyage des compteurs expirés (appelé par le cron de maintenance). */
    public static function prune(int $olderThan = 86400): int
    {
        $n = 0;
        foreach (glob(Config::storagePath('locks/rl-*.json')) ?: [] as $file) {
            if (filemtime($file) < time() - $olderThan) {
                @unlink($file);
                $n++;
            }
        }
        return $n;
    }
}
