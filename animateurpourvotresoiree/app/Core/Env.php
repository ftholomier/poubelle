<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lecture / écriture du fichier config/.env (hors du dossier public).
 * Le fichier est créé automatiquement au premier lancement avec des secrets aléatoires.
 */
final class Env
{
    private static array $vars = [];
    private static string $path = '';

    public static function boot(string $path): void
    {
        self::$path = $path;
        if (!is_file($path)) {
            self::createDefault($path);
        }
        self::$vars = self::parse((string) file_get_contents($path));
        // Secrets manquants (mise à jour d'une ancienne installation) : on les génère.
        $missing = [];
        foreach (self::generatedSecrets() as $k => $gen) {
            if ((self::$vars[$k] ?? '') === '') {
                $missing[$k] = $gen();
            }
        }
        if ($missing && is_writable(dirname($path))) {
            self::write($missing, false);
        }
    }

    public static function path(): string
    {
        return self::$path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, self::$vars)) {
            return $default;
        }
        $v = self::$vars[$key];
        if ($v === '' && $default !== null) {
            return $default;
        }
        return $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::$vars[$key] ?? null;
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'on', 'yes', 'oui'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::$vars[$key] ?? null;
        return ($v === null || $v === '' || !is_numeric($v)) ? $default : (int) $v;
    }

    public static function all(): array
    {
        return self::$vars;
    }

    /** Analyse un contenu .env (KEY=VALUE, guillemets, commentaires). */
    public static function parse(string $content): array
    {
        $vars = [];
        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                continue;
            }
            $value = $m[2];
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $q = $value[0];
                $end = strrpos($value, $q);
                $value = $end > 0 ? substr($value, 1, $end - 1) : substr($value, 1);
                if ($q === '"') {
                    // remplacement en une seule passe (sinon « \\n » serait transformé en retour à la ligne)
                    $value = strtr($value, ['\\"' => '"', '\\\\' => '\\', '\\n' => "\n"]);
                }
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value);
            }
            $vars[$m[1]] = $value;
        }
        return $vars;
    }

    /**
     * Met à jour des clés en conservant l'ordre et les commentaires du fichier.
     * Une copie de sauvegarde horodatée est conservée à chaque modification.
     */
    public static function write(array $changes, bool $backup = true): void
    {
        Fs::withLock(self::$path . '.lock', static fn () => self::writeLocked($changes, $backup), 10);
    }

    private static function writeLocked(array $changes, bool $backup): void
    {
        $path = self::$path;
        $content = is_file($path) ? (string) file_get_contents($path) : '';
        if ($backup && $content !== '') {
            $dir = STORAGE_PATH . '/backups/env';
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            @file_put_contents($dir . '/env-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.bak', $content);
            $files = glob($dir . '/env-*.bak') ?: [];
            rsort($files);
            foreach (array_slice($files, 30) as $old) {
                @unlink($old);
            }
        }
        $lines = $content === '' ? [] : preg_split('/\R/', $content);
        $done = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $m) && array_key_exists($m[1], $changes)) {
                $lines[$i] = $m[1] . '=' . self::quote((string) $changes[$m[1]]);
                $done[$m[1]] = true;
            }
        }
        foreach ($changes as $k => $v) {
            if (!isset($done[$k])) {
                $lines[] = $k . '=' . self::quote((string) $v);
            }
        }
        $out = rtrim(implode("\n", $lines)) . "\n";
        Fs::writeAtomic($path, $out, 0600);
        foreach ($changes as $k => $v) {
            self::$vars[$k] = (string) $v;
        }
    }

    public static function quote(string $v): string
    {
        $v = str_replace(["\r", "\n"], '', $v);
        if ($v === '' || preg_match('/^[A-Za-z0-9_\-.,:\/@+=]+$/', $v)) {
            return $v;
        }
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
    }

    private static function generatedSecrets(): array
    {
        return [
            'APP_KEY' => static fn () => 'base64:' . base64_encode(random_bytes(32)),
            'CRON_TOKEN' => static fn () => bin2hex(random_bytes(16)),
        ];
    }

    private static function createDefault(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $tpl = is_file($dir . '/.env.example') ? (string) file_get_contents($dir . '/.env.example') : '';
        $vars = self::parse($tpl);
        $vars['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
        $vars['CRON_TOKEN'] = bin2hex(random_bytes(16));
        $vars['SETUP_TOKEN'] = bin2hex(random_bytes(12));
        $lines = [];
        foreach (preg_split('/\R/', $tpl) as $line) {
            if (preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $m)) {
                $lines[] = $m[1] . '=' . self::quote((string) ($vars[$m[1]] ?? ''));
                unset($vars[$m[1]]);
            } else {
                $lines[] = $line;
            }
        }
        foreach ($vars as $k => $v) {
            $lines[] = $k . '=' . self::quote((string) $v);
        }
        @file_put_contents($path, rtrim(implode("\n", $lines)) . "\n");
        @chmod($path, 0600);
    }
}
