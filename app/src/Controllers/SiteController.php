<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Content\Pages;
use App\Content\Reviews;
use App\Content\Settings;
use App\Core\View;
use App\Http\Request;
use App\Http\Response;
use App\I18n\Translator;

/**
 * Rendu du site public.
 */
final class SiteController
{
    public static function home(Request $request, array $args = []): void
    {
        self::boot($args['lang'] ?? null);
        $page = Pages::home();

        if ($page === null) {
            self::renderEmptyState();
            return;
        }
        self::renderPage($request, $page, true);
    }

    public static function page(Request $request, array $args = []): void
    {
        self::boot($args['lang'] ?? null);
        $slug = (string) ($args['slug'] ?? '');

        // /accueil redirige vers la racine (une seule URL canonique).
        if ($slug !== '' && $slug === Pages::homeSlug()) {
            Response::redirect(Translator::url('/'));
        }

        $page = Pages::find($slug);
        if ($page === null) {
            self::notFound($request, $args);
            return;
        }
        self::renderPage($request, $page, false);
    }

    public static function notFound(Request $request, array $args = []): void
    {
        self::boot($args['lang'] ?? null);
        Response::notFound();
        Response::securityHeaders();
        View::display('front/404', ['request' => $request] + self::commonData());
    }

    /* ------------------------------------------------------------------ */

    private static function renderPage(Request $request, array $page, bool $isHome): void
    {
        Response::securityHeaders();

        $data = self::commonData() + [
            'page'    => $page,
            'isHome'  => $isHome,
            'request' => $request,
            'reviews' => Reviews::get((int) Settings::get('reviews.max_items', 9)),
        ];
        View::display('front/page', $data);
    }

    private static function renderEmptyState(): void
    {
        Response::securityHeaders();
        View::display('front/empty', self::commonData());
    }

    /** Contexte partagé par toutes les vues front. */
    public static function commonData(): array
    {
        return [
            'settings'   => Settings::all(),
            'menu'       => Pages::menu(),
            'footerNav'  => Pages::footerLinks(),
            'lang'       => Translator::lang(),
            'languages'  => Settings::languages(),
            'homeSlug'   => Pages::homeSlug(),
        ];
    }

    /** Détermine la langue à partir de l'URL, du cookie puis du navigateur. */
    private static function boot(?string $lang): void
    {
        $available = Settings::languages();

        if ($lang === null || !in_array($lang, $available, true)) {
            $cookie = (string) ($_COOKIE['lcal_lang'] ?? '');
            if (in_array($cookie, $available, true)) {
                $lang = $cookie;
            }
        }
        if ($lang === null || !in_array($lang, $available, true)) {
            $lang = self::negotiate($available);
        }

        Translator::boot($lang);
        View::share('lang', Translator::lang());
    }

    private static function negotiate(array $available): string
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (in_array($code, $available, true)) {
                return $code;
            }
        }
        return Settings::str('i18n.default', 'fr');
    }

    /* ------------------------------------------------------------------ */
    /* SEO                                                                 */
    /* ------------------------------------------------------------------ */

    public static function sitemap(Request $request): never
    {
        Translator::boot(Settings::str('i18n.default', 'fr'));
        $base = $request->baseUrl();
        $languages = Settings::languages();
        $default = Settings::str('i18n.default', 'fr');
        $homeSlug = Pages::homeSlug();

        header('Content-Type: application/xml; charset=UTF-8');
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

        foreach (Pages::index() as $entry) {
            if (($entry['status'] ?? '') !== 'published') {
                continue;
            }
            $slug = (string) $entry['slug'];
            $path = $slug === $homeSlug ? '/' : '/' . $slug;

            foreach ($languages as $language) {
                $prefix = $language === $default ? '' : '/' . $language;
                $xml->startElement('url');
                $xml->writeElement('loc', $base . $prefix . ($path === '/' ? '/' : $path));
                if ($entry['updated_at'] !== '') {
                    $xml->writeElement('lastmod', substr((string) $entry['updated_at'], 0, 10));
                }
                $xml->writeElement('changefreq', $slug === $homeSlug ? 'weekly' : 'monthly');
                $xml->writeElement('priority', $slug === $homeSlug ? '1.0' : '0.8');

                foreach ($languages as $alternate) {
                    $altPrefix = $alternate === $default ? '' : '/' . $alternate;
                    $xml->startElement('xhtml:link');
                    $xml->writeAttribute('rel', 'alternate');
                    $xml->writeAttribute('hreflang', $alternate);
                    $xml->writeAttribute('href', $base . $altPrefix . ($path === '/' ? '/' : $path));
                    $xml->endElement();
                }
                $xml->endElement();
            }
        }

        $xml->endElement();
        $xml->endDocument();
        echo $xml->outputMemory();
        exit;
    }

    public static function robots(Request $request): never
    {
        header('Content-Type: text/plain; charset=UTF-8');
        $base = $request->baseUrl();
        $indexable = str_contains(Settings::str('seo.robots', 'index,follow'), 'noindex') === false;

        echo "User-agent: *\n";
        echo $indexable ? "Allow: /\n" : "Disallow: /\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /api/\n";
        echo "\nSitemap: {$base}/sitemap.xml\n";
        exit;
    }
}
