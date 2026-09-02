<?php

declare(strict_types=1);

namespace App;

/**
 * Rendu des gabarits PHP, avec gabarit englobant optionnel.
 */
final class View
{
    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $content = self::capture($template, $data);

        if ($layout === '') {
            echo $content;
            return;
        }

        echo self::capture($layout, $data + ['content' => $content]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function capture(string $template, array $data = []): string
    {
        $file = APP_VIEWS . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Gabarit introuvable : views/{$template}.php");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /** Échappement HTML — à utiliser sur toute donnée insérée dans une page. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Insertion d'un objet JSON dans une balise script, sans risque de fermeture prématurée.
     */
    public static function json(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    /**
     * URL d'un fichier statique, suffixée par sa date de modification
     * pour que le navigateur récupère bien la dernière version.
     */
    public static function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = APP_PUBLIC . $path;

        return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
    }
}
