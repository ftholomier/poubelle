<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Fs;
use App\Core\Str;
use App\Core\Url;

/**
 * Référencement : titres et descriptions (gabarits éditables), données structurées JSON-LD,
 * textes d'introduction des pages locales, sitemaps XML.
 */
final class Seo
{
    public static function markSitemapDirty(): void
    {
        @touch(STORAGE_PATH . '/cache/sitemap.dirty');
    }

    /** Remplace {variables} dans un gabarit. */
    public static function fill(string $tpl, array $vars): string
    {
        $out = (string) preg_replace_callback('/\{([A-Za-z_]+)\}/', static function ($m) use ($vars) {
            $k = $m[1];
            if (array_key_exists($k, $vars)) {
                return (string) $vars[$k];
            }
            $lk = lcfirst($k);
            if ($k !== $lk && array_key_exists($lk, $vars)) {
                return Str::ucfirst((string) $vars[$lk]);
            }
            return '';
        }, $tpl);
        return trim((string) preg_replace('/\s{2,}/', ' ', $out));
    }

    /** Titre et description d'une page à partir du gabarit de son type. */
    public static function meta(string $type, array $vars): array
    {
        $vars += ['site' => Settings::siteName(), 'nb' => Pros::publicCount()];
        $tpl = Settings::get('seo.' . $type, []);
        $title = self::fill((string) ($tpl['title'] ?? '{site}'), $vars);
        $desc = self::fill((string) ($tpl['description'] ?? ''), $vars);
        return ['title' => $title, 'description' => Str::limit($desc, 165)];
    }

    /** Titre final de la balise <title> (suffixe de marque si la place le permet). */
    public static function title(string $title): string
    {
        $suffix = (string) Settings::get('seo.suffix', '');
        if ($suffix !== '' && mb_strlen($title . $suffix) <= 70 && !str_contains($title, Settings::siteName())) {
            return $title . $suffix;
        }
        return $title;
    }

    /** Variables d'une catégorie pour les gabarits ({one}, {many}, {an_one}…). */
    public static function catVars(?array $cat): array
    {
        if (!$cat) {
            return ['one' => 'animateur', 'many' => 'animateurs et artistes', 'an_one' => 'un animateur', 'cat' => 'animation', 'Many' => 'Animateurs et artistes'];
        }
        $one = (string) ($cat['one'] ?? $cat['name']);
        $many = (string) ($cat['many'] ?? $cat['name']);
        $article = preg_match('/^[aeiouyhéè]/i', Str::ascii($one)) ? "un " : 'un ';
        return ['one' => $one, 'many' => $many, 'an_one' => $article . $one, 'cat' => $cat['name'], 'Many' => Str::ucfirst($many)];
    }

    // ------------------------------------------------------------- JSON-LD

    public static function organization(): array
    {
        $sameAs = array_values(array_filter((array) Settings::get('site.socials', [])));
        return array_filter([
            '@type' => 'Organization',
            '@id' => Url::abs('/#organization'),
            'name' => Settings::siteName(),
            'url' => Url::abs('/'),
            'logo' => Url::abs('/assets/img/icon-512.png'),
            'foundingDate' => (string) Settings::get('site.founded', '2001'),
            'sameAs' => $sameAs ?: null,
        ]);
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => Url::abs('/#website'),
            'url' => Url::abs('/'),
            'name' => Settings::siteName(),
            'inLanguage' => 'fr-FR',
            'publisher' => ['@id' => Url::abs('/#organization')],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => Url::abs('/recherche/') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param array<int,array{0:string,1:string}> $crumbs [nom, url] */
    public static function breadcrumbs(array $crumbs): array
    {
        $items = [];
        foreach ($crumbs as $i => [$name, $url]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => Url::abs($url)];
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    public static function itemList(array $pros): array
    {
        $items = [];
        foreach (array_values($pros) as $i => $p) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => Url::abs(Url::pro($p)), 'name' => $p['name']];
        }
        return ['@type' => 'ItemList', 'itemListElement' => $items];
    }

    public static function proLd(array $p, array $reviews = []): array
    {
        $cat = Pros::primaryCategory($p);
        $c = !empty($p['insee']) ? Geo::commune((string) $p['insee']) : null;
        $photos = array_map(static fn ($ph) => Url::abs(Pros::photo($ph, 'lg')), array_slice((array) ($p['photos'] ?? []), 0, 5));
        $ld = [
            '@type' => ['LocalBusiness', 'EntertainmentBusiness'],
            '@id' => Url::abs(Url::pro($p)) . '#pro',
            'name' => Pros::displayName($p),
            'url' => Url::abs(Url::pro($p)),
            'description' => Str::limit((string) ($p['tagline'] ?: Str::text((string) ($p['description'] ?? ''))), 300),
            'image' => $photos ?: null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $c['n'] ?? ($p['city'] ?? null),
                'postalCode' => $p['postcode'] ?? null,
                'addressRegion' => Geo::region((string) ($p['region'] ?? ''))['name'] ?? null,
                'addressCountry' => 'FR',
            ]),
            'geo' => isset($p['lat']) && ($p['geo_precision'] ?? 'commune') === 'commune' ? ['@type' => 'GeoCoordinates', 'latitude' => $p['lat'], 'longitude' => $p['lng']] : null,
            'areaServed' => array_values(array_filter(array_map(static fn ($z) => Geo::dep((string) $z)['name'] ?? null, (array) ($p['zones'] ?? [])))) ?: null,
            'priceRange' => !empty($p['price_from']) ? 'À partir de ' . (int) $p['price_from'] . ' €' : null,
            'sameAs' => array_values(array_filter(array_merge([$p['website'] ?? ''], array_values((array) ($p['socials'] ?? []))))) ?: null,
            'knowsAbout' => $cat['name'] ?? null,
        ];
        $count = (int) ($p['rating']['count'] ?? 0);
        if ($count > 0) {
            $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round((float) $p['rating']['avg'], 1), 'reviewCount' => $count, 'bestRating' => 5, 'worstRating' => 1];
            $ld['review'] = array_map(static fn ($r) => [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $r['author_name']],
                'datePublished' => substr((string) $r['created_at'], 0, 10),
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $r['rating'], 'bestRating' => 5],
                'reviewBody' => Str::limit((string) $r['body'], 500),
            ], array_slice($reviews, 0, 5));
        }
        return array_filter($ld, static fn ($v) => $v !== null && $v !== []);
    }

    public static function article(array $a): array
    {
        return array_filter([
            '@type' => 'BlogPosting',
            'headline' => Str::limit((string) $a['title'], 110),
            'description' => (string) ($a['excerpt'] ?? ''),
            'image' => !empty($a['image']) ? Url::abs((string) Blog::imageUrl($a['image'], 'lg')) : null,
            'datePublished' => $a['published_at'] ?? null,
            'dateModified' => $a['updated_at'] ?? null,
            'author' => ['@type' => 'Organization', 'name' => (string) ($a['author'] ?? Settings::siteName())],
            'publisher' => ['@id' => Url::abs('/#organization')],
            'mainEntityOfPage' => Url::abs(Url::blog((string) $a['slug'])),
            'inLanguage' => 'fr-FR',
        ]);
    }

    public static function faq(array $qa): array
    {
        return ['@type' => 'FAQPage', 'mainEntity' => array_map(static fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $qa)];
    }

    public static function graph(array $nodes): string
    {
        $nodes = array_values(array_filter($nodes));
        return (string) json_encode(['@context' => 'https://schema.org', '@graph' => $nodes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    }

    // ------------------------------------------------- textes des pages locales

    /** Texte d'introduction personnalisé (back-office / IA) pour une page locale. */
    public static function landingText(string $key): ?array
    {
        $t = Store::doc('landings')->get(str_replace(['.', '/'], '_', $key));
        return is_array($t) ? $t : null;
    }

    public static function saveLanding(string $key, array $data): void
    {
        Store::doc('landings')->set(str_replace(['.', '/'], '_', $key), $data + ['updated_at' => date('c')]);
    }

    /** Paragraphe d'introduction généré automatiquement (sans IA) à partir des données réelles. */
    public static function autoIntro(?array $cat, ?string $placeIn, int $count, array $topCities = []): string
    {
        $v = self::catVars($cat);
        $where = $placeIn ?? 'partout en France';
        $s = $count > 0
            ? sprintf('%s %s %s : %d %s %s. ', 'Vous cherchez', $v['an_one'], $where, $count, $count > 1 ? 'professionnels référencés' : 'professionnel référencé', $count > 1 ? 'interviennent dans votre secteur' : 'intervient dans votre secteur')
            : sprintf('Vous cherchez %s %s ? ', $v['an_one'], $where);
        $s .= "Comparez les profils, les photos, les avis clients et les tarifs, puis demandez vos devis gratuitement : les pros vous répondent directement, sans commission.";
        if ($topCities) {
            $s .= ' Pros présents notamment à ' . implode(', ', array_slice($topCities, 0, 5)) . '.';
        }
        return $s;
    }

    // ------------------------------------------------------------- sitemaps

    /** @return array<string,array<int,array{loc:string,lastmod?:string,img?:string}>> */
    public static function sitemaps(): array
    {
        $now = date('Y-m-d');
        $pages = [['loc' => '/', 'lastmod' => $now], ['loc' => '/recherche/'], ['loc' => '/devis/'], ['loc' => '/professionnels/'], ['loc' => '/inscription-pro/'], ['loc' => '/blog/'], ['loc' => '/plan-du-site/'], ['loc' => '/contact/']];
        foreach (Store::pages()->iterate() as $p) {
            if ($p['status'] === 'published') {
                $pages[] = ['loc' => Url::page($p['slug']), 'lastmod' => substr((string) $p['updated'], 0, 10)];
            }
        }
        $pros = [];
        $catDep = [];
        $catCity = [];
        $allDep = [];
        $allCity = [];
        $catRegion = [];
        $occDep = [];
        foreach (Pros::publicIndex() as $p) {
            $full = Store::pros()->get((int) $p['id']);
            $img = !empty($full['photos'][0]) ? Pros::photo($full['photos'][0], 'lg') : null;
            $pros[] = array_filter(['loc' => Url::pro($p), 'lastmod' => substr((string) $p['updated'], 0, 10), 'img' => $img]);
            $deps = array_unique(array_filter(array_merge([$p['dep']], $p['zones'])));
            foreach ($p['cats'] as $c) {
                foreach ($deps as $d) {
                    $catDep[$c . '|' . $d] = true;
                }
                if ($p['insee']) {
                    $catCity[$c . '|' . $p['insee']] = true;
                }
                if ($p['region']) {
                    $catRegion[$c . '|' . $p['region']] = true;
                }
            }
            foreach ($deps as $d) {
                $allDep[$d] = true;
            }
            if ($p['insee']) {
                $allCity[$p['insee']] = true;
            }
        }
        $local = [];
        foreach (Categories::all() as $slug => $c) {
            $local[] = ['loc' => Url::category($slug)];
        }
        $local[] = ['loc' => Url::category(null)];
        foreach (Geo::regions() as $code => $r) {
            $local[] = ['loc' => Url::region(null, (string) $code)];
        }
        foreach (array_keys($catRegion) as $k) {
            [$c, $r] = explode('|', $k);
            $local[] = ['loc' => Url::region($c, $r)];
        }
        foreach (array_keys($allDep) as $d) {
            $local[] = ['loc' => Url::dep(null, (string) $d)];
            foreach (Categories::occasions() as $slug => $o) {
                $occDep[] = ['loc' => Url::occasion($slug, (string) $d)];
            }
        }
        foreach (array_keys($catDep) as $k) {
            [$c, $d] = explode('|', $k);
            $local[] = ['loc' => Url::dep($c, $d)];
        }
        foreach (array_keys($allCity) as $insee) {
            $local[] = ['loc' => Url::city(null, (string) $insee)];
        }
        foreach (array_keys($catCity) as $k) {
            [$c, $i] = explode('|', $k);
            $local[] = ['loc' => Url::city($c, $i)];
        }
        foreach (Categories::occasions() as $slug => $o) {
            $local[] = ['loc' => Url::occasion($slug)];
        }
        $blog = [];
        foreach (Blog::published(1000)['items'] as $a) {
            $blog[] = array_filter(['loc' => Url::blog($a['slug']), 'lastmod' => substr((string) $a['published'], 0, 10), 'img' => Blog::imageUrl($a['image'] ?: null, 'lg')]);
        }
        return ['pages' => $pages, 'pros' => $pros, 'local' => array_merge($local, $occDep), 'blog' => $blog];
    }

    public static function sitemapXml(array $urls): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($urls as $u) {
            $x .= '<url><loc>' . e(Url::abs($u['loc'])) . '</loc>';
            if (!empty($u['lastmod'])) {
                $x .= '<lastmod>' . e($u['lastmod']) . '</lastmod>';
            }
            if (!empty($u['img'])) {
                $x .= '<image:image><image:loc>' . e(Url::abs($u['img'])) . '</image:loc></image:image>';
            }
            $x .= "</url>\n";
        }
        return $x . '</urlset>';
    }

    /**
     * Régénère les sitemaps en cache quand le contenu publié a changé (fiches, articles, pages), quand ils ont été
     * marqués à refaire ou qu'ils ont plus d'un jour. Appelé par le cron et à chaque lecture d'un sitemap : des
     * sitemaps générés avant l'installation des données (site encore vide) sont donc refaits d'eux-mêmes.
     */
    public static function buildSitemaps(bool $force = false): bool
    {
        $flag = STORAGE_PATH . '/cache/sitemap.dirty';
        $dir = STORAGE_PATH . '/cache/sitemaps';
        $sig = self::sitemapSignature();
        $meta = Fs::readJson($dir . '/meta.json', []);
        $fresh = is_array($meta) && ($meta['sig'] ?? '') === $sig && (int) ($meta['at'] ?? 0) > time() - 86400 && is_file($dir . '/pros.xml');
        if (!$force && $fresh && !is_file($flag)) {
            return false;
        }
        Fs::ensureDir($dir);
        foreach (self::sitemaps() as $name => $urls) {
            Fs::writeAtomic($dir . '/' . $name . '.xml', self::sitemapXml($urls));
        }
        Fs::writeJson($dir . '/meta.json', ['at' => time(), 'sig' => $sig]);
        @unlink($flag);
        return true;
    }

    /** Empreinte légère du contenu publié (nombre et dernière mise à jour des fiches, des articles et des pages). */
    private static function sitemapSignature(): string
    {
        $pros = Pros::publicIndex();
        $last = '';
        foreach ($pros as $p) {
            $last = max($last, (string) ($p['updated'] ?? ''));
        }
        $blog = Blog::published(1);
        $pages = Store::pages()->meta();
        return sha1(implode('|', [count($pros), $last, $blog['total'], (string) ($blog['items'][0]['published'] ?? ''), (int) ($pages['count'] ?? 0), (int) ($pages['updated'] ?? 0)]));
    }
}
