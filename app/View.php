<?php
declare(strict_types=1);

namespace App;

/** Rendu des gabarits PHP : une vue par page, un layout commun, des partials. */
final class View
{
    private static array $shared = [];

    public static function share(array $data): void
    {
        self::$shared = array_replace(self::$shared, $data);
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    /** Rend une vue dans le layout du site public. */
    public static function page(string $view, array $data = [], string $layout = 'layout'): string
    {
        $content = self::partial('pages/' . $view, $data);
        return self::partial($layout, array_replace($data, ['content' => $content]));
    }

    /** Rend un gabarit du back-office (app/admin/views). */
    public static function admin(string $view, array $data = []): string
    {
        return self::render(Config::path('app/admin/views/' . self::safe($view) . '.php'), $view, $data);
    }

    /** Rend un gabarit isolé et renvoie le HTML. */
    public static function partial(string $view, array $data = []): string
    {
        return self::render(Config::path('app/views/' . self::safe($view) . '.php'), $view, $data);
    }

    private static function safe(string $view): string
    {
        return preg_replace('#[^a-zA-Z0-9_/\-]#', '', str_replace('..', '', $view)) ?? '';
    }

    private static function render(string $file, string $view, array $data): string
    {
        if (!is_file($file)) {
            Log::write('error', 'Vue introuvable : ' . $view);
            return '';
        }
        $vars = array_replace(self::$shared, $data);
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return Text::e($value);
    }

    /** Balise image complète : alt, dimensions connues, srcset, chargement paresseux. */
    public static function image(string $path, string $alt = '', array $options = []): string
    {
        $path = trim($path);
        if ($path === '') {
            $placeholder = (string) ($options['placeholder'] ?? $alt);
            return '<span class="cover-placeholder">' . Text::e($placeholder) . '</span>';
        }

        $class = (string) ($options['class'] ?? 'cover');
        $lang = (string) ($options['lang'] ?? I18n::lang());
        $alt = $alt !== '' ? $alt : Media::alt($path, $lang);
        $dimensions = Media::dimensions($path);
        $srcset = Media::srcset($path);
        $sizes = (string) ($options['sizes'] ?? '(max-width: 880px) 100vw, 600px');
        $eager = (bool) ($options['eager'] ?? false);

        $attributes = [
            'src' => Config::basePath() . $path,
            'alt' => $alt,
            'class' => $class,
            'loading' => $eager ? 'eager' : 'lazy',
            'decoding' => 'async',
        ];
        if ($eager) {
            $attributes['fetchpriority'] = 'high';
        }
        if ($dimensions['width'] > 0) {
            $attributes['width'] = (string) $dimensions['width'];
            $attributes['height'] = (string) $dimensions['height'];
        }
        if ($srcset !== '') {
            $attributes['srcset'] = $srcset;
            $attributes['sizes'] = $sizes;
        }
        if (!empty($options['id'])) {
            $attributes['data-gallery-main'] = '';
        }

        $html = '<img';
        foreach ($attributes as $name => $value) {
            $html .= $value === '' ? ' ' . $name : ' ' . $name . '="' . Text::e((string) $value) . '"';
        }
        return $html . '>';
    }

    /** Fond CSS d'un bloc décoratif (diaporama). */
    public static function bgUrl(string $path): string
    {
        return $path === '' ? 'none' : "url('" . Text::e(Config::basePath() . $path) . "')";
    }

    /** Remplace {count} et consorts dans un texte éditorial. */
    public static function fill(string $text, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return $text;
    }
}
