<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Str;
use App\Core\Url;

/** Fiches des professionnels (adhérents). */
final class Pros
{
    public const STATUSES = [
        'active' => 'En ligne',
        'pending' => 'À valider',
        'suspended' => 'Suspendue',
        'inactive' => 'Ancien membre',
        'rejected' => 'Refusée',
        'deleted' => 'Supprimée',
    ];

    public static function displayName(array $p): string
    {
        $n = trim((string) ($p['display_name'] ?? ''));
        if ($n !== '') {
            return $n;
        }
        $company = trim((string) ($p['company'] ?? ''));
        if ($company !== '') {
            return $company;
        }
        return trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: 'Pro #' . ($p['id'] ?? '?');
    }

    public static function isPublished(array $p): bool
    {
        return ($p['status'] ?? '') === 'active';
    }

    /** Entrée d'index (champs utiles aux listes, à la recherche et à la carte). */
    public static function light(array $p): array
    {
        $cats = array_values(array_filter((array) ($p['categories'] ?? [])));
        $photos = (array) ($p['photos'] ?? []);
        $cover = null;
        foreach ($photos as $ph) {
            if (!empty($ph['cover'])) {
                $cover = $ph;
                break;
            }
        }
        $cover ??= $photos[0] ?? null;
        $catNames = array_map(static fn ($s) => Categories::name($s), $cats);
        $text = implode(' ', [
            self::displayName($p), $p['company'] ?? '', implode(' ', (array) ($p['tags'] ?? [])), $p['tagline'] ?? '',
            implode(' ', $catNames), $p['city'] ?? '', mb_substr(Str::text((string) ($p['description'] ?? '')), 0, 1500),
        ]);
        return [
            'id' => (int) $p['id'],
            'status' => $p['status'] ?? 'pending',
            'slug' => $p['slug'] ?? '',
            'name' => self::displayName($p),
            'first' => $p['first_name'] ?? '',
            'last' => $p['last_name'] ?? '',
            'email' => $p['email'] ?? '',
            'login' => $p['login'] ?? '',
            'phone' => $p['phone'] ?? '',
            'city' => $p['city'] ?? '',
            'cp' => $p['postcode'] ?? '',
            'insee' => $p['insee'] ?? '',
            'dep' => $p['dep'] ?? '',
            'region' => $p['region'] ?? '',
            'lat' => isset($p['lat']) ? (float) $p['lat'] : null,
            'lng' => isset($p['lng']) ? (float) $p['lng'] : null,
            'cats' => $cats,
            'zones' => array_values((array) ($p['zones'] ?? [])),
            'france' => !empty($p['all_france']),
            'accept' => ($p['accept_requests'] ?? true) !== false,
            'vacation' => !empty($p['settings']['vacation']),
            'photo' => $cover ? ($cover['file'] ?? null) : null,
            'photo_ext' => $cover ? ($cover['ext'] ?? 'webp') : null,
            'photos' => count($photos),
            'rating' => (float) ($p['rating']['avg'] ?? 0),
            'reviews' => (int) ($p['rating']['count'] ?? 0),
            'price' => isset($p['price_from']) && $p['price_from'] !== '' && $p['price_from'] !== null ? (int) $p['price_from'] : null,
            'tagline' => Str::limit((string) ($p['tagline'] ?? ''), 160),
            'hot' => !empty($p['stats']['hot']),
            'featured' => !empty($p['featured']) && (empty($p['featured_until']) || $p['featured_until'] >= date('Y-m-d')),
            'src' => $p['source'] ?? '',
            'verified' => !empty($p['email_verified']),
            'score' => self::completeness($p),
            'created' => $p['created_at'] ?? '',
            'updated' => $p['updated_at'] ?? '',
            'login_at' => $p['last_login_at'] ?? '',
            'views' => (int) ($p['stats']['views'] ?? 0),
            'video' => !empty($p['videos']),
            'text' => Str::norm($text),
        ];
    }

    /** Score de complétude de la fiche (0 à 100) — sert au classement et aux conseils. */
    public static function completeness(array $p): int
    {
        $s = 0;
        $photos = count((array) ($p['photos'] ?? []));
        $s += min(25, $photos * 9);
        $desc = mb_strlen(Str::text((string) ($p['description'] ?? '')));
        $s += $desc >= 600 ? 20 : ($desc >= 250 ? 14 : ($desc >= 80 ? 7 : 0));
        $s += mb_strlen((string) ($p['tagline'] ?? '')) >= 30 ? 10 : 0;
        $s += !empty($p['categories']) ? 10 : 0;
        $s += !empty($p['zones']) || !empty($p['all_france']) ? 5 : 0;
        $s += (!empty($p['website']) || !empty(array_filter((array) ($p['socials'] ?? [])))) ? 10 : 0;
        $s += !empty($p['videos']) ? 5 : 0;
        $s += isset($p['price_from']) && $p['price_from'] !== '' && $p['price_from'] !== null ? 5 : 0;
        $s += !empty($p['phone']) ? 5 : 0;
        $s += !empty($p['insee']) ? 5 : 0;
        return min(100, $s);
    }

    /** Conseils pour améliorer la fiche. @return string[] */
    public static function tips(array $p): array
    {
        $t = [];
        if (count((array) ($p['photos'] ?? [])) < 3) {
            $t[] = 'Ajoutez au moins 3 photos : les fiches illustrées reçoivent beaucoup plus de demandes.';
        }
        if (mb_strlen(Str::text((string) ($p['description'] ?? ''))) < 600) {
            $t[] = 'Détaillez votre prestation (600 caractères ou plus) : déroulé, matériel, formules, expérience.';
        }
        if (mb_strlen((string) ($p['tagline'] ?? '')) < 30) {
            $t[] = 'Rédigez une accroche percutante : elle apparaît sur votre carte dans les résultats.';
        }
        if (empty($p['videos'])) {
            $t[] = 'Ajoutez une vidéo YouTube ou Vimeo de vos prestations.';
        }
        if (!isset($p['price_from']) || $p['price_from'] === '' || $p['price_from'] === null) {
            $t[] = 'Indiquez un prix de départ (« dès … € ») pour rassurer les clients.';
        }
        if (empty($p['website']) && empty(array_filter((array) ($p['socials'] ?? [])))) {
            $t[] = 'Ajoutez votre site web ou vos réseaux sociaux.';
        }
        if ((int) ($p['rating']['count'] ?? 0) === 0) {
            $t[] = 'Invitez vos derniers clients à laisser un avis vérifié depuis votre espace.';
        }
        return $t;
    }

    public static function findByLogin(string $identifier): ?array
    {
        $id = trim($identifier);
        if ($id === '') {
            return null;
        }
        $isEmail = str_contains($id, '@');
        $low = mb_strtolower($id);
        $match = null;
        foreach (Store::pros()->iterate() as $row) {
            if (in_array($row['status'], ['deleted'], true)) {
                continue;
            }
            if ($isEmail ? mb_strtolower((string) $row['email']) === $low : ((string) $row['login'] === $id || mb_strtolower((string) $row['login']) === $low)) {
                // en cas de doublon d'email, on privilégie la fiche active la plus récente
                if ($match === null || ($row['status'] === 'active' && $match['status'] !== 'active')) {
                    $match = $row;
                }
            }
        }
        return $match ? Store::pros()->get((int) $match['id']) : null;
    }

    public static function emailTaken(string $email, int $exceptId = 0): bool
    {
        $email = mb_strtolower(trim($email));
        foreach (Store::pros()->iterate() as $row) {
            if ((int) $row['id'] !== $exceptId && $row['status'] !== 'deleted' && mb_strtolower((string) $row['email']) === $email) {
                return true;
            }
        }
        return false;
    }

    public static function loginTaken(string $login, int $exceptId = 0): bool
    {
        $low = mb_strtolower(trim($login));
        foreach (Store::pros()->iterate() as $row) {
            if ((int) $row['id'] !== $exceptId && mb_strtolower((string) $row['login']) === $low) {
                return true;
            }
        }
        return false;
    }

    public static function uniqueSlug(string $base, int $id): string
    {
        $slug = Str::slug($base, 60) ?: 'pro';
        $taken = [];
        foreach (Store::pros()->iterate() as $row) {
            if ((int) $row['id'] !== $id) {
                $taken[$row['slug']] = true;
            }
        }
        $candidate = $slug;
        $i = 2;
        while (isset($taken[$candidate])) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    public static function bySlug(string $slug): ?array
    {
        foreach (self::publicIndex() as $row) {
            if ($row['slug'] === $slug) {
                return Store::pros()->get((int) $row['id']);
            }
        }
        // anciens slugs (redirection 301)
        foreach (Store::pros()->iterate() as $row) {
            if ($row['slug'] === $slug) {
                return Store::pros()->get((int) $row['id']);
            }
        }
        $hist = Store::doc('slug_history')->get('pros.' . $slug);
        return $hist ? Store::pros()->get((int) $hist) : null;
    }

    /** @return array<int,array> pros publiés (index léger), mis en cache */
    public static function publicIndex(): array
    {
        return Cache::remember('pros_public', 0, static function (): array {
            $out = [];
            foreach (Store::pros()->iterate(false) as $id => $row) {
                if ($row['status'] === 'active') {
                    unset($row['email'], $row['login'], $row['phone'], $row['first'], $row['last'], $row['login_at']);
                    $out[(int) $id] = $row;
                }
            }
            return $out;
        });
    }

    public static function publicCount(): int
    {
        return count(self::publicIndex());
    }

    /** Domaines acceptés pour chaque réseau social (variantes courtes et mobiles comprises). */
    public const SOCIAL_HOSTS = [
        'facebook' => ['facebook.com', 'fb.com', 'fb.me', 'fb.watch'],
        'instagram' => ['instagram.com', 'instagr.am'],
        'tiktok' => ['tiktok.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'linkedin' => ['linkedin.com', 'lnkd.in'],
    ];

    /** Lien de réseau social normalisé (https://…), ou '' s'il ne correspond pas au réseau. */
    public static function socialUrl(string $net, string $raw): string
    {
        $raw = trim($raw);
        if ($raw !== '' && $raw[0] === '@' && in_array($net, ['instagram', 'tiktok'], true)) {
            $raw = ($net === 'tiktok' ? 'tiktok.com/' : 'instagram.com/') . ($net === 'tiktok' ? $raw : substr($raw, 1));
        }
        $u = \App\Core\Str::url($raw);
        $host = strtolower((string) parse_url($u, PHP_URL_HOST));
        foreach (self::SOCIAL_HOSTS[$net] ?? [] as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return $u;
            }
        }
        return '';
    }

    /**
     * Vidéo lisible sur la fiche à partir d'un lien YouTube ou Vimeo, quelle que soit sa forme (watch?v=, youtu.be,
     * shorts, live, embed, lien mobile, paramètres en plus, player.vimeo.com…). null si ce n'est pas une vidéo
     * (lien de chaîne, de playlist seule…).
     * @return array{src:string, label:string}|null
     */
    public static function video(string $url): ?array
    {
        $url = trim($url);
        $host = strtolower((string) parse_url(\App\Core\Str::url($url), PHP_URL_HOST));
        $path = (string) parse_url(\App\Core\Str::url($url), PHP_URL_PATH);
        parse_str((string) parse_url(\App\Core\Str::url($url), PHP_URL_QUERY), $q);
        $yt = preg_match('/(^|\.)(youtube\.com|youtube-nocookie\.com)$/', $host) === 1;
        $id = null;
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif ($yt && isset($q['v']) && is_string($q['v'])) {
            $id = $q['v'];
        } elseif ($yt && preg_match('#^/(?:shorts|live|embed|v)/([^/?]+)#', $path, $m)) {
            $id = $m[1];
        }
        if ($id !== null && preg_match('/^[A-Za-z0-9_-]{6,15}$/', $id)) {
            return ['src' => 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0', 'label' => 'Vidéo YouTube'];
        }
        if (preg_match('/(^|\.)vimeo\.com$/', $host) && preg_match('#/(\d{5,12})(?:/|$)#', $path, $m)) {
            return ['src' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1', 'label' => 'Vidéo Vimeo'];
        }
        return null;
    }

    /** À appeler après toute modification d'une fiche. */
    public static function changed(): void
    {
        Cache::forget('pros_public');
        Cache::forget('map_points');
        Cache::forget('stats_public');
        Cache::forget('local_links');
        Cache::bump();
        Seo::markSitemapDirty();
    }

    /**
     * Enregistre une fiche (création ou mise à jour) en recalculant les champs dérivés.
     */
    public static function save(int $id, array|callable $patch): ?array
    {
        $col = Store::pros();
        $res = $col->update($id, static function (array $cur) use ($patch): array {
            $next = is_callable($patch) ? $patch($cur) : \App\Core\Collection::merge($cur, $patch);
            return self::derive($next, $cur);
        });
        self::changed();
        return $res;
    }

    public static function create(array $data): array
    {
        $data = self::derive($data, null);
        $p = Store::pros()->insert($data);
        if (empty($p['slug'])) {
            $p = Store::pros()->update((int) $p['id'], ['slug' => self::uniqueSlug(self::displayName($p) . ' ' . ($p['city'] ?? ''), (int) $p['id'])]) ?? $p;
        }
        self::changed();
        return $p;
    }

    /** Champs calculés : géolocalisation, région, slug, catégories par défaut. */
    public static function derive(array $p, ?array $previous): array
    {
        $cityChanged = $previous === null || ($previous['city'] ?? '') !== ($p['city'] ?? '') || ($previous['postcode'] ?? '') !== ($p['postcode'] ?? '');
        if ($cityChanged && empty($p['geo_manual'])) {
            $c = Geo::match((string) ($p['city'] ?? ''), (string) ($p['postcode'] ?? ''));
            if ($c) {
                $p['insee'] = $c['insee'];
                $p['dep'] = $c['d'];
                $p['region'] = $c['r'];
                $p['lat'] = (float) $c['la'];
                $p['lng'] = (float) $c['lo'];
                $p['city_official'] = $c['n'];
                $p['geo_precision'] = 'commune';
            } else {
                $dep = Geo::depFromPostcode((string) ($p['postcode'] ?? ''));
                if ($dep === '' && !empty($p['zones'])) {
                    $dep = Geo::depCode((string) array_values((array) $p['zones'])[0]);
                }
                $p['insee'] = '';
                $p['dep'] = Geo::dep($dep) ? $dep : '';
                $d = Geo::dep($dep);
                $p['region'] = $d['region'] ?? '';
                $p['lat'] = $d['la'] ?? null;
                $p['lng'] = $d['lo'] ?? null;
                $p['geo_precision'] = $d ? 'departement' : 'none';
            }
        }
        if (!empty($p['dep']) && empty($p['region'])) {
            $p['region'] = Geo::dep((string) $p['dep'])['region'] ?? '';
        }
        $p['zones'] = array_values(array_unique(array_filter(array_map(static fn ($z) => Geo::depCode((string) $z), (array) ($p['zones'] ?? [])), static fn ($z) => Geo::dep($z) !== null)));
        $p['categories'] = array_values(array_unique(array_filter((array) ($p['categories'] ?? []), static fn ($c) => Categories::get((string) $c) !== null)));
        if ($previous !== null && ($previous['slug'] ?? '') !== '' && ($p['slug'] ?? '') !== ($previous['slug'] ?? '')) {
            Store::doc('slug_history')->set('pros.' . $previous['slug'], (int) $p['id']);
        }
        if (($p['status'] ?? '') === 'active' && empty($p['published_at'])) {
            $p['published_at'] = date('c');
        }
        return $p;
    }

    /** URL d'image (gère les deux extensions possibles selon le support WebP du serveur). */
    public static function photo(array $photo, string $size = 'md'): string
    {
        $ext = $photo['ext'] ?? 'webp';
        return '/media/pros/' . $photo['file'] . '-' . $size . '.' . $ext;
    }

    public static function coverOf(array $light, string $size = 'md'): ?string
    {
        if (empty($light['photo'])) {
            return null;
        }
        return '/media/pros/' . $light['photo'] . '-' . $size . '.' . ($light['photo_ext'] ?? 'webp');
    }

    public static function url(array $p): string
    {
        return Url::pro($p);
    }

    public static function primaryCategory(array $p): ?array
    {
        $cats = (array) ($p['categories'] ?? $p['cats'] ?? []);
        return Categories::get($cats[0] ?? null);
    }

    /** Pros qui reçoivent une demande pour un département / une ville / des catégories. @return int[] */
    public static function recipientsFor(string $dep, ?float $lat, ?float $lng, array $cats, int $max, int $radiusKm): array
    {
        $scored = [];
        foreach (self::publicIndex() as $id => $p) {
            if (!$p['accept'] || $p['vacation']) {
                continue;
            }
            $catOk = !$cats || array_intersect($cats, $p['cats']);
            if (!$catOk) {
                continue;
            }
            $score = null;
            if ($dep !== '' && ($p['dep'] === $dep || in_array($dep, $p['zones'], true))) {
                $score = 100;
            }
            if ($lat !== null && $lng !== null && $p['lat'] !== null) {
                $d = Geo::distance($lat, $lng, (float) $p['lat'], (float) $p['lng']);
                if ($d <= $radiusKm) {
                    $score = max($score ?? 0, 100 - $d);
                }
            }
            if ($score === null && $p['france']) {
                $score = 10;
            }
            if ($score !== null) {
                $scored[$id] = $score + $p['score'] / 20;
            }
        }
        arsort($scored);
        return array_slice(array_map('intval', array_keys($scored)), 0, $max);
    }
}
