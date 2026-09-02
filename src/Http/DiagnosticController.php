<?php

declare(strict_types=1);

namespace App\Http;

use App\Content;
use App\View;

/**
 * Page de diagnostic.
 *
 * Le site dépend de modules JavaScript, dont le chargement peut échouer pour
 * des raisons invisibles depuis le serveur : type MIME refusé par le
 * navigateur, fichier absent, navigateur trop ancien. Cette page teste chaque
 * maillon et affiche ce qui bloque, plutôt que de laisser deviner.
 *
 * Elle n'expose ni chemin absolu, ni réglage sensible.
 */
final class DiagnosticController
{
    /** Fichiers indispensables au fonctionnement du site. */
    private const REQUIRED_ASSETS = [
        'assets/css/app.css',
        'assets/js/main.js',
        'assets/js/ui.js',
        'assets/js/particles/ParticleField.js',
        'assets/js/particles/DustField.js',
        'assets/js/particles/shaders.js',
        'assets/js/particles/shapeLoader.js',
        'assets/js/vendor/three.module.min.js',
    ];

    public static function register(Router $router): void
    {
        $router->get('/diagnostic', [self::class, 'show']);
    }

    public static function show(): void
    {
        View::render('diagnostic', [
            'site'       => Content::site(),
            'page'       => ['title' => 'Diagnostic', 'slug' => 'diagnostic'],
            'navigation' => [],
            'shapesData' => [],
            'checks'     => self::serverChecks(),
            'assets'     => self::assetChecks(),
        ], 'layout-plain');
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    private static function serverChecks(): array
    {
        $checks = [];

        $checks[] = [
            'label'  => 'Version de PHP',
            'ok'     => PHP_VERSION_ID >= 80100,
            'detail' => PHP_VERSION . (PHP_VERSION_ID >= 80100 ? '' : ' — PHP 8.1 minimum'),
        ];

        foreach (['json', 'dom', 'mbstring'] as $extension) {
            $checks[] = [
                'label'  => "Extension {$extension}",
                'ok'     => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'chargée' : 'absente — indispensable',
            ];
        }

        $checks[] = [
            'label'  => 'Extension gd',
            'ok'     => extension_loaded('gd'),
            'detail' => extension_loaded('gd')
                ? 'chargée'
                : 'absente — seules les formes issues d\'images sont concernées',
        ];

        $checks[] = [
            'label'  => 'Dossier de cache accessible en écriture',
            'ok'     => is_writable(APP_CACHE),
            'detail' => is_writable(APP_CACHE) ? 'var/cache' : 'var/cache n\'est pas inscriptible',
        ];

        try {
            $pages = Content::pages();
            $checks[] = [
                'label'  => 'Contenu lisible',
                'ok'     => $pages !== [],
                'detail' => count($pages) . ' page(s) trouvée(s)',
            ];
        } catch (\Throwable $e) {
            $checks[] = ['label' => 'Contenu lisible', 'ok' => false, 'detail' => $e->getMessage()];
        }

        return $checks;
    }

    /**
     * @return list<array{path: string, ok: bool, bytes: int}>
     */
    private static function assetChecks(): array
    {
        $out = [];
        foreach (self::REQUIRED_ASSETS as $path) {
            $file = APP_PUBLIC . '/' . $path;
            $out[] = [
                'path'  => $path,
                'ok'    => is_file($file) && filesize($file) > 0,
                'bytes' => is_file($file) ? (int) filesize($file) : 0,
            ];
        }

        return $out;
    }
}
