<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Journaux JSON Lines : storage/logs/{canal}-AAAA-MM-JJ.log
 * Canaux : app, error, security, audit, mail, ai, spam, cron, import.
 */
final class Logger
{
    public const CHANNELS = ['app', 'error', 'security', 'audit', 'mail', 'ai', 'spam', 'cron', 'import', 'push'];

    public static function log(string $channel, string $message, array $context = [], string $level = 'info'): void
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'app';
        }
        $entry = [
            't' => date('c'),
            'level' => $level,
            'msg' => $message,
        ];
        if ($context) {
            $entry['ctx'] = self::scrub($context);
        }
        if (PHP_SAPI !== 'cli') {
            $entry['ip'] = Request::ip();
            $entry['url'] = substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 300);
        }
        $file = STORAGE_PATH . '/logs/' . $channel . '-' . date('Y-m-d') . '.log';
        try {
            Fs::ensureDir(dirname($file));
            @file_put_contents($file, json_encode($entry, Fs::JSON_FLAGS) . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }

    public static function info(string $msg, array $ctx = []): void
    {
        self::log('app', $msg, $ctx);
    }

    public static function error(string $msg, array $ctx = []): void
    {
        self::log('error', $msg, $ctx, 'error');
    }

    public static function security(string $msg, array $ctx = [], string $level = 'warning'): void
    {
        self::log('security', $msg, $ctx, $level);
    }

    /** Trace des actions d'administration (qui a fait quoi). */
    public static function audit(string $action, array $ctx = []): void
    {
        $user = Auth::admin();
        $ctx['by'] = $user ? ($user['email'] ?? ('#' . $user['id'])) : (PHP_SAPI === 'cli' ? 'cli' : 'système');
        self::log('audit', $action, $ctx);
    }

    /** Lit les dernières lignes d'un canal (le plus récent en premier). */
    public static function tail(string $channel, int $limit = 200, ?string $date = null, ?string $search = null): array
    {
        $files = $date
            ? [STORAGE_PATH . '/logs/' . $channel . '-' . $date . '.log']
            : array_reverse(glob(STORAGE_PATH . '/logs/' . $channel . '-*.log') ?: []);
        $out = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                if ($search !== null && $search !== '' && stripos($lines[$i], $search) === false) {
                    continue;
                }
                $row = json_decode($lines[$i], true);
                if (is_array($row)) {
                    $out[] = $row;
                    if (count($out) >= $limit) {
                        return $out;
                    }
                }
            }
        }
        return $out;
    }

    /** Supprime les journaux plus vieux que $days jours. */
    public static function prune(int $days = 180): int
    {
        $n = 0;
        foreach (glob(STORAGE_PATH . '/logs/*.log') ?: [] as $f) {
            if (filemtime($f) < time() - $days * 86400 && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    private static function scrub(array $ctx): array
    {
        foreach ($ctx as $k => $v) {
            if (is_string($k) && preg_match('/pass|secret|token|key|authorization/i', $k)) {
                $ctx[$k] = '***';
            } elseif (is_array($v)) {
                $ctx[$k] = self::scrub($v);
            } elseif (is_string($v) && strlen($v) > 2000) {
                $ctx[$k] = substr($v, 0, 2000) . '…';
            }
        }
        return $ctx;
    }
}
