<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Controller;
use App\Core\Env;
use App\Core\Fs;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\Ads;
use App\Services\Cron;
use App\Services\Seo;
use App\Services\Settings;

/** robots.txt, sitemaps, ads.txt, manifeste PWA, service worker, page hors ligne, cron web. */
final class SeoController extends Controller
{
    public function robots(): Response
    {
        $custom = trim((string) Settings::get('seo.robots', ''));
        $txt = $custom !== '' ? $custom : implode("\n", [
            'User-agent: *',
            'Disallow: /api/',
            'Disallow: /espace-pro/',
            'Disallow: /e/',
            'Disallow: /go/',
            'Disallow: /favoris/',
            'Disallow: /devis/merci/',
            'Disallow: /message/merci/',
            'Disallow: /cron/',
            'Allow: /',
        ]);
        if (Env::get('APP_ENV') !== 'production') {
            $txt = "User-agent: *\nDisallow: /";
        }
        $txt .= "\n\nSitemap: " . Url::abs('/sitemap.xml') . "\n";
        return Response::text($txt)->header('Cache-Control', 'public, max-age=3600');
    }

    public function adsTxt(): Response
    {
        $txt = Ads::adsTxt();
        if ($txt === '') {
            throw new HttpException(404);
        }
        return Response::text($txt)->header('Cache-Control', 'public, max-age=86400');
    }

    public function sitemapIndex(): Response
    {
        Seo::buildSitemaps(false);
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (['pages', 'local', 'pros', 'blog'] as $name) {
            $f = STORAGE_PATH . '/cache/sitemaps/' . $name . '.xml';
            $x .= '<sitemap><loc>' . e(Url::abs('/sitemaps/' . $name . '.xml')) . '</loc>' . (is_file($f) ? '<lastmod>' . date('c', (int) filemtime($f)) . '</lastmod>' : '') . "</sitemap>\n";
        }
        return new Response($x . '</sitemapindex>', 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function sitemap(string $name): Response
    {
        if (!in_array($name, ['pages', 'local', 'pros', 'blog'], true)) {
            throw new HttpException(404);
        }
        $f = STORAGE_PATH . '/cache/sitemaps/' . $name . '.xml';
        Seo::buildSitemaps(!is_file($f)); // reconstruit si des fiches ont changé depuis
        return new Response((string) file_get_contents($f), 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function manifest(): Response
    {
        $admin = Request::query('app') === 'admin';
        $name = Settings::siteName();
        $data = [
            'id' => $admin ? Url::admin() : '/',
            'name' => $admin ? $name . ' — Administration' : $name,
            'short_name' => $admin ? 'APVS Admin' : (string) Settings::get('site.short_name', 'APVS'),
            'description' => (string) Settings::get('site.baseline'),
            'lang' => 'fr',
            'dir' => 'ltr',
            'start_url' => $admin ? Url::admin() . '?source=pwa' : '/?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'orientation' => 'portrait-primary',
            'background_color' => '#fff6e8',
            'theme_color' => $admin ? '#1c1233' : '#fff6e8',
            'categories' => ['entertainment', 'lifestyle', 'business'],
            'icons' => [
                ['src' => '/assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/assets/img/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => $admin ? [
                ['name' => 'Tableau de bord', 'url' => Url::admin()],
                ['name' => 'Demandes à modérer', 'url' => Url::admin('demandes?statut=pending')],
                ['name' => 'Pros à valider', 'url' => Url::admin('pros?statut=pending')],
            ] : [
                ['name' => 'Trouver un pro', 'short_name' => 'Rechercher', 'url' => '/recherche/', 'icons' => [['src' => '/assets/img/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Demander un devis', 'short_name' => 'Devis', 'url' => '/devis/', 'icons' => [['src' => '/assets/img/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Mes favoris', 'short_name' => 'Favoris', 'url' => '/favoris/', 'icons' => [['src' => '/assets/img/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Mon espace pro', 'short_name' => 'Espace pro', 'url' => '/espace-pro/', 'icons' => [['src' => '/assets/img/icon-192.png', 'sizes' => '192x192']]],
            ],
        ];
        return new Response((string) json_encode($data, Fs::JSON_FLAGS | JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/manifest+json; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function serviceWorker(): Response
    {
        $files = ['css/app.css', 'js/app.js', 'js/explorer.js'];
        $core = ['/hors-ligne/', '/assets/img/icon-192.png', '/assets/img/favicon.svg', '/assets/fonts/bricolage-grotesque-latin.woff2', '/assets/fonts/instrument-serif-italic-latin.woff2', '/assets/fonts/dm-mono-500-latin.woff2'];
        $v = '';
        foreach ($files as $f) {
            $core[] = Url::asset($f);
            $v .= (string) @filemtime(PUBLIC_PATH . '/assets/' . $f);
        }
        $version = 'apvs-' . substr(sha1($v . APP_VERSION), 0, 10);
        $admin = '/' . trim((string) Env::get('ADMIN_PATH', 'gestion'), '/');
        $js = 'const VERSION = ' . js($version) . ";\nconst CORE = " . js($core) . ";\nconst NO_CACHE = " . js(['/api/', '/espace-pro', $admin, '/e/', '/go/', '/cron/', '/connexion', '/deconnexion', '/inscription-pro', '/devis/merci', '/sw.js']) . ";\n"
            . (string) file_get_contents(APP_PATH . '/Views/front/sw.js');
        return new Response($js, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => '/']);
    }

    public function offline(): Response
    {
        return $this->view('front/offline', ['meta' => ['title' => 'Hors ligne', 'robots' => 'noindex', 'ads' => false]]);
    }

    /** Cron par appel web (hébergements sans tâche planifiée) : /cron/{CRON_TOKEN} */
    public function cron(string $token): Response
    {
        $expected = (string) Env::get('CRON_TOKEN', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            throw new HttpException(404);
        }
        @set_time_limit(300);
        $report = Cron::run('web');
        return Response::text(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: 'ok');
    }
}
