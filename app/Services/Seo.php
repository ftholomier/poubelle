<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\PageRepository;
use App\Storage\Audit;
use App\Storage\Json;
use App\Storage\Lock;

/**
 * Référencement piloté depuis le back-office.
 *
 * Trois choses se règlent ici sans toucher au code : l'adresse publique de
 * chaque rubrique (« /offres » peut devenir « /emplois »), le titre et la
 * méta description de chaque page, et le gabarit de titre des fiches.
 *
 * Le code continue de parler en chemins internes — `/offres`, `/offre/{slug}` —
 * et c'est `I18n::url()` qui traduit vers l'adresse publique du moment. Changer
 * une adresse laisse donc une redirection permanente derrière elle : aucun lien
 * existant ne se casse, et le référencement acquis se reporte.
 */
final class Seo
{
    /**
     * Rubriques adressables. `prefix` marque les préfixes de fiche, dont le
     * suffixe est le slug de l'enregistrement.
     */
    public const ROUTES = [
        'home'      => ['path' => '/',                   'label' => 'Accueil'],
        'jobs'      => ['path' => '/offres',             'label' => 'Liste des offres'],
        'job'       => ['path' => '/offre',              'label' => 'Fiche d’offre', 'prefix' => true],
        'cvs'       => ['path' => '/cv',                 'label' => 'Annuaire des CV'],
        'cv'        => ['path' => '/cv',                 'label' => 'Fiche de CV', 'prefix' => true],
        'employers' => ['path' => '/employeurs',         'label' => 'Liste des employeurs'],
        'employer'  => ['path' => '/employeur',          'label' => 'Fiche employeur', 'prefix' => true],
        'post_cv'   => ['path' => '/deposer-un-cv',      'label' => 'Déposer un CV'],
        'post_job'  => ['path' => '/deposer-une-annonce','label' => 'Déposer une annonce'],
        'resources' => ['path' => '/ressources',         'label' => 'Ressources'],
    ];

    /**
     * Variables acceptées dans les gabarits de titre et de description.
     *
     * Chaque rubrique déclare les siennes ; le contrôleur correspondant les
     * fournit à chaque rendu. Une variable absente est retirée du texte plutôt
     * que laissée en évidence.
     */
    public const PLACEHOLDERS = [
        'home'      => ['{site}', '{offres}', '{profils}'],
        'jobs'      => ['{total}', '{ville}', '{motcle}', '{site}'],
        'job'       => ['{titre}', '{employeur}', '{ville}', '{region}', '{contrat}', '{salaire}', '{site}'],
        'cvs'       => ['{total}', '{ville}', '{motcle}', '{site}'],
        'cv'        => ['{nom}', '{metier}', '{ville}', '{region}', '{annees}', '{competences}', '{site}'],
        'employers' => ['{total}', '{site}'],
        'employer'  => ['{nom}', '{ville}', '{offres}', '{site}'],
        'post_cv'   => ['{site}'],
        'post_job'  => ['{site}'],
        'resources' => ['{site}'],
    ];

    private static ?array $store = null;

    private static function path(): string
    {
        return Config::path('data') . '/private/seo.json';
    }

    public static function all(): array
    {
        if (self::$store === null) {
            self::$store = Json::read(self::path());
        }
        return self::$store;
    }

    /* ------------------------------------------------------------- adresses */

    /** Adresse publique d'une rubrique, telle qu'elle est configurée. */
    public static function routePath(string $key): string
    {
        $default = (string) (self::ROUTES[$key]['path'] ?? '/');
        $custom = trim((string) (self::all()['routes'][$key]['path'] ?? ''));
        return $custom !== '' ? $custom : $default;
    }

    /**
     * Traduit un chemin interne en chemin public. Appelé par `I18n::url()`,
     * c'est le seul point de passage : tous les liens du site en héritent.
     */
    public static function publicPath(string $path): string
    {
        $store = self::all()['routes'] ?? [];
        if ($store === []) {
            return $path;   // aucune personnalisation : rien à traduire
        }

        $path = '/' . ltrim($path, '/');
        foreach (self::ROUTES as $key => $meta) {
            $default = (string) $meta['path'];
            if (empty($meta['prefix'])) {
                if ($path === $default || ($default !== '/' && rtrim($path, '/') === $default)) {
                    return self::routePath($key);
                }
                continue;
            }
            if (str_starts_with($path, $default . '/')) {
                return self::routePath($key) . substr($path, strlen($default));
            }
        }
        return $path;
    }

    /**
     * Anciennes adresses d'une rubrique, à rediriger en 301.
     *
     * @return string[]
     */
    public static function formerPaths(string $key): array
    {
        $current = self::routePath($key);
        $former = (array) (self::all()['routes'][$key]['former'] ?? []);
        $former[] = (string) self::ROUTES[$key]['path'];

        return array_values(array_unique(array_filter(
            array_map('strval', $former),
            static fn(string $p) => $p !== '' && $p !== $current,
        )));
    }

    /** Redirections de pages éditoriales dont le slug a changé. */
    public static function pageRedirect(string $slug): string
    {
        return (string) (self::all()['page_redirects'][$slug] ?? '');
    }

    /* ---------------------------------------------------------------- métas */

    /** Clé de rubrique correspondant à un chemin interne, ou null. */
    public static function keyForPath(string $path): ?string
    {
        $path = '/' . ltrim($path, '/');
        foreach (self::ROUTES as $key => $meta) {
            if (empty($meta['prefix']) && rtrim($path, '/') === rtrim((string) $meta['path'], '/')) {
                return $key;
            }
        }
        if ($path === '/') {
            return 'home';
        }
        foreach (self::ROUTES as $key => $meta) {
            if (!empty($meta['prefix']) && str_starts_with($path, $meta['path'] . '/')) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Applique les réglages à la méta d'une page : titre, description, robots.
     *
     * @param array<string,string> $vars variables du gabarit ({titre}, {ville}…)
     */
    public static function apply(array $meta, array $vars = []): array
    {
        $key = self::keyForPath((string) ($meta['path'] ?? '/'));
        if ($key === null) {
            return $meta;
        }

        $settings = (array) (self::all()['routes'][$key] ?? []);
        $vars['{site}'] = (string) Config::get('site.name');

        // Un gabarit qui ne donnerait qu'une chaîne vide — toutes ses variables
        // absentes — laisserait la page sans titre : on garde alors celui que
        // le contrôleur a calculé.
        $title = trim((string) ($settings['title'] ?? ''));
        if ($title !== '') {
            $filled = self::fill($title, $vars);
            if ($filled !== '') {
                $meta['title'] = $filled;
            }
        }

        $description = trim((string) ($settings['description'] ?? ''));
        if ($description !== '') {
            $filled = self::fill($description, $vars);
            if ($filled !== '') {
                $meta['desc'] = $filled;
            }
        }

        if (($settings['robots'] ?? '') === 'noindex' && ($meta['robots'] ?? '') === '') {
            $meta['robots'] = 'noindex, follow';
        }

        return $meta;
    }

    /**
     * Remplit un gabarit et nettoie ce que les variables absentes laissent
     * derrière elles.
     *
     * Une fiche sans ville, sans employeur ou sans ancienneté est courante :
     * le titre ne doit ni montrer une accolade, ni garder une parenthèse vide
     * ou deux séparateurs collés.
     */
    private static function fill(string $template, array $vars): string
    {
        $out = strtr($template, $vars);

        // Une variable non fournie ne doit pas rester visible dans le titre.
        $out = (string) preg_replace('/\{[a-z_]+\}/u', '', $out);

        // Délimiteurs devenus vides : « Monteuse () ».
        $out = (string) preg_replace('/\(\s*\)|\[\s*\]|«\s*»/u', '', $out);

        // Séparateurs qui se suivent : « Costumière ·  · intermittent.fr ».
        $out = (string) preg_replace('/\s*([—·|,–-])(?:\s*[—·|,–-])+\s*/u', ' $1 ', $out);

        // Séparateur resté en tête ou en queue.
        $out = (string) preg_replace('/^[\s—·|,–]+|[\s—·|,–]+$/u', '', $out);
        $out = (string) preg_replace('/^\s*-\s+|\s+-\s*$/u', '', $out);

        // Espaces : un seul, et un de chaque côté des séparateurs qui en
        // prennent. La virgule et le trait d'union n'en prennent pas à gauche.
        $out = (string) preg_replace('/\s*([—·|])\s*/u', ' $1 ', $out);
        $out = (string) preg_replace('/\s+,/u', ',', $out);
        $out = (string) preg_replace('/\s{2,}/u', ' ', $out);

        return trim($out);
    }

    /**
     * Langues à déclarer en hreflang.
     *
     * Une langue sans traduction d'interface servirait le français sous une
     * autre adresse : la déclarer créerait un doublon de plus. Et une seule
     * langue ne justifie aucune balise.
     *
     * @return array<string, string>
     */
    public static function alternates(string $path, bool $untranslated = false): array
    {
        if ($untranslated) {
            return [];
        }
        $out = [];
        foreach (array_keys(I18n::languages()) as $code) {
            if ($code !== 'fr' && !I18n::hasTranslations($code)) {
                continue;
            }
            $out[$code] = I18n::url($path, $code);
        }
        return count($out) > 1 ? $out : [];
    }

    /** Image de partage : celle réglée au back-office, sinon celle du site. */
    public static function ogImage(string $custom = ''): string
    {
        $base = rtrim((string) Config::get('site.url'), '/');
        if ($custom !== '') {
            return str_starts_with($custom, 'http') ? $custom : $base . $custom;
        }
        $configured = trim((string) (self::all()['og_image'] ?? ''));
        if ($configured !== '') {
            return str_starts_with($configured, 'http') ? $configured : $base . $configured;
        }
        return $base . '/assets/img/og-default.png';
    }

    /* ----------------------------------------------------- enregistrement */

    /**
     * Enregistre les réglages. Une adresse qui change est conservée dans
     * `former` pour être redirigée ; les collisions sont refusées.
     *
     * @return array{ok:bool, errors:string[]}
     */
    public static function save(array $routes, array $globals = [], ?int $userId = null): array
    {
        $store = self::all();
        $store['routes'] ??= [];
        $errors = [];
        $seen = [];

        foreach (self::ROUTES as $key => $meta) {
            $input = (array) ($routes[$key] ?? []);
            $entry = (array) ($store['routes'][$key] ?? []);

            $path = self::normalizePath((string) ($input['path'] ?? ''), (string) $meta['path']);
            $current = self::routePath($key);

            if (isset($seen[$path]) && $key !== 'cvs' && $seen[$path] !== 'cvs') {
                $errors[] = sprintf('« %s » : l’adresse %s est déjà utilisée par « %s ».',
                    $meta['label'], $path, self::ROUTES[$seen[$path]]['label']);
                $path = $current;
            }
            if ($path !== $current && self::collidesWithPage($path)) {
                $errors[] = sprintf('« %s » : l’adresse %s est celle d’une page éditoriale.',
                    $meta['label'], $path);
                $path = $current;
            }

            if ($path !== $current) {
                // L'ancienne adresse survit en redirection permanente.
                $former = (array) ($entry['former'] ?? []);
                $former[] = $current;
                $entry['former'] = array_values(array_unique(array_filter(
                    $former,
                    static fn(string $p) => $p !== $path,
                )));
            }

            $entry['path'] = $path;
            $entry['title'] = mb_substr(trim((string) ($input['title'] ?? '')), 0, 180);
            $entry['description'] = mb_substr(trim((string) ($input['description'] ?? '')), 0, 320);
            $entry['robots'] = ($input['robots'] ?? '') === 'noindex' ? 'noindex' : '';

            $store['routes'][$key] = $entry;
            $seen[$path] = $key;
        }

        if (array_key_exists('og_image', $globals)) {
            $store['og_image'] = mb_substr(trim((string) $globals['og_image']), 0, 300);
        }

        $ok = Lock::transaction('seo', static fn(): bool => Json::write(self::path(), $store));
        if ($ok) {
            self::$store = $store;
            Audit::log('seo.updated', ['routes' => count($store['routes'])], $userId);
        }
        return ['ok' => $ok, 'errors' => $errors];
    }

    /**
     * Change le slug d'une page éditoriale et garde la trace de l'ancien,
     * pour que l'ancienne adresse redirige au lieu de répondre 404.
     */
    public static function renamePage(string $from, string $to, ?int $userId = null): bool
    {
        $from = slugify($from);
        $to = slugify($to);
        if ($from === '' || $to === '' || $from === $to) {
            return false;
        }

        $store = self::all();
        $redirects = (array) ($store['page_redirects'] ?? []);
        $redirects[$from] = $to;

        // Une redirection qui pointait vers l'ancien slug suit le déplacement :
        // on ne construit jamais de chaîne de redirections.
        foreach ($redirects as $old => $target) {
            if ($target === $from) {
                $redirects[$old] = $to;
            }
        }
        unset($redirects[$to]);

        $store['page_redirects'] = $redirects;
        $ok = Lock::transaction('seo', static fn(): bool => Json::write(self::path(), $store));
        if ($ok) {
            self::$store = $store;
            Audit::log('seo.page_renamed', ['from' => $from, 'to' => $to], $userId);
        }
        return $ok;
    }

    private static function collidesWithPage(string $path): bool
    {
        $slug = trim($path, '/');
        return $slug !== '' && PageRepository::find($slug, 'fr') !== null;
    }

    /** Chemin propre : une barre initiale, pas de barre finale, slug ASCII. */
    private static function normalizePath(string $raw, string $fallback): string
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '/') {
            return $raw === '/' ? '/' : $fallback;
        }
        $segments = [];
        foreach (explode('/', trim($raw, '/')) as $segment) {
            $clean = slugify($segment, 60);
            if ($clean !== '' && $clean !== 'item') {
                $segments[] = $clean;
            }
        }
        return $segments === [] ? $fallback : '/' . implode('/', $segments);
    }
}
