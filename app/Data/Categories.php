<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/**
 * Rubriques (catégories WordPress reprises à l'identique) : arborescence,
 * adresses (option B), ordre manuel des mosaïques.
 */
final class Categories
{
    public const FILE = DATA_PATH . '/categories.json';

    /** Rubriques racines du menu principal, dans l'ordre. */
    public const ROOTS = [
        'matchs-fc-sochaux-retro-fcsm',
        'nos-lions-fc-sochaux-retro-fcsm',
        'supporters',
        'infrastructures',
        'symboles',
    ];

    /** Ordres d'affichage possibles d'une mosaïque (réglés par rubrique dans le back-office). */
    public const SORTS = [
        'selection' => 'Ordre manuel (glisser-déposer)',
        'ancien' => 'Chronologique : du plus ancien au plus récent',
        'recent' => 'Chronologique : du plus récent au plus ancien',
        'az' => 'Alphabétique (A → Z)',
    ];

    private static ?array $byPath = null;

    /** @return array<string,array> */
    public static function all(): array
    {
        return JsonStore::read(self::FILE, []) ?? [];
    }

    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function save(array $cats, ?array $user = null): void
    {
        JsonStore::write(self::FILE, $cats);
        self::$byPath = null;
        Activity::log($user, 'a modifié les rubriques', null);
    }

    /**
     * Rubriques de saison manquantes : toute saison, de la fondation du club à la saison en cours,
     * a sa rubrique (Matchs › Années 50 › 1952 - 1953), avec sa décennie si besoin. Rien d'existant
     * n'est modifié. Renvoie le nombre de rubriques créées.
     */
    public static function ensureSeasons(): int
    {
        $from = (int) \App\Core\Club::s('founded');
        if ($from < 1850) {
            return 0;
        }
        $to = (int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1;
        $made = 0;
        JsonStore::update(self::FILE, function (?array $cats) use ($from, $to, &$made) {
            $cats = $cats ?: [];
            // Rubrique « Matchs » : parente des décennies existantes, sinon la racine du modèle.
            $root = 'matchs-fc-sochaux-retro-fcsm';
            $decades = []; // décennie (1950) => slug de sa rubrique
            $seasons = [];
            foreach ($cats as $slug => $c) {
                if (preg_match('/^Ann[ée]es\s+(\d{2,4})$/u', trim((string) ($c['name'] ?? '')), $m)) {
                    $n = (int) $m[1];
                    $decades[$n < 100 ? ($n >= 20 ? 1900 + $n : 2000 + $n) : $n] = (string) $slug;
                    $root = (string) ($c['parent'] ?? $root);
                }
                if (!empty($c['season'])) {
                    $seasons[(string) $c['season']] = true;
                }
            }
            if (!isset($cats[$root])) {
                return $cats;
            }
            $id = (int) max(array_merge([0], array_map(fn ($c) => (int) ($c['id'] ?? 0), $cats)));
            for ($y = $from; $y <= $to; $y++) {
                $season = $y . '-' . ($y + 1);
                if (isset($seasons[$season]) || isset($cats[$season])) {
                    continue;
                }
                $dec = intdiv($y, 10) * 10;
                if (!isset($decades[$dec])) {
                    $short = $dec < 2000 ? (string) ($dec % 100) : (string) $dec;
                    $dkey = 'annees-' . $short . '-fc-sochaux-retro-fcsm';
                    if (isset($cats[$dkey])) {
                        continue; // nom inattendu : on ne touche à rien
                    }
                    $cats[$dkey] = ['id' => ++$id, 'slug' => $dkey, 'name' => 'Années ' . $short, 'label' => null, 'position' => $dec, 'description' => '',
                        'parent' => $root, 'path' => '/matchs/annees-' . $short . '/', 'technical' => false, 'season' => null, 'old_path' => null, 'order' => [], 'wp_count' => 0];
                    $decades[$dec] = $dkey;
                    $made++;
                }
                $cats[$season] = ['id' => ++$id, 'slug' => $season, 'name' => $y . ' - ' . ($y + 1), 'label' => null, 'position' => null, 'description' => '',
                    'parent' => $decades[$dec], 'path' => '/matchs/' . $season . '/', 'technical' => false, 'season' => $season, 'old_path' => null, 'order' => [], 'wp_count' => 0];
                $made++;
            }
            return $cats;
        }, []);
        if ($made) {
            self::$byPath = null;
        }
        return $made;
    }

    /**
     * Ordre d'affichage d'une rubrique sur le site : celui choisi dans le back-office, sinon
     * l'ordre manuel s'il existe (saisons, décennies, compétitions, rubriques éditoriales),
     * sinon les plus récents d'abord (matchs) ou l'ordre alphabétique (personnes).
     */
    public static function displaySort(string $slug): string
    {
        $c = self::get($slug) ?? [];
        // Une saison ou une décennie de matchs se lit toujours dans l'ordre du calendrier : premier match
        // en haut, dernier en bas.
        if (!empty($c['season']) || self::isDecade($slug)) {
            return 'ancien';
        }
        $hasOrder = !empty($c['order']);
        $sort = $c['sort'] ?? null;
        if (is_string($sort) && isset(self::SORTS[$sort]) && ($sort !== 'selection' || $hasOrder)) {
            return $sort;
        }
        return match (self::root($slug)) {
            'matchs-fc-sochaux-retro-fcsm' => $hasOrder && !empty($c['parent']) ? 'selection' : 'recent',
            'nos-lions-fc-sochaux-retro-fcsm' => 'az',
            default => $hasOrder ? 'selection' : 'recent',
        };
    }

    /** Rubrique d'une décennie de matchs (« Années 80 », « Années 2020 »). */
    public static function isDecade(string $slug): bool
    {
        return (bool) preg_match('/^annees-\d+/', $slug) && str_starts_with((string) (self::get($slug)['path'] ?? ''), '/matchs/');
    }

    public static function byPath(string $path): ?array
    {
        if (self::$byPath === null) {
            self::$byPath = [];
            foreach (self::all() as $slug => $c) {
                if (!empty($c['path'])) {
                    self::$byPath[$c['path']] = $slug;
                }
            }
        }
        $slug = self::$byPath[$path] ?? null;
        return $slug ? self::get($slug) : null;
    }

    /** @return list<array> sous-rubriques directes, dans l'ordre */
    public static function children(string $slug): array
    {
        $out = array_values(array_filter(self::all(), fn ($c) => ($c['parent'] ?? null) === $slug && empty($c['technical'])));
        usort($out, fn ($a, $b) => ($a['position'] ?? 999) <=> ($b['position'] ?? 999) ?: self::seasonOrName($a, $b));
        return $out;
    }

    private static function seasonOrName(array $a, array $b): int
    {
        if (!empty($a['season']) && !empty($b['season'])) {
            return strcmp($a['season'], $b['season']);
        }
        return strcoll($a['name'], $b['name']);
    }

    /** @return list<string> slugs de la rubrique et de toutes ses descendantes */
    public static function descendants(string $slug, bool $self = true): array
    {
        $all = self::all();
        $out = $self ? [$slug] : [];
        $stack = [$slug];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($all as $s => $c) {
                if (($c['parent'] ?? null) === $cur) {
                    $out[] = $s;
                    $stack[] = $s;
                }
            }
        }
        return $out;
    }

    /** @return list<array> ancêtres, de la racine à la rubrique (incluse) */
    public static function trail(string $slug): array
    {
        $all = self::all();
        $out = [];
        $guard = 0;
        while ($slug !== null && isset($all[$slug]) && $guard++ < 10) {
            array_unshift($out, $all[$slug]);
            $slug = $all[$slug]['parent'] ?? null;
        }
        return $out;
    }

    public static function root(string $slug): string
    {
        $t = self::trail($slug);
        return $t ? $t[0]['slug'] : $slug;
    }

    /** Nom court affiché (« Années 80 », « Coupe de France »). */
    public static function label(string $slug): string
    {
        $c = self::get($slug);
        if (!$c) {
            return $slug;
        }
        if (!empty($c['label_en']) && \App\Services\I18n::isEn()) {
            return (string) $c['label_en'];
        }
        if (!empty($c['label'])) {
            return (string) $c['label'];
        }
        $name = $c['name'];
        if (mb_strtoupper($name) === $name && mb_strlen($name) > 3) {
            $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
        }
        return str_replace(['Nos Lions', ' D’', " D'"], ['Nos Lions', ' d’', " d'"], $name);
    }

    /** Rubrique « principale » d'une fiche (la plus profonde, hors techniques). */
    public static function primaryOf(array $catSlugs): ?string
    {
        $best = null;
        $bestDepth = -1;
        foreach ($catSlugs as $s) {
            $c = self::get($s);
            if (!$c || !empty($c['technical']) || !empty($c['season'])) {
                continue;
            }
            $depth = count(self::trail($s));
            if ($depth > $bestDepth) {
                $best = $s;
                $bestDepth = $depth;
            }
        }
        return $best;
    }

    public static function forget(): void
    {
        self::$byPath = null;
        JsonStore::forget(self::FILE);
    }
}
