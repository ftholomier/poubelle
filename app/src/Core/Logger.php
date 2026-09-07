<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARN', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /** Journal d'audit des actions back-office (traçabilité, sans BDD). */
    public static function audit(string $action, array $context = []): void
    {
        self::write('AUDIT', $action, $context, 'audit.log');
    }

    private static function write(string $level, string $message, array $context, string $file = 'app.log'): void
    {
        $dir = Config::get('paths.logs', STORAGE_PATH . '/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('c'),
            $level,
            $message,
            $context ? json_encode(self::redact($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents($dir . '/' . $file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Ne jamais journaliser de secret en clair. */
    private static function redact(array $context): array
    {
        $sensitive = ['password', 'pass', 'token', 'api_key', 'secret', 'authorization'];
        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                $context[$key] = '***';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }
        return $context;
    }
}
