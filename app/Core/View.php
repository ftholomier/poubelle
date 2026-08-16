<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Gabarits PHP natifs. Une vue est rendue à l'intérieur d'un layout,
 * son résultat étant injecté dans $slot.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $dir)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $slot = $this->capture("pages/{$template}", $data);

        return $layout === null
            ? $slot
            : $this->capture($layout, $data + ['slot' => $slot]);
    }

    /**
     * Inclusion d'un fragment depuis un gabarit : <?= $view->partial('header') ?>
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->capture("partials/{$template}", $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $template, array $data): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Gabarit introuvable : {$template}.php");
        }

        // $view reste disponible dans le gabarit pour les inclusions imbriquées.
        extract($this->shared + $data + ['view' => $this], EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
