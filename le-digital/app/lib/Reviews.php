<?php
declare(strict_types=1);

namespace LD;

/**
 * Témoignages clients (page Avis, accueil, formation).
 */
final class Reviews
{
    private const FILE = 'data/reviews.json';
    public const TAGS = ['Développement', 'Formation', 'Accompagnement'];

    public static function all(bool $publishedOnly = false): array
    {
        $all = Store::read(self::FILE, []);
        if ($publishedOnly) {
            $all = array_filter($all, static fn($r) => !empty($r['published']));
        }
        usort($all, static fn($a, $b) => ((int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0)) ?: strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_values($all);
    }

    /** Avis mis en avant (accueil) : ceux cochés « accueil », sinon les premiers publiés. */
    public static function featured(int $n = 3, ?string $tag = null): array
    {
        $pub = self::all(true);
        if ($tag) {
            $pub = array_values(array_filter($pub, static fn($r) => ($r['tag'] ?? '') === $tag));
        }
        $home = array_values(array_filter($pub, static fn($r) => !empty($r['home'])));
        return array_slice($home ?: $pub, 0, $n);
    }

    public static function find(string $id): ?array
    {
        foreach (Store::read(self::FILE, []) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        return null;
    }

    public static function save(array $review): array
    {
        $review['id'] = $review['id'] ?: bin2hex(random_bytes(5));
        $review['created_at'] ??= date('c');
        $review['updated_at'] = date('c');
        Store::update(self::FILE, static function (array &$all) use ($review): void {
            foreach ($all as $i => $r) {
                if ($r['id'] === $review['id']) {
                    $all[$i] = $review;
                    return;
                }
            }
            $all[] = $review;
        });
        PageCache::clear();
        return $review;
    }

    public static function delete(string $id): void
    {
        Store::update(self::FILE, static function (array &$all) use ($id): void {
            $all = array_values(array_filter($all, static fn($r) => $r['id'] !== $id));
        });
        PageCache::clear();
    }
}
