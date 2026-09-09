<?php
declare(strict_types=1);

namespace App;

/**
 * Routage par chemin propre et résolution de la locale.
 * La langue est détectée au premier segment (/en/…), sinon cookie, sinon
 * Accept-Language, sinon français.
 */
final class Router
{
    /** Slugs par langue. La clé est le nom de route utilisé dans les vues. */
    private const ROUTES = [
        'fr' => [
            'home' => '',
            'spaces' => 'nos-espaces',
            'offices' => 'nos-bureaux',
            'office' => 'nos-bureaux/{id}',
            'news' => 'l-actu',
            'post' => 'l-actu/{slug}',
            'contact' => 'contact',
            'legal' => 'mentions-legales',
            'privacy' => 'politique-de-confidentialite',
        ],
        'en' => [
            'home' => '',
            'spaces' => 'our-spaces',
            'offices' => 'offices',
            'office' => 'offices/{id}',
            'news' => 'news',
            'post' => 'news/{slug}',
            'contact' => 'contact',
            'legal' => 'legal-notice',
            'privacy' => 'privacy-policy',
        ],
    ];

    /** Page de contenu associée à chaque route (content/pages/<slug>.<lang>.json). */
    public const PAGE_OF_ROUTE = [
        'home' => 'home',
        'spaces' => 'spaces',
        'offices' => 'offices',
        'office' => 'offices',
        'news' => 'news',
        'post' => 'news',
        'contact' => 'contact',
        'legal' => 'legal',
        'privacy' => 'privacy',
    ];

    public const COOKIE = 'ioio_lang';

    /**
     * @return array{name:string,lang:string,params:array<string,string>}
     */
    public static function resolve(string $uri): array
    {
        $path = trim(parse_url($uri, PHP_URL_PATH) ?: '/', '/');
        $base = trim(Config::basePath(), '/');
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = trim(substr($path, \strlen($base)), '/');
        }

        $segments = $path === '' ? [] : explode('/', $path);
        $lang = null;
        if ($segments !== [] && \in_array($segments[0], Config::LANGS, true)) {
            $lang = array_shift($segments);
        }
        $lang ??= self::detectLang();
        $path = implode('/', array_map('rawurldecode', $segments));

        foreach (self::ROUTES[$lang] ?? self::ROUTES[Config::DEFAULT_LANG] as $name => $pattern) {
            $params = self::match($pattern, $path);
            if ($params !== null) {
                return ['name' => $name, 'lang' => $lang, 'params' => $params];
            }
        }

        // Tolérance : un slug d'une autre langue reste servi (utile après traduction d'URL).
        foreach (self::ROUTES as $routeLang => $routes) {
            foreach ($routes as $name => $pattern) {
                $params = self::match($pattern, $path);
                if ($params !== null) {
                    return ['name' => $name, 'lang' => $routeLang, 'params' => $params];
                }
            }
        }

        return ['name' => 'notfound', 'lang' => $lang, 'params' => []];
    }

    /** @return array<string,string>|null */
    private static function match(string $pattern, string $path): ?array
    {
        if (!str_contains($pattern, '{')) {
            return $pattern === $path ? [] : null;
        }
        $regex = '#^' . preg_replace('/\\\{([a-z]+)\\\}/', '(?P<$1>[^/]+)', preg_quote($pattern, '#')) . '$#u';
        if (preg_match($regex, $path, $m) !== 1) {
            return null;
        }
        return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /** URL absolue-relative d'une route (préfixe de langue compris). */
    public static function url(string $name, ?string $lang = null, array $params = []): string
    {
        $lang = $lang !== null && \in_array($lang, Config::LANGS, true) ? $lang : I18n::lang();
        $pattern = self::ROUTES[$lang][$name] ?? self::ROUTES[Config::DEFAULT_LANG][$name] ?? '';
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', rawurlencode((string) $value), $pattern);
        }
        $prefix = Config::basePath() . ($lang === Config::DEFAULT_LANG ? '' : '/' . $lang);
        $url = $prefix . '/' . $pattern;
        return rtrim($url, '/') === '' ? ($prefix === '' ? '/' : $prefix . '/') : rtrim($url, '/');
    }

    public static function absolute(string $name, ?string $lang = null, array $params = []): string
    {
        return Config::baseUrl() . self::url($name, $lang, $params);
    }

    public static function adminUrl(string $screen = '', array $query = []): string
    {
        $url = Config::basePath() . '/admin/';
        if ($screen !== '') {
            $query = ['screen' => $screen] + $query;
        }
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    /** Détection de la langue hors URL : cookie puis Accept-Language. */
    public static function detectLang(): string
    {
        $cookie = $_COOKIE[self::COOKIE] ?? '';
        if (\is_string($cookie) && \in_array($cookie, Config::LANGS, true)) {
            return $cookie;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        foreach (explode(',', $accept) as $chunk) {
            $code = substr(trim(explode(';', $chunk)[0]), 0, 2);
            if (\in_array($code, Config::LANGS, true)) {
                return $code;
            }
        }
        return Config::DEFAULT_LANG;
    }

    public static function rememberLang(string $lang): void
    {
        if (!\in_array($lang, Config::LANGS, true) || headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $lang, [
            'expires' => time() + 31_536_000,
            'path' => Config::basePath() . '/',
            'secure' => Config::isHttps(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /** Équivalents de la page courante dans les autres langues (hreflang). */
    public static function alternates(string $name, array $params = []): array
    {
        $out = [];
        foreach (Config::LANGS as $lang) {
            $out[$lang] = self::absolute($name, $lang, $params);
        }
        return $out;
    }

    public static function routeNames(): array
    {
        return array_keys(self::ROUTES[Config::DEFAULT_LANG]);
    }
}
