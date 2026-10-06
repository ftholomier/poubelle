<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\Response;
use App\Core\Settings;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Index;
use App\Services\I18n;

/** robots.txt et plan du site XML (fiches, rubriques, pages calculées, images). */
final class Seo
{
    private const CACHE = STORAGE_PATH . '/cache/sitemap.xml';

    /** Site pas encore ouvert au public : page d'attente active ou mot de passe d'accès. */
    public static function closed(): bool
    {
        return (bool) Settings::get('waiting.enabled', false) || (string) Settings::get('general.front_password', '') !== '';
    }

    /** Rien ne doit être indexé : site fermé au public, ou masqué aux moteurs (Réglages › Général). */
    public static function hidden(): bool
    {
        return self::closed() || (bool) Settings::get('general.noindex', false);
    }

    public static function robots(): Response
    {
        $base = base_url();
        $lines = ['User-agent: *'];
        if (self::hidden()) {
            // Site pas encore ouvert : rien ne doit être indexé.
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/api/', '/recherche/', '/partage/', '/pdf/', '/en/pdf/', '/*?q=', '/*&q=', '/*?tri=', '/*&tri=', '/*?vue=', '/*&vue=', '/*?fragment=', '/*?apercu'] as $d) {
                $lines[] = 'Disallow: ' . $d;
            }
            $lines[] = 'Allow: /media/';
            $lines[] = '';
            $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
        }
        return new Response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public static function sitemap(): Response
    {
        $indexFile = \App\Data\Index::CACHE;
        $fresh = is_file(self::CACHE) && filemtime(self::CACHE) > time() - 6 * 3600
            && (!is_file($indexFile) || filemtime(self::CACHE) >= filemtime($indexFile));
        // Sans adresse publique réglée, l'adresse vient de la requête : on ne met pas en cache
        // (un en-tête Host forgé ne doit pas empoisonner le plan du site).
        $configured = trim((string) Settings::get('general.base_url', '')) !== '';
        if (!$fresh || !$configured) {
            $xml = self::build($configured);
        } else {
            $xml = (string) file_get_contents(self::CACHE);
        }
        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public static function build(bool $write = true): string
    {
        $base = base_url();
        $langs = I18n::enabled();
        $urls = [];
        $add = function (string $path, ?string $lastmod, string $freq, string $prio, bool $translated, ?string $image = null, string $imageTitle = '') use (&$urls) {
            $urls[] = compact('path', 'lastmod', 'freq', 'prio', 'translated', 'image', 'imageTitle');
        };

        // Pages fixes
        $add('/', date('c'), 'daily', '1.0', true);
        foreach (['/matchs/', '/nos-lions/', '/saisons/', '/face-a-face/', '/records/', '/chiffres/', '/bilans/coupe-de-france/', '/bilans/stade-auguste-bonal/',
            '/interactif/', '/interactif/quiz/', '/interactif/defi/', '/interactif/quiz-live/championnat/', '/interactif/album/', '/interactif/maillots/', '/interactif/frise/', '/interactif/carto/', '/interactif/retro-direct/', '/interactif/fil-jaune/', '/interactif/souvenirs/',
            '/centenaire/', '/centenaire/100-moments/', '/reserves/', '/faire-un-don/', '/contribuer/', '/contact/', '/partage-et-newsletter/', '/mentions-legales/', '/confidentialite/', '/cookies/'] as $p) {
            $add($p, null, 'weekly', '0.7', true);
        }
        // Rétro-Direct programmés
        foreach (\App\Services\RetroDirect::program() as $e) {
            if ($e['state'] !== 'termine') {
                $add('/interactif/retro-direct/' . \App\Services\RetroDirect::slug($e['s']) . '/', null, 'daily', '0.6', true);
            }
        }
        // Rubriques
        foreach (Categories::all() as $c) {
            if (!empty($c['path']) && empty($c['technical']) && empty($c['season']) && Site::count($c['slug']) > 0) {
                $add((string) $c['path'], null, 'weekly', '0.6', true);
            }
        }
        // Saisons et adversaires (pages calculées)
        foreach (array_keys(Derived::part('seasons')) as $season) {
            $add('/matchs/' . $season . '/', null, 'monthly', '0.6', true);
        }
        foreach (Derived::part('clubs') as $club => $c) {
            if (($c['count'] ?? 0) > 0) {
                $add('/face-a-face/' . $club . '/', null, 'monthly', '0.5', true);
            }
        }
        // Fiches
        foreach (Index::published() as $s) {
            if (!$s['path'] || $s['path'] === '/') {
                continue;
            }
            if ($s['type'] === 'page' && ($doc = \App\Data\Fiches::get((int) $s['id'])) && Pages::isListingRedirect($doc)) {
                continue;
            }
            $prio = match ($s['type']) { 'personne' => '0.8', 'match' => '0.6', default => '0.7' };
            $add($s['path'], $s['modified'] ?: $s['date'], 'monthly', $prio, (bool) $s['has_en'], $s['image'], $s['title']);
        }

        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        $count = 0;
        foreach ($urls as $u) {
            $variants = ['fr' => $u['path']];
            if ($u['translated'] && in_array('en', $langs, true)) {
                $variants['en'] = '/en' . $u['path'];
            }
            foreach ($variants as $lang => $path) {
                $x .= '<url><loc>' . self::x($base . $path) . '</loc>';
                if ($u['lastmod']) {
                    $x .= '<lastmod>' . self::x(date('c', strtotime((string) $u['lastmod']) ?: time())) . '</lastmod>';
                }
                $x .= '<changefreq>' . $u['freq'] . '</changefreq><priority>' . ($lang === 'fr' ? $u['prio'] : number_format((float) $u['prio'] - 0.1, 1)) . '</priority>';
                if (count($variants) > 1) {
                    foreach ($variants as $l => $p) {
                        $x .= '<xhtml:link rel="alternate" hreflang="' . $l . '" href="' . self::x($base . $p) . '"/>';
                    }
                    $x .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . self::x($base . $u['path']) . '"/>';
                }
                if ($u['image'] && $lang === 'fr') {
                    $x .= '<image:image><image:loc>' . self::x($base . img($u['image'], 1200)) . '</image:loc></image:image>';
                }
                $x .= "</url>\n";
                $count++;
            }
        }
        $x .= "</urlset>\n";
        if ($write) {
            $dir = dirname(self::CACHE);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents(self::CACHE . '.tmp', $x, LOCK_EX);
            rename(self::CACHE . '.tmp', self::CACHE);
        }
        return $x;
    }

    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
