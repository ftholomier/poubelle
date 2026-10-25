<?php
declare(strict_types=1);

namespace LD;

/**
 * Rendu des gabarits PHP (app/views).
 */
final class View
{
    public static function render(string $template, array $vars = []): string
    {
        $file = LD_APP . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Gabarit introuvable : ' . $template);
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Page publique complète : contenu + gabarit commun (en-tête, pied, assistant). */
    public static function page(string $template, array $vars = []): string
    {
        $vars['g'] = Content::get('global');
        $vars['s'] = Settings::all();
        $body = self::render('pages/' . $template, $vars);
        return self::render('layout', $vars + ['body' => $body]);
    }

    public static function partial(string $name, array $vars = []): string
    {
        return self::render('partials/' . $name, $vars);
    }
}
