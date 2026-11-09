<?php

declare(strict_types=1);

/** Pages publiques : routage, gabarits, référencement (sitemap, robots, redirections). */
final class Site
{
    public static function handle(string $path, string $method): void
    {
        $norm = Content::norm($path);

        // Anciennes adresses WordPress
        if (preg_match('#^/wp-content/uploads/(.+)$#', rawurldecode($path), $m)) {
            $rel = str_replace('..', '', $m[1]);
            if (is_file(APP_ROOT . '/public/media/' . $rel)) {
                Http::redirect('/media/' . implode('/', array_map('rawurlencode', explode('/', $rel))), 301);
            }
            self::notFound();
        }
        $legacy = [
            '/category/actualites' => Content::path('actu'),
            '/actu-site' => Content::path('actu'),
            '/feed' => Content::path('actu'),
            '/accueil' => '/',
            '/contact' => Content::path('contact'),
            '/devis-gratuit' => Content::path('devis'),
            '/actualites' => Content::path('actu'),
            '/produits' => Content::path('produits'),
            '/realisations' => Content::path('realisations'),
            '/avis' => Content::path('avis'),
            '/presentation' => Content::path('presentation'),
            '/mentions-legales' => Content::path('mentions'),
            '/menuiserie-exterieure-a-montbeliard-fenetres-portes-volets-sur-mesure-renovest' => '/',
        ];
        if (isset($legacy[$norm]) && Content::norm($legacy[$norm]) !== $norm) {
            Http::redirect($legacy[$norm], 301);
        }
        if (str_starts_with($norm, '/slider/') || str_starts_with($norm, '/author/') || str_starts_with($norm, '/wp-json') || $norm === '/wp-login.php' || str_starts_with($norm, '/wp-admin')) {
            Http::redirect('/', 301);
        }

        $routes = Content::routes();
        if (!isset($routes[$norm])) {
            self::notFound();
        }
        [$type, $key] = $routes[$norm];
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            exit;
        }
        // Adresse canonique (slash final comme sur le site d'origine)
        $canonical = match ($type) {
            'page' => Content::path($key),
            'product' => (string) (Content::product($key)['path'] ?? '/'),
            'post' => (string) (Content::post($key)['path'] ?? '/'),
        };
        if (rawurldecode($path) !== $canonical && rtrim(rawurldecode($path), '/') . '/' === $canonical) {
            $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
            Http::redirect(implode('/', array_map('rawurlencode', explode('/', $canonical))) . ($qs !== '' ? '?' . $qs : ''), 301);
        }
        self::render($type, $key);
    }

    public static function render(string $type, string $key): void
    {
        security_headers();
        header('Content-Type: text/html; charset=utf-8');
        $site = Content::site();
        if ($type === 'product') {
            $p = Content::product($key);
            $content = View::fetch('pages/product', ['p' => $p]);
            $meta = self::meta($p['seo'] ?? [], $p['title'], $p['path'], $p['summary'] ?? '', $p['image'] ?? null);
            self::layout($content, $meta, 'product:' . $key);
            return;
        }
        if ($type === 'post') {
            $p = Content::post($key);
            $content = View::fetch('pages/article', ['post' => $p]);
            $meta = self::meta($p['seo'] ?? [], plain((string) $p['title']), $p['path'], Content::postExcerpt($p, 155), Content::postImage($p) ?: null);
            $meta['og_type'] = 'article';
            $meta['jsonld'] = [
                '@context' => 'https://schema.org', '@type' => 'NewsArticle',
                'headline' => plain((string) $p['title']), 'datePublished' => $p['date'],
                'image' => Content::postImage($p) !== '' ? [Http::abs(Content::postImage($p))] : [],
                'publisher' => ['@type' => 'Organization', 'name' => $site['name'], 'logo' => ['@type' => 'ImageObject', 'url' => Http::abs('/assets/img/logo.png')]],
            ];
            self::layout($content, $meta, 'post:' . $key);
            return;
        }
        $pg = Content::page($key);
        $tpl = match ($key) {
            'home' => 'pages/home',
            'produits' => 'pages/produits',
            'realisations' => 'pages/realisations',
            'actu' => 'pages/actus',
            'avis' => 'pages/avis',
            'contact' => 'pages/contact',
            'devis' => 'pages/devis',
            default => 'pages/page',
        };
        $content = View::fetch($tpl, ['pg' => $pg, 'key' => $key]);
        $meta = self::meta($pg['seo'] ?? [], $pg['title'] ?? $site['name'], $pg['path'] ?? '/', '', null);
        if ($key === 'home') {
            $meta['jsonld'] = self::localBusiness();
        }
        if ($key === 'devis' && (isset($_GET['produit']) || isset($_GET['etape']) || isset($_GET['tel']))) {
            $meta['noindex'] = true;
        }
        self::layout($content, $meta, $key);
    }

    private static function meta(array $seo, string $fallbackTitle, string $path, string $fallbackDesc, ?string $image): array
    {
        $site = Content::site();
        $title = trim((string) ($seo['title'] ?? ''));
        return [
            'title' => $title !== '' ? $title : $fallbackTitle . ' - ' . $site['name'],
            'description' => trim((string) ($seo['description'] ?? '')) ?: $fallbackDesc,
            'canonical' => Http::abs($path),
            'image' => $image,
        ];
    }

    private static function layout(string $content, array $meta, string $active): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }
        View::render('layout', ['content' => $content, 'meta' => array_filter($meta, fn($v) => $v !== null), 'active' => $active]);
    }

    private static function localBusiness(): array
    {
        $s = Content::site();
        $r = Content::rating();
        $out = [];
        foreach ($s['agencies'] ?? [] as $a) {
            $out[] = [
                '@type' => 'HomeAndConstructionBusiness',
                'name' => $s['name'] . ' — ' . $a['name'],
                'telephone' => $s['phone'],
                'url' => Http::abs('/'),
                'image' => Http::abs('/assets/img/logo.png'),
                'address' => ['@type' => 'PostalAddress', 'streetAddress' => $a['street'], 'postalCode' => $a['zip'], 'addressLocality' => ucfirst(mb_strtolower($a['town'])), 'addressCountry' => 'FR'],
                'openingHours' => 'Mo-Fr 09:00-18:00',
                'sameAs' => array_values(array_filter([$s['facebook'] ?? '', $s['instagram'] ?? ''])),
            ];
        }
        if ($out && $r['count'] > 0) {
            $out[0]['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $r['value'], 'reviewCount' => $r['count']];
        }
        return ['@context' => 'https://schema.org', '@graph' => $out];
    }

    public static function notFound(): void
    {
        http_response_code(404);
        security_headers();
        header('Content-Type: text/html; charset=utf-8');
        $content = View::fetch('pages/404');
        View::render('layout', ['content' => $content, 'meta' => ['title' => 'Page introuvable - ' . Content::site()['name'], 'noindex' => true], 'active' => '']);
        exit;
    }

    public static function maintenancePage(): void
    {
        http_response_code(503);
        header('Retry-After: 60');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Mise à jour en cours</title>'
            . '<div style="font:16px system-ui;text-align:center;margin-top:22vh;color:#1c1c1c"><b style="display:inline-block;background:#FFF200;padding:6px 12px;border-radius:6px">Renov\'Est</b>'
            . '<p>Le site est en cours de mise à jour. Revenez dans une minute.</p></div>';
        exit;
    }

    public static function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $urls = [];
        foreach (Content::pages() as $key => $p) {
            if (!empty($p['path']) && $key !== 'devis') {
                $urls[] = [$p['path'], null];
            }
        }
        $urls[] = [Content::path('devis'), null];
        foreach (Content::products() as $p) {
            $urls[] = [$p['path'], null];
        }
        foreach (Content::posts() as $p) {
            $urls[] = [$p['path'], $p['date']];
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$u, $d]) {
            echo '  <url><loc>' . e(Http::abs($u)) . '</loc>' . ($d ? '<lastmod>' . e($d) . '</lastmod>' : '') . "</url>\n";
        }
        echo '</urlset>';
        exit;
    }

    public static function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /api/\nSitemap: " . Http::abs('/sitemap.xml') . "\n";
        exit;
    }
}
