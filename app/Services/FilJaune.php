<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Derived;
use App\Data\Index;
use App\Data\Names;

/**
 * Le Fil jaune : tous les Lionceaux sont reliés. Deux joueurs sont coéquipiers s'ils ont
 * joué le même match (compositions des fiches, entrées en jeu comprises) ; le musée trouve
 * la chaîne de coéquipiers la plus courte entre deux joueurs, la constellation des
 * coéquipiers d'un joueur, les records du réseau et un défi par jour.
 *
 * Le réseau est recalculé à chaque page depuis les données calculées (Derived, quelques
 * centièmes de seconde) ; seuls les records (distances entre tous les joueurs) sont gardés
 * dans storage/cache/fil-jaune.json, refaits quand les données calculées changent.
 */
final class FilJaune
{
    public const CACHE = STORAGE_PATH . '/cache/fil-jaune.json';
    /** Défi du jour : joueurs d'au moins tant de matchs, à 3 ou 4 passes l'un de l'autre. */
    public const DAILY_MIN_GAMES = 40;
    private static ?array $g = null;

    /**
     * Réseau : adj [pid => [pid => matchs ensemble]], first/last ["a-b" => id du premier et du
     * dernier match ensemble], games [pid => matchs], slugs [slug => pid].
     */
    public static function graph(): array
    {
        if (self::$g !== null) {
            return self::$g;
        }
        $d = Derived::get();
        $M = $d['matches'] ?? [];
        $byMatch = [];
        $ok = [];
        foreach ($d['apps'] ?? [] as $a) {
            [$pid, $mid, , , , , $role] = $a;
            if ($role !== 'player' || empty($M[$mid]['v'])) {
                continue;
            }
            if (!isset($ok[$pid])) {
                $s = Index::get((int) $pid);
                $ok[$pid] = $s && $s['type'] === 'personne' && Index::visible($s);
            }
            if ($ok[$pid]) {
                $byMatch[$mid][$pid] = true;
            }
        }
        // Dans l'ordre des dates : premier et dernier match ensemble.
        uksort($byMatch, fn ($x, $y) => strcmp((string) ($M[$x]['date'] ?? ''), (string) ($M[$y]['date'] ?? '')) ?: $x <=> $y);
        $adj = $first = $last = $games = [];
        foreach ($byMatch as $mid => $ps) {
            $ps = array_keys($ps);
            sort($ps);
            $n = count($ps);
            for ($i = 0; $i < $n; $i++) {
                $a = $ps[$i];
                $games[$a] = ($games[$a] ?? 0) + 1;
                for ($j = $i + 1; $j < $n; $j++) {
                    $b = $ps[$j];
                    $adj[$a][$b] = ($adj[$a][$b] ?? 0) + 1;
                    $adj[$b][$a] = $adj[$a][$b];
                    $first["$a-$b"] ??= $mid;
                    $last["$a-$b"] = $mid;
                }
            }
        }
        $slugs = [];
        foreach (array_keys($adj) as $pid) {
            $slug = basename(rtrim((string) (Index::get((int) $pid)['path'] ?? ''), '/'));
            $slugs[isset($slugs[$slug]) || $slug === '' ? (string) $pid : $slug] = $pid;
        }
        return self::$g = ['adj' => $adj, 'first' => $first, 'last' => $last, 'games' => $games, 'slugs' => $slugs];
    }

    public static function forget(): void
    {
        self::$g = null;
    }

    public static function has(int $pid): bool
    {
        return isset(self::graph()['adj'][$pid]);
    }

    public static function slug(int $pid): string
    {
        $s = array_search($pid, self::graph()['slugs'], true);
        return $s === false ? (string) $pid : (string) $s;
    }

    /** Joueur retrouvé par son adresse (« franck-sauzee »), son numéro ou son nom. */
    public static function find(string $q): ?int
    {
        $g = self::graph();
        $q = trim($q);
        if ($q === '') {
            return null;
        }
        if (isset($g['slugs'][$q])) {
            return (int) $g['slugs'][$q];
        }
        if (ctype_digit($q) && isset($g['adj'][(int) $q])) {
            return (int) $q;
        }
        // Nom tapé (« Sauzée », « Franck Sauzee ») : même nom, sinon nom de famille, le plus capé d'abord.
        $key = Names::personKey($q);
        $best = null;
        foreach (array_keys($g['adj']) as $pid) {
            $s = Index::get((int) $pid);
            $name = (string) ($s['p']['name'] ?? '');
            $score = Names::personKey($name) === $key ? 2 : (Names::personKey((string) ($s['p']['last'] ?? '')) === $key ? 1 : 0);
            if ($score && (!$best || [$score, $g['games'][$pid]] > [$best[0], $best[1]])) {
                $best = [$score, $g['games'][$pid], $pid];
            }
        }
        return $best ? (int) $best[2] : null;
    }

    /** Fiche résumée d'un joueur : nom, adresse, photo, années, matchs reliés, coéquipiers. */
    public static function player(int $pid): ?array
    {
        $g = self::graph();
        $s = Index::get($pid);
        if (!$s || !isset($g['adj'][$pid])) {
            return null;
        }
        $p = $s['p'] ?? [];
        $years = $p['arrival'] ? $p['arrival'] . ($p['departure'] && $p['departure'] !== $p['arrival'] ? '–' . $p['departure'] : '') : '';
        return ['id' => $pid, 'name' => (string) ($p['name'] ?: $s['title']), 'path' => $s['path'], 'slug' => self::slug($pid),
            'image' => Index::isPlaceholderImage($s['image']) ? null : $s['image'], 'years' => $years, 'pos' => $p['line'] ?? null,
            'games' => (int) $g['games'][$pid], 'mates' => count($g['adj'][$pid])];
    }

    /** Coéquipiers d'un joueur, du plus fidèle au plus rare. @return list<array{id:int,n:int}> */
    public static function teammates(int $pid): array
    {
        $out = [];
        foreach (self::graph()['adj'][$pid] ?? [] as $q => $n) {
            $out[] = ['id' => (int) $q, 'n' => $n];
        }
        usort($out, fn ($a, $b) => $b['n'] <=> $a['n'] ?: $a['id'] <=> $b['id']);
        return $out;
    }

    /** Distances (en passes) depuis un joueur. @return array<int,int> */
    public static function distances(int $from): array
    {
        $adj = self::graph()['adj'];
        if (!isset($adj[$from])) {
            return [];
        }
        $dist = [$from => 0];
        $queue = [$from];
        for ($h = 0; $h < count($queue); $h++) {
            $u = $queue[$h];
            foreach ($adj[$u] as $v => $_) {
                if (!isset($dist[$v])) {
                    $dist[$v] = $dist[$u] + 1;
                    $queue[] = $v;
                }
            }
        }
        return $dist;
    }

    /**
     * Chaîne la plus courte de $a à $b (liste des joueurs), null s'ils ne sont pas reliés. À
     * longueur égale, les liens les plus solides (le plus de matchs ensemble) sont préférés.
     */
    public static function path(int $a, int $b): ?array
    {
        $adj = self::graph()['adj'];
        if (!isset($adj[$a], $adj[$b])) {
            return null;
        }
        if ($a === $b) {
            return [$a];
        }
        // Distances depuis l'arrivée, puis descente gloutonne par le lien le plus solide.
        $dist = self::distances($b);
        if (!isset($dist[$a])) {
            return null;
        }
        $chain = [$a];
        $u = $a;
        while ($u !== $b) {
            $next = null;
            foreach ($adj[$u] as $v => $n) {
                if (($dist[$v] ?? PHP_INT_MAX) === $dist[$u] - 1 && ($next === null || $n > $adj[$u][$next])) {
                    $next = (int) $v;
                }
            }
            $chain[] = $u = $next;
        }
        return $chain;
    }

    /** Le lien entre deux coéquipiers : matchs ensemble, premier et dernier (résumés Derived). */
    public static function link(int $a, int $b): ?array
    {
        $g = self::graph();
        $n = $g['adj'][$a][$b] ?? 0;
        if (!$n) {
            return null;
        }
        $k = min($a, $b) . '-' . max($a, $b);
        $M = Derived::get()['matches'] ?? [];
        return ['n' => $n, 'first' => $M[$g['first'][$k]] ?? null, 'last' => $M[$g['last'][$k]] ?? null];
    }

    /**
     * Records du réseau (gardés en cache) : joueurs reliés, liens, familles (composantes),
     * plus grande distance et distance moyenne, joueurs les plus connectés, inséparables.
     */
    public static function records(): array
    {
        $sig = is_file(STORAGE_PATH . '/cache/derived.php') ? (string) filemtime(STORAGE_PATH . '/cache/derived.php') : '0';
        $c = JsonStore::read(self::CACHE);
        if (is_array($c) && ($c['sig'] ?? '') === $sig && ($c['v'] ?? 0) === 1) {
            return $c;
        }
        $g = self::graph();
        $adj = $g['adj'];
        $seen = [];
        $families = [];
        foreach (array_keys($adj) as $s) {
            if (isset($seen[$s])) {
                continue;
            }
            $dist = self::distances((int) $s);
            foreach ($dist as $v => $_) {
                $seen[$v] = true;
            }
            $families[] = array_keys($dist);
        }
        usort($families, fn ($x, $y) => count($y) <=> count($x));
        $big = $families[0] ?? [];
        $max = 0;
        $sum = 0;
        $pairs = 0;
        $far = null;
        foreach ($big as $s) {
            foreach (self::distances((int) $s) as $v => $d) {
                if ($d > $max || ($d === $max && $far && $g['games'][$s] + $g['games'][$v] > $g['games'][$far[0]] + $g['games'][$far[1]])) {
                    $max = $d;
                    $far = [(int) $s, (int) $v];
                }
                $sum += $d;
                $pairs += $d > 0 ? 1 : 0;
            }
        }
        $deg = array_map('count', $adj);
        arsort($deg);
        $duos = [];
        foreach ($adj as $a => $row) {
            foreach ($row as $b => $n) {
                if ($a < $b) {
                    $duos[] = [(int) $a, (int) $b, $n];
                }
            }
        }
        usort($duos, fn ($x, $y) => $y[2] <=> $x[2]);
        $edges = 0;
        foreach ($adj as $row) {
            $edges += count($row);
        }
        $c = ['v' => 1, 'sig' => $sig, 'players' => count($adj), 'edges' => intdiv($edges, 2), 'families' => array_map('count', $families),
            'family' => count($big), 'diameter' => $max, 'average' => $pairs ? round($sum / $pairs, 2) : 0, 'far' => $far,
            'connected' => array_map(fn ($p, $n) => [(int) $p, $n], array_slice(array_keys($deg), 0, 5), array_slice(array_values($deg), 0, 5)),
            'duos' => array_slice($duos, 0, 5),
            'outside' => array_values(array_map('intval', array_merge(...array_slice($families, 1) ?: [[]])))];
        JsonStore::write(self::CACHE, $c);
        return $c;
    }

    /** Défi du jour : deux joueurs connus (40 matchs ou plus) à 3 ou 4 passes ; le même pour tous ce jour-là. */
    public static function daily(?string $date = null): ?array
    {
        $date ??= date('Y-m-d');
        $g = self::graph();
        $rec = self::records();
        $pool = array_values(array_filter(array_keys($g['adj']), fn ($p) => $g['games'][$p] >= self::DAILY_MIN_GAMES && !in_array((int) $p, $rec['outside'], true)));
        sort($pool);
        if (count($pool) < 2) {
            return null;
        }
        $rnd = new \Random\Randomizer(new \Random\Engine\Mt19937(crc32('fil-jaune|' . $date)));
        for ($try = 0; $try < 20; $try++) {
            $a = $pool[$rnd->getInt(0, count($pool) - 1)];
            $dist = self::distances($a);
            $cands = array_values(array_filter($pool, fn ($p) => in_array($dist[$p] ?? 0, [3, 4], true)));
            if ($cands) {
                $b = $cands[$rnd->getInt(0, count($cands) - 1)];
                return ['date' => $date, 'from' => (int) $a, 'to' => (int) $b, 'best' => $dist[$b]];
            }
        }
        return null;
    }
}
