<?php
declare(strict_types=1);

namespace App\Storage;

use App\Core\Config;
use App\Core\Net;
use App\Services\Secrets;

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
            // Empreinte, jamais l'adresse : elle permet de rapprocher deux
            // lignes du même visiteur sans conserver de donnée identifiante.
            'ip'      => self::fingerprint(),
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @file_put_contents(
            $dir . '/audit-' . date('Y-m') . '.jsonl',
            $line . "\n",
            FILE_APPEND | LOCK_EX,
        );
    }

    /** Empreinte courte et non réversible de l'adresse du visiteur. */
    private static function fingerprint(): ?string
    {
        if (PHP_SAPI === 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') === '') {
            return null;
        }
        $ip = Net::clientIp();
        return $ip === '' ? null : substr(hash_hmac('sha256', $ip, Secrets::appKey()), 0, 16);
    }

    /**
     * Supprime les fichiers de journal plus vieux que la durée de conservation.
     * Appelé par bin/cron.php : rien ne s'accumule indéfiniment.
     *
     * @return int nombre de fichiers supprimés
     */
    public static function purge(int $months = 12): int
    {
        $keep = [];
        for ($i = 0; $i < max(1, $months); $i++) {
            $keep[] = date('Y-m', strtotime('-' . $i . ' month'));
        }

        $removed = 0;
        foreach (glob(Config::path('data') . '/logs/audit-*.jsonl') ?: [] as $file) {
            if (preg_match('/audit-(\d{4}-\d{2})\.jsonl$/', $file, $m) && !in_array($m[1], $keep, true)) {
                $removed += @unlink($file) ? 1 : 0;
            }
        }
        return $removed;
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
