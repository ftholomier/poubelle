<?php
declare(strict_types=1);

namespace App;

/** Données structurées schema.org (organisation, lieux, bureaux, articles). */
final class Seo
{
    /**
     * L'entreprise, décrite comme un commerce local : adresse, coordonnées,
     * téléphone et fourchette de prix. C'est ce que Google lit pour la fiche
     * locale et les recherches « coworking Montbéliard ». Un seul lieu : il
     * porte directement l'adresse ; s'il y en a plusieurs, chacun devient un
     * établissement rattaché.
     */
    public static function organization(): array
    {
        $settings = Content::settings();
        $lang = I18n::lang();
        $sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));
        $name = (string) ($settings['site']['name'] ?? 'Le Signal');
        $phone = (string) ($settings['contact']['phone'] ?? '');

        $place = static function (array $site) use ($name, $phone, $lang): array {
            $hasGeo = isset($site['lat'], $site['lng']) && $site['lat'] !== '' && $site['lng'] !== '';
            return array_filter([
                '@type' => ['LocalBusiness', 'CoworkingSpace'],
                'name' => (string) ($site['name'] ?? $name),
                'description' => Content::i18n($site, 'description', $lang),
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => (string) ($site['address'] ?? ''),
                    'postalCode' => (string) ($site['zip'] ?? ''),
                    'addressLocality' => (string) ($site['city'] ?? ''),
                    'addressCountry' => 'FR',
                ],
                'geo' => $hasGeo ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $site['lat'], 'longitude' => (float) $site['lng']] : null,
                'hasMap' => (string) ($site['mapUrl'] ?? '') ?: null,
                'image' => !empty($site['photo']) ? Config::baseUrl() . Config::basePath() . $site['photo'] : null,
                'telephone' => $phone !== '' ? '+33' . ltrim((string) preg_replace('/\D/', '', $phone), '0') : null,
            ], static fn ($v): bool => $v !== null && $v !== '' && $v !== []);
        };

        $prices = array_filter(array_map(static fn (array $o): int => Offices::effectivePrice($o), Offices::published()));
        $data = [
            '@context' => 'https://schema.org',
            '@id' => Config::baseUrl() . Config::basePath() . '/#entreprise',
            'url' => Router::absolute('home', $lang),
            'logo' => Config::baseUrl() . Config::basePath() . (string) ($settings['site']['logo'] ?? '/assets/img/lesignal.svg'),
            'priceRange' => $prices !== [] ? min($prices) . ' – ' . max($prices) . ' € HT/' . ($lang === 'fr' ? 'mois' : 'month') : null,
        ];
        if (\count($sites) === 1) {
            $data = $data + $place($sites[0]);
            $data['name'] = $name;
        } else {
            $data += ['@type' => 'Organization', 'name' => $name, 'department' => array_map($place, $sites)];
        }
        $email = (string) ($settings['contact']['email'] ?? '');
        if ($email !== '') {
            $data['email'] = $email;
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

    /** Liste des bureaux d'une page catalogue (ItemList d'offres). */
    public static function offerList(array $offices, string $lang): ?array
    {
        if ($offices === []) {
            return null;
        }
        $items = [];
        foreach (array_values($offices) as $i => $office) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => Router::absolute('office', $lang, ['id' => (string) $office['id']]), 'name' => (string) $office['name']];
        }
        return ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items];
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
            'image' => $office['cover'] !== '' ? Config::baseUrl() . Config::basePath() . $office['cover'] : null,
            'category' => (string) $office['typeLabel'],
            'offers' => array_filter([
                '@type' => 'Offer',
                'price' => Offices::effectivePrice($office) ?: null,
                'priceSpecification' => [
                    '@type' => 'UnitPriceSpecification',
                    'price' => Offices::effectivePrice($office),
                    'priceCurrency' => 'EUR',
                    'unitCode' => 'MON',
                    'valueAddedTaxIncluded' => false,
                ],
                'seller' => ['@id' => Config::baseUrl() . Config::basePath() . '/#entreprise'],
                'priceCurrency' => (string) ($office['currency'] ?? 'EUR'),
                'availability' => ($office['status'] ?? '') === 'available'
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => Router::absolute('office', $lang, ['id' => (string) $office['id']]),
                'areaServed' => $site !== null ? (string) ($site['city'] ?? '') : null,
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
            'image' => !empty($post['image']) ? Config::baseUrl() . Config::basePath() . $post['image'] : null,
            'mainEntityOfPage' => Router::absolute('post', $lang, ['slug' => (string) ($post['slug'] ?? '')]),
            'publisher' => ['@id' => Config::baseUrl() . Config::basePath() . '/#entreprise'],
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
                // Pas de page Actualités vide dans l'index.
                if ($route === 'news' && Content::publishedPosts() === []) {
                    continue;
                }
                $add(Router::absolute($route, $lang), $priority, $route === 'offices' ? 'daily' : 'monthly');
                if ($route === 'offices') {
                    foreach (array_keys(Router::FACETS) as $facet) {
                        $add(Router::absolute('offices', $lang, ['facet' => $facet]), '0.8', 'daily');
                    }
                }
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
