<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Fs;
use App\Core\Sanitizer;
use App\Core\Str;

/** Articles du blog (dont les anciennes « Actualités » migrées avec redirection 301). */
final class Blog
{
    public const CATEGORIES = [
        'conseils' => 'Conseils',
        'mariage' => 'Mariage',
        'anniversaire' => 'Anniversaire',
        'entreprise' => 'Entreprise',
        'materiel' => 'Matériel & location',
        'partenaires' => 'Partenaires',
    ];

    /** @return array<int,array> articles de l'ancien site (app/data/seed/articles.json) */
    public static function seed(): array
    {
        $data = Fs::readJson(APP_PATH . '/data/seed/articles.json', []);
        return $data['articles'] ?? [];
    }

    public static function seedIfEmpty(): int
    {
        $col = Store::articles();
        if ($col->count() > 0) {
            return 0;
        }
        $n = 0;
        $base = strtotime('2024-01-15');
        foreach (self::seed() as $i => $a) {
            $title = Str::clean((string) $a['title'], false);
            $text = Str::text((string) $a['body']);
            $cat = 'conseils';
            $t = Str::norm($title . ' ' . $text);
            if (str_contains($t, 'mariage')) {
                $cat = 'mariage';
            } elseif (str_contains($t, 'anniversaire')) {
                $cat = 'anniversaire';
            } elseif (str_contains($t, 'entreprise') || str_contains($t, 'seminaire')) {
                $cat = 'entreprise';
            } elseif (preg_match('/location|materiel|sonorisation|vaisselle|kakemono/', $t)) {
                $cat = 'materiel';
            }
            $col->insert([
                'status' => 'published',
                'slug' => (string) $a['slug'],
                'title' => $title,
                'excerpt' => Str::limit((string) $a['excerpt'], 220),
                'body' => Sanitizer::html((string) $a['body'], ['images' => true, 'link_rel' => 'noopener']),
                'image' => (string) ($a['image'] ?? ''),
                'image_alt' => $title,
                'category' => $cat,
                'tags' => [],
                'sponsored' => !empty($a['sponsored']),
                'legacy_url' => (string) $a['legacy_url'],
                'seo' => ['title' => '', 'description' => Str::limit((string) ($a['meta_description'] ?? ''), 170, '')],
                'author' => Settings::siteName(),
                'views' => 0,
                'reading_time' => max(1, (int) round(str_word_count(Str::ascii($text)) / 220)),
                'published_at' => date('c', $base - $i * 86400 * 23),
            ]);
            $n++;
        }
        return $n;
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (Store::articles()->iterate() as $row) {
            if ($row['slug'] === $slug) {
                return Store::articles()->get((int) $row['id']);
            }
        }
        return null;
    }

    public static function byLegacyUrl(string $path): ?array
    {
        foreach (Store::articles()->iterate() as $row) {
            if ($row['legacy'] !== '' && strcasecmp($row['legacy'], $path) === 0) {
                return $row;
            }
        }
        return null;
    }

    /** @return array{total:int, items:array} articles publiés */
    public static function published(int $limit = 12, int $offset = 0, ?string $cat = null): array
    {
        $now = date('c');
        return Store::articles()->find(
            static fn ($a) => $a['status'] === 'published' && $a['published'] <= $now && ($cat === null || $a['cat'] === $cat),
            static fn ($a, $b) => strcmp($b['published'], $a['published']),
            $limit,
            $offset
        );
    }

    public static function imageUrl(?string $image, string $size = 'lg'): ?string
    {
        if (!$image) {
            return null;
        }
        if (preg_match('/\.(webp|jpe?g|png|gif)$/i', $image)) {
            return $image;
        }
        return $image . '-' . $size . '.webp';
    }

    /** Insère un bloc publicitaire après le 2e paragraphe. */
    public static function withInlineAd(string $html, string $ad): string
    {
        if ($ad === '') {
            return $html;
        }
        $pos = 0;
        for ($i = 0; $i < 2; $i++) {
            $p = stripos($html, '</p>', $pos);
            if ($p === false) {
                return $html . $ad;
            }
            $pos = $p + 4;
        }
        return substr($html, 0, $pos) . $ad . substr($html, $pos);
    }
}
