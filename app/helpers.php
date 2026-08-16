<?php
declare(strict_types=1);

/**
 * Fonctions utilitaires disponibles dans toute l'application et les gabarits.
 */

if (!function_exists('e')) {
    /**
     * Échappement HTML. À utiliser sur TOUTE valeur affichée dans un gabarit.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * URL absolue à partir d'un chemin racine.
     */
    function url(string $path = '/'): string
    {
        static $base;
        $base ??= rtrim($GLOBALS['config']['app']['base_url'] ?? '', '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * URL d'un asset, avec empreinte de cache basée sur la date du fichier.
     */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = ($GLOBALS['config']['paths']['public'] ?? '') . '/' . $path;
        $version = is_file($file) ? '?v=' . filemtime($file) : '';
        return url($path) . $version;
    }
}

if (!function_exists('json_response')) {
    /**
     * Réponse JSON pour les points d'entrée d'API.
     */
    function json_response(mixed $data, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
