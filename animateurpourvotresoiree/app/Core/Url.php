<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Categories;
use App\Services\Geo;

/**
 * Construction des URL du site (structure SEO : métier / département / ville / pro).
 *   /dj/                              hub national d'un métier
 *   /dj/auvergne-rhone-alpes/         région
 *   /dj/rhone-69/                     département
 *   /dj/rhone-69/lyon/                ville
 *   /dj/rhone-69/lyon/sunny-groove/   fiche d'un pro
 *   /animateurs/...                   tous métiers confondus
 */
final class Url
{
    public const ALL = 'animateurs';

    public static function to(string $path = '/', array $query = []): string
    {
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '' && $v !== []);
        return $path . ($query ? '?' . http_build_query($query) : '');
    }

    public static function abs(string $path = '/'): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return rtrim(Request::baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = PUBLIC_PATH . '/assets/' . $path;
        $v = is_file($file) ? substr(base_convert((string) filemtime($file), 10, 36), -6) : APP_VERSION;
        return '/assets/' . $path . '?v=' . $v;
    }

    public static function admin(string $path = ''): string
    {
        $base = '/' . trim((string) Env::get('ADMIN_PATH', 'gestion'), '/');
        if ($path === '') {
            return $base . '/';
        }
        return $base . '/' . ltrim($path, '/');
    }

    public static function category(?string $cat): string
    {
        return '/' . ($cat ?: self::ALL) . '/';
    }

    public static function region(?string $cat, string $regionCode): string
    {
        $r = Geo::region($regionCode);
        return $r ? '/' . ($cat ?: self::ALL) . '/' . $r['slug'] . '/' : self::category($cat);
    }

    public static function dep(?string $cat, string $depCode): string
    {
        $d = Geo::dep($depCode);
        return $d ? '/' . ($cat ?: self::ALL) . '/' . $d['slug'] . '/' : self::category($cat);
    }

    public static function city(?string $cat, string $insee): string
    {
        $c = Geo::commune($insee);
        if (!$c) {
            return self::category($cat);
        }
        $d = Geo::dep($c['d']);
        return '/' . ($cat ?: self::ALL) . '/' . $d['slug'] . '/' . $c['s'] . '/';
    }

    public static function occasion(string $slug, ?string $depCode = null): string
    {
        if ($depCode && ($d = Geo::dep($depCode))) {
            return '/' . $slug . '/' . $d['slug'] . '/';
        }
        return '/' . $slug . '/';
    }

    /** URL canonique de la fiche d'un pro (enregistrement complet ou entrée d'index). */
    public static function pro(array $p): string
    {
        $cats = (array) ($p['categories'] ?? $p['cats'] ?? []);
        $cat = $cats[0] ?? null;
        $cat = ($cat && Categories::get($cat)) ? $cat : self::ALL;
        $slug = (string) ($p['slug'] ?? '');
        $dep = Geo::dep((string) ($p['dep'] ?? ''));
        if (!$dep || $slug === '') {
            return '/pro/' . ($slug !== '' ? $slug : (string) ($p['id'] ?? '')) . '/';
        }
        $c = !empty($p['insee']) ? Geo::commune((string) $p['insee']) : null;
        $citySlug = $c['s'] ?? (Str::slug((string) ($p['city'] ?? '')) ?: 'ville');
        return '/' . $cat . '/' . $dep['slug'] . '/' . $citySlug . '/' . $slug . '/';
    }

    public static function blog(?string $slug = null): string
    {
        return $slug ? '/blog/' . $slug . '/' : '/blog/';
    }

    public static function page(string $slug): string
    {
        return '/' . trim($slug, '/') . '/';
    }

    public static function devis(array $params = []): string
    {
        return self::to('/devis/', $params);
    }

    public static function search(array $params = []): string
    {
        return self::to('/recherche/', $params);
    }

    public static function media(string $rel): string
    {
        return '/media/' . ltrim($rel, '/');
    }
}
