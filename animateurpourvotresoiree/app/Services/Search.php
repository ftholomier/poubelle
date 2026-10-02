<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Str;
use App\Core\Url;

/**
 * Moteur de recherche des pros (index en mémoire, quelques centaines de fiches publiées).
 * Critères : texte, métier, occasion, région, département, ville + rayon, position GPS.
 */
final class Search
{
    private const STOP = ['de', 'du', 'des', 'la', 'le', 'les', 'un', 'une', 'et', 'pour', 'a', 'au', 'aux', 'en', 'dans', 'sur', 'mon', 'ma', 'mes', 'notre', 'nos', 'avec', 'pas', 'cher', 'pro', 'pros', 'professionnel', 'recherche', 'cherche', 'trouver', 'je', 'j', 'l', 'd'];
    private const SYN = [
        'dj' => ['dj', 'disc jockey', 'disque jockey', 'discjockey', 'deejay', 'discomobile'],
        'disc' => ['dj'], 'jockey' => ['dj'],
        'karaoke' => ['karaoke', 'karaok'],
        'magie' => ['magicien', 'magie', 'illusionniste'],
        'clown' => ['clown', 'enfant'],
        'photomaton' => ['photobooth', 'photomaton', 'borne photo'],
        'photobooth' => ['photobooth', 'photomaton', 'borne photo'],
        'orchestre' => ['orchestre', 'groupe', 'musiciens'],
        'groupe' => ['groupe', 'orchestre'],
        'chanteuse' => ['chanteuse', 'chanteur'],
        'mariage' => ['mariage', 'wedding', 'vin d honneur'],
        'sono' => ['sono', 'sonorisation'],
    ];

    /**
     * @param array{q?:string,cat?:string,occasion?:string,region?:string,dep?:string,insee?:string,lat?:float,lng?:float,radius?:int,photo?:bool,reviews?:bool,sort?:string,page?:int,per?:int,ids?:int[]} $c
     * @return array{total:int, ids:int[], items:array<int,array>, page:int, pages:int, center:?array, focus:?int[]}
     */
    public static function run(array $c): array
    {
        $all = Pros::publicIndex();
        $q = trim((string) ($c['q'] ?? ''));
        $cat = (string) ($c['cat'] ?? '');
        $occasion = !empty($c['occasion']) ? Categories::occasion((string) $c['occasion']) : null;
        $region = !empty($c['region']) ? Geo::region((string) $c['region']) : null;
        $dep = !empty($c['dep']) ? Geo::depCode((string) $c['dep']) : '';
        $city = !empty($c['insee']) ? Geo::commune((string) $c['insee']) : null;
        $lat = isset($c['lat']) && $c['lat'] !== '' ? (float) $c['lat'] : ($city ? (float) $city['la'] : null);
        $lng = isset($c['lng']) && $c['lng'] !== '' ? (float) $c['lng'] : ($city ? (float) $city['lo'] : null);
        $radius = max(5, min(300, (int) ($c['radius'] ?? Settings::get('listing.radius_km', 40))));
        if ($city) {
            $dep = $city['d'];
        }
        $tokens = self::tokens($q);
        $qCats = $q !== '' ? Categories::detect($q) : [];
        $onlyIds = isset($c['ids']) ? array_flip(array_map('intval', (array) $c['ids'])) : null;
        $seed = (int) date('Ymd');

        $rows = [];
        foreach ($all as $id => $p) {
            if ($onlyIds !== null && !isset($onlyIds[$id])) {
                continue;
            }
            if ($cat !== '' && !in_array($cat, $p['cats'], true)) {
                continue;
            }
            if (!empty($c['photo']) && empty($p['photo'])) {
                continue;
            }
            if (!empty($c['reviews']) && $p['reviews'] < 1) {
                continue;
            }
            $score = 0.0;
            $dist = null;
            $local = 0;
            // --- localisation
            if ($city || ($lat !== null && $lng !== null && empty($dep) && !$region)) {
                if ($p['lat'] !== null && $lat !== null) {
                    $dist = Geo::distance($lat, $lng, (float) $p['lat'], (float) $p['lng']);
                }
                $inZone = $dep !== '' && in_array($dep, $p['zones'], true);
                if ($dist !== null && $dist <= $radius) {
                    $local = 3;
                } elseif ($inZone) {
                    $local = 2;
                } elseif ($p['france']) {
                    $local = 1;
                } else {
                    continue;
                }
            } elseif ($dep !== '') {
                if ($p['dep'] === $dep) {
                    $local = 3;
                } elseif (in_array($dep, $p['zones'], true)) {
                    $local = 2;
                } elseif ($p['france']) {
                    $local = 1;
                } else {
                    continue;
                }
            } elseif ($region) {
                if ($p['region'] === $region['code']) {
                    $local = 3;
                } elseif (array_intersect($region['deps'], $p['zones'])) {
                    $local = 2;
                } elseif ($p['france']) {
                    $local = 1;
                } else {
                    continue;
                }
            }
            // --- occasion
            if ($occasion) {
                $hit = (bool) array_intersect($occasion['cats'] ?? [], $p['cats']);
                foreach ($occasion['keywords'] ?? [] as $kw) {
                    if (str_contains(' ' . $p['text'] . ' ', ' ' . Str::norm($kw))) {
                        $hit = true;
                        $score += 2;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            // --- texte
            if ($tokens) {
                $ts = self::textScore($p, $tokens);
                if ($qCats && array_intersect($qCats, $p['cats'])) {
                    $ts += 6;
                }
                if ($ts <= 0) {
                    continue;
                }
                $score += $ts * 10;
            }
            $score += $local * 6 + $p['score'] / 12 + $p['rating'] * 1.5 + min(5, $p['reviews']) * 0.6 + ($p['photo'] ? 3 : 0) + (!empty($p['featured']) ? 4 : 0);
            // rotation quotidienne pour donner leur chance à tous
            $score += (crc32($seed . ':' . $id) % 1000) / 250;
            $rows[] = ['id' => (int) $id, 'score' => $score, 'dist' => $dist, 'local' => $local];
        }

        $sort = (string) ($c['sort'] ?? '');
        if ($sort === '' && ($city || ($lat !== null && !$dep && !$region))) {
            $sort = 'distance';
        }
        usort($rows, static function ($a, $b) use ($sort, $all): int {
            return match ($sort) {
                'distance' => [$b['local'] >= 2 ? 1 : 0, $a['dist'] ?? 99999] <=> [$a['local'] >= 2 ? 1 : 0, $b['dist'] ?? 99999],
                'rating' => [$all[$b['id']]['rating'], $all[$b['id']]['reviews']] <=> [$all[$a['id']]['rating'], $all[$a['id']]['reviews']],
                'recent' => strcmp((string) $all[$b['id']]['created'], (string) $all[$a['id']]['created']),
                'price' => ($all[$a['id']]['price'] ?? PHP_INT_MAX) <=> ($all[$b['id']]['price'] ?? PHP_INT_MAX),
                default => $b['score'] <=> $a['score'],
            };
        });

        $per = max(1, min(100, (int) ($c['per'] ?? Settings::get('listing.per_page', 24))));
        $total = count($rows);
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($pages, (int) ($c['page'] ?? 1)));
        $offset = isset($c['offset']) ? max(0, (int) $c['offset']) : ($page - 1) * $per;
        $items = [];
        foreach (array_slice($rows, $offset, $per) as $r) {
            $items[] = $all[$r['id']] + ['distance' => $r['dist']];
        }
        return [
            'total' => $total,
            'ids' => array_column($rows, 'id'),
            'items' => $items,
            'page' => $page,
            'pages' => $pages,
            'center' => $lat !== null ? ['lat' => $lat, 'lng' => $lng] : null,
            // lieu demandé : pros situés sur place (la carte se cadre sur eux, pas sur ceux qui viennent de loin)
            'focus' => ($city || $dep !== '' || $region || $lat !== null) ? array_values(array_column(array_filter($rows, static fn (array $r): bool => $r['local'] === 3), 'id')) : null,
        ];
    }

    private static function tokens(string $q): array
    {
        $words = array_filter(explode(' ', Str::norm($q)), static fn ($w) => $w !== '' && !in_array($w, self::STOP, true) && (strlen($w) > 1 || $w === 'dj'));
        $out = [];
        foreach ($words as $w) {
            $out[$w] = self::SYN[$w] ?? [$w];
        }
        return $out;
    }

    private static function textScore(array $p, array $tokens): float
    {
        $text = ' ' . $p['text'] . ' ';
        $name = ' ' . Str::norm($p['name']) . ' ';
        $score = 0.0;
        $matched = 0;
        foreach ($tokens as $word => $variants) {
            $best = 0.0;
            foreach ($variants as $v) {
                $v = Str::norm($v);
                if ($v === '') {
                    continue;
                }
                if (str_contains($name, ' ' . $v)) {
                    $best = max($best, 3.0);
                } elseif (str_contains($text, ' ' . $v . ' ')) {
                    $best = max($best, 2.0);
                } elseif (strlen($v) >= 4 && str_contains($text, ' ' . $v)) {
                    $best = max($best, 1.2);
                }
            }
            if ($best > 0) {
                $matched++;
                $score += $best;
            }
        }
        // Tous les mots doivent correspondre (au moins la majorité pour les longues requêtes).
        $need = count($tokens) <= 2 ? count($tokens) : (int) ceil(count($tokens) * 0.6);
        return $matched >= $need ? $score : 0.0;
    }

    /** Carte de résultat pour l'API (données publiques uniquement). */
    public static function card(array $p): array
    {
        $cat = Categories::get($p['cats'][0] ?? null);
        return [
            'id' => $p['id'],
            'name' => $p['name'],
            'url' => Url::pro($p),
            'cat' => $cat['name'] ?? 'Animation',
            'catSlug' => $cat['slug'] ?? '',
            'color' => $cat['color'] ?? '#ffd23f',
            'city' => $p['city'] ?: (Geo::commune($p['insee'])['n'] ?? ''),
            'dep' => $p['dep'],
            'tagline' => $p['tagline'],
            'photo' => Pros::coverOf($p, 'sm'),
            'rating' => $p['reviews'] > 0 ? number_format($p['rating'], 1, ',', '') : null,
            'reviews' => $p['reviews'],
            'price' => $p['price'],
            'hot' => $p['hot'],
            'distance' => isset($p['distance']) && $p['distance'] !== null ? (int) round($p['distance']) : null,
            'lat' => $p['lat'],
            'lng' => $p['lng'],
        ];
    }

    /** Tous les points de la carte (créés une seule fois côté navigateur). */
    public static function mapPoints(): array
    {
        return \App\Core\Cache::remember('map_points', 0, static function (): array {
            $out = [];
            foreach (Pros::publicIndex() as $p) {
                if ($p['lat'] === null) {
                    continue;
                }
                $cat = Categories::get($p['cats'][0] ?? null);
                $out[] = [
                    'id' => $p['id'],
                    'lat' => round((float) $p['lat'], 4),
                    'lng' => round((float) $p['lng'], 4),
                    'name' => $p['name'],
                    'color' => $cat['color'] ?? '#ffd23f',
                    'sub' => ($cat['name'] ?? 'Animation') . ' · ' . ($p['city'] ?: ''),
                    'img' => Pros::coverOf($p, 'sm'),
                    'url' => Url::pro($p),
                ];
            }
            return $out;
        });
    }
}
