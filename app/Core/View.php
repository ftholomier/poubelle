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
        // les gabarits de pages publiques vivent sous pages/, les autres
        // (admin/...) sont référencés par leur chemin complet
        $chemin = str_contains($template, '/') ? $template : "pages/{$template}";
        $slot = $this->capture($chemin, $data);

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
    private function capture(string $__nom, array $__donnees): string
    {
        /* Les variables locales de cette méthode sont préfixées, et ce n'est
           pas une coquetterie. `extract()` en mode EXTR_SKIP écarte toute
           donnée qui porterait le nom d'une variable déjà présente ici — sans
           erreur, sans alerte, sans rien. Un contrôleur qui passait
           `'file' => [...]` voyait donc son tableau remplacé par le chemin du
           gabarit, et le gabarit parcourait une chaîne : trouvé par
           `outils/verifs/alertes.py`, dans un écran neuf, sur le seul indice
           d'une alerte PHP que personne ne lisait.

           Un préfixe qu'aucune donnée de contenu ne portera jamais ferme la
           question pour de bon, plutôt que d'interdire une liste de noms que
           personne ne retiendra. */
        $__gabarit = $this->dir . '/' . $__nom . '.php';
        if (!is_file($__gabarit)) {
            throw new RuntimeException("Gabarit introuvable : {$__nom}.php");
        }

        // Les données du rendu priment sur celles partagées : un contrôleur
        // qui passe explicitement une valeur doit l'emporter sur le repli
        // global, sans quoi elle serait silencieusement ignorée.
        // $view reste disponible dans le gabarit pour les inclusions imbriquées.
        extract($__donnees + $this->shared + ['view' => $this], EXTR_SKIP);

        ob_start();
        try {
            require $__gabarit;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
