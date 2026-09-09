<?php
declare(strict_types=1);

namespace App;

/** Données structurées schema.org (organisation, lieux, bureaux, articles). */
final class Seo
{
    public static function organization(): array
    {
        $settings = Content::settings();
        $sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));

        $locations = [];
        foreach ($sites as $site) {
            $locations[] = [
                '@type' => 'LocalBusiness',
                'name' => (string) ($site['name'] ?? ''),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => (string) ($site['address'] ?? ''),
                    'postalCode' => (string) ($site['zip'] ?? ''),
                    'addressLocality' => (string) ($site['city'] ?? 'Besançon'),
                    'addressCountry' => 'FR',
                ],
                'image' => $site['photo'] ? Config::baseUrl() . $site['photo'] : null,
            ];
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) ($settings['site']['name'] ?? 'Le iOiO'),
            'url' => Config::baseUrl(),
            'logo' => Config::baseUrl() . (string) ($settings['site']['logo'] ?? '/assets/img/ioio-logo.png'),
            'description' => (string) ($settings['seo']['description'] ?? ''),
            'department' => $locations,
        ];
        $email = (string) ($settings['contact']['email'] ?? '');
        $phone = (string) ($settings['contact']['phone'] ?? '');
        if ($email !== '') {
            $data['email'] = $email;
        }
        if ($phone !== '') {
            $data['telephone'] = $phone;
        }
        $social = array_values(array_filter(array_map(
            static fn (array $s): string => (string) ($s['url'] ?? ''),
            (array) ($settings['social'] ?? [])
        )));
        if ($social !== []) {
            $data['sameAs'] = $social;
        }

        return array_filter($data, static fn ($v): bool => $v !== null && $v !== '' && $v !== []);
    }

    /** Fiche d'un bureau : offre de location. */
    public static function office(array $office, string $lang): array
    {
        $settings = Content::settings();
        $site = null;
        foreach ((array) ($settings['sites'] ?? []) as $entry) {
            if ((string) ($entry['id'] ?? '') === (string) ($office['site'] ?? '')) {
                $site = $entry;
            }
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $office['name'],
            'description' => (string) ($office['description'] ?? ''),
            'image' => $office['cover'] !== '' ? Config::baseUrl() . $office['cover'] : null,
            'category' => (string) $office['typeLabel'],
            'offers' => array_filter([
                '@type' => 'Offer',
                'price' => (int) ($office['price'] ?? 0) ?: null,
                'priceCurrency' => (string) ($office['currency'] ?? 'EUR'),
                'availability' => ($office['status'] ?? '') === 'available'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => Router::absolute('office', $lang, ['id' => (string) $office['id']]),
                'areaServed' => $site !== null ? (string) ($site['city'] ?? 'Besançon') : null,
            ]),
        ]);
    }

    public static function article(array $post, string $lang): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => Content::i18n($post, 'title', $lang),
            'description' => Content::i18n($post, 'excerpt', $lang),
            'datePublished' => (string) ($post['date'] ?? ''),
            'image' => !empty($post['image']) ? Config::baseUrl() . $post['image'] : null,
            'mainEntityOfPage' => Router::absolute('post', $lang, ['slug' => (string) ($post['slug'] ?? '')]),
            'publisher' => ['@type' => 'Organization', 'name' => (string) (Content::settings()['site']['name'] ?? 'Le iOiO')],
        ]);
    }

    /** Questions fréquentes de l'accueil. */
    public static function faq(array $items): ?array
    {
        $entities = [];
        foreach ($items as $item) {
            $q = (string) ($item['q'] ?? '');
            $a = (string) ($item['a'] ?? '');
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
        return $entities === [] ? null : ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities];
    }

    /** sitemap.xml, une entrée par page et par langue. */
    public static function sitemap(): string
    {
        $urls = [];
        $add = static function (string $loc, string $priority, string $changefreq) use (&$urls): void {
            $urls[] = '  <url>' . "\n"
                . '    <loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . "\n"
                . '    <changefreq>' . $changefreq . '</changefreq>' . "\n"
                . '    <priority>' . $priority . '</priority>' . "\n"
                . '  </url>';
        };

        foreach (Config::LANGS as $lang) {
            foreach (['home' => '1.0', 'spaces' => '0.9', 'offices' => '0.9', 'news' => '0.6', 'contact' => '0.8', 'legal' => '0.2', 'privacy' => '0.2'] as $route => $priority) {
                $page = Content::page(Router::PAGE_OF_ROUTE[$route] ?? $route, $lang);
                if ($page !== [] && !Content::isPublished($page)) {
                    continue;
                }
                $add(Router::absolute($route, $lang), $priority, $route === 'offices' ? 'daily' : 'monthly');
            }
            foreach (Offices::published() as $office) {
                $add(Router::absolute('office', $lang, ['id' => (string) $office['id']]), '0.7', 'weekly');
            }
            foreach (Content::publishedPosts() as $post) {
                $add(Router::absolute('post', $lang, ['slug' => (string) ($post['slug'] ?? '')]), '0.5', 'monthly');
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . implode("\n", $urls) . "\n"
            . '</urlset>' . "\n";
    }
}
