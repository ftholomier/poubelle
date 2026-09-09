<?php
declare(strict_types=1);

/**
 * Contrôleur frontal du site public.
 * Le DocumentRoot doit pointer sur ce dossier : app/, content/ et storage/
 * restent hors de la racine web.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Ai\Gemini;
use App\Config;
use App\Content;
use App\I18n;
use App\Media;
use App\Offices;
use App\Reviews;
use App\Router;
use App\Seo;
use App\Text;
use App\View;

$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
$basePath = trim(Config::basePath(), '/');
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = trim(substr($path, \strlen($basePath)), '/');
}

// Fichiers techniques servis par PHP (pas de fichier statique à maintenir).
if ($path === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo Seo::sitemap();
    exit;
}
if ($path === 'robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /api/\n";
    echo 'Sitemap: ' . Config::baseUrl() . Config::basePath() . "/sitemap.xml\n";
    exit;
}

// Le français est servi sans préfixe : /fr/... redirige en 301 vers l'URL courte.
if ($path === Config::DEFAULT_LANG || str_starts_with($path, Config::DEFAULT_LANG . '/')) {
    $rest = trim(substr($path, \strlen(Config::DEFAULT_LANG)), '/');
    $target = rtrim(Config::basePath() . '/' . $rest, '/');
    header('Location: ' . ($target === '' ? '/' : $target), true, 301);
    exit;
}

$route = Router::resolve($uri);
$lang = $route['lang'];
I18n::setLang($lang);
Router::rememberLang($lang);

$settings = Content::settings();
$pageSlug = Router::PAGE_OF_ROUTE[$route['name']] ?? 'home';
$page = Content::page($pageSlug, $lang);

View::share([
    'settings' => $settings,
    'lang' => $lang,
    'route' => $route['name'],
    'params' => $route['params'],
    'page' => $page,
    'seo' => (array) ($page['seo'] ?? []),
    'jsonLd' => null,
]);

/** Rend une page 404 complète et arrête l'exécution. */
$notFound = static function () use ($settings): never {
    http_response_code(404);
    echo View::page('error', [
        'code' => 404,
        'seo' => ['title' => I18n::t('error.404Title'), 'noindex' => true],
        'route' => 'home',
        'params' => [],
        'settings' => $settings,
    ]);
    exit;
};

// Une page dépubliée au back-office ne doit plus être servie.
if ($page !== [] && !Content::isPublished($page)) {
    $notFound();
}

switch ($route['name']) {
    case 'home':
        echo View::page('home', [
            'reviews' => Reviews::get(4),
            'jsonLd' => Seo::faq(Content::list($page, 'faq.items')),
        ]);
        break;

    case 'spaces':
        echo View::page('spaces');
        break;

    case 'offices':
        $filters = [
            'site' => (string) ($_GET['site'] ?? ''),
            'type' => (string) ($_GET['type'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        foreach ($filters as $key => $value) {
            $allowed = match ($key) {
                'site' => Config::SITES,
                'type' => Config::TYPES,
                default => Config::STATUSES,
            };
            if ($value !== '' && !\in_array($value, $allowed, true)) {
                $filters[$key] = '';
            }
        }
        $offices = Offices::decorateAll(Offices::filter(Offices::published(), $filters), $lang);
        echo View::page('offices', ['offices' => $offices, 'filters' => $filters]);
        break;

    case 'office':
        $raw = Offices::findPublished((string) ($route['params']['id'] ?? ''));
        if ($raw === null) {
            $notFound();
        }
        $office = Offices::decorate($raw, $lang);
        echo View::page('office', [
            'office' => $office,
            'seo' => [
                'title' => $office['name'] . ' — ' . $office['priceLabel'] . ' ' . I18n::t('office.perMonthShort'),
                'description' => $office['description'] !== ''
                    ? Text::excerpt($office['description'], 170)
                    : Content::text($page, 'seo.description'),
                'ogImage' => $office['cover'] !== '' ? $office['cover'] : Content::text($page, 'seo.ogImage'),
            ],
            'jsonLd' => Seo::office($office, $lang),
        ]);
        break;

    case 'news':
        echo View::page('news', ['posts' => Content::publishedPosts()]);
        break;

    case 'post':
        $post = Content::post((string) ($route['params']['slug'] ?? ''));
        if ($post === null || ($post['status'] ?? 'published') !== 'published') {
            $notFound();
        }
        $related = array_values(array_filter(
            Content::publishedPosts(),
            static fn (array $p): bool => (string) ($p['slug'] ?? '') !== (string) $post['slug']
        ));
        echo View::page('post', [
            'post' => $post,
            'related' => \array_slice($related, 0, 2),
            'seo' => [
                'title' => Content::i18n($post, 'title', $lang),
                'description' => Content::i18n($post, 'excerpt', $lang),
                'ogImage' => (string) ($post['image'] ?? ''),
            ],
            'jsonLd' => Seo::article($post, $lang),
        ]);
        break;

    case 'contact':
        echo View::page('contact');
        break;

    case 'legal':
    case 'privacy':
        echo View::page('legal');
        break;

    default:
        $notFound();
}
