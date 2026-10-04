<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\Response;

/** robots.txt et plan du site XML du site de l'association. */
final class Seo
{
    public static function robots(): Response
    {
        $lines = ['User-agent: *'];
        if (Site::hidden()) {
            $lines[] = 'Disallow: /';
        } else {
            foreach (['/admin', '/api/', '/nous-soutenir/adherer/merci/', '/*?'] as $d) {
                $lines[] = 'Disallow: ' . $d;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: ' . Host::abs('/sitemap.xml');
        }
        return new Response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Toutes les pages publiques : [adresse, dernière modification, priorité]. */
    public static function urls(): array
    {
        $u = [['/', date('Y-m-d'), '1.0']];
        foreach (['/association/', '/association/equipe/', '/association/statuts-et-documents/', '/nos-actions/', '/actualites/', '/agenda/', '/nous-soutenir/', '/nous-soutenir/adherer/', '/nous-soutenir/benevolat/', '/partenaires/', '/presse/', '/contact/', '/plan-du-site/', '/mentions-legales/', '/confidentialite/', '/cookies/'] as $p) {
            $u[] = [$p, null, '0.7'];
        }
        foreach (Content::actions() as $a) {
            $u[] = ['/nos-actions/' . $a['slug'] . '/', null, '0.8'];
        }
        foreach (Content::news() as $n) {
            $u[] = ['/actualites/' . $n['slug'] . '/', (string) ($n['date'] ?? ''), '0.6'];
        }
        foreach (array_merge(Content::events(), Content::events(true)) as $e) {
            if ($e['kind'] === 'asso') {
                $u[] = ['/agenda/' . $e['slug'] . '/', null, '0.5'];
            }
        }
        return $u;
    }

    public static function sitemap(): Response
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        if (!Site::hidden()) {
            foreach (self::urls() as [$path, $mod, $prio]) {
                $x .= '<url><loc>' . htmlspecialchars(Host::abs($path), ENT_XML1) . '</loc>'
                    . ($mod && preg_match('/^\d{4}-\d{2}-\d{2}$/', $mod) ? '<lastmod>' . $mod . '</lastmod>' : '')
                    . '<priority>' . $prio . '</priority></url>' . "\n";
            }
        }
        $x .= "</urlset>\n";
        return new Response($x, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
