<?php

declare(strict_types=1);

namespace App\Content;

use App\Core\JsonStore;
use App\Core\Logger;
use App\Core\Schema;
use App\Security\Sanitizer;

/**
 * Pages du site : un fichier JSON par page dans data/pages/.
 * Un index léger (data/pages-index.json) évite de tout relire à chaque requête.
 */
final class Pages
{
    private static ?array $index = null;

    public static function dir(): string
    {
        return DATA_PATH . '/pages';
    }

    public static function indexFile(): string
    {
        return DATA_PATH . '/pages-index.json';
    }

    public static function pageSchema(): array
    {
        return [
            'id'          => '',
            'slug'        => '',
            'status'      => 'published',   // published | draft
            'type'        => 'page',        // page | post
            'home'        => false,
            'order'       => 100,
            'in_menu'     => true,
            'in_footer'   => false,
            'nav_label'   => ['fr' => ''],
            'title'       => ['fr' => ''],
            'excerpt'     => ['fr' => ''],
            'cover'       => '',
            'published_at'=> '',
            'seo' => [
                'title'       => ['fr' => ''],
                'description' => ['fr' => ''],
                'og_image'    => '',
                'noindex'     => false,
            ],
            'blocks'      => [],
            'created_at'  => '',
            'updated_at'  => '',
            'version'     => 1,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Index                                                               */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array<string,mixed>> */
    public static function index(bool $fresh = false): array
    {
        if (!$fresh && self::$index !== null) {
            return self::$index;
        }
        $data = JsonStore::read(self::indexFile(), ['pages' => []], $fresh);
        $pages = array_values(array_filter($data['pages'] ?? [], 'is_array'));

        // Reconstruction si l'index est vide alors que des pages existent
        // (auto-réparation après restauration ou copie manuelle de fichiers).
        if ($pages === [] && glob(self::dir() . '/*.json')) {
            $pages = self::rebuildIndex();
        }

        usort($pages, static fn (array $a, array $b): int => ((int) ($a['order'] ?? 100)) <=> ((int) ($b['order'] ?? 100)));
        return self::$index = $pages;
    }

    /** @return array<int,array<string,mixed>> */
    public static function rebuildIndex(): array
    {
        $entries = [];
        foreach (glob(self::dir() . '/*.json') ?: [] as $file) {
            $page = JsonStore::read($file, self::pageSchema(), true);
            if (($page['slug'] ?? '') === '') {
                continue;
            }
            $entries[] = self::indexEntry($page);
        }
        usort($entries, static fn (array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));
        JsonStore::write(self::indexFile(), ['pages' => $entries, 'rebuilt_at' => date('c')]);
        self::$index = $entries;
        return $entries;
    }

    private static function indexEntry(array $page): array
    {
        return [
            'id'        => (string) $page['id'],
            'slug'      => (string) $page['slug'],
            'status'    => (string) $page['status'],
            'type'      => (string) $page['type'],
            'home'      => (bool) $page['home'],
            'order'     => (int) $page['order'],
            'in_menu'   => (bool) $page['in_menu'],
            'in_footer' => (bool) $page['in_footer'],
            'nav_label' => $page['nav_label'],
            'title'     => $page['title'],
            'excerpt'   => $page['excerpt'],
            'cover'     => (string) $page['cover'],
            'published_at' => (string) $page['published_at'],
            'updated_at'=> (string) $page['updated_at'],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Lecture                                                             */
    /* ------------------------------------------------------------------ */

    public static function path(string $slug): string
    {
        // Anti-traversée : le slug ne peut produire qu'un nom de fichier plat.
        $safe = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug)) ?? '';
        return self::dir() . '/' . ($safe !== '' ? $safe : 'page') . '.json';
    }

    /** @return array<string,mixed>|null */
    public static function find(string $slug, bool $includeDrafts = false): ?array
    {
        $file = self::path($slug);
        if (!is_file($file)) {
            return null;
        }
        $page = JsonStore::read($file, self::pageSchema());
        if (!$includeDrafts && ($page['status'] ?? '') !== 'published') {
            return null;
        }
        $page['blocks'] = array_map(
            [Blocks::class, 'normalize'],
            array_values(array_filter($page['blocks'] ?? [], 'is_array'))
        );
        return $page;
    }

    /** @return array<string,mixed>|null */
    public static function home(): ?array
    {
        foreach (self::index() as $entry) {
            if (!empty($entry['home']) && ($entry['status'] ?? '') === 'published') {
                return self::find((string) $entry['slug']);
            }
        }
        $first = self::index()[0] ?? null;
        return $first ? self::find((string) $first['slug']) : null;
    }

    public static function homeSlug(): string
    {
        foreach (self::index() as $entry) {
            if (!empty($entry['home'])) {
                return (string) $entry['slug'];
            }
        }
        return (string) (self::index()[0]['slug'] ?? 'accueil');
    }

    /** @return array<int,array<string,mixed>> pages publiées du menu principal */
    public static function menu(): array
    {
        return array_values(array_filter(
            self::index(),
            static fn (array $p): bool => ($p['status'] ?? '') === 'published'
                && !empty($p['in_menu'])
                && ($p['type'] ?? 'page') === 'page'
        ));
    }

    /** @return array<int,array<string,mixed>> */
    public static function footerLinks(): array
    {
        return array_values(array_filter(
            self::index(),
            static fn (array $p): bool => ($p['status'] ?? '') === 'published' && !empty($p['in_footer'])
        ));
    }

    /** @return array<int,array<string,mixed>> actualités les plus récentes */
    public static function posts(int $limit = 6): array
    {
        $posts = array_values(array_filter(
            self::index(),
            static fn (array $p): bool => ($p['status'] ?? '') === 'published' && ($p['type'] ?? '') === 'post'
        ));
        usort($posts, static fn (array $a, array $b): int => strcmp((string) ($b['published_at'] ?? ''), (string) ($a['published_at'] ?? '')));
        return array_slice($posts, 0, max(1, $limit));
    }

    /** @return array<int,array<string,mixed>> toutes les pages, brouillons compris (back-office) */
    public static function allForAdmin(): array
    {
        return self::index(true);
    }

    /* ------------------------------------------------------------------ */
    /* Écriture                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string,mixed> $page
     * @return array{ok:bool,error?:string,slug?:string}
     */
    public static function save(array $page, ?string $originalSlug = null): array
    {
        $page = Schema::normalize($page, self::pageSchema());

        $slug = Sanitizer::slug((string) ($page['slug'] !== '' ? $page['slug'] : \App\I18n\Translator::pick($page['title'])));
        if ($slug === '') {
            return ['ok' => false, 'error' => 'invalid_slug'];
        }
        $page['slug'] = $slug;

        if ($page['id'] === '') {
            $page['id'] = bin2hex(random_bytes(8));
        }
        if ($page['created_at'] === '') {
            $page['created_at'] = date('c');
        }
        if ($page['type'] === 'post' && $page['published_at'] === '') {
            $page['published_at'] = date('c');
        }
        $page['updated_at'] = date('c');

        // Conflit de slug avec une autre page.
        $existing = self::findRaw($slug);
        if ($existing && (string) $existing['id'] !== (string) $page['id']) {
            return ['ok' => false, 'error' => 'slug_taken'];
        }

        $page['blocks'] = array_map([Blocks::class, 'normalize'], array_values(array_filter($page['blocks'], 'is_array')));

        JsonStore::write(self::path($slug), $page);

        // Renommage : on supprime l'ancien fichier après écriture du nouveau.
        if ($originalSlug !== null && $originalSlug !== '' && $originalSlug !== $slug) {
            $old = self::path($originalSlug);
            if (is_file($old)) {
                @unlink($old);
            }
        }

        // Une seule page d'accueil.
        if (!empty($page['home'])) {
            self::clearHomeExcept($page['id']);
        }

        self::rebuildIndex();
        Logger::audit('page.save', ['slug' => $slug]);

        return ['ok' => true, 'slug' => $slug];
    }

    /** @return array<string,mixed>|null lecture sans filtre de statut */
    public static function findRaw(string $slug): ?array
    {
        $file = self::path($slug);
        return is_file($file) ? JsonStore::read($file, self::pageSchema(), true) : null;
    }

    private static function clearHomeExcept(string $keepId): void
    {
        foreach (glob(self::dir() . '/*.json') ?: [] as $file) {
            $page = JsonStore::read($file, self::pageSchema(), true);
            if ((string) $page['id'] !== $keepId && !empty($page['home'])) {
                $page['home'] = false;
                JsonStore::write($file, $page, false);
            }
        }
    }

    public static function delete(string $slug): bool
    {
        $page = self::findRaw($slug);
        if (!$page) {
            return false;
        }
        if (!empty($page['home'])) {
            return false; // La page d'accueil ne peut pas être supprimée.
        }
        // Sauvegarde avant suppression : la reprise de contenu reste possible.
        JsonStore::write(self::path($slug), $page);
        @unlink(self::path($slug));
        self::rebuildIndex();
        Logger::audit('page.delete', ['slug' => $slug]);
        return true;
    }

    public static function duplicate(string $slug): ?string
    {
        $page = self::findRaw($slug);
        if (!$page) {
            return null;
        }
        $page['id']     = bin2hex(random_bytes(8));
        $page['slug']   = $slug . '-copie';
        $page['home']   = false;
        $page['status'] = 'draft';
        foreach ($page['title'] as $lang => $value) {
            $page['title'][$lang] = $value . ' (copie)';
        }
        $result = self::save($page);
        return $result['ok'] ? ($result['slug'] ?? null) : null;
    }

    /** Réordonne le menu depuis le back-office. @param array<int,string> $slugs */
    public static function reorder(array $slugs): void
    {
        $order = 10;
        foreach ($slugs as $slug) {
            $page = self::findRaw((string) $slug);
            if ($page) {
                $page['order'] = $order;
                JsonStore::write(self::path((string) $slug), $page, false);
                $order += 10;
            }
        }
        self::rebuildIndex();
    }

    public static function flush(): void
    {
        self::$index = null;
    }
}
