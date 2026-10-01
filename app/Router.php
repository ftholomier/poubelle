<?php
declare(strict_types=1);

namespace App;

/**
 * Routage par chemin propre et résolution de la locale.
 * La langue est donnée par le premier segment (/en/…) ; sans préfixe, c'est
 * le français.
 */
final class Router
{
    /**
     * Slugs par langue. La clé est le nom de route utilisé dans les vues.
     *
     * Chaque adresse porte les mots que l'on tape dans Google (« location
     * bureaux Montbéliard », « coworking Montbéliard ») sans le bourrage de
     * l'ancien site : un seul groupe de mots-clés par page, lisible par un
     * humain. Les anciennes adresses sont redirigées en 301 par Redirects.
     */
    private const ROUTES = [
        'fr' => [
            'home' => '',
            'spaces' => 'coworking-montbeliard',
            'offices' => 'location-bureaux-montbeliard',
            'office' => 'location-bureaux-montbeliard/{id}',
            'news' => 'actualites',
            'post' => 'actualites/{slug}',
            'contact' => 'contact',
            'legal' => 'mentions-legales',
            'privacy' => 'politique-de-confidentialite',
        ],
        'en' => [
            'home' => '',
            'spaces' => 'coworking-montbeliard',
            'offices' => 'office-rental-montbeliard',
            'office' => 'office-rental-montbeliard/{id}',
            'news' => 'news',
            'post' => 'news/{slug}',
            'contact' => 'contact',
            'legal' => 'legal-notice',
            'privacy' => 'privacy-policy',
        ],
    ];

    /**
     * Sélections du catalogue servies sous leur propre adresse : ce sont les
     * pages que l'on cherche (« location bureau privé Montbéliard »), elles
     * reprennent les trois catégories de l'ancienne boutique. Chacune a son
     * titre, son texte et sa place dans le sitemap (contenu « facets » de la
     * page offices). facette => [filtre, valeur, slug par langue].
     */
    public const FACETS = [
        'private' => ['type', 'private', ['fr' => 'bureaux-prives', 'en' => 'private-offices']],
        'openspace' => ['type', 'openspace', ['fr' => 'bureaux-ouverts', 'en' => 'open-plan-desks']],
        'available' => ['status', 'available', ['fr' => 'bureaux-disponibles', 'en' => 'available-offices']],
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

    public const COOKIE = Config::COOKIE_PREFIX . 'lang';

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
        // Sans préfixe, c'est toujours le français : une même adresse ne doit
        // jamais servir deux langues selon le navigateur (Google n'indexerait
        // qu'une version, au hasard). Le choix de langue passe par le sélecteur.
        $lang ??= Config::DEFAULT_LANG;
        $path = implode('/', array_map('rawurldecode', $segments));

        foreach (self::ROUTES[$lang] ?? self::ROUTES[Config::DEFAULT_LANG] as $name => $pattern) {
            $params = self::match($pattern, $path);
            if ($params !== null) {
                return self::facet(['name' => $name, 'lang' => $lang, 'params' => $params]);
            }
        }

        // Tolérance : un slug d'une autre langue est reconnu ; le contrôleur
        // frontal redirige alors en 301 vers l'adresse canonique.
        foreach (self::ROUTES as $routeLang => $routes) {
            foreach ($routes as $name => $pattern) {
                $params = self::match($pattern, $path);
                if ($params !== null) {
                    return self::facet(['name' => $name, 'lang' => $routeLang, 'params' => $params]);
                }
            }
        }

        return ['name' => 'notfound', 'lang' => $lang, 'params' => []];
    }

    /** « location-bureaux-montbeliard/bureaux-prives » est une sélection, pas une fiche. */
    private static function facet(array $route): array
    {
        if ($route['name'] !== 'office') {
            return $route;
        }
        foreach (self::FACETS as $facet => [, , $slugs]) {
            foreach ($slugs as $slug) {
                if (($route['params']['id'] ?? '') === $slug) {
                    return ['name' => 'offices', 'lang' => $route['lang'], 'params' => ['facet' => $facet]];
                }
            }
        }
        return $route;
    }

    /** Sélection correspondant à un filtre unique (?type=private…), sinon ''. */
    public static function facetOf(string $key, string $value): string
    {
        foreach (self::FACETS as $facet => [$filter, $filterValue]) {
            if ($filter === $key && $filterValue === $value) {
                return $facet;
            }
        }
        return '';
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
    /**
     * @param array<string,string> $params valeurs des segments {id}, {slug}…
     * @param array<string,string> $query  paramètres ajoutés après le « ? » (filtres)
     */
    public static function url(string $name, ?string $lang = null, array $params = [], array $query = []): string
    {
        $lang = $lang !== null && \in_array($lang, Config::LANGS, true) ? $lang : I18n::lang();
        $pattern = self::ROUTES[$lang][$name] ?? self::ROUTES[Config::DEFAULT_LANG][$name] ?? '';
        if ($name === 'offices' && isset(self::FACETS[$params['facet'] ?? ''])) {
            $pattern .= '/' . self::FACETS[$params['facet']][2][$lang];
            unset($params['facet']);
        }
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', rawurlencode((string) $value), $pattern);
        }
        $prefix = Config::basePath() . ($lang === Config::DEFAULT_LANG ? '' : '/' . $lang);
        $url = $prefix . '/' . $pattern;
        $url = rtrim($url, '/') === '' ? ($prefix === '' ? '/' : $prefix . '/') : rtrim($url, '/');

        $query = array_filter($query, static fn ($v): bool => (string) $v !== '');
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    public static function absolute(string $name, ?string $lang = null, array $params = [], array $query = []): string
    {
        return Config::baseUrl() . self::url($name, $lang, $params, $query);
    }

    /**
     * Page « Nos bureaux » déjà filtrée sur les bureaux libres : c'est la
     * destination de tous les liens qui promettent de montrer ce qui est
     * disponible, pour éviter au visiteur de cliquer un filtre de plus.
     *
     * Un seul filtre par URL : les pastilles de la page sont exclusives, un
     * lien qui en cumulerait deux afficherait des compteurs incohérents.
     */
    public static function availableOffices(?string $lang = null): string
    {
        return self::url('offices', $lang, ['facet' => 'available']);
    }

    /** Page « Nos bureaux » filtrée sur un lieu. */
    public static function officesAtSite(string $site, ?string $lang = null): string
    {
        return self::url('offices', $lang, [], ['site' => $site]);
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
