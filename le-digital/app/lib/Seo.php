<?php
declare(strict_types=1);

namespace LD;

/**
 * Données structurées (JSON-LD), sitemap et robots.txt.
 */
final class Seo
{
    public static function business(): array
    {
        $s = Settings::all();
        $zones = Content::get('zones')['cities'] ?? [];
        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'ProfessionalService',
            '@id'         => abs_url('/#entreprise'),
            'name'        => $s['site']['name'] ?? 'LE-DIGITAL.com',
            'url'         => abs_url('/'),
            'logo'        => abs_url($s['site']['logo'] ?? '/assets/img/logo.png'),
            'image'       => abs_url($s['site']['og_image'] ?? '/assets/img/fred-situation-1600.webp'),
            'slogan'      => 'Le digital provoque la conversation. La conversation provoque la conversion.',
            'description' => 'Développement sur mesure boosté à l’IA (sites web, CRM métier, applications, automatisation) et formation DIGITAL 360.',
            'founder'     => ['@type' => 'Person', 'name' => $s['site']['owner'] ?? 'Frédéric Tholomier', 'jobTitle' => 'Développeur et formateur digital'],
            'areaServed'  => array_merge(array_map(static fn($z) => ['@type' => 'City', 'name' => $z['name']], $zones), [['@type' => 'Country', 'name' => 'France']]),
            'priceRange'  => 'Sur devis',
        ];
        if (!empty($s['contact']['phone'])) {
            $data['telephone'] = $s['contact']['phone'];
        }
        if (!empty($s['contact']['email'])) {
            $data['email'] = $s['contact']['email'];
        }
        if (!empty($s['contact']['linkedin'])) {
            $data['sameAs'] = [$s['contact']['linkedin']];
        }
        if (!empty($s['contact']['city'])) {
            $data['address'] = array_filter(['@type' => 'PostalAddress', 'streetAddress' => $s['contact']['address'] ?? '', 'postalCode' => $s['contact']['postal_code'] ?? '', 'addressLocality' => $s['contact']['city'], 'addressCountry' => 'FR']);
        }
        $reviews = Reviews::all(true);
        if (count($reviews) >= 3) {
            $sum = array_sum(array_map(static fn($r) => (int) ($r['rating'] ?? 5), $reviews));
            $data['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($sum / count($reviews), 1), 'reviewCount' => count($reviews)];
        }
        return $data;
    }

    public static function article(array $post): array
    {
        $s = Settings::all();
        return [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'headline'         => $post['title'],
            'description'      => $post['seo_description'] ?: ($post['excerpt'] ?? ''),
            'image'            => abs_url(($post['image'] ?? '') ?: ($s['site']['og_image'] ?? '')),
            'datePublished'    => substr((string) $post['date'], 0, 10),
            'dateModified'     => substr((string) ($post['updated_at'] ?? $post['date']), 0, 10),
            'author'           => ['@type' => 'Person', 'name' => $s['site']['owner'] ?? 'Frédéric Tholomier'],
            'publisher'        => ['@id' => abs_url('/#entreprise')],
            'mainEntityOfPage' => abs_url('/blog/' . $post['slug']),
            'articleSection'   => $post['theme'] ?? '',
        ];
    }

    public static function sitemap(): string
    {
        $urls = ['/', '/sur-mesure-ia', '/formation', '/accompagnement', '/mes-solutions', '/qui-suis-je', '/avis', '/blog', '/on-prend-rdv', '/mentions-legales'];
        foreach (Content::get('zones')['cities'] ?? [] as $z) {
            $urls[] = '/zones/' . $z['slug'];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . e(abs_url($u)) . '</loc><changefreq>' . ($u === '/' ? 'weekly' : 'monthly') . '</changefreq><priority>' . ($u === '/' ? '1.0' : '0.8') . "</priority></url>\n";
        }
        foreach (Posts::all(true) as $p) {
            $xml .= '  <url><loc>' . e(abs_url('/blog/' . $p['slug'])) . '</loc><lastmod>' . e(substr((string) ($p['updated_at'] ?? $p['date']), 0, 10)) . "</lastmod><priority>0.6</priority></url>\n";
        }
        return $xml . '</urlset>' . "\n";
    }

    public static function robots(): string
    {
        if (Settings::get('seo.noindex')) {
            return "User-agent: *\nDisallow: /\n";
        }
        return "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\n\nSitemap: " . abs_url('/sitemap.xml') . "\n";
    }
}
