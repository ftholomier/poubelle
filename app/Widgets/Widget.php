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

    /**
     * Réglages propres au widget, choisis par l'administrateur dans le back-office (et non par le
     * site qui l'affiche) : nom => [label, choices (valeur => libellé), default, help]. Lus dans
     * data() par Store::widget($this->key())['settings'].
     * @return array<string,array>
     */
    public function settings(): array
    {
        return [];
    }

    /** Réglages propres en vigueur (validés). @return array<string,string> */
    protected function setting(string $name): string
    {
        return (string) (Store::widget($this->key())['settings'][$name] ?? ($this->settings()[$name]['default'] ?? ''));
    }

    /**
     * Chemin du musée sans préfixe de langue (« /en/records/ » → « /records/ ») : les pages du site
     * donnent des liens déjà traduits (url()) ; Context::link remet la langue du widget.
     */
    public static function local(?string $href): string
    {
        $href = (string) $href;
        return (string) preg_replace('#^/en(?=/|$)#', '', $href) ?: '/';
    }

    /** Début d'un texte (sans balises), coupé proprement vers $max caractères. */
    public static function excerpt(string $html, int $max = 280): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $cut = mb_substr($t, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        return rtrim(mb_substr($cut, 0, $sp !== false && $sp > $max * 0.6 ? $sp : $max), " ,;:.–-") . '…';
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
