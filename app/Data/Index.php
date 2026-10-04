<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\PhpCache;
use App\Front\Unknown;

/**
 * Index compact de toutes les fiches (un résumé par fiche), mis en cache en
 * PHP (storage/cache/index-2.php) pour profiter d'OPcache : les mosaïques, menus,
 * recherches et calculs n'ouvrent jamais les 3 000 fichiers.
 */
final class Index
{
    /** Le numéro change quand le résumé change : l'ancien index est alors ignoré et refait. */
    public const CACHE = STORAGE_PATH . '/cache/index-2.php';
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
            // Empreinte des textes français relus par le correcteur (vérification gardée si elle ne change pas).
            'tsig' => \App\Services\Proofreader::textSig($d),
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
                'stadium' => Unknown::has($m['stadium'] ?? '') ? null : ($m['stadium'] ?? null),
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
                'subtitle' => Unknown::line((string) ($p['subtitle'] ?? '')),
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

    /** Extrait affiché sur les cartes, l'accueil et dans la recherche (sans les « xx » de l'ancien site). */
    private static function excerpt(array $d): string
    {
        if (!empty($d['seo']['description']) && is_string($d['seo']['description'])) {
            return mb_substr($d['seo']['description'], 0, 220);
        }
        if ($d['type'] === 'personne' && is_string($d['personne']['subtitle'] ?? null) && ($sub = Unknown::line($d['personne']['subtitle'])) !== '') {
            return $sub;
        }
        foreach (is_array($d['sections'] ?? null) ? $d['sections'] : [] as $s) {
            $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Unknown::html((string) ($s['html'] ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
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
                // Index absent (installation, cache vidé) : refait une seule fois pour tous.
                self::$changes = [];
                self::loaded(PhpCache::remember(self::CACHE, fn () => self::scan()));
            }
        }
        return self::$items;
    }

    public static function rebuild(): array
    {
        self::$changes = [];
        return self::loaded(PhpCache::update(self::CACHE, fn () => self::scan()));
    }

    public static function put(array $doc): void
    {
        self::change((int) $doc['id'], self::summary($doc));
    }

    public static function remove(int $id): void
    {
        self::change($id, null);
    }

    private static int $defer = 0;
    /** @var array<int,?array> modifications pas encore écrites (null = fiche retirée) */
    private static array $changes = [];

    /** Mode « lot » : l'index n'est réécrit qu'une fois à la fin (actions groupées). */
    public static function defer(bool $on): void
    {
        if ($on) {
            self::$defer++;
            return;
        }
        self::$defer = max(0, self::$defer - 1);
        if (self::$defer === 0) {
            self::flush();
        }
    }

    private static function change(int $id, ?array $summary): void
    {
        self::$changes[$id] = $summary;
        if (self::$items !== null) {
            if ($summary === null) {
                unset(self::$items[$id]);
            } else {
                self::$items[$id] = $summary;
            }
            self::$byPath = null;
            self::$published = [];
        }
        if (self::$defer === 0) {
            self::flush();
        }
    }

    /**
     * Écrit les modifications sur l'index tel qu'il est sur le disque (relu sous verrou) :
     * ce que d'autres processus ont enregistré entre-temps est conservé.
     */
    private static function flush(): void
    {
        if (!self::$changes) {
            return;
        }
        $changes = self::$changes;
        self::$changes = [];
        self::loaded(PhpCache::update(self::CACHE, function (?array $items) use ($changes) {
            $items ??= self::scan();
            foreach ($changes as $id => $s) {
                if ($s === null) {
                    unset($items[$id]);
                } else {
                    $items[$id] = $s;
                }
            }
            return $items;
        }));
    }

    /** @return array<int,array> résumés de toutes les fiches, lus sur le disque */
    private static function scan(): array
    {
        $items = [];
        foreach (Fiches::all() as $id => $doc) {
            try {
                $items[$id] = self::summary($doc);
            } catch (\Throwable $e) {
                // Données dans un format inattendu : fiche laissée de côté (signalée dans Qualité).
                error_log('Index, fiche ' . $id . ' : ' . $e->getMessage());
            }
        }
        return $items;
    }

    private static function loaded(array $items): array
    {
        self::$items = $items;
        self::$byPath = null;
        self::$published = [];
        return $items;
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
    /**
     * Image générique partagée par de nombreuses fiches (silhouette « ? » des joueurs sans photo…) :
     * à écarter des vitrines (slider de l'accueil).
     */
    public static function isPlaceholderImage(mixed $image): bool
    {
        static $counts = null;
        if (!is_string($image) || $image === '') {
            return false;
        }
        if ($counts === null) {
            $counts = [];
            foreach (self::all() as $s) {
                if (is_string($s['image'] ?? null) && $s['image'] !== '') {
                    $counts[$s['image']] = ($counts[$s['image']] ?? 0) + 1;
                }
            }
        }
        return ($counts[$image] ?? 0) >= 10;
    }

    public static function published(?string $type = null): array
    {
        // Gardé pour la requête : menus, compteurs et listes le demandent plusieurs fois par page.
        return self::$published[$type ?? '*'] ??= array_filter(self::all(), fn ($s) => self::visible($s) && ($type === null || $s['type'] === $type));
    }

    /** @var array<string,array<int,array>> fiches visibles par type, pour la requête en cours */
    private static array $published = [];

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
        // Ordre de la rubrique gardé en cache tant que les fiches et les rubriques ne changent pas.
        $ids = \App\Core\Memo::get('rubrique/' . $slug . ($withChildren ? '' : '/seule'), [self::CACHE, Categories::FILE, __FILE__], '', function () use ($slug, $withChildren) {
            $slugs = $withChildren ? Categories::descendants($slug, true) : [$slug];
            $items = array_filter(self::published(), fn ($s) => (bool) array_intersect($slugs, $s['categories']));
            return array_column(self::ordered($items, $slug), 'id');
        });
        return self::pick($ids);
    }

    /** Personnes d'une rubrique (et de ses sous-rubriques) par ordre alphabétique (fiche précédente / suivante). */
    public static function personsByName(string $slug): array
    {
        $ids = \App\Core\Memo::get('personnes-par-nom/' . $slug, [self::CACHE, Categories::FILE, __FILE__], '', function () use ($slug) {
            $list = array_values(array_filter(self::inCategory($slug), fn ($s) => $s['type'] === 'personne'));
            usort($list, fn ($a, $b) => strcoll(self::sortName($a), self::sortName($b)));
            return array_column($list, 'id');
        });
        return self::pick($ids);
    }

    /** Résumés des fiches dans l'ordre donné (fiches disparues entre-temps écartées). */
    private static function pick(array $ids): array
    {
        $all = self::all();
        $out = [];
        foreach ($ids as $id) {
            if (isset($all[$id])) {
                $out[] = $all[$id];
            }
        }
        return $out;
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
        static $cache = [];
        $key = $s['id'] ?? null;
        if ($key !== null && isset($cache[$key])) {
            return $cache[$key];
        }
        $last = $s['p']['last'] ?? $s['title'];
        $first = $s['p']['first'] ?? '';
        $name = Names::ascii("$last $first");
        if ($key !== null) {
            $cache[$key] = $name;
        }
        return $name;
    }

    public static function forget(): void
    {
        self::$items = null;
        self::$byPath = null;
    }
}
