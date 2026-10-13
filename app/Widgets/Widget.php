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
     * Options propres au widget, que le site qui l'affiche choisit (?nom=valeur ou data-nom="…") :
     * nom => [label, label_en, type, default, …] avec pour type :
     * - « select » : liste fermée, choices (valeur => libellé, ou fonction qui la renvoie) ;
     * - « search » : longue liste filtrable (joueurs, adversaires) : choices aussi (validation),
     *   avec « free » => true une valeur hors liste est acceptée si le widget la nettoie (cleanName) ;
     *   proposés par /api/v1/widgets/<clé>/suggestions/<nom>?q=… et une <datalist> ;
     * - « date » : AAAA-MM-JJ (format « md » : stocké MM-JJ, l'année est ignorée), min « today » ;
     * - « number » : entier entre min et max ;
     * - « text » : texte libre court, pattern (expression régulière).
     * La valeur « auto » (quand elle est dans choices) laisse le widget décider (dernier match…).
     * Les options communes (theme, lang, format) sont gérées par le cadre.
     * @return array<string,array>
     */
    public function options(): array
    {
        return [];
    }

    /** Catégories du catalogue (une ou deux), voir Registry::CATEGORIES. @return list<string> */
    public function category(): array
    {
        return Registry::CATEGORY_OF[$this->key()] ?? [];
    }

    /** @var array<string,array<string,string>> choix calculés (par musée et par requête) */
    private static array $choiceMemo = [];

    /** Choix d'une option (liste calculée une fois par musée). @return array<string,string> */
    public function optionChoices(string $name): array
    {
        $o = $this->options()[$name] ?? [];
        $c = $o['choices'] ?? [];
        if (!is_callable($c)) {
            return (array) $c;
        }
        $k = \App\Core\Club::id() . '|' . $this->key() . '|' . $name;
        return self::$choiceMemo[$k] ??= array_map('strval', (array) $c());
    }

    /** Vide le mémo des choix (tests, changement de musée). */
    public static function forgetChoices(): void
    {
        self::$choiceMemo = [];
    }

    /**
     * Suggestions pour une option « search » ou « select » : au plus $max, filtrées sans tenir
     * compte des accents ni de la casse (libellé ou valeur). @return list<array{value:string,label:string}>
     */
    public function suggest(string $name, string $q, int $max = 20): array
    {
        $norm = fn (string $s) => strtolower((string) preg_replace('/[^a-z0-9]+/i', ' ', (string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s)));
        $q = trim($norm(mb_substr($q, 0, 60)));
        $out = [];
        foreach ($this->optionChoices($name) as $v => $label) {
            if ($q === '' || str_contains($norm((string) $label), $q) || str_contains($norm((string) $v), $q)) {
                $out[] = ['value' => (string) $v, 'label' => (string) $label];
                if (count($out) >= $max) {
                    break;
                }
            }
        }
        return $out;
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

    /**
     * Les données peuvent-elles être gardées en cache (Cache) et par les navigateurs ? Non pour un
     * tirage propre à chaque chargement (quiz « nouvelle question à chaque chargement ») : no-store.
     */
    public function cacheable(array $opt): bool
    {
        return true;
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
     * Options validées : valeur hors liste, hors bornes ou mal formée : valeur par défaut.
     * @return array<string,string>
     */
    public function clean(array $query): array
    {
        $out = [];
        foreach ($this->options() as $name => $o) {
            $v = $query[$name] ?? null;
            $v = is_scalar($v) ? trim((string) $v) : '';
            $def = (string) ($o['default'] ?? '');
            $type = $o['type'] ?? 'select';
            $ok = null;
            if ($v === '' || mb_strlen($v) > 80) {
                $ok = null;
            } elseif ($type === 'select' || $type === 'search') {
                $ok = array_key_exists($v, $this->optionChoices($name)) ? $v : null;
                // « free » : valeur hors liste acceptée, nettoyée par le widget (nom d'un club jamais affronté).
                if ($ok === null && !empty($o['free']) && method_exists($this, 'cleanName')) {
                    $ok = static::cleanName($v);
                }
            } elseif ($type === 'number') {
                $ok = preg_match('/^-?\d{1,6}$/', $v) && (int) $v >= (int) ($o['min'] ?? 0) && (int) $v <= (int) ($o['max'] ?? 999999) ? (string) (int) $v : null;
            } elseif ($type === 'date') {
                $md = ($o['format'] ?? '') === 'md';
                if (preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    $ok = $md ? $m[2] . '-' . $m[3] : $v;
                    if (!$md && ($o['min'] ?? '') === 'today' && $v <= date('Y-m-d')) {
                        $ok = null;
                    }
                } elseif ($md && preg_match('/^(\d\d)-(\d\d)$/', $v, $m) && checkdate((int) $m[1], (int) $m[2], 2000)) {
                    $ok = $v;
                }
            } else {
                $ok = !isset($o['pattern']) || preg_match($o['pattern'], $v) ? $v : null;
            }
            $out[$name] = $ok ?? $def;
        }
        return $out;
    }

    /** Catalogue : ce que l'API et le back-office disent du widget. */
    public function describe(string $lang = 'fr'): array
    {
        $opts = [];
        foreach ($this->options() as $name => $o) {
            $type = $o['type'] ?? 'select';
            $x = ['label' => $o['label_' . $lang] ?? $o['label'], 'type' => $type, 'default' => (string) ($o['default'] ?? '')];
            if ($type === 'select') {
                $x['choices'] = $this->optionChoices($name);
            } elseif ($type === 'search') {
                // Longue liste : les premiers choix (les plus fréquents) et l'adresse des suggestions.
                $all = $this->optionChoices($name);
                $x['choices'] = array_slice($all, 0, 400, true);
                $x['total'] = count($all);
                $x['suggest'] = '/api/v1/widgets/' . $this->key() . '/suggestions/' . $name;
            }
            foreach (['min', 'max', 'format', 'placeholder'] as $k) {
                if (isset($o[$k])) {
                    $x[$k] = $o[$k] === 'today' ? date('Y-m-d', strtotime('+1 day')) : $o[$k];
                }
            }
            $help = $o['help_' . $lang] ?? $o['help'] ?? null;
            if ($help !== null) {
                $x['help'] = (string) $help;
            }
            $opts[$name] = $x;
        }
        $cats = $this->category();
        return ['key' => $this->key(), 'name' => $this->name($lang), 'description' => $this->description($lang), 'category' => $cats,
            'category_labels' => array_map(fn ($k) => Registry::CATEGORIES[$k][$lang === 'en' ? 1 : 0] ?? $k, $cats), 'customizable' => $opts !== [], 'options' => $opts, 'height' => $this->height()];
    }
}
