<?php
declare(strict_types=1);

/**
 * Traitement centralisé des erreurs.
 *
 * Sans gestionnaire global, une exception non rattrapée interrompt le
 * rendu au milieu de la page : le visiteur reçoit un document tronqué,
 * et l'exploitant n'en sait rien. Ici, toute erreur est journalisée dans
 * data/logs/erreurs-AAAA-MM.log puis présentée proprement — page 500 en
 * HTML, objet JSON pour les routes /api, message détaillé uniquement
 * lorsque APP_DEBUG vaut 1.
 */
final class ErrorHandler
{
    private static bool $enVol = false;

    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Erreurs PHP.
     *
     * Seules celles qui empêchent réellement de poursuivre deviennent des
     * exceptions ; un avertissement ou une dépréciation est journalisé et
     * l'exécution continue, pour ne pas transformer un détail en page 500.
     */
    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;   // erreur volontairement masquée par un @
        }
        if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
        self::log(new ErrorException($message, 0, $severity, $file, $line));
        return true;
    }

    public static function handleException(Throwable $e): void
    {
        self::log($e);
        self::render($e);
    }

    /** Erreurs fatales : elles ne passent pas par le gestionnaire d'exceptions. */
    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $e = new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
        self::log($e);
        self::render($e);
    }

    public static function log(Throwable $e): void
    {
        $dir = DATA_DIR . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ligne = sprintf(
            "[%s] %s: %s dans %s:%d — %s %s\n%s\n\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $_SERVER['REQUEST_URI'] ?? '',
            $e->getTraceAsString()
        );
        @file_put_contents($dir . '/erreurs-' . date('Y-m') . '.log', $ligne, FILE_APPEND | LOCK_EX);
    }

    private static function render(Throwable $e): void
    {
        // Une erreur survenue pendant l'affichage de la page d'erreur ne
        // doit pas provoquer de boucle.
        if (self::$enVol) {
            return;
        }
        self::$enVol = true;

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e->getMessage() . "\n");
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
        }

        $detail = APP_DEBUG
            ? $e::class . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')'
            : '';
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        // Les réglages peuvent être justement ce qui a échoué : la racine
        // du site est calculée avec un repli sûr.
        try { $racine = url('/'); } catch (Throwable $ignore) { $racine = '/'; }

        if (str_contains($uri, '/api/')) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'ok' => false,
                'error' => 'Une erreur technique est survenue. Merci de réessayer dans un instant.',
                'detail' => $detail,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Erreur technique — Suisse Immo</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07080c;'
            . 'color:#f2f4f8;font:16px/1.6 system-ui,-apple-system,Segoe UI,sans-serif;padding:24px;text-align:center}'
            . 'h1{font-size:1.6rem;margin:0 0 12px}p{color:#8d99ae;max-width:46ch;margin:0 auto 22px}'
            . 'a{display:inline-block;padding:.9em 1.8em;border-radius:99px;background:#e62f43;color:#fff;text-decoration:none}'
            . 'pre{max-width:90vw;overflow:auto;text-align:left;color:#ff8a3d;font-size:.8rem}</style></head><body><div>'
            . '<h1>Une erreur technique est survenue</h1>'
            . '<p>Nos équipes en sont informées. Vous pouvez revenir à l’accueil ou nous appeler directement.</p>'
            . ($detail !== '' ? '<pre>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>' : '')
            . '<a href="' . htmlspecialchars($racine, ENT_QUOTES, 'UTF-8') . '">Retour à l’accueil</a>'
            . '</div></body></html>';
    }
}
