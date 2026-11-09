<?php

declare(strict_types=1);

/**
 * Accès au contenu du site (coordonnées, pages, produits, actus, avis, réalisations…).
 * Toutes les données viennent de Store (storage/data, initialisé depuis content/).
 */
final class Content
{
    public static function site(): array
    {
        return Store::get('site');
    }

    public static function ui(): array
    {
        return Store::get('ui');
    }

    public static function pages(): array
    {
        return Store::get('pages');
    }

    public static function page(string $key): array
    {
        return self::pages()[$key] ?? [];
    }

    /** Chemin public d'une page (« / », « /devis/ »…). */
    public static function path(string $key): string
    {
        return (string) (self::page($key)['path'] ?? '/');
    }

    public static function menu(): array
    {
        return array_values(array_filter(Store::get('menu'), fn($m) => !empty($m['visible'])));
    }

    public static function products(bool $all = false): array
    {
        $p = Store::get('products');
        if (!$all) {
            $p = array_filter($p, fn($x) => !empty($x['visible']));
        }
        usort($p, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return array_values($p);
    }

    public static function product(string $id): ?array
    {
        foreach (Store::get('products') as $p) {
            if (($p['id'] ?? '') === $id) {
                return $p;
            }
        }
        return null;
    }

    /** Actualités visibles, de la plus récente à la plus ancienne (site et Facebook mélangés). */
    public static function posts(bool $all = false): array
    {
        $p = Store::get('posts');
        if (!$all) {
            $p = array_filter($p, fn($x) => !empty($x['visible']) && ($x['date'] ?? '') <= date('Y-m-d'));
        }
        usort($p, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')) ?: strcmp((string) $b['id'], (string) $a['id']));
        return array_values($p);
    }

    public static function post(string $id): ?array
    {
        foreach (Store::get('posts') as $p) {
            if (($p['id'] ?? '') === $id) {
                return $p;
            }
        }
        return null;
    }

    public static function categories(): array
    {
        $c = array_values(array_filter((array) (self::site()['news_categories'] ?? [])));
        return $c ?: ['Chantiers', 'Conseils', 'Agence'];
    }

    /**
     * Avis affichés sur le site. Fiche Google connectée : avis Google (mode « google »), ou avis
     * Google puis avis saisis dans le back-office sans doublon (mode « mix », par défaut).
     * Sinon, ou en mode « manual » : avis saisis dans le back-office.
     */
    public static function reviews(bool $all = false): array
    {
        $r = Store::get('reviews');
        if ($all) {
            return array_values($r);
        }
        $manual = array_values(array_filter($r, fn($x) => !empty($x['visible']) && trim((string) ($x['text'] ?? '')) !== ''));
        $mode = (string) Settings::get('google_reviews_mode', 'mix');
        if ($mode === 'manual' || !GoogleReviews::connected()) {
            return $manual;
        }
        $google = GoogleReviews::reviews();
        if (!$google) {
            return $manual;
        }
        if ($mode === 'google') {
            return $google;
        }
        $key = fn($x) => mb_strtolower(trim((string) $x['name'])) . '|' . mb_strtolower(mb_substr(preg_replace('/\s+/u', ' ', trim((string) $x['text'])) ?? '', 0, 40));
        $seen = array_flip(array_map($key, $google));
        return array_merge($google, array_values(array_filter($manual, fn($x) => !isset($seen[$key($x)]))));
    }

    /** Note moyenne et nombre d'avis affichés : fiche Google si connectée, sinon calculés (réglables). */
    public static function rating(): array
    {
        $s = self::site();
        $g = Settings::get('google_reviews_mode', 'mix') !== 'manual' && GoogleReviews::connected() ? GoogleReviews::data() : [];
        $all = array_filter(Store::get('reviews'), fn($x) => !empty($x['visible']));
        $avg = $all ? array_sum(array_map(fn($x) => (float) ($x['rating'] ?? 5), $all)) / count($all) : 5.0;
        if (isset($g['rating']) && $g['rating'] !== null) {
            $avg = (float) $g['rating'];
        }
        $cnt = !empty($g['count']) ? (int) $g['count'] : count($all);
        $note = trim((string) ($s['rating_value'] ?? '')) !== '' ? (float) str_replace(',', '.', (string) $s['rating_value']) : round($avg, 1);
        $count = trim((string) ($s['rating_count'] ?? '')) !== '' ? (int) $s['rating_count'] : $cnt;
        return ['value' => $note, 'label' => number_format($note, 1, ',', ''), 'count' => $count, 'source' => (string) ($s['rating_source'] ?? 'avis Google'),
            'url' => trim((string) ($s['reviews_url'] ?? '')) ?: (string) ($g['maps_url'] ?? ''), 'review_url' => (string) ($g['review_url'] ?? '')];
    }

    public static function realisations(): array
    {
        return array_values(array_filter(Store::get('realisations'), fn($x) => !isset($x['visible']) || $x['visible']));
    }

    public static function gallery(): array
    {
        $g = Store::get('gallery');
        $g['items'] = array_values(array_filter($g['items'] ?? [], fn($x) => !isset($x['visible']) || $x['visible']));
        return $g;
    }

    public static function devisChoices(): array
    {
        return Store::get('devis')['choices'] ?? [];
    }

    /**
     * Table des adresses : chemin => [type, clé]. Les adresses d'origine de renovest.fr
     * (WordPress) sont gardées telles quelles pour le référencement.
     */
    public static function routes(): array
    {
        static $r = null;
        if ($r !== null) {
            return $r;
        }
        $r = [];
        foreach (self::pages() as $key => $p) {
            if (!empty($p['path'])) {
                $r[self::norm($p['path'])] = ['page', $key];
            }
        }
        foreach (self::products() as $p) {
            $r[self::norm($p['path'])] = ['product', $p['id']];
        }
        foreach (self::posts() as $p) {
            if (!empty($p['path'])) {
                $r[self::norm($p['path'])] = ['post', $p['id']];
            }
        }
        return $r;
    }

    /** Chemin comparable : décodé, sans slash final (sauf la racine), en minuscules. */
    public static function norm(string $path): string
    {
        $p = rawurldecode($path);
        $p = '/' . trim($p, '/');
        return mb_strtolower($p);
    }

    /** Chemin unique pour un nouvel élément (actu, produit). */
    public static function uniquePath(string $title, string $exceptId = ''): string
    {
        $base = '/' . slugify($title) . '/';
        $path = $base;
        $i = 2;
        $routes = self::routes();
        while (isset($routes[self::norm($path)]) && $routes[self::norm($path)][1] !== $exceptId) {
            $path = '/' . slugify($title) . '-' . $i++ . '/';
        }
        return $path;
    }

    /** Première image d'une actu. */
    public static function postImage(array $p): string
    {
        return (string) (($p['images'] ?? [])[0] ?? '');
    }

    public static function postExcerpt(array $p, int $n = 160): string
    {
        $e = trim((string) ($p['excerpt'] ?? ''));
        if ($e === '') {
            $e = plain((string) ($p['content'] ?? ''));
        }
        return excerpt($e, $n);
    }

    /** Adresse e-mail de contact, découpée pour ne pas être lue par les robots. */
    public static function emailParts(): array
    {
        $m = (string) (self::site()['email'] ?? '');
        $at = strpos($m, '@');
        return $at === false ? [$m, ''] : [substr($m, 0, $at), substr($m, $at + 1)];
    }
}
