<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lecture d'un fichier .env très simple (KEY=value, # commentaires).
 * Les variables ne sont jamais exposées côté public.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (strlen($value) > 1) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $native = getenv($key);
        if ($native !== false && $native !== '') {
            return $native;
        }
        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');
        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, (string) $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$vars) || getenv($key) !== false;
    }
}
