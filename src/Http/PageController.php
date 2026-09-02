<?php

declare(strict_types=1);

namespace App\Http;

use App\Content;
use App\View;

/**
 * Rendu des pages publiques.
 */
final class PageController
{
    public static function register(Router $router): void
    {
        $router->get('/', static fn() => self::show(Content::HOME));
        // Une page se reconnaît à son seul identifiant : /solutions, /methode…
        $router->get('/{slug}', static fn(array $p) => self::show((string) $p['slug']));
    }

    public static function show(string $slug): void
    {
        $page = Content::isValidSlug($slug) ? Content::page($slug) : null;

        if ($page === null) {
            self::notFound($slug);
            return;
        }

        $site = Content::site();
        $data = [
            'site'       => $site,
            'page'       => $page,
            'sections'   => $page['sections'],
            'navigation' => Content::navigation(),
            'shapesData' => self::shapeDescriptors($page),
        ];

        // Une navigation sans rechargement ne réclame que le corps de la page :
        // le nuage de particules et l'en-tête restent en place.
        if (self::wantsFragment()) {
            Response::html(View::capture('page', $data), 200, [
                'X-Page-Title'  => rawurlencode(self::documentTitle($site, $page)),
                'X-Page-Slug'   => $slug,
                'X-Page-Shapes' => rawurlencode(View::json($data['shapesData'])),
            ]);
            return;
        }

        View::render('page', $data);
    }

    public static function notFound(string $slug = ''): void
    {
        http_response_code(404);
        $site = Content::site();
        View::render('error', [
            'site'       => $site,
            'page'       => ['title' => 'Page introuvable', 'slug' => '404'],
            'navigation' => Content::navigation(),
            'shapesData' => [],
            'code'       => 404,
            'message'    => 'Cette page n\'existe pas.',
        ]);
    }

    /**
     * Descripteurs de formes déposés dans la page : le front sait quoi dessiner
     * sans requête préalable.
     *
     * @param  array<string,mixed> $page
     * @return array<string,array<string,mixed>>
     */
    public static function shapeDescriptors(array $page): array
    {
        $shapes = [];
        foreach ($page['sections'] as $section) {
            $shape = $section['shape'];
            $id = (string) $section['id'];

            $descriptor = [
                'id'       => $id,
                'type'     => $shape['type'],
                'count'    => $shape['count'],
                'spin'     => (float) ($shape['spin'] ?? 0),
                'spinAxis' => ($shape['spinAxis'] ?? 'y') === 'z' ? 'z' : 'y',
                'offsetX'  => (float) ($shape['offsetX'] ?? 0),
                'offsetY'  => (float) ($shape['offsetY'] ?? 0),
                'label'    => $shape['label'] ?? null,
                'shapeUrl' => '/api/shape/' . rawurlencode((string) $page['slug'])
                              . '/' . rawurlencode($id),
            ];

            // Une forme textuelle est tracée par le navigateur, avec ses polices :
            // il lui faut la recette complète.
            if ($shape['type'] === 'text') {
                $descriptor += [
                    'text'  => $shape['text'] ?? '',
                    'font'  => $shape['font'] ?? '900 220px Montserrat, sans-serif',
                    'depth' => (float) ($shape['depth'] ?? 0.08),
                    'scale' => (float) ($shape['scale'] ?? 1.0),
                    'seed'  => (int) ($shape['seed'] ?? 1337),
                ];
            }

            $shapes[$id] = $descriptor;
        }

        return $shapes;
    }

    /**
     * @param array<string,mixed> $site
     * @param array<string,mixed> $page
     */
    public static function documentTitle(array $site, array $page): string
    {
        $suffix = (string) ($site['meta']['titleSuffix'] ?? $site['name'] ?? '');
        $title = (string) ($page['title'] ?? '');

        return $suffix !== '' && $title !== '' ? "{$title} — {$suffix}" : ($title ?: $suffix);
    }

    /** Vrai si la requête vient de la navigation interne et non de la barre d'adresse. */
    private static function wantsFragment(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fragment';
    }
}
