<?php

declare(strict_types=1);

namespace Poubelle\WhatsApp;

/**
 * Logger minimaliste sur fichier (une ligne par événement, JSON pour le contexte).
 */
final class Logger
{
    public function __construct(private string $file)
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
}
