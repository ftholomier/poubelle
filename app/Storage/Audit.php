<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;

/** Journal horodaté : connexions, publications, incidents de stockage. */
final class Audit
{
    public static function log(string $event, array $context = [], ?int $userId = null): void
    {
        $dir = Config::path('data') . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = json_encode([
            'at'      => date('c'),
            'event'   => $event,
            'user'    => $userId,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @file_put_contents(
            $dir . '/audit-' . date('Y-m') . '.jsonl',
            $line . "\n",
            FILE_APPEND | LOCK_EX,
        );
    }

    /** Dernières entrées, plus récentes d'abord — alimente « Journal & sauvegardes ». */
    public static function recent(int $limit = 30, ?string $prefix = null): array
    {
        $out = [];
        $months = [date('Y-m'), date('Y-m', strtotime('-1 month'))];

        foreach ($months as $month) {
            $file = Config::path('data') . '/logs/audit-' . $month . '.jsonl';
            if (!is_file($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_reverse($lines) as $line) {
                $entry = json_decode($line, true);
                if (!is_array($entry)) {
                    continue;
                }
                if ($prefix !== null && !str_starts_with((string) ($entry['event'] ?? ''), $prefix)) {
                    continue;
                }
                $out[] = $entry;
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }
        return $out;
    }
}
