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
