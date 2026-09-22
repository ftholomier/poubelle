<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Services\I18n;
use App\Services\JobLifecycle;
use App\Storage\Index;

/** Plan du site, avec une entrée par langue et les balises hreflang associées. */
final class SitemapController extends Controller
{
    private const TTL = 3600;

    public function xml(Request $request, array $params): Response
    {
        // Le plan complet pèse quelques mégaoctets : on le garde sur disque
        // plutôt que de le reconstruire à chaque passage d'un robot.
        $cache = Config::path('data') . '/index/sitemap.xml';
        if (is_file($cache) && (time() - (int) filemtime($cache)) < self::TTL) {
            return $this->respond((string) file_get_contents($cache));
        }

        $xml = $this->build();
        @file_put_contents($cache, $xml, LOCK_EX);

        return $this->respond($xml);
    }

    private function respond(string $xml): Response
    {
        return Response::text($xml)
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600');
    }

    private function build(): string
    {
        $base = rtrim((string) Config::get('site.url'), '/');

        /**
         * Seules les langues réellement traduites figurent au plan. Déclarer
         * sept versions d'une page qui n'existe qu'en français revenait à
         * proposer six doublons à l'indexation.
         */
        $languages = array_values(array_filter(
            array_keys(I18n::languages()),
            static fn(string $code) => $code === 'fr' || I18n::hasTranslations($code),
        ));

        /** @var array<int, array{path:string, lastmod:string, priority:string}> $paths */
        $paths = [
            ['path' => '/',            'lastmod' => '', 'priority' => '1.0'],
            ['path' => '/offres',      'lastmod' => '', 'priority' => '0.9'],
            ['path' => '/cv',          'lastmod' => '', 'priority' => '0.8'],
            ['path' => '/employeurs',  'lastmod' => '', 'priority' => '0.7'],
            ['path' => '/ressources',  'lastmod' => '', 'priority' => '0.5'],
            ['path' => '/deposer-un-cv',       'lastmod' => '', 'priority' => '0.9'],
            ['path' => '/deposer-une-annonce', 'lastmod' => '', 'priority' => '0.9'],
        ];

        foreach (Index::load('jobs') as $job) {
            // Une annonce périmée n'a plus à être proposée à l'indexation.
            if (($job['status'] ?? '') !== 'publish' || JobLifecycle::isExpired($job)) {
                continue;
            }
            $paths[] = ['path' => '/offre/' . $job['slug'],
                        'lastmod' => (string) $job['published_at'], 'priority' => '0.7'];
        }
        foreach (Index::load('cv') as $cv) {
            if (($cv['status'] ?? '') !== 'publish') {
                continue;
            }
            $paths[] = ['path' => '/cv/' . $cv['slug'],
                        'lastmod' => (string) $cv['published_at'], 'priority' => '0.5'];
        }
        foreach (Index::load('employers') as $employer) {
            $paths[] = ['path' => '/employeur/' . $employer['slug'], 'lastmod' => '', 'priority' => '0.4'];
        }
        foreach (Index::load('pages') as $page) {
            $paths[] = ['path' => '/' . $page['slug'],
                        'lastmod' => (string) $page['updated_at'], 'priority' => '0.6'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
             . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($paths as $entry) {
            foreach ($languages as $lang) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . e($base . I18n::url($entry['path'], $lang)) . "</loc>\n";
                if ($entry['lastmod'] !== '') {
                    $timestamp = strtotime($entry['lastmod']);
                    if ($timestamp !== false) {
                        $xml .= '    <lastmod>' . date('Y-m-d', $timestamp) . "</lastmod>\n";
                    }
                }
                // Une seule langue ne justifie aucune balise d'alternative.
                if (count($languages) > 1) {
                    foreach ($languages as $alternate) {
                        $xml .= '    <xhtml:link rel="alternate" hreflang="' . e($alternate)
                              . '" href="' . e($base . I18n::url($entry['path'], $alternate)) . "\"/>\n";
                    }
                }
                $xml .= '    <priority>' . e($entry['priority']) . "</priority>\n";
                $xml .= "  </url>\n";
            }
        }
        return $xml . '</urlset>';
    }
}
