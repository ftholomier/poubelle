<?php
declare(strict_types=1);

namespace LD;

/**
 * Articles du blog.
 */
final class Posts
{
    private const FILE = 'data/posts.json';

    public static function all(bool $publishedOnly = false): array
    {
        $all = Store::read(self::FILE, []);
        if ($publishedOnly) {
            $today = date('Y-m-d');
            $all = array_filter($all, static fn($p) => !empty($p['published']) && substr($p['date'] ?? '', 0, 10) <= $today);
        }
        usort($all, static fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        return array_values($all);
    }

    /** Code du fil de posts LinkedIn (application externe), inséré tel quel sur la page Blog. */
    public static function linkedinCode(): string
    {
        return trim((string) Settings::get('linkedin_feed.code', ''));
    }

    /** Position du fil LinkedIn sur la page Blog : before|after|replace, ou '' si rien à afficher. */
    public static function linkedinPlace(): string
    {
        if (self::linkedinCode() === '') {
            return '';
        }
        $place = (string) Settings::get('linkedin_feed.place', 'before');
        return in_array($place, ['before', 'after', 'replace'], true) ? $place : '';
    }

    public static function find(string $id): ?array
    {
        foreach (Store::read(self::FILE, []) as $p) {
            if ($p['id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (Store::read(self::FILE, []) as $p) {
            if (($p['slug'] ?? '') === $slug) {
                return $p;
            }
        }
        return null;
    }

    /** Articles similaires : même thème d'abord, puis les plus récents. */
    public static function related(array $post, int $n = 3): array
    {
        $others = array_values(array_filter(self::all(true), static fn($p) => $p['id'] !== $post['id']));
        usort($others, static fn($a, $b) => (($b['theme'] ?? '') === ($post['theme'] ?? '')) <=> (($a['theme'] ?? '') === ($post['theme'] ?? '')) ?: strcmp($b['date'] ?? '', $a['date'] ?? ''));
        return array_slice($others, 0, $n);
    }

    public static function uniqueSlug(string $slug, string $id): string
    {
        $base = slugify($slug);
        $slug = $base;
        $n = 2;
        while (($p = self::bySlug($slug)) && $p['id'] !== $id) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }

    public static function save(array $post): array
    {
        $post['id'] = $post['id'] ?: bin2hex(random_bytes(5));
        // Programmé : publié mais daté dans le futur (mis en ligne automatiquement le jour venu, voir releaseDue()).
        $post['scheduled'] = !empty($post['published']) && substr((string) ($post['date'] ?? ''), 0, 10) > date('Y-m-d');
        $post['slug'] = self::uniqueSlug($post['slug'] ?: $post['title'], $post['id']);
        $post['content'] = Html::sanitize((string) $post['content']);
        $post['read'] = reading_minutes($post['content']);
        $post['created_at'] ??= date('c');
        $post['updated_at'] = date('c');
        Store::update(self::FILE, static function (array &$all) use ($post): void {
            foreach ($all as $i => $p) {
                if ($p['id'] === $post['id']) {
                    $all[$i] = $post;
                    return;
                }
            }
            $all[] = $post;
        });
        PageCache::clear();
        self::refreshRelease();
        return $post;
    }

    public static function delete(string $id): void
    {
        Store::update(self::FILE, static function (array &$all) use ($id): void {
            $all = array_values(array_filter($all, static fn($p) => $p['id'] !== $id));
        });
        PageCache::clear();
        self::refreshRelease();
    }

    /** Articles programmés (publiés, datés dans le futur), du plus proche au plus lointain. */
    public static function scheduled(): array
    {
        $list = array_values(array_filter(self::all(), static fn($p) => !empty($p['published']) && substr((string) ($p['date'] ?? ''), 0, 10) > date('Y-m-d')));
        return array_reverse($list);
    }

    /** Date de la prochaine mise en ligne programmée (petit fichier lu à chaque visite, sans ouvrir tous les articles). */
    private static function refreshRelease(): void
    {
        $next = '';
        foreach (Store::read(self::FILE, []) as $p) {
            $d = substr((string) ($p['date'] ?? ''), 0, 10);
            if (!empty($p['scheduled']) && !empty($p['published']) && ($next === '' || $d < $next)) {
                $next = $d;
            }
        }
        Store::write('data/blog-release.json', ['next' => $next]);
    }

    /**
     * Mise en ligne des articles programmés : le jour venu, le cache des pages est vidé (l'article apparaît aussitôt
     * sur le blog) et Fred reçoit un e-mail. Appelé à chaque visite (lecture d'un petit fichier) et par la tâche planifiée.
     * @return string[] titres des articles mis en ligne
     */
    public static function releaseDue(): array
    {
        $next = (string) (Store::read('data/blog-release.json', [])['next'] ?? '');
        if ($next === '' || $next > date('Y-m-d')) {
            return [];
        }
        $today = date('Y-m-d');
        $released = [];
        Store::update(self::FILE, static function (array &$all) use ($today, &$released): void {
            foreach ($all as &$p) {
                if (!empty($p['scheduled']) && substr((string) ($p['date'] ?? ''), 0, 10) <= $today) {
                    $p['scheduled'] = false;
                    if (!empty($p['published'])) {
                        $released[] = $p;
                    }
                }
            }
        });
        PageCache::clear();
        self::refreshRelease();
        if ($released && Settings::get('mail.notify', true)) {
            $html = '<p>' . (count($released) > 1 ? 'Ces articles programmés sont en ligne' : 'Ton article programmé est en ligne') . ' sur le blog :</p><ul>';
            foreach ($released as $p) {
                $html .= '<li><a href="' . e(abs_url('/blog/' . $p['slug'])) . '"><b>' . e($p['title']) . '</b></a></li>';
            }
            $html .= '</ul><p>Pense à ' . (count($released) > 1 ? 'les' : 'le') . ' partager sur LinkedIn 😉</p>';
            Mailer::send(Mailer::notifyAddress(), '📰 ' . (count($released) > 1 ? count($released) . ' articles en ligne' : 'En ligne : ' . $released[0]['title']),
                Mailer::layout('Nouvel article en ligne', $html, 'Voir le blog', abs_url('/blog')));
        }
        return array_map(static fn($p) => $p['title'], $released);
    }
}
