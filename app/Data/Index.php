<?php
declare(strict_types=1);

namespace App\Data;

/**
 * Index compact de toutes les fiches (un résumé par fiche), mis en cache en
 * PHP (storage/cache/index.php) pour profiter d'OPcache : les mosaïques, menus,
 * recherches et calculs n'ouvrent jamais les 3 000 fichiers.
 */
final class Index
{
    private const CACHE = STORAGE_PATH . '/cache/index.php';
    private static ?array $items = null;
    private static ?array $byPath = null;

    /** Résumé d'une fiche (ce dont ont besoin listes, cartes et calculs). */
    public static function summary(array $d): array
    {
        $s = [
            'id' => (int) $d['id'],
            'type' => $d['type'],
            'status' => $d['status'] ?? 'publie',
            'publish_at' => $d['publish_at'] ?? null,
            'path' => $d['path'] ?? '',
            'title' => $d['title'] ?? '',
            'categories' => $d['categories'] ?? [],
            'a_la_une' => (bool) ($d['a_la_une'] ?? false),
            'image' => $d['featured_image'] ?? null,
            'date' => $d['date'] ?? null,
            'modified' => $d['modified'] ?? null,
            'excerpt' => self::excerpt($d),
            'has_en' => !empty($d['i18n']['en']['title']),
        ];
        if ($d['type'] === 'match') {
            $m = $d['match'];
            $s['m'] = [
                'date' => $m['date'] ?? null,
                'season' => $m['season'] ?? null,
                'competition' => $m['competition'] ?? null,
                'label' => $m['competition_label'] ?? null,
                'round' => $m['round'] ?? null,
                'home' => $m['home']['name'] ?? '',
                'home_level' => $m['home']['level'] ?? null,
                'away' => $m['away']['name'] ?? '',
                'away_level' => $m['away']['level'] ?? null,
                'sh' => (bool) ($m['sochaux_home'] ?? true),
                'sh_score' => isset($m['score']['home']) ? [$m['score']['home'], $m['score']['away']] : null,
                'extra' => $m['score']['extra'] ?? null,
                'pens' => isset($m['score']['pens']['home']) ? [$m['score']['pens']['home'], $m['score']['pens']['away']] : null,
                'result' => $m['result'] ?? null,
                'stadium' => $m['stadium'] ?? null,
                'spectators' => $m['spectators'] ?? null,
                'event' => $m['event'] ?? null,
                'opponent' => $m['opponent_club'] ?? null,
            ];
        } elseif ($d['type'] === 'personne') {
            $p = $d['personne'];
            $s['p'] = [
                'roles' => $p['roles'] ?? [],
                'name' => $p['display_name'] ?: ($d['title'] ?? ''),
                'first' => $p['first_name'] ?? '',
                'last' => $p['last_name'] ?? '',
                'position' => $p['position'] ?? null,
                'line' => $p['line'] ?? null,
                'birth_year' => isset($p['birth']['date']['iso']) ? (int) substr((string) $p['birth']['date']['iso'], 0, 4) : null,
                'birth_place' => $p['birth']['place']['city'] ?? null,
                'birth_country' => $p['birth']['place']['country'] ?? null,
                'arrival' => isset($p['arrival']['iso']) ? (int) substr((string) $p['arrival']['iso'], 0, 4) : (isset($p['arrival_coach']['iso']) ? (int) substr((string) $p['arrival_coach']['iso'], 0, 4) : null),
                'departure' => isset($p['departure']['iso']) ? (int) substr((string) $p['departure']['iso'], 0, 4) : (isset($p['departure_coach']['iso']) ? (int) substr((string) $p['departure_coach']['iso'], 0, 4) : null),
                'subtitle' => $p['subtitle'] ?? '',
                'trial' => (bool) ($p['is_trial'] ?? false),
                'formed' => (bool) ($p['formed_at_club'] ?? false),
                'intl' => (bool) ($p['international_flag'] ?? false),
                'legend' => (bool) ($p['legend'] ?? false),
                'album' => $p['album'] ?? null,
                'nickname' => $p['nickname'] ?? '',
            ];
        } elseif (in_array($d['type'], ['article', 'page'], true)) {
            $s['a'] = ['kind' => $d['article']['kind'] ?? 'article', 'season' => $d['article']['season'] ?? null];
        } elseif ($d['type'] === 'objet') {
            $s['o'] = ['collection' => $d['objet']['collection'] ?? null, 'year' => $d['objet']['year'] ?? null];
        } elseif ($d['type'] === 'moment') {
            $s['mo'] = ['number' => $d['moment']['number'] ?? null, 'year' => $d['moment']['year'] ?? null];
        }
        return $s;
    }

    private static function excerpt(array $d): string
    {
        if (!empty($d['seo']['description'])) {
            return mb_substr($d['seo']['description'], 0, 220);
        }
        if ($d['type'] === 'personne' && !empty($d['personne']['subtitle'])) {
            return $d['personne']['subtitle'];
        }
        foreach ($d['sections'] ?? [] as $s) {
            $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $s['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if (mb_strlen($t) > 40) {
                return mb_strlen($t) > 200 ? rtrim(mb_substr($t, 0, 197)) . '…' : $t;
            }
        }
        return '';
    }

    /** @return array<int,array> */
    public static function all(): array
    {
        if (self::$items === null) {
            if (is_file(self::CACHE)) {
                self::$items = include self::CACHE;
            }
            if (!is_array(self::$items)) {
                self::rebuild();
            }
        }
        return self::$items;
    }

    public static function rebuild(): array
    {
        $items = [];
        foreach (Fiches::all() as $id => $doc) {
            $items[$id] = self::summary($doc);
        }
        self::persist($items);
        return $items;
    }

    public static function put(array $doc): void
    {
        $items = self::all();
        $items[(int) $doc['id']] = self::summary($doc);
        self::persist($items);
    }

    public static function remove(int $id): void
    {
        $items = self::all();
        unset($items[$id]);
        self::persist($items);
    }

    private static function persist(array $items): void
    {
        $dir = dirname(self::CACHE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $tmp = self::CACHE . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, '<?php return ' . var_export($items, true) . ";\n", LOCK_EX);
        rename($tmp, self::CACHE);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::CACHE, true);
        }
        self::$items = $items;
        self::$byPath = null;
    }

    public static function get(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function visible(array $s): bool
    {
        if ($s['status'] === 'publie') {
            return true;
        }
        return $s['status'] === 'planifie' && $s['publish_at'] && strtotime($s['publish_at']) <= time();
    }

    /** @return array<int,array> fiches visibles du public */
    public static function published(?string $type = null): array
    {
        return array_filter(self::all(), fn ($s) => self::visible($s) && ($type === null || $s['type'] === $type));
    }

    public static function byPath(string $path): ?array
    {
        if (self::$byPath === null) {
            self::$byPath = [];
            foreach (self::all() as $id => $s) {
                if ($s['path']) {
                    self::$byPath[$s['path']] = $id;
                }
            }
        }
        $id = self::$byPath[$path] ?? self::$byPath[rtrim($path, '/') . '/'] ?? null;
        return $id ? self::get($id) : null;
    }

    /**
     * Fiches visibles d'une rubrique (et de ses sous-rubriques), dans l'ordre de la mosaïque.
     *
     * @return list<array>
     */
    public static function inCategory(string $slug, bool $withChildren = true): array
    {
        $slugs = $withChildren ? Categories::descendants($slug, true) : [$slug];
        $items = array_filter(self::published(), fn ($s) => (bool) array_intersect($slugs, $s['categories']));
        return self::ordered($items, $slug);
    }

    /** Applique l'ordre manuel de la rubrique, puis le tri naturel pour les fiches non classées. */
    public static function ordered(array $items, ?string $catSlug = null): array
    {
        $order = $catSlug ? array_flip(Categories::get($catSlug)['order'] ?? []) : [];
        uasort($items, function ($a, $b) use ($order) {
            $oa = $order[$a['id']] ?? null;
            $ob = $order[$b['id']] ?? null;
            if ($oa !== null && $ob !== null) {
                return $oa <=> $ob;
            }
            if ($oa !== null || $ob !== null) {
                return $oa !== null ? -1 : 1;
            }
            return self::naturalCompare($a, $b);
        });
        return array_values($items);
    }

    /** Tri naturel : matchs par date décroissante, personnes par nom, le reste par date de publication. */
    public static function naturalCompare(array $a, array $b): int
    {
        if (isset($a['m'], $b['m'])) {
            return strcmp((string) $b['m']['date'], (string) $a['m']['date']);
        }
        if (isset($a['p'], $b['p'])) {
            return strcoll(self::sortName($a), self::sortName($b));
        }
        return strcmp((string) $b['date'], (string) $a['date']);
    }

    public static function sortName(array $s): string
    {
        $last = $s['p']['last'] ?? $s['title'];
        $first = $s['p']['first'] ?? '';
        return mb_strtolower(transliterator_transliterate('Any-Latin; Latin-ASCII', "$last $first") ?: "$last $first");
    }

    public static function forget(): void
    {
        self::$items = null;
        self::$byPath = null;
    }
}
