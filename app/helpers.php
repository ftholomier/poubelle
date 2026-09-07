<?php
/**
 * Fonctions raccourcies disponibles dans les vues.
 */

declare(strict_types=1);

use App\Core\View;
use App\I18n\Translator;

if (!function_exists('e')) {
    /** Échappement HTML. */
    function e(mixed $value): string
    {
        return View::e($value);
    }
}

if (!function_exists('tr')) {
    /** Champ multilingue → texte échappé. */
    function tr(mixed $field): string
    {
        return View::t($field);
    }
}

if (!function_exists('trRaw')) {
    /** Champ multilingue → texte brut. */
    function trRaw(mixed $field): string
    {
        return Translator::pick($field);
    }
}

if (!function_exists('__')) {
    /** Chaîne d'interface traduite. */
    function __(string $key, array $replace = []): string
    {
        return Translator::t($key, $replace);
    }
}

if (!function_exists('__e')) {
    function __e(string $key, array $replace = []): string
    {
        return View::e(Translator::t($key, $replace));
    }
}

if (!function_exists('u')) {
    /** URL interne préfixée par la langue. */
    function u(string $path = '/'): string
    {
        return View::url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return View::asset($path);
    }
}

if (!function_exists('icon')) {
    /** Icône SVG plate du jeu interne. */
    function icon(string $name, string $class = 'icon', int $size = 24): string
    {
        return App\Core\Icons::svg($name, $class, $size);
    }
}

if (!function_exists('safeHtml')) {
    /** Contenu WYSIWYG déjà nettoyé à l'enregistrement, réassaini au rendu. */
    function safeHtml(mixed $field): string
    {
        return App\Security\Sanitizer::html(Translator::pick($field));
    }
}

if (!function_exists('classes')) {
    function classes(array $map): string
    {
        return View::classes($map);
    }
}

if (!function_exists('reveal')) {
    /**
     * Attributs d'animation d'apparition au défilement.
     * @param string $effect up | left | right | zoom | fade
     */
    function reveal(string $effect = 'up', int $delay = 0): string
    {
        $attr = ' data-reveal="' . View::e($effect) . '"';
        if ($delay > 0) {
            $attr .= ' style="--reveal-delay:' . (int) $delay . 'ms"';
        }
        return $attr;
    }
}
