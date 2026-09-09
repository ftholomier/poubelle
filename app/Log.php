<?php
declare(strict_types=1);

namespace App;

/** Journaux applicatifs (storage/logs), rotation simple à 2 Mo. */
final class Log
{
    public static function write(string $channel, string $message): void
    {
        $channel = preg_replace('/[^a-z0-9_\-]/i', '', $channel) ?: 'app';
        $dir = Config::storagePath('logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . $channel . '.log';
        if (is_file($file) && filesize($file) > 2_097_152) {
            @rename($file, $file . '.1');
        }
        $line = sprintf(
            "[%s] %s %s\n",
            (new \DateTimeImmutable())->format(\DATE_ATOM),
            str_replace(["\n", "\r"], ' ', $message),
            '(' . (string) ($_SERVER['REQUEST_URI'] ?? 'cli') . ')'
        );
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Lit les N dernières lignes d'un journal, pour l'affichage au back-office. */
    public static function tail(string $channel, int $lines = 50): array
    {
        $channel = preg_replace('/[^a-z0-9_\-]/i', '', $channel) ?: 'app';
        $file = Config::storagePath('logs/' . $channel . '.log');
        if (!is_readable($file)) {
            return [];
        }
        $all = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_reverse(\array_slice($all, -$lines));
    }
}
