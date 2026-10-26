<?php
declare(strict_types=1);

namespace LD;

/**
 * Témoignages clients (page Avis, accueil, formation) : saisis à la main ou importés depuis Google (source « google »).
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

    /**
     * Avis mis en avant (accueil) : ceux cochés « accueil » d'abord, complétés par les autres avis publiés
     * (les bonnes notes au format d'une carte avant les autres).
     */
    public static function featured(int $n = 3, ?string $tag = null): array
    {
        $pub = self::all(true);
        if ($tag) {
            $pub = array_values(array_filter($pub, static fn($r) => ($r['tag'] ?? '') === $tag));
        }
        $rank = static fn($r) => !empty($r['home']) ? 0 : ((int) ($r['rating'] ?? 5) >= 4 && mb_strlen((string) ($r['text'] ?? '')) <= 420 ? 1 : 2);
        $ranked = array_map(null, $pub, array_keys($pub));
        usort($ranked, static fn($a, $b) => ($rank($a[0]) <=> $rank($b[0])) ?: ($a[1] <=> $b[1]));
        return array_slice(array_column($ranked, 0), 0, $n);
    }

    public static function isGoogle(array $r): bool
    {
        return ($r['source'] ?? '') === 'google';
    }

    /**
     * Widget d'avis d'un service tiers (code HTML/JS collé dans le back-office).
     * Retourne la position voulue pour la page ('avis' : before|after|replace, 'home' : after|replace) ou '' si rien à afficher.
     * Tant qu'aucun avis n'est publié, le widget prend la place des emplacements « Témoignage réel à fournir ».
     */
    public static function widgetPosition(string $page): string
    {
        $w = (array) Settings::get('reviews_widget', []);
        if (trim((string) ($w['code'] ?? '')) === '') {
            return '';
        }
        $pos = (string) ($w[$page] ?? 'off');
        if (in_array($pos, ['before', 'after', 'replace'], true)) {
            return $pos;
        }
        return self::all(true) ? '' : 'replace';
    }

    /** Code du widget, inséré tel quel (saisi uniquement par l'administrateur, comme le code de mesure d'audience). */
    public static function widgetCode(): string
    {
        return trim((string) Settings::get('reviews_widget.code', ''));
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
