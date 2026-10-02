<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Notify;

/** Capture les erreurs PHP : journal + alerte admin (limitée à 1 par heure et par erreur). */
final class ErrorHandler
{
    public static function register(): void
    {
        $debug = Env::bool('APP_DEBUG');
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            if (in_array($no, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)) {
                Logger::log('error', $str, ['file' => self::short($file), 'line' => $line], 'notice');
                return true;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });

        set_exception_handler(static function (\Throwable $e): void {
            self::report($e);
            self::render($e);
        });

        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::report(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
            }
        });
    }

    public static function report(\Throwable $e): void
    {
        $sig = sha1(get_class($e) . $e->getFile() . $e->getLine());
        Logger::error($e->getMessage(), [
            'type' => get_class($e),
            'file' => self::short($e->getFile()),
            'line' => $e->getLine(),
            'trace' => array_slice(array_map(static fn ($f) => (isset($f['file']) ? self::short($f['file']) . ':' . ($f['line'] ?? '?') : '') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''), $e->getTrace()), 0, 12),
        ]);
        if (self::updating($e)) {
            return; // mise à jour du site en cours : journalisée seulement, l'alerte partira si l'erreur se reproduit ensuite
        }
        try {
            if (RateLimiter::attempt('err-alert:' . $sig, 1, 3600)) {
                Notify::admin('error', 'Erreur PHP : ' . mb_substr($e->getMessage(), 0, 120), self::short($e->getFile()) . ':' . $e->getLine(), Url::admin('journal?canal=error'), 'danger');
            }
        } catch (\Throwable) {
        }
    }

    public static function render(\Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[' . get_class($e) . '] ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ")\n" . $e->getTraceAsString() . "\n");
            return;
        }
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (Env::bool('APP_DEBUG')) {
            echo '<pre style="padding:20px;font:13px monospace;white-space:pre-wrap">' . htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre>';
            return;
        }
        try {
            echo View::render('errors/500', [], 'front/layout');
        } catch (\Throwable) {
            echo '<!doctype html><meta charset="utf-8"><title>Erreur</title><p style="font-family:sans-serif;padding:40px">Oups, une erreur est survenue. Réessayez dans un instant.</p>';
        }
    }

    /**
     * Erreur dans un fichier du site modifié il y a moins de 5 minutes : très probablement un envoi par FTP en
     * cours (fichier encore incomplet, ou appelant un fichier pas encore envoyé), donc passagère.
     */
    private static function updating(\Throwable $e): bool
    {
        $files = [$e->getFile()];
        foreach (array_slice($e->getTrace(), 0, 12) as $f) {
            $files[] = (string) ($f['file'] ?? '');
        }
        foreach (array_unique($files) as $f) {
            if ($f !== '' && !str_starts_with($f, STORAGE_PATH . '/') && (int) @filemtime($f) > time() - 300) {
                return true;
            }
        }
        return false;
    }

    private static function short(string $file): string
    {
        return str_replace(BASE_PATH . '/', '', $file);
    }
}
