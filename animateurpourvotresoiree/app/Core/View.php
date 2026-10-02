<?php
declare(strict_types=1);

namespace App\Core;

/** Gabarits PHP natifs (app/Views). Les variables sont échappées avec e() dans les gabarits. */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(string $key, mixed $default = null): mixed
    {
        return self::$shared[$key] ?? $default;
    }

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, ['content' => $content] + $data);
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!preg_match('#^[a-z0-9_\-/]+$#i', $template) || !is_file($file)) {
            throw new \RuntimeException('Gabarit introuvable : ' . $template);
        }
        $data = self::$shared + $data;
        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
