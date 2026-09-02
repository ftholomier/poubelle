<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;
use App\Content;
use App\Shape\PresetSampler;
use App\Shape\ShapeService;

/**
 * Points d'entrée JSON consommés par le front.
 */
final class Api
{
    public static function register(Router $router): void
    {
        $router
            ->get('/api', [self::class, 'index'])
            ->get('/api/site', [self::class, 'site'])
            ->get('/api/sections', [self::class, 'sections'])
            ->get('/api/shapes', [self::class, 'catalogue'])
            ->get('/api/shape/{id}', [self::class, 'shape'])
            ->get('/api/preview', [self::class, 'preview']);
    }

    public static function index(): void
    {
        Response::json([
            'name'      => 'API particules',
            'version'   => '1.0',
            'endpoints' => [
                'GET /api/site'          => 'Réglages globaux, navigation, thème',
                'GET /api/sections'      => 'Sections éditoriales et forme associée à chacune',
                'GET /api/shapes'        => 'Catalogue des formes disponibles (fichiers et préréglages)',
                'GET /api/shape/{id}'    => 'Nuage de points d\'une section — ?format=bin pour du Float32 brut',
                'GET /api/preview'       => 'Nuage de points à la volée — ?type=&src=&preset=&count=&mode=',
            ],
        ]);
    }

    public static function site(): void
    {
        Response::json(Content::site(), 200, (int) Config::get('cache.http_ttl', 3600));
    }

    public static function sections(): void
    {
        $sections = array_map(
            static function (array $section): array {
                // Le nuage lui-même est servi par /api/shape/{id} : la liste reste légère.
                $section['shapeUrl'] = '/api/shape/' . rawurlencode((string) $section['id']);
                return $section;
            },
            Content::sections()
        );

        Response::json(['sections' => $sections], 200, (int) Config::get('cache.http_ttl', 3600));
    }

    public static function catalogue(): void
    {
        $files = [];
        $root = APP_CONTENT . '/shapes';
        if (is_dir($root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                $extension = strtolower($file->getExtension());
                if (!in_array($extension, ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                    continue;
                }
                $files[] = [
                    'src'   => 'shapes/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)),
                    'type'  => $extension === 'svg' ? 'svg' : 'image',
                    'bytes' => $file->getSize(),
                ];
            }
            usort($files, static fn(array $a, array $b): int => strcmp($a['src'], $b['src']));
        }

        Response::json([
            'presets' => PresetSampler::AVAILABLE,
            'files'   => $files,
            'used'    => Content::shapes(),
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public static function shape(array $params): void
    {
        $section = Content::section($params['id'] ?? '');
        if ($section === null) {
            Response::error('Section inconnue : ' . ($params['id'] ?? ''), 404);
            return;
        }

        try {
            $shape = ShapeService::build($section['shape']);
        } catch (\Throwable $e) {
            Response::error('Génération de la forme impossible', 500, $e->getMessage());
            return;
        }

        self::emit($shape);
    }

    /**
     * Génération à la volée depuis les paramètres d'URL : utilisée par le laboratoire de formes.
     */
    public static function preview(): void
    {
        $query = $_GET;
        $type = strtolower((string) ($query['type'] ?? 'preset'));
        if (!in_array($type, ['svg', 'image', 'preset', 'text'], true)) {
            Response::error('Type de forme invalide : ' . $type, 422);
            return;
        }

        $count = (int) ($query['count'] ?? Config::get('shape.default_points', 12000));
        $shape = [
            'id'        => 'preview',
            'type'      => $type,
            'count'     => max(64, min($count, (int) Config::get('shape.max_points', 40000))),
            'src'       => (string) ($query['src'] ?? ''),
            'preset'    => (string) ($query['preset'] ?? 'sphere'),
            'mode'      => (string) ($query['mode'] ?? 'fill'),
            'fillRule'  => (string) ($query['fillRule'] ?? 'nonzero'),
            'criterion' => (string) ($query['criterion'] ?? 'auto'),
            'text'      => (string) ($query['text'] ?? ''),
            'depth'     => (float) ($query['depth'] ?? 0.12),
            'scale'     => (float) ($query['scale'] ?? 1.0),
            'seed'      => (int) ($query['seed'] ?? 1337),
            'colors'    => ($query['colors'] ?? '0') === '1',
        ];

        try {
            self::emit(ShapeService::build($shape));
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 422, $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $shape
     */
    private static function emit(array $shape): void
    {
        $ttl = (int) Config::get('cache.http_ttl', 3600);

        if (($_GET['format'] ?? 'json') === 'bin' && ($shape['source'] ?? '') === 'server') {
            Response::float32($shape['positions'], $ttl);
            return;
        }

        Response::json($shape, 200, $ttl);
    }
}
