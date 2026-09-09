<?php
declare(strict_types=1);

namespace App;

/**
 * Lecture du contenu éditorial. Tolérante par construction : une clé absente
 * renvoie la valeur par défaut, journalise le manque et ne casse jamais le rendu.
 */
final class Content
{
    /** @var array<string,array> cache de requête */
    private static array $cache = [];

    private static array $missing = [];

    public static function settings(): array
    {
        return self::load('settings.json');
    }

    /**
     * Contenu d'une page dans une langue, avec repli automatique sur le français.
     * Les clés de la langue demandée écrasent celles du FR (traduction partielle possible).
     */
    public static function page(string $slug, string $lang = Config::DEFAULT_LANG): array
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?: 'home';
        $fr = self::load("pages/{$slug}." . Config::DEFAULT_LANG . '.json');
        if ($lang === Config::DEFAULT_LANG) {
            return $fr;
        }
        $translated = self::load("pages/{$slug}.{$lang}.json");
        if ($translated === []) {
            return $fr;
        }
        return self::mergeDeep($fr, $translated);
    }

    /** Une page est publiée si aucun statut « draft » n'est posé. */
    public static function isPublished(array $page): bool
    {
        return ($page['status'] ?? 'published') === 'published';
    }

    public static function posts(): array
    {
        $data = self::load('posts.json');
        $posts = \is_array($data['posts'] ?? null) ? $data['posts'] : [];
        usort($posts, static fn (array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
        return $posts;
    }

    public static function publishedPosts(): array
    {
        return array_values(array_filter(self::posts(), static fn (array $p): bool => ($p['status'] ?? 'published') === 'published'));
    }

    public static function post(string $slug): ?array
    {
        foreach (self::posts() as $post) {
            if (($post['slug'] ?? '') === $slug) {
                return $post;
            }
        }
        return null;
    }

    /**
     * Accès tolérant par chemin pointé : Content::get($page, 'hero.title', 'Défaut').
     * Toute clé manquante est journalisée une seule fois puis signalée au back-office.
     */
    public static function get(array $data, string $path, mixed $default = ''): mixed
    {
        $node = $data;
        foreach (explode('.', $path) as $key) {
            if (!\is_array($node) || !\array_key_exists($key, $node)) {
                self::flagMissing($path);
                return $default;
            }
            $node = $node[$key];
        }
        if ($node === null || $node === '') {
            return $default;
        }
        return $node;
    }

    /** Valeur texte, toujours une chaîne. */
    public static function text(array $data, string $path, string $default = ''): string
    {
        $v = self::get($data, $path, $default);
        return \is_scalar($v) ? (string) $v : $default;
    }

    /** Valeur liste, toujours un tableau de liste. */
    public static function list(array $data, string $path, array $default = []): array
    {
        $v = self::get($data, $path, $default);
        return \is_array($v) ? array_values($v) : $default;
    }

    /** Traduction d'un champ d'objet : i18n.<lang>.<champ> avec repli FR. */
    public static function i18n(array $item, string $field, string $lang, string $default = ''): string
    {
        if ($lang !== Config::DEFAULT_LANG) {
            $translated = $item['i18n'][$lang][$field] ?? null;
            if (\is_scalar($translated) && (string) $translated !== '') {
                return (string) $translated;
            }
        }
        $value = $item[$field] ?? $default;
        return \is_scalar($value) ? (string) $value : $default;
    }

    /** @return string[] chemins de contenu manquants rencontrés pendant la requête */
    public static function missing(): array
    {
        return array_keys(self::$missing);
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function load(string $relative): array
    {
        if (!isset(self::$cache[$relative])) {
            self::$cache[$relative] = Store::read($relative);
        }
        return self::$cache[$relative];
    }

    private static function flagMissing(string $path): void
    {
        if (isset(self::$missing[$path])) {
            return;
        }
        self::$missing[$path] = true;
        Log::write('content', 'Clé de contenu absente : ' . $path);
    }

    /** Fusion récursive : les listes sont remplacées, les objets fusionnés. */
    private static function mergeDeep(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (\is_array($value) && \is_array($base[$key] ?? null) && !array_is_list($value)) {
                $base[$key] = self::mergeDeep($base[$key], $value);
            } elseif ($value !== null && $value !== '' && $value !== []) {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
