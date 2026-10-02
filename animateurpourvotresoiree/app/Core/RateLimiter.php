<?php
declare(strict_types=1);

namespace App\Core;

/** Limitation de débit par fenêtre fixe, stockée en fichiers (storage/cache/rl). */
final class RateLimiter
{
    private static function file(string $key): string
    {
        $h = sha1($key);
        return STORAGE_PATH . '/cache/rl/' . substr($h, 0, 2) . '/' . $h . '.json';
    }

    /** Enregistre une tentative ; renvoie false si la limite est dépassée. */
    public static function attempt(string $key, int $max, int $window): bool
    {
        return self::hit($key, $window) <= $max;
    }

    /** Incrémente et renvoie le nombre de tentatives dans la fenêtre courante. */
    public static function hit(string $key, int $window): int
    {
        $f = self::file($key);
        try {
            return Fs::withLock($f . '.lock', static function () use ($f, $window): int {
                $now = time();
                $d = Fs::readJson($f, null);
                if (!is_array($d) || ($d['r'] ?? 0) <= $now) {
                    $d = ['n' => 0, 'r' => $now + $window];
                }
                $d['n']++;
                Fs::writeJson($f, $d);
                return (int) $d['n'];
            }, 3);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function tooMany(string $key, int $max): bool
    {
        $d = Fs::readJson(self::file($key), null);
        return is_array($d) && ($d['r'] ?? 0) > time() && (int) ($d['n'] ?? 0) >= $max;
    }

    public static function count(string $key): int
    {
        $d = Fs::readJson(self::file($key), null);
        return (is_array($d) && ($d['r'] ?? 0) > time()) ? (int) $d['n'] : 0;
    }

    public static function retryAfter(string $key): int
    {
        $d = Fs::readJson(self::file($key), null);
        return is_array($d) ? max(0, (int) ($d['r'] ?? 0) - time()) : 0;
    }

    public static function clear(string $key): void
    {
        $f = self::file($key);
        @unlink($f);
        @unlink($f . '.lock');
    }

    /** Nettoyage des compteurs expirés (cron). */
    public static function prune(): int
    {
        $n = 0;
        foreach (glob(STORAGE_PATH . '/cache/rl/*/*.json') ?: [] as $f) {
            $d = Fs::readJson($f, null);
            if (!is_array($d) || ($d['r'] ?? 0) < time() - 60) {
                @unlink($f);
                @unlink($f . '.lock');
                $n++;
            }
        }
        return $n;
    }
}
