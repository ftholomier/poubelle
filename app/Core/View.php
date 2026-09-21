<?php
declare(strict_types=1);

namespace App\Core;

/** Rendu des gabarits PHP, avec mise en page et sections. */
final class View
{
    private static array $shared = [];
    private static array $sections = [];
    private static array $stack = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** Rend un gabarit dans une mise en page. `$template` est relatif à /templates. */
    public static function render(string $template, array $data = [], string $layout = 'layout'): string
    {
        self::$sections = [];
        $content = self::partial($template, $data);

        if ($layout === '') {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** Rend un fragment sans mise en page. */
    public static function partial(string $template, array $data = []): string
    {
        $file = Config::path('templates') . '/' . ltrim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Gabarit introuvable : ' . $template);
        }
        return self::evaluate($file, $data + self::$shared);
    }

    /**
     * Exécute le gabarit dans une portée isolée.
     * Les noms locaux sont préfixés : extract() ne doit jamais pouvoir écraser
     * le chemin du fichier (un gabarit reçoit couramment une variable $path).
     */
    private static function evaluate(string $__file, array $__vars): string
    {
        extract($__vars, EXTR_SKIP);
        unset($__vars);

        ob_start();
        try {
            require $__file;
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
        if ($name !== null) {
            self::$sections[$name] = (self::$sections[$name] ?? '') . (string) ob_get_clean();
        }
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }
}
