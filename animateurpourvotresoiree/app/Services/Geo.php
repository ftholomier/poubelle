<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Fs;
use App\Core\Str;

/**
 * Référentiel géographique (communes, départements, régions) : app/data/geo.
 * Les fichiers sont des tableaux PHP mis en cache par OPcache.
 */
final class Geo
{
    private const DIR = APP_PATH . '/data/geo';
    private static array $files = [];

    private static function load(string $rel): array
    {
        if (!isset(self::$files[$rel])) {
            $override = STORAGE_PATH . '/data/geo/' . $rel;
            self::$files[$rel] = Fs::readPhpArray(is_file($override) ? $override : self::DIR . '/' . $rel);
        }
        return self::$files[$rel];
    }

    public static function norm(string $s): string
    {
        $s = Str::norm($s);
        $s = preg_replace('/\bst\b/', 'saint', $s) ?? $s;
        $s = preg_replace('/\bste\b/', 'sainte', $s) ?? $s;
        return $s;
    }

    // ------------------------------------------------------------ départements

    /** @return array<string,array> */
    public static function departements(): array
    {
        return self::load('departements.php');
    }

    public static function depCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return '';
        }
        if (ctype_digit($code) && strlen($code) === 1) {
            $code = '0' . $code;
        }
        if ($code === '20') {
            return '';
        }
        return $code;
    }

    public static function dep(?string $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }
        return self::departements()[self::depCode($code)] ?? null;
    }

    public static function depBySlug(string $slug): ?array
    {
        foreach (self::departements() as $d) {
            if ($d['slug'] === $slug) {
                return $d;
            }
        }
        return null;
    }

    /** Département déduit d'un code postal (Corse et outre-mer compris). */
    public static function depFromPostcode(string $cp): string
    {
        $cp = preg_replace('/\D/', '', $cp) ?? '';
        if (strlen($cp) !== 5) {
            return '';
        }
        if (str_starts_with($cp, '97')) {
            return substr($cp, 0, 3);
        }
        if (str_starts_with($cp, '20')) {
            return ((int) substr($cp, 0, 3)) < 202 ? '2A' : '2B';
        }
        $d = substr($cp, 0, 2);
        return isset(self::departements()[$d]) ? $d : '';
    }

    // ---------------------------------------------------------------- régions

    public static function regions(): array
    {
        return self::load('regions.php');
    }

    public static function region(?string $code): ?array
    {
        return $code ? (self::regions()[$code] ?? null) : null;
    }

    public static function regionBySlug(string $slug): ?array
    {
        foreach (self::regions() as $r) {
            if ($r['slug'] === $slug) {
                return $r;
            }
        }
        return null;
    }

    /** Ancien code région (avant 2016) → région actuelle. */
    public static function fromOldRegion(string $old): ?array
    {
        $map = self::load('old_regions.php');
        $old = ltrim(trim($old), '0') ?: '0';
        $code = $map[$old] ?? $map[str_pad($old, 2, '0', STR_PAD_LEFT)] ?? null;
        return $code ? self::region($code) : null;
    }

    // --------------------------------------------------------------- communes

    /** @return array<string,array> */
    public static function communesOfDep(string $dep): array
    {
        $dep = self::depCode($dep);
        if (!isset(self::departements()[$dep])) {
            return [];
        }
        return self::load('communes/' . strtolower($dep) . '.php');
    }

    public static function commune(?string $insee): ?array
    {
        if (!$insee) {
            return null;
        }
        $insee = strtoupper($insee);
        $dep = str_starts_with($insee, '97') ? substr($insee, 0, 3) : substr($insee, 0, 2);
        $c = self::communesOfDep($dep)[$insee] ?? null;
        return $c ? $c + ['insee' => $insee] : null;
    }

    public static function communeBySlug(string $dep, string $slug): ?array
    {
        foreach (self::communesOfDep($dep) as $insee => $c) {
            if ($c['s'] === $slug) {
                return $c + ['insee' => (string) $insee];
            }
        }
        return null;
    }

    /** @return array<int,array> communes partageant un code postal */
    public static function byPostcode(string $cp): array
    {
        $cp = preg_replace('/\D/', '', $cp) ?? '';
        $codes = self::load('cp.php')[$cp] ?? [];
        $out = [];
        foreach ($codes as $insee) {
            $c = self::commune((string) $insee);
            if ($c) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** Libellé « Lyon (69) ». */
    public static function label(?array $c): string
    {
        return $c ? $c['n'] . ' (' . $c['d'] . ')' : '';
    }

    /**
     * Autocomplétion : communes dont le nom commence par la saisie (les plus peuplées d'abord).
     * @return array<int,array{insee:string,name:string,dep:string,cp:string,label:string,lat:float,lng:float}>
     */
    public static function search(string $q, int $limit = 8): array
    {
        $raw = trim($q);
        if (preg_match('/^\d{2,5}$/', $raw)) {
            $out = [];
            if (strlen($raw) === 5) {
                foreach (self::byPostcode($raw) as $c) {
                    $out[] = self::row($c, $raw);
                }
            } else {
                $dep = self::dep($raw);
                if ($dep && ($c = self::commune($dep['top']))) {
                    $out[] = self::row($c);
                }
            }
            return array_slice($out, 0, $limit);
        }
        $n = self::norm($raw);
        if (mb_strlen($n) < 2) {
            return [];
        }
        $rows = self::load('search/' . str_replace(' ', '_', substr($n, 0, 2)) . '.php');
        $out = [];
        foreach ($rows as [$name, $insee]) {
            if (str_starts_with($name, $n)) {
                $out[] = (string) $insee;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        $res = [];
        foreach ($out as $insee) {
            $c = self::commune($insee);
            if ($c) {
                $res[] = self::row($c);
            }
        }
        return $res;
    }

    private static function row(array $c, ?string $cp = null): array
    {
        return [
            'insee' => $c['insee'],
            'name' => $c['n'],
            'dep' => $c['d'],
            'cp' => $cp ?? ($c['cp'][0] ?? ''),
            'label' => $c['n'] . ' (' . $c['d'] . ')',
            'lat' => (float) $c['la'],
            'lng' => (float) $c['lo'],
        ];
    }

    /**
     * Retrouve la commune d'une adresse saisie librement (ville + code postal).
     * Tolère les fautes, les « ST », les anciennes communes fusionnées.
     */
    public static function match(string $city, string $postcode = '', string $depHint = ''): ?array
    {
        $cp = preg_replace('/\D/', '', $postcode) ?? '';
        $cityClean = trim(preg_replace('/\b\d{5}\b/', '', $city) ?? $city);
        if ($cp === '' && preg_match('/\b(\d{5})\b/', $city, $m)) {
            $cp = $m[1];
        }
        // « Lyon 3e », « Paris 15ème », « Marseille 8 » → la commune
        $cityClean = preg_replace('/\s+(\d{1,2})\s*(e|eme|ème|er|ere|è)?\s*(arr\.?|arrondissement)?$/iu', '', $cityClean) ?? $cityClean;
        $cityClean = preg_replace('/\s+cedex.*$/i', '', $cityClean) ?? $cityClean;
        $n = self::norm($cityClean);
        $candidates = strlen($cp) === 5 ? self::byPostcode($cp) : [];
        if ($n !== '' && $candidates) {
            foreach ($candidates as $c) {
                if (self::norm($c['n']) === $n) {
                    return $c;
                }
            }
            foreach ($candidates as $c) {
                $cn = self::norm($c['n']);
                if (str_contains($cn, $n) || str_contains($n, $cn) || Str::similar($cn, $n)) {
                    return $c;
                }
            }
        }
        $dep = self::depFromPostcode($cp) ?: self::depCode($depHint);
        if ($n !== '' && $dep !== '') {
            $best = null;
            foreach (self::communesOfDep($dep) as $insee => $c) {
                $cn = self::norm($c['n']);
                if ($cn === $n) {
                    return $c + ['insee' => (string) $insee];
                }
                if ($best === null && Str::similar($cn, $n)) {
                    $best = $c + ['insee' => (string) $insee];
                }
            }
            $alias = self::load('alias.php')[$n . '|' . $dep] ?? null;
            if ($alias) {
                return self::commune((string) $alias);
            }
            if ($best) {
                return $best;
            }
        }
        if ($candidates) {
            usort($candidates, static fn ($a, $b) => $b['p'] <=> $a['p']);
            return $candidates[0];
        }
        if ($n !== '' && $dep === '') {
            $found = self::search($cityClean, 1);
            if ($found) {
                return self::commune($found[0]['insee']);
            }
        }
        return null;
    }

    /** Distance en km (haversine). */
    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** « à Lyon », « au Havre », « aux Sables-d'Olonne » */
    public static function inCity(string $name): string
    {
        if (preg_match('/^Le\s+(.+)$/u', $name, $m)) {
            return 'au ' . $m[1];
        }
        if (preg_match('/^Les\s+(.+)$/u', $name, $m)) {
            return 'aux ' . $m[1];
        }
        return 'à ' . $name;
    }

    /** Liste des départements triés par code, pour les menus déroulants. */
    public static function depOptions(): array
    {
        $out = [];
        foreach (self::departements() as $code => $d) {
            $out[$code] = $code . ' – ' . $d['name'];
        }
        uksort($out, static fn ($a, $b) => strnatcmp(str_replace(['2A', '2B'], ['20.1', '20.2'], (string) $a), str_replace(['2A', '2B'], ['20.1', '20.2'], (string) $b)));
        return $out;
    }

    /** Remplace le référentiel par une version téléchargée depuis geo.api.gouv.fr (coordonnées exactes). */
    public static function refreshDepFromApi(string $dep): int
    {
        $base = rtrim((string) \App\Core\Env::get('GEO_API_URL', 'https://geo.api.gouv.fr'), '/');
        $res = \App\Core\Http::get($base . '/departements/' . rawurlencode($dep) . '/communes?fields=nom,code,centre,population,codesPostaux,codeRegion&format=json', ['Accept' => 'application/json'], 30);
        if ($res['status'] !== 200) {
            throw new \RuntimeException('geo.api.gouv.fr indisponible (' . $res['status'] . ')');
        }
        $rows = json_decode($res['body'], true);
        if (!is_array($rows) || !$rows) {
            throw new \RuntimeException('Réponse vide pour le département ' . $dep);
        }
        $current = self::communesOfDep($dep);
        $out = [];
        foreach ($rows as $r) {
            $code = (string) $r['code'];
            $prev = $current[$code] ?? null;
            $coords = $r['centre']['coordinates'] ?? null;
            $out[$code] = [
                'n' => (string) $r['nom'],
                'd' => $dep,
                'r' => (string) ($r['codeRegion'] ?? ($prev['r'] ?? '')),
                'cp' => array_values($r['codesPostaux'] ?? []),
                'p' => (int) ($r['population'] ?? 0),
                'la' => $coords ? round((float) $coords[1], 5) : ($prev['la'] ?? 0),
                'lo' => $coords ? round((float) $coords[0], 5) : ($prev['lo'] ?? 0),
                's' => $prev['s'] ?? Str::slug((string) $r['nom']),
            ];
        }
        ksort($out);
        Fs::writePhpArray(STORAGE_PATH . '/data/geo/communes/' . strtolower($dep) . '.php', $out);
        unset(self::$files['communes/' . strtolower($dep) . '.php']);
        return count($out);
    }
}
