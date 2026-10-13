<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\PhpCache;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;

/**
 * Album Sochini (Album Parini à Paris : nom dans config/clubs/<club>/libelles.php, clé « album_vignettes ») :
 * des albums d'autocollants calculés depuis les compositions des fiches matchs, sur une même
 * mécanique que l'album de saison (App\Front\SeasonAlbum) : une liste de joueurs + leurs chiffres
 * sur un périmètre de matchs officiels (amicaux comptés à part).
 *
 * Types : saison, décennie, entraîneur, épopée (finales et parcours européens), Lionceaux (formés
 * au club), internationaux, gardiens, buteurs, fidèles, entraîneurs (staff).
 *
 * Tout est calculé d'un coup puis mis en cache (storage/cache/album/sochini/) : un catalogue (liste
 * des albums, titres, nombres de vignettes) et un fichier par album. Invalidation comme
 * SeasonAlbum::raw : empreinte Derived::built() + VERSION. La fiche liée (nom, photo, visibilité)
 * est relue à l'affichage.
 */
final class AlbumBook
{
    public const VERSION = 1;
    /** Entraîneurs proposés : au moins N matchs officiels dirigés. */
    public const MIN_COACH = 20;
    /** Fidèles : au moins N matchs officiels, ou au moins N saisons avec un match officiel. */
    public const LOYAL_MATCHES = 200;
    public const LOYAL_SEASONS = 10;
    public const TOP_SCORERS = 50;
    public const SHINY_SCORERS = 10;
    /** Vignettes rendues par page (la suite se charge à la demande). */
    public const PAGE = 60;

    public const TYPES = ['saison', 'decennie', 'entraineur', 'epopee', 'lionceaux', 'internationaux', 'gardiens', 'buteurs', 'fideles', 'staff'];
    /** Types à album unique (pas de valeur « v » dans l'adresse). */
    public const SINGLE = ['lionceaux', 'internationaux', 'gardiens', 'buteurs', 'fideles', 'staff'];
    public const PATH = '/interactif/album-vignettes/';

    private static ?array $catalog = null;

    /** Nom de la rubrique pour le club servi (« Album Sochini », « Album Parini »). */
    public static function name(): string
    {
        return (string) (\App\Core\Club::content('libelles', [])['album_vignettes'] ?? 'Album du club');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'saison' => t('Par saison'),
            'decennie' => t('Par décennie'),
            'entraineur' => t('Par entraîneur'),
            'epopee' => t('Épopées'),
            'lionceaux' => t('Les Lionceaux'),
            'internationaux' => t('Les internationaux'),
            'gardiens' => t('Les gardiens'),
            'buteurs' => t('Les buteurs'),
            'fideles' => t('Les fidèles'),
            'staff' => t('Les entraîneurs'),
            default => $type,
        };
    }

    public static function url(string $type, string $v = '', int $page = 1): string
    {
        $q = ['album' => $type];
        if ($v !== '' && !in_array($type, self::SINGLE, true)) {
            $q['v'] = $v;
        }
        if ($page > 1) {
            $q['p'] = $page;
        }
        return url(self::PATH) . '?' . http_build_query($q);
    }

    // ------------------------------------------------------------------ cache

    private static function dir(): string
    {
        return STORAGE_PATH . '/cache/album/sochini';
    }

    private static function file(string $type, string $v): string
    {
        return self::dir() . '/' . $type . ($v !== '' ? '-' . preg_replace('/[^a-z0-9-]/', '', $v) : '') . '.php';
    }

    /**
     * Catalogue : type => [v => {title, kicker, count, period, cover}] (albums vides exclus).
     * @return array<string,array<string,array>>
     */
    public static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }
        $built = Derived::built();
        $c = PhpCache::read(self::dir() . '/catalogue.php');
        if ($c && ($c['built'] ?? null) === $built && ($c['v'] ?? 0) === self::VERSION) {
            return self::$catalog = $c['data'];
        }
        $all = self::computeAll();
        $cat = [];
        foreach ($all as $type => $albums) {
            foreach ($albums as $v => $a) {
                $cat[$type][$v] = array_diff_key($a, ['items' => 1]) + ['count' => count($a['items']),
                    'cover' => array_slice(array_values(array_filter(array_column(self::sortedBy($a['items'], 'mj'), 'pid'))), 0, 6)];
                try {
                    PhpCache::write(self::file($type, (string) $v), ['built' => $built, 'v' => self::VERSION, 'data' => $a]);
                } catch (\Throwable) {
                }
            }
        }
        try {
            PhpCache::write(self::dir() . '/catalogue.php', ['built' => $built, 'v' => self::VERSION, 'data' => $cat]);
        } catch (\Throwable) {
            // Cache en lecture seule : catalogue servi sans être enregistré.
        }
        self::$albumsMemo = $all;
        return self::$catalog = $cat;
    }

    private static ?array $albumsMemo = null;

    /** Album brut (vignettes non résolues), ou null s'il n'existe pas. */
    public static function raw(string $type, string $v = ''): ?array
    {
        $v = in_array($type, self::SINGLE, true) ? '' : $v;
        $cat = self::catalog();
        if (!isset($cat[$type][$v])) {
            return null;
        }
        if (self::$albumsMemo !== null) {
            return self::$albumsMemo[$type][$v] ?? null;
        }
        $c = PhpCache::read(self::file($type, $v));
        if ($c && ($c['built'] ?? null) === Derived::built() && ($c['v'] ?? 0) === self::VERSION) {
            return $c['data'];
        }
        self::$albumsMemo = self::computeAll();
        return self::$albumsMemo[$type][$v] ?? null;
    }

    /** Vide le cache mémoire (tests). */
    public static function forget(): void
    {
        self::$catalog = null;
        self::$albumsMemo = null;
    }

    // ------------------------------------------------------------------ calcul

    /**
     * Matchs avec composition : [id, saison, décennie, compétition, coupe, finale, amical, lignes].
     * @return list<array>
     */
    public static function matrix(): array
    {
        $out = [];
        foreach (Derived::part('matches') as $mid => $x) {
            if (!$x['v']) {
                continue;
            }
            $rows = SeasonAlbum::lineup((int) $mid);
            if (!$rows) {
                continue;
            }
            $out[] = [
                'id' => (int) $mid, 'season' => (string) $x['season'], 'decade' => (int) ($x['decade'] ?? 0), 'date' => (string) ($x['date'] ?? ''),
                'comp' => (string) ($x['comp'] ?? ''), 'cup' => self::cupOf($x), 'final' => Palmares::isFinal((string) ($x['round'] ?? '')),
                'amical' => ($x['comp'] ?? '') === 'Amical', 'rows' => $rows,
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: $a['id'] <=> $b['id']);
        return $out;
    }

    /** Coupe d'un match (nom harmonisé), ou null (championnat, barrages, amical). */
    public static function cupOf(array $x): ?string
    {
        $comp = (string) ($x['comp'] ?? '');
        $l = mb_strtolower(Names::ascii((string) ($x['label'] ?? '') . ' ' . ($x['title'] ?? '')));
        return match (true) {
            $comp === 'Coupe de France' => 'Coupe de France',
            $comp === 'Coupe de la Ligue' => 'Coupe de la Ligue',
            $comp === "Coupe d'Europe" => str_contains($l, 'intertoto') ? 'Coupe Intertoto' : (str_contains($l, 'champions') ? 'Ligue des champions' : (str_contains($l, 'coupes') ? 'Coupe des coupes' : 'Coupe UEFA')),
            str_contains($l, 'peugeot') => 'Coupe Peugeot',
            str_contains($l, 'drago') => 'Coupe Charles Drago',
            $comp === 'Coupes diverses' && trim((string) ($x['label'] ?? '')) !== '' && trim((string) $x['label']) !== 'Coupes diverses' => trim((string) $x['label']),
            default => null,
        };
    }

    /**
     * Décompte sur un périmètre : joueurs (matchs, buts, minutes, amicaux, poste principal, première
     * et dernière date, saisons, finale jouée) et entraîneurs (matchs dirigés, période).
     * @param list<array> $matches lignes de matrix()
     * @return array{players:array<string,array>,coaches:array<string,array>}
     */
    public static function tally(array $matches): array
    {
        $P = [];
        $C = [];
        foreach ($matches as $m) {
            $seen = [];
            foreach ($m['rows'] as [$key, $pid, $name, $pos, $goals, $min]) {
                if ($pos === 'E') {
                    if ($m['amical']) {
                        continue;
                    }
                    $C[$key] ??= ['key' => $key, 'pid' => $pid, 'name' => $name, 'n' => 0, 'first' => $m['date'], 'last' => $m['date']];
                    $C[$key]['n']++;
                    $C[$key]['last'] = $m['date'];
                    continue;
                }
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $P[$key] ??= ['key' => $key, 'pid' => $pid, 'name' => $name, 'mj' => 0, 'goals' => 0, 'min' => 0, 'amic' => 0, 'pos' => [], 'first' => null, 'last' => null, 'seasons' => [], 'final' => false];
                if ($m['amical']) {
                    $P[$key]['amic']++;
                    continue;
                }
                $p = &$P[$key];
                $p['mj']++;
                $p['goals'] += $goals;
                $p['min'] += $min;
                $p['first'] ??= $m['date'];
                $p['last'] = $m['date'];
                $p['seasons'][$m['season']] = true;
                $p['final'] = $p['final'] || $m['final'];
                if (isset(SeasonAlbum::ORDER[$pos])) {
                    $p['pos'][$pos] = ($p['pos'][$pos] ?? 0) + 1;
                }
                unset($p);
            }
        }
        foreach ($P as $k => $p) {
            if ($p['mj'] === 0 && $p['amic'] > 0 && $p['first'] === null) {
                // Joueur vu seulement en amical : pas de vignette (les chiffres sont officiels).
                unset($P[$k]);
                continue;
            }
            arsort($p['pos']);
            $p['line'] = array_key_first($p['pos']) ?? 'X';
            $p['seasons'] = count($p['seasons']);
            unset($p['pos']);
            $P[$k] = $p;
        }
        return ['players' => $P, 'coaches' => $C];
    }

    /** Vignette brute d'un joueur : chiffres + groupe d'affichage (+ brillante). */
    private static function item(array $p, string $group, bool $shine = false): array
    {
        return ['pid' => $p['pid'], 'name' => $p['name'], 'line' => $p['line'], 'group' => $group, 'mj' => $p['mj'], 'goals' => $p['goals'],
            'min' => $p['min'], 'amic' => $p['amic'], 'shine' => $shine, 'first' => $p['first'], 'last' => $p['last'], 'seasons' => $p['seasons']];
    }

    private static function coachItem(array $c, string $group = 'E'): array
    {
        return ['pid' => $c['pid'], 'name' => $c['name'], 'line' => 'E', 'group' => $group, 'mj' => $c['n'], 'goals' => 0, 'min' => 0, 'amic' => 0,
            'shine' => false, 'first' => $c['first'], 'last' => $c['last'], 'seasons' => 0, 'coach' => true];
    }

    /** Joueurs rangés par poste puis matchs, entraîneurs à la fin. */
    private static function byLine(array $tally, bool $coaches = true, ?callable $shine = null): array
    {
        $items = [];
        foreach ($tally['players'] as $p) {
            if ($p['mj'] > 0) {
                $items[] = self::item($p, $p['line'], $shine ? $shine($p) : false);
            }
        }
        usort($items, fn ($a, $b) => SeasonAlbum::ORDER[$a['line']] <=> SeasonAlbum::ORDER[$b['line']] ?: $b['mj'] <=> $a['mj'] ?: strcmp($a['name'], $b['name']));
        if ($coaches && $items) {
            $cs = array_values(array_filter($tally['coaches'], fn ($c) => $c['n'] > 0));
            usort($cs, fn ($a, $b) => strcmp($a['first'], $b['first']));
            foreach ($cs as $c) {
                $items[] = self::coachItem($c);
            }
        }
        return $items;
    }

    private static function sortedBy(array $items, string $k): array
    {
        usort($items, fn ($a, $b) => $b[$k] <=> $a[$k]);
        return $items;
    }

    private static function period(array $items): array
    {
        $f = array_filter(array_column($items, 'first'));
        $l = array_filter(array_column($items, 'last'));
        return $f ? [(int) substr(min($f), 0, 4), (int) substr(max($l), 0, 4)] : [0, 0];
    }

    /** Fiches d'une rubrique dont l'adresse finit par $suffix (« /joueurs/formes-au-club/ »). */
    private static function categoryIds(string $suffix): array
    {
        foreach (Categories::all() as $c) {
            if (str_ends_with((string) ($c['path'] ?? ''), $suffix)) {
                return array_flip(array_column(Index::inCategory((string) $c['slug']), 'id'));
            }
        }
        return [];
    }

    /** Pays de sélection lu dans la fiche (« International camerounais (23 sélections) » → « camerounais »). */
    public static function country(int $pid): string
    {
        $d = Fiches::get($pid);
        $p = $d['personne'] ?? [];
        foreach ((array) ($p['international'] ?? []) as $line) {
            if (preg_match('/^internationale?s?\s+([\p{L}\-]+)/iu', trim((string) $line), $m) && !preg_match('/^(a|b|u\d+|espoirs?|junior|olympique)$/iu', $m[1])) {
                return mb_strtolower($m[1]);
            }
        }
        return trim((string) ($p['nationality'] ?? ''));
    }

    /** Tous les albums, calculés en une passe. @return array<string,array<string,array>> */
    public static function computeAll(): array
    {
        $matrix = self::matrix();
        $official = array_values(array_filter($matrix, fn ($m) => true));
        $out = array_fill_keys(self::TYPES, []);
        $add = function (string $type, string $v, array $items, string $title, string $kicker, array $more = []) use (&$out) {
            if (!$items) {
                return;
            }
            $period = self::period($items);
            $out[$type][$v] = ['type' => $type, 'v' => $v, 'title' => $title, 'kicker' => $kicker, 'period' => $period, 'items' => array_values($items)] + $more;
        };

        // 1. Saisons
        $bySeason = [];
        $byDecade = [];
        foreach ($matrix as $m) {
            $bySeason[$m['season']][] = $m;
            $byDecade[$m['decade']][] = $m;
        }
        ksort($bySeason);
        foreach ($bySeason as $s => $ms) {
            $add('saison', $s, self::byLine(self::tally($ms)), 'Saison ' . $s, 'saison');
        }
        // 2. Décennies
        ksort($byDecade);
        foreach ($byDecade as $d => $ms) {
            if ($d >= 1900) {
                $add('decennie', (string) $d, self::byLine(self::tally($ms)), 'Les années ' . $d, 'decennie');
            }
        }
        // 3. Entraîneurs (au moins MIN_COACH matchs officiels)
        $all = self::tally($matrix);
        $coachMatches = [];
        foreach ($matrix as $m) {
            if ($m['amical']) {
                continue;
            }
            foreach ($m['rows'] as $r) {
                if ($r[3] === 'E') {
                    $coachMatches[$r[0]][] = $m;
                }
            }
        }
        $coaches = $all['coaches'];
        uasort($coaches, fn ($a, $b) => strcmp($a['first'], $b['first']));
        $slugs = [];
        foreach ($coaches as $key => $c) {
            if ($c['n'] < self::MIN_COACH) {
                continue;
            }
            $slug = Names::slug($c['name']) ?: substr(md5($key), 0, 8);
            $slug = isset($slugs[$slug]) ? $slug . '-' . substr($c['first'], 0, 4) : $slug;
            $slugs[$slug] = true;
            $add('entraineur', $slug, self::byLine(self::tally($coachMatches[$key] ?? []), false), $c['name'], 'entraineur', ['coach' => self::coachItem($c)]);
        }
        // 4. Épopées : finales et titres en coupe (palmarès + rounds « Finale »), parcours européens
        $cand = [];
        foreach (Palmares::data()['groups'] as $g) {
            foreach (['titles' => 'titre', 'finals' => 'finale'] as $k => $kind) {
                foreach ($g[$k] as $it) {
                    if (stripos($g['comp'], 'coupe') !== false || stripos($g['comp'], 'ligue des') !== false) {
                        $cand[$g['comp'] . '|' . $it['season']] = $kind;
                    }
                }
            }
        }
        foreach ($matrix as $m) {
            if ($m['cup'] && ($m['final'] || in_array($m['cup'], ['Coupe UEFA', 'Coupe Intertoto', 'Ligue des champions', 'Coupe des coupes'], true))) {
                $cand[$m['cup'] . '|' . $m['season']] ??= $m['final'] ? 'finale' : 'europe';
            }
        }
        $cups = [];
        foreach ($matrix as $m) {
            if ($m['cup'] && !$m['amical']) {
                $cups[$m['cup'] . '|' . $m['season']][] = $m;
            }
        }
        foreach ($cand as $k => $kind) {
            $ms = $cups[$k] ?? [];
            if (!$ms) {
                continue;
            }
            [$cup, $season] = explode('|', $k);
            $hasFinal = (bool) array_filter($ms, fn ($m) => $m['final']);
            $add('epopee', Names::slug($cup) . '-' . $season, self::byLine(self::tally($ms), true, fn ($p) => $hasFinal && $p['final']), $cup . ' ' . $season, $kind,
                ['cup' => $cup, 'season' => $season, 'final' => $hasFinal]);
        }
        uasort($out['epopee'], fn ($a, $b) => strcmp($a['season'], $b['season']));

        // Albums « tous temps » sur le décompte global.
        $players = array_filter($all['players'], fn ($p) => $p['mj'] > 0);
        $sortLine = fn ($a, $b) => SeasonAlbum::ORDER[$a['line']] <=> SeasonAlbum::ORDER[$b['line']] ?: $b['mj'] <=> $a['mj'] ?: strcmp($a['name'], $b['name']);
        // 5. Lionceaux
        $formedCat = self::categoryIds('/joueurs/formes-au-club/');
        $intlCat = self::categoryIds('/joueurs/internationaux/');
        $flag = function (?int $pid, string $k, array $cat): bool {
            if (!$pid) {
                return false;
            }
            $s = Index::get($pid);
            return isset($cat[$pid]) || !empty($s['p'][$k]);
        };
        $items = [];
        foreach ($players as $p) {
            if ($flag($p['pid'], 'formed', $formedCat)) {
                $items[] = self::item($p, $p['line']);
            }
        }
        usort($items, $sortLine);
        $add('lionceaux', '', $items, 'Les Lionceaux', 'lionceaux');
        // 6. Internationaux, groupés par pays
        $items = [];
        foreach ($players as $p) {
            if ($flag($p['pid'], 'intl', $intlCat)) {
                $items[] = self::item($p, 'c:' . (self::country((int) $p['pid']) ?: '?'));
            }
        }
        $sizes = array_count_values(array_column($items, 'group'));
        usort($items, fn ($a, $b) => ($a['group'] === 'c:?') <=> ($b['group'] === 'c:?') ?: $sizes[$b['group']] <=> $sizes[$a['group']] ?: strcmp($a['group'], $b['group']) ?: $b['mj'] <=> $a['mj']);
        $add('internationaux', '', $items, 'Les internationaux', 'internationaux');
        // 7. Gardiens, par ordre chronologique
        $items = [];
        foreach ($players as $p) {
            if ($p['line'] === 'G') {
                $items[] = self::item($p, 'G');
            }
        }
        usort($items, fn ($a, $b) => strcmp($a['first'], $b['first']));
        $add('gardiens', '', $items, 'Les gardiens', 'gardiens');
        // 8. Buteurs : top 50, les 10 premiers en brillantes
        $sc = array_filter($players, fn ($p) => $p['goals'] > 0);
        usort($sc, fn ($a, $b) => $b['goals'] <=> $a['goals'] ?: $a['mj'] <=> $b['mj']);
        $items = [];
        foreach (array_slice($sc, 0, self::TOP_SCORERS) as $i => $p) {
            $items[] = self::item($p, 'B', $i < self::SHINY_SCORERS);
        }
        $add('buteurs', '', $items, 'Les buteurs', 'buteurs');
        // 9. Fidèles
        $items = [];
        foreach ($players as $p) {
            if ($p['mj'] >= self::LOYAL_MATCHES || $p['seasons'] >= self::LOYAL_SEASONS) {
                $items[] = self::item($p, $p['line']);
            }
        }
        usort($items, $sortLine);
        $add('fideles', '', $items, 'Les fidèles', 'fideles');
        // 10. Staff : tous les entraîneurs, par ordre chronologique
        $cs = $all['coaches'];
        uasort($cs, fn ($a, $b) => strcmp($a['first'], $b['first']));
        $add('staff', '', array_map(fn ($c) => self::coachItem($c, 'S'), array_values($cs)), 'Les entraîneurs', 'staff');

        return array_filter($out);
    }

    // ------------------------------------------------------------------ affichage

    /** Fiche visible (nom, lien, photo) d'une vignette. */
    public static function resolve(array $p): array
    {
        $s = $p['pid'] ? Index::get((int) $p['pid']) : null;
        $s = $s && Index::visible($s) ? $s : null;
        return $p + ['stuck' => (bool) $s, 'display' => $s['p']['name'] ?? $p['name'], 'href' => $s ? url($s['path']) : null, 'image' => $s['image'] ?? null];
    }

    /** Titre affiché (traduit) d'un album du catalogue. */
    public static function title(array $a): string
    {
        return match ($a['type']) {
            'saison' => t('Saison {s}', ['s' => $a['v']]),
            'decennie' => t('Les années {d}', ['d' => $a['v']]),
            'epopee' => t($a['cup']) . ' ' . $a['season'],
            'entraineur' => $a['title'],
            default => self::typeLabel($a['type']),
        };
    }

    /** Sous-titre : nombre de vignettes et période. */
    public static function subtitle(array $a, int $count): string
    {
        [$y1, $y2] = $a['period'];
        $n = $count > 1 ? t('{n} vignettes', ['n' => $count]) : t('{n} vignette', ['n' => $count]);
        return $n . ($y1 ? ' · ' . ($y1 === $y2 ? (string) $y1 : $y1 . '–' . $y2) : '');
    }

    public static function kicker(array $a): string
    {
        return match ($a['kicker']) {
            'titre' => t('Épopée victorieuse'),
            'finale' => t('Jusqu’en finale'),
            'europe' => t('Campagne européenne'),
            'entraineur' => t('Les joueurs de l’entraîneur'),
            'saison' => t("L'effectif de la saison"),
            'decennie' => t('Une décennie de joueurs'),
            'lionceaux' => t('Formés au club'),
            'internationaux' => t('Sélectionnés en équipe nationale'),
            'gardiens' => t('Les derniers remparts'),
            'buteurs' => t('Top {n} en matchs officiels', ['n' => self::TOP_SCORERS]),
            'fideles' => t('{m} matchs officiels ou {s} saisons', ['m' => self::LOYAL_MATCHES, 's' => self::LOYAL_SEASONS]),
            'staff' => t('Le staff'),
            default => '',
        };
    }

    /** Libellé d'un groupe de vignettes. */
    public static function groupLabel(string $g): string
    {
        if (str_starts_with($g, 'c:')) {
            $c = substr($g, 2);
            return $c === '?' ? t('Pays non précisé') : t('Internationaux {c}', ['c' => $c]);
        }
        return match ($g) {
            'G' => t('Gardiens'), 'D' => t('Défenseurs'), 'M' => t('Milieux'), 'A' => t('Attaquants'), 'X' => t('Poste inconnu'),
            'E' => t('Entraîneurs'), 'B' => t('Classement des buteurs'), 'S' => t('Les entraîneurs'), default => $g,
        };
    }

    public static function role(array $p): string
    {
        if (!empty($p['coach'])) {
            return t('Entraîneur');
        }
        return match ($p['line']) {
            'G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant'), default => t('Poste inconnu'),
        };
    }

    /** Ligne de chiffres d'une vignette. */
    public static function stats(array $p, string $type): string
    {
        if (!empty($p['coach'])) {
            $y = $p['first'] ? substr($p['first'], 0, 4) . ($p['last'] && substr($p['last'], 0, 4) !== substr($p['first'], 0, 4) ? '–' . substr($p['last'], 0, 4) : '') : '';
            return $p['mj'] . ' ' . ($p['mj'] > 1 ? t('matchs dirigés') : t('match dirigé')) . ($y && $type !== 'saison' ? ' · ' . $y : '');
        }
        $en = \App\Services\I18n::isEn();
        $s = $p['mj'] . ' ' . ($p['mj'] > 1 ? t('matchs officiels') : t('match officiel'))
            . ' · ' . $p['goals'] . ' ' . ($p['goals'] > 1 || ($p['goals'] === 0 && $en) ? t('buts') : t('but'));
        if ($type === 'saison' && $p['min'] > 0) {
            $s .= ' · ' . number_format((int) $p['min'], 0, ',', ' ') . ' ' . t('min');
        }
        if (in_array($type, ['gardiens', 'lionceaux', 'internationaux', 'fideles', 'buteurs', 'decennie'], true) && $p['first']) {
            $a = (int) substr($p['first'], 0, 4);
            $b = (int) substr((string) $p['last'], 0, 4);
            $s .= ' · ' . ($a === $b ? $a : $a . '–' . $b);
        }
        if (!empty($p['amic'])) {
            $s .= ' · +' . (int) $p['amic'] . ' ' . ($p['amic'] > 1 ? t('amicaux') : t('amical'));
        }
        return $s;
    }

    /**
     * Album prêt à afficher : vignettes numérotées et résolues, page $page (PAGE par page).
     * null si l'album n'existe pas.
     */
    public static function build(string $type, string $v, int $page = 1): ?array
    {
        $a = self::raw($type, $v);
        if (!$a) {
            return null;
        }
        $total = count($a['items']);
        $pages = max(1, (int) ceil($total / self::PAGE));
        $page = max(1, min($page, $pages));
        $start = ($page - 1) * self::PAGE;
        $sizes = array_count_values(array_column($a['items'], 'group'));
        $items = [];
        foreach (array_slice($a['items'], $start, self::PAGE) as $i => $p) {
            $items[] = self::resolve($p) + ['no' => $start + $i + 1];
        }
        $stuck = count(array_filter($a['items'], fn ($p) => $p['pid'] && ($s = Index::get((int) $p['pid'])) && Index::visible($s)));
        return $a + ['shown' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'start' => $start, 'sizes' => $sizes,
            'prevGroup' => $start > 0 ? $a['items'][$start - 1]['group'] : null, 'stuckCount' => $stuck,
            'coach' => isset($a['coach']) ? self::resolve($a['coach']) : null];
    }

    /** Couverture d'un album du catalogue : première fiche visible avec photo parmi les plus capés. */
    public static function cover(array $a): ?string
    {
        foreach ($a['cover'] ?? [] as $pid) {
            $s = Index::get((int) $pid);
            if ($s && Index::visible($s) && !empty($s['image']) && !Index::isPlaceholderImage($s['image'])) {
                return (string) $s['image'];
            }
        }
        return null;
    }

    /** Albums précédent et suivant du même type. @return array{0:?array,1:?array} */
    public static function neighbours(string $type, string $v): array
    {
        $keys = array_map('strval', array_keys(self::catalog()[$type] ?? []));
        $i = array_search($v, $keys, true);
        if ($i === false) {
            return [null, null];
        }
        $cat = self::catalog()[$type];
        return [$i > 0 ? $cat[$keys[$i - 1]] : null, $i < count($keys) - 1 ? $cat[$keys[$i + 1]] : null];
    }
}
