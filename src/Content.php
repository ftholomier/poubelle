<?php

declare(strict_types=1);

namespace App;

/**
 * Chargeur du contenu éditorial. Toute la matière du site vit dans content/*.json :
 * aucune base de données, un simple fichier suffit pour ajouter ou réordonner une section.
 */
final class Content
{
    /** @var array<string,array<mixed>> */
    private static array $memo = [];

    /**
     * @return array<string,mixed>
     */
    public static function site(): array
    {
        return self::load('site');
    }

    /**
     * Sections du site, dans l'ordre d'apparition, normalisées.
     *
     * @return list<array<string,mixed>>
     */
    public static function sections(): array
    {
        $raw = self::load('sections');
        $items = $raw['sections'] ?? [];
        $out = [];
        foreach ($items as $index => $section) {
            if (!is_array($section) || !isset($section['id'])) {
                continue;
            }
            $section['index'] = $index;
            $section['shape'] = self::normalizeShape($section['shape'] ?? null, (string) $section['id']);
            $out[] = $section;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function section(string $id): ?array
    {
        foreach (self::sections() as $section) {
            if ($section['id'] === $id) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Toutes les formes déclarées dans le site, indexées par identifiant de section.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function shapes(): array
    {
        $shapes = [];
        foreach (self::sections() as $section) {
            $shapes[(string) $section['id']] = $section['shape'];
        }

        return $shapes;
    }

    /**
     * Complète une déclaration de forme avec ses valeurs par défaut.
     *
     * @param  array<string,mixed>|string|null $shape
     * @return array<string,mixed>
     */
    private static function normalizeShape(array|string|null $shape, string $sectionId): array
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
        $shape['id'] ??= $sectionId;
        $shape['count'] = (int) ($shape['count'] ?? Config::get('shape.default_points', 12000));
        $shape['count'] = max(64, min($shape['count'], (int) Config::get('shape.max_points', 40000)));
        $shape['depth'] = (float) ($shape['depth'] ?? 0.12);
        $shape['seed'] = (int) ($shape['seed'] ?? 1337);

        return $shape;
    }

    /**
     * @return array<string,mixed>
     */
    private static function load(string $name): array
    {
        if (isset(self::$memo[$name])) {
            return self::$memo[$name];
        }

        $file = APP_CONTENT . '/' . $name . '.json';
        if (!is_file($file)) {
            throw new \RuntimeException("Fichier de contenu introuvable : content/{$name}.json");
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new \RuntimeException(
                "content/{$name}.json invalide : " . json_last_error_msg()
            );
        }

        return self::$memo[$name] = $data;
    }
}
