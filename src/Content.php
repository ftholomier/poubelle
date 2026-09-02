<?php

declare(strict_types=1);

namespace App;

use App\Theme\Palette;

/**
 * Chargeur du contenu éditorial.
 *
 * Toute la matière du site vit dans content/ : site.json pour les réglages
 * globaux, un fichier par page dans content/pages/. Aucune base de données —
 * ajouter une page revient à déposer un fichier.
 */
final class Content
{
    /** @var array<string,array<mixed>> */
    private static array $memo = [];

    /** Page servie à la racine du site. */
    public const HOME = 'accueil';

    /**
     * Réglages globaux, charte graphique déjà dérivée de la couleur dominante.
     *
     * @return array<string,mixed>
     */
    public static function site(): array
    {
        if (isset(self::$memo['__site'])) {
            return self::$memo['__site'];
        }

        $site = self::readJson(APP_CONTENT . '/site.json');
        $site['theme'] = Palette::build($site['theme'] ?? []);

        return self::$memo['__site'] = $site;
    }

    /**
     * Toutes les pages, triées selon leur rang de navigation.
     *
     * @return list<array<string,mixed>>
     */
    public static function pages(): array
    {
        if (isset(self::$memo['__pages'])) {
            return self::$memo['__pages'];
        }

        $pages = [];
        foreach (glob(APP_CONTENT . '/pages/*.json') ?: [] as $file) {
            $slug = basename($file, '.json');
            if (!self::isValidSlug($slug)) {
                continue;
            }
            $page = self::readJson($file);
            $page['slug'] = $slug;
            $page['url'] = $slug === self::HOME ? '/' : '/' . $slug;
            $page['order'] = (int) ($page['order'] ?? 99);
            $page['navLabel'] = (string) ($page['navLabel'] ?? $page['title'] ?? $slug);
            $page['inNav'] = ($page['inNav'] ?? true) !== false;
            $page['sections'] = self::normalizeSections($page['sections'] ?? [], $slug);
            $pages[] = $page;
        }

        usort($pages, static fn(array $a, array $b): int => [$a['order'], $a['slug']] <=> [$b['order'], $b['slug']]);

        return self::$memo['__pages'] = $pages;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function page(string $slug): ?array
    {
        foreach (self::pages() as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }

        return null;
    }

    /**
     * Entrées de menu, déduites des pages : déposer un fichier suffit à
     * faire apparaître le lien.
     *
     * @return list<array{label: string, url: string, slug: string}>
     */
    public static function navigation(): array
    {
        $nav = [];
        foreach (self::pages() as $page) {
            if (!$page['inNav']) {
                continue;
            }
            $nav[] = ['label' => $page['navLabel'], 'url' => $page['url'], 'slug' => $page['slug']];
        }

        return $nav;
    }

    /**
     * Retrouve une section par son identifiant complet « page/section ».
     *
     * @return array<string,mixed>|null
     */
    public static function section(string $pageSlug, string $sectionId): ?array
    {
        $page = self::page($pageSlug);
        foreach ($page['sections'] ?? [] as $section) {
            if ($section['id'] === $sectionId) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Un identifiant de page ou de section doit rester un mot simple :
     * il finit dans une URL et dans un nom de fichier.
     */
    public static function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /**
     * @param  array<mixed> $sections
     * @return list<array<string,mixed>>
     */
    private static function normalizeSections(array $sections, string $pageSlug): array
    {
        $out = [];
        foreach ($sections as $index => $section) {
            if (!is_array($section) || !isset($section['id'])) {
                continue;
            }
            $id = (string) $section['id'];
            $section['index'] = $index;
            $section['page'] = $pageSlug;
            // La clé de forme porte la page : deux sections « hero » peuvent
            // coexister sur deux pages sans se marcher dessus.
            $section['shapeKey'] = $pageSlug . '/' . $id;
            $section['shape'] = self::normalizeShape($section['shape'] ?? null, $section['shapeKey']);
            $out[] = $section;
        }

        return $out;
    }

    /**
     * Complète une déclaration de forme avec ses valeurs par défaut.
     *
     * @param  array<string,mixed>|string|null $shape
     * @return array<string,mixed>
     */
    private static function normalizeShape(array|string|null $shape, string $key): array
    {
        if ($shape === null) {
            $shape = ['type' => 'preset', 'preset' => 'sphere'];
        }
        // Écriture courte : "shape": "sphere" ou "shape": "shapes/fusee.svg"
        if (is_string($shape)) {
            $shape = str_contains($shape, '/') || str_ends_with($shape, '.svg')
                ? ['type' => 'svg', 'src' => $shape]
                : ['type' => 'preset', 'preset' => $shape];
        }

        $shape['type'] ??= 'preset';
        $shape['id'] = $key;
        $shape['count'] = (int) ($shape['count'] ?? Config::get('shape.default_points', 12000));
        $shape['count'] = max(64, min($shape['count'], (int) Config::get('shape.max_points', 40000)));
        $shape['depth'] = (float) ($shape['depth'] ?? 0.12);
        $shape['seed'] = (int) ($shape['seed'] ?? 1337);

        return $shape;
    }

    /** Vide la mémoire interne, après une écriture par le back-office. */
    public static function forget(): void
    {
        self::$memo = [];
    }

    /**
     * @return array<string,mixed>
     */
    private static function readJson(string $file): array
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Fichier de contenu introuvable : ' . basename($file));
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new \RuntimeException(
                basename($file) . ' est un JSON invalide : ' . json_last_error_msg()
            );
        }

        return $data;
    }
}
