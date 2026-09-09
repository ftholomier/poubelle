<?php
declare(strict_types=1);

namespace App;

/**
 * Constantes, chemins et lecture des clés (.env puis storage/secrets.json).
 *
 * Priorité de résolution d'une clé : variable d'environnement > .env > secrets.json
 * (écrit par le back-office). Une clé posée dans .env est donc toujours gagnante,
 * ce qui permet de figer la configuration d'un serveur sans toucher au back-office.
 */
final class Config
{
    public const SCHEMA = 1;

    /** Langues servies. La première est la langue de référence (fallback). */
    public const LANGS = ['fr', 'en'];
    public const DEFAULT_LANG = 'fr';

    /** Sites (lieux) gérés par le catalogue. */
    public const SITES = ['carnot', 'granvelle'];

    /** Statuts de disponibilité d'un bureau. */
    public const STATUSES = ['available', 'soon', 'rented'];

    /** Types de bureau. */
    public const TYPES = ['private', 'openspace', 'meeting'];

    private static ?array $env = null;
    private static ?array $secrets = null;

    public static function root(): string
    {
        return \dirname(__DIR__);
    }

    public static function path(string $sub = ''): string
    {
        $p = self::root();
        return $sub === '' ? $p : $p . '/' . ltrim($sub, '/');
    }

    public static function contentPath(string $sub = ''): string
    {
        return self::path('content' . ($sub === '' ? '' : '/' . ltrim($sub, '/')));
    }

    public static function storagePath(string $sub = ''): string
    {
        return self::path('storage' . ($sub === '' ? '' : '/' . ltrim($sub, '/')));
    }

    public static function publicPath(string $sub = ''): string
    {
        return self::path('public' . ($sub === '' ? '' : '/' . ltrim($sub, '/')));
    }

    /** Lecture paresseuse du fichier .env (format KEY=value, # pour les commentaires). */
    private static function env(): array
    {
        if (self::$env === null) {
            self::$env = [];
            $file = self::path('.env');
            if (is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $v = trim($v);
                    if (\strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                        $v = substr($v, 1, -1);
                    }
                    self::$env[trim($k)] = $v;
                }
            }
        }
        return self::$env;
    }

    /** Clés saisies au back-office (storage/secrets.json, hors racine web, chmod 0600). */
    public static function secrets(): array
    {
        if (self::$secrets === null) {
            $file = self::storagePath('secrets.json');
            $raw = is_readable($file) ? file_get_contents($file) : '';
            $data = $raw === '' || $raw === false ? null : json_decode($raw, true);
            self::$secrets = \is_array($data) ? $data : [];
        }
        return self::$secrets;
    }

    public static function forgetSecrets(): void
    {
        self::$secrets = null;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $fromServer = $_SERVER[$key] ?? getenv($key);
        if (\is_string($fromServer) && $fromServer !== '') {
            return $fromServer;
        }
        $env = self::env();
        if (isset($env[$key]) && $env[$key] !== '') {
            return $env[$key];
        }
        $secrets = self::secrets();
        if (isset($secrets[$key]) && \is_string($secrets[$key]) && $secrets[$key] !== '') {
            return $secrets[$key];
        }
        return $default;
    }

    public static function has(string $key): bool
    {
        return (string) self::get($key, '') !== '';
    }

    /** true si la clé est figée dans l'environnement : le back-office l'affiche en lecture seule. */
    public static function isLockedByEnv(string $key): bool
    {
        $fromServer = $_SERVER[$key] ?? getenv($key);
        if (\is_string($fromServer) && $fromServer !== '') {
            return true;
        }
        $env = self::env();
        return isset($env[$key]) && $env[$key] !== '';
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return \in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    /** Base URL publique (sans slash final), déduite de la requête si non configurée. */
    public static function baseUrl(): string
    {
        $configured = self::get('SITE_URL');
        if ($configured) {
            return rtrim($configured, '/');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return (self::isHttps() ? 'https://' : 'http://') . $host;
    }

    /**
     * Préfixe d'URL de l'application. Vide quand le DocumentRoot pointe sur /public
     * (déploiement recommandé), sinon le sous-dossier détecté.
     */
    public static function basePath(): string
    {
        $configured = self::get('BASE_PATH');
        if ($configured !== null) {
            return rtrim($configured, '/');
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        // On retire le fichier exécuté, quel que soit son nom : index.php mais
        // aussi chat.php, lead.php… sinon les liens fabriqués depuis un point
        // d'entrée de l'API héritent de son chemin.
        $dir = rtrim((string) preg_replace('#/[^/]*\.php$#', '', $script), '/');
        foreach (['/api', '/admin'] as $suffix) {
            if (str_ends_with($dir, $suffix)) {
                $dir = substr($dir, 0, -\strlen($suffix));
            }
        }
        return $dir === '/' ? '' : $dir;
    }

    public static function isDebug(): bool
    {
        return self::bool('APP_DEBUG', false);
    }
}
