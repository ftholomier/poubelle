<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Gabarits PHP natifs (templates/*.php). Les variables sont échappées avec e().
 * Un gabarit peut définir des blocs via View::start()/View::end().
 */
final class View
{
    private static array $blocks = [];
    private static array $stack = [];
    public static array $shared = [];

    public static function render(string $template, array $vars = [], ?string $layout = null): string
    {
        $content = self::partial($template, $vars);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $vars + ['content' => $content]);
    }

    public static function partial(string $template, array $vars = []): string
    {
        $file = TEMPLATES_PATH . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Gabarit introuvable : $template");
        }
        extract(self::$shared + $vars, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function start(string $name): void
    {
        self::$stack[] = $name;
        ob_start();
    }

    public static function end(): void
    {
        $name = array_pop(self::$stack);
        self::$blocks[$name] = (self::$blocks[$name] ?? '') . ob_get_clean();
    }

    public static function block(string $name, string $default = ''): string
    {
        return self::$blocks[$name] ?? $default;
    }
}
