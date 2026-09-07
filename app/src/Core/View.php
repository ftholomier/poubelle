<?php

declare(strict_types=1);

namespace App\Core;

use App\I18n\Translator;

/**
 * Moteur de vues : templates PHP, échappement systématique.
 */
final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Les variables internes portent un préfixe improbable : une vue peut ainsi
     * recevoir des variables nommées $data, $template ou $file sans que le
     * moteur ne les masque (extract() n'écrase jamais une variable existante).
     *
     * @param array<string,mixed> $lcalViewData
     */
    public static function render(string $lcalViewName, array $lcalViewData = []): string
    {
        $lcalViewFile = VIEW_PATH . '/' . ltrim(str_replace(['..', '\\'], '', $lcalViewName), '/') . '.php';
        if (!is_file($lcalViewFile)) {
            throw new \RuntimeException("Vue introuvable : {$lcalViewName}");
        }

        extract(array_merge(self::$shared, $lcalViewData), EXTR_SKIP);
        unset($lcalViewData, $lcalViewName);

        $lcalViewLevel = ob_get_level();
        ob_start();
        try {
            include $lcalViewFile;
        } catch (\Throwable $e) {
            while (ob_get_level() > $lcalViewLevel) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public static function display(string $template, array $data = []): void
    {
        echo self::render($template, $data);
    }

    /* ------------------------- Helpers de vue ------------------------- */

    /** Échappement HTML. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) (is_scalar($value) ? $value : ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Échappement d'attribut. */
    public static function attr(mixed $value): string
    {
        return self::e($value);
    }

    /** Champ multilingue → texte échappé. */
    public static function t(mixed $field): string
    {
        return self::e(Translator::pick($field));
    }

    /** Champ multilingue → texte brut (pour les attributs meta, JSON-LD…). */
    public static function raw(mixed $field): string
    {
        return Translator::pick($field);
    }

    /** URL interne préfixée par la langue courante. */
    public static function url(string $path = '/'): string
    {
        if (preg_match('#^(https?:|mailto:|tel:|\#)#i', $path)) {
            return $path;
        }
        // Les paramètres de requête sont préservés.
        $query = '';
        if (str_contains($path, '?')) {
            [$path, $query] = explode('?', $path, 2);
            $query = '?' . $query;
        }
        return Translator::url($path) . $query;
    }

    /** Version d'asset : invalidation de cache automatique au déploiement. */
    public static function asset(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $file = PUBLIC_PATH . $path;
        $version = is_file($file) ? substr((string) filemtime($file), -6) : '1';
        return $path . '?v=' . $version;
    }

    /** JSON sûr pour injection dans un attribut ou un <script type="application/json">. */
    public static function json(mixed $value): string
    {
        return htmlspecialchars(
            (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /** Classes conditionnelles. @param array<string,bool> $map */
    public static function classes(array $map): string
    {
        $out = [];
        foreach ($map as $class => $enabled) {
            if ($enabled) {
                $out[] = $class;
            }
        }
        return implode(' ', $out);
    }
}
