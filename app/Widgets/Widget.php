<?php
declare(strict_types=1);

namespace App\Widgets;

/**
 * Un widget du musée : clé, nom, description, options, données (tableau, servi tel quel par
 * l'API JSON) et rendu HTML (le cœur du cadre ; le cadre commun ajoute le logo du musée, la
 * mention « non officiel » et le lien obligatoire, voir Frame).
 *
 * Pour ajouter un widget : une classe dans app/Widgets/Items/ qui étend celle-ci, puis sa clé
 * dans Registry::CLASSES (et retirée de Registry::PLANNED).
 */
abstract class Widget
{
    /** Clé dans les adresses (/w/<clé>, /api/v1/widgets/<clé>). */
    abstract public function key(): string;

    /** Nom affiché (catalogue, back-office, page de démonstration). */
    abstract public function name(string $lang = 'fr'): string;

    abstract public function description(string $lang = 'fr'): string;

    /**
     * Options propres au widget : nom => [label, type (select|text), choices (valeur => libellé),
     * default, pattern (expression régulière pour « text »)]. Les options communes (theme, lang,
     * format) sont gérées par le cadre.
     * @return array<string,array>
     */
    public function options(): array
    {
        return [];
    }

    /** Accès par défaut tant que l'administrateur n'a rien réglé. */
    public function defaultAccess(): string
    {
        return 'libre';
    }

    /** Durée de vie des données en cache, en secondes (jamais au-delà de minuit : voir Cache). */
    public function ttl(): int
    {
        return 3600;
    }

    /**
     * Les données du widget (rien de personnel : seulement ce que le musée publie déjà), ou null
     * si le musée n'a rien à montrer. Les liens sont des chemins du musée (« /joueurs/… ») ; les
     * images, des adresses complètes déjà vérifiées (Photos::safe).
     * @param array<string,string> $opt options validées
     */
    abstract public function data(array $opt, Context $c): ?array;

    /** Le cœur du widget, en HTML (échappé), à partir des données. */
    abstract public function body(array $d, Context $c): string;

    /** Hauteur conseillée de l'iframe, en pixels (code à copier). */
    public function height(): int
    {
        return 420;
    }

    /**
     * Options validées (valeur inconnue ou mal formée : valeur par défaut).
     * @return array<string,string>
     */
    public function clean(array $query): array
    {
        $out = [];
        foreach ($this->options() as $name => $o) {
            $v = $query[$name] ?? null;
            $v = is_scalar($v) ? trim((string) $v) : '';
            $def = (string) ($o['default'] ?? '');
            if (($o['type'] ?? 'select') === 'select') {
                $out[$name] = $v !== '' && isset($o['choices'][$v]) ? $v : $def;
            } else {
                $out[$name] = $v !== '' && mb_strlen($v) <= 80 && (!isset($o['pattern']) || preg_match($o['pattern'], $v)) ? $v : $def;
            }
        }
        return $out;
    }

    /** Catalogue : ce que l'API et le back-office disent du widget. */
    public function describe(string $lang = 'fr'): array
    {
        $opts = [];
        foreach ($this->options() as $name => $o) {
            $opts[$name] = ['label' => $o['label_' . $lang] ?? $o['label'], 'type' => $o['type'] ?? 'select', 'default' => (string) ($o['default'] ?? '')]
                + (isset($o['choices']) ? ['choices' => $o['choices']] : []);
        }
        return ['key' => $this->key(), 'name' => $this->name($lang), 'description' => $this->description($lang), 'options' => $opts, 'height' => $this->height()];
    }
}
