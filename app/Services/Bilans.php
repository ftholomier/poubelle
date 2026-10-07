<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Index;
use App\Data\Names;

/**
 * Bilans de l'association (BIL_*.xls(x), 1932-2024) : pour chaque saison et compétition, matchs,
 * titularisations, minutes, buts et cartons de chaque joueur, plus le classement final de l'équipe.
 * Lus une fois pour toutes dans app/Resources/import/bilans-joueurs.json.gz.
 *
 * Les noms des bilans (« REVELLI P. », « STOPYRA Y. », « BENOÎT ») sont reliés aux fiches du musée
 * seulement sans ambiguïté : même nom de famille, même initiale du prénom si elle est donnée, et
 * époque compatible (années de présence ou de naissance) quand plusieurs fiches portent ce nom.
 */
final class Bilans
{
    public static string $data = APP_ROOT . '/app/Resources/import/bilans-joueurs.json.gz';
    private static ?array $raw = null;
    private static ?array $links = null;

    /** Libellés des feuilles de minutes (« D1-J ») ; D1/D2 deviennent Ligue 1/2 à partir de 2002. */
    public static function competition(string $sheet, string $season): string
    {
        $y = (int) substr($season, 0, 4);
        $code = strtoupper((string) preg_replace('/-J$/i', '', trim($sheet)));
        return match ($code) {
            'D1' => $y >= 2002 ? 'Ligue 1' : 'Division 1',
            'D2' => $y >= 2002 ? 'Ligue 2' : 'Division 2',
            'D3' => 'National',
            'L1' => 'Ligue 1',
            'L2' => 'Ligue 2',
            'CF' => 'Coupe de France',
            'CL' => 'Coupe de la Ligue',
            'C3' => 'Coupe d’Europe',
            'INT' => 'Coupe Intertoto',
            'CD' => 'Coupe Drago',
            'CA' => 'Coupe des Alpes',
            'B12' => 'Barrages',
            'CH2' => 'Championnat (2e phase)',
            'TC' => 'Trophée des champions',
            'TFI' => 'Trophée franco-italien',
            default => $code,
        };
    }

    private static function raw(): array
    {
        if (self::$raw !== null) {
            return self::$raw;
        }
        $j = is_file(self::$data) ? gzdecode((string) file_get_contents(self::$data)) : false;
        $d = $j ? json_decode($j, true) : null;
        $rows = [];
        foreach ((array) ($d['rows'] ?? []) as $r) {
            [$season, $sheet, $player, $m, $t, $min, $g, $y, $red] = $r;
            $rows[] = ['season' => $season, 'comp' => self::competition((string) $sheet, (string) $season), 'player' => (string) $player,
                'matches' => (int) $m, 'starts' => $t >= 0 ? (int) $t : null, 'minutes' => (int) $min, 'goals' => (int) $g, 'yellow' => (int) $y, 'red' => (int) $red];
        }
        return self::$raw = ['rows' => $rows, 'bilans' => (array) ($d['bilans'] ?? [])];
    }

    /** @return list<array> toutes les lignes joueur × saison × compétition */
    public static function rows(): array
    {
        return self::raw()['rows'];
    }

    /** [nom de famille normalisé, initiales] de « REVELLI P. », « MILLER L.R. », « De La Quintinie ». */
    public static function split(string $name): array
    {
        $n = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        $ini = '';
        if (preg_match('/^(\p{Lu})\.\s*(\S.*)$/u', $n, $m)) {
            return [self::key($m[2]), mb_strtolower(Names::ascii($m[1]))];
        }
        if (preg_match('/^(.*?)\s+((?:\p{Lu}\.?\s?-?){1,3})$/u', $n, $m) && mb_strlen(str_replace(['.', ' ', '-'], '', $m[2])) <= 3 && str_contains($m[2], '.')) {
            $n = $m[1];
            $ini = mb_strtolower(Names::ascii(mb_substr(str_replace(['.', ' '], '', $m[2]), 0, 1)));
        }
        return [self::key($n), $ini];
    }

    private static function key(string $last): string
    {
        return preg_replace('/[^a-z]/', '', strtolower(Names::ascii($last))) ?? '';
    }

    /** Nom de bilan → fiche personne (ou null). */
    public static function links(): array
    {
        if (self::$links !== null) {
            return self::$links;
        }
        $byLast = [];
        foreach (Index::all() as $e) {
            if (($e['type'] ?? '') !== 'personne' || !Index::visible($e)) {
                continue;
            }
            $p = $e['p'] ?? [];
            $last = (string) ($p['last'] ?? '');
            if ($last === '') {
                $w = preg_split('/\s+/u', trim((string) ($p['name'] ?? ''))) ?: [];
                $last = (string) end($w);
            }
            $byLast[self::key($last)][] = $e;
        }
        // Saisons de chaque nom dans les bilans (pour départager par l'époque).
        $seasons = [];
        foreach (self::rows() as $r) {
            $seasons[$r['player']][(int) substr($r['season'], 0, 4)] = true;
        }
        $out = [];
        foreach ($seasons as $name => $years) {
            [$last, $ini] = self::split($name);
            $cands = $byLast[$last] ?? [];
            if ($ini !== '') {
                $cands = array_values(array_filter($cands, fn ($e) => str_starts_with(strtolower(Names::ascii((string) ($e['p']['first'] ?? ''))), $ini)));
            }
            $years = array_keys($years);
            $fits = array_values(array_filter($cands, fn ($e) => self::era($e, min($years), max($years))));
            $out[$name] = count($fits) === 1 ? (int) $fits[0]['id'] : null;
        }
        return self::$links = $out;
    }

    /** L'époque de la fiche est-elle compatible avec les saisons du bilan ? (inconnue : oui) */
    private static function era(array $e, int $from, int $to): bool
    {
        $p = $e['p'] ?? [];
        if (!empty($p['arrival']) || !empty($p['departure'])) {
            return $to >= (int) ($p['arrival'] ?: 1900) - 1 && $from <= (int) ($p['departure'] ?: 2100) + 1;
        }
        if (!empty($p['birth_year'])) {
            return $from >= (int) $p['birth_year'] + 14 && $to <= (int) $p['birth_year'] + 45;
        }
        return true;
    }

    /** Bilan d'une fiche : lignes saison par saison et totaux, ou null. */
    public static function forPerson(int $id): ?array
    {
        $names = array_keys(array_filter(self::links(), fn ($v) => $v === $id));
        if (!$names) {
            return null;
        }
        $rows = array_values(array_filter(self::rows(), fn ($r) => in_array($r['player'], $names, true)));
        usort($rows, fn ($a, $b) => [$a['season'], self::rank($a['comp'])] <=> [$b['season'], self::rank($b['comp'])]);
        $tot = ['matches' => 0, 'starts' => 0, 'minutes' => 0, 'goals' => 0, 'yellow' => 0, 'red' => 0];
        $seasons = [];
        foreach ($rows as $r) {
            foreach ($tot as $k => $_) {
                $tot[$k] += (int) $r[$k];
            }
            $seasons[$r['season']] = true;
        }
        $tot['seasons'] = count($seasons);
        return ['rows' => $rows, 'total' => $tot];
    }

    /** Compétition du bilan → clé des filtres du site (Mosaic::COMPS). */
    public static function compKey(string $comp): string
    {
        return match (self::rank($comp)) {
            0 => 'championnat',
            1 => 'coupe-de-france',
            2 => 'coupe-de-la-ligue',
            default => in_array($comp, ['Coupe d’Europe', 'Coupe Intertoto'], true) ? 'coupe-d-europe' : 'autre',
        };
    }

    private static function rank(string $comp): int
    {
        return match (true) {
            str_starts_with($comp, 'Division') || str_starts_with($comp, 'Ligue ') || $comp === 'National' || str_starts_with($comp, 'Championnat') => 0,
            $comp === 'Coupe de France' => 1,
            $comp === 'Coupe de la Ligue' => 2,
            default => 3,
        };
    }

    /**
     * Classement de tous les temps : [nom affiché, fiche|null, valeur, saisons] par minutes, matchs ou buts.
     * Les noms reliés à une même fiche sont additionnés.
     */
    public static function top(string $field = 'minutes', int $n = 20, ?int $decade = null, ?string $comp = null): array
    {
        $links = self::links();
        $acc = [];
        foreach (self::rows() as $r) {
            $y = (int) substr($r['season'], 0, 4);
            if ($decade && intdiv($y, 10) * 10 !== $decade) {
                continue;
            }
            if ($comp && self::compKey($r['comp']) !== $comp) {
                continue;
            }
            $id = $links[$r['player']] ?? null;
            $k = $id ? "f$id" : 'n' . $r['player'];
            $acc[$k] ??= ['id' => $id, 'name' => $r['player'], 'value' => 0, 'seasons' => []];
            $acc[$k]['value'] += (int) $r[$field];
            $acc[$k]['seasons'][$r['season']] = true;
        }
        usort($acc, fn ($a, $b) => $b['value'] <=> $a['value']);
        $out = [];
        foreach (array_slice($acc, 0, $n) as $a) {
            $e = $a['id'] ? Index::get($a['id']) : null;
            $ys = array_keys($a['seasons']);
            sort($ys);
            $out[] = ['name' => $e ? (string) ($e['p']['name'] ?? $e['title']) : self::pretty($a['name']), 'path' => $e['path'] ?? null, 'image' => $e['image'] ?? null, 'position' => $e['p']['position'] ?? null, 'value' => $a['value'],
                'years' => substr((string) $ys[0], 0, 4) . '-' . substr((string) end($ys), 5, 4)];
        }
        return $out;
    }

    /** « REVELLI P. » → « P. Revelli ». */
    public static function pretty(string $n): string
    {
        [, $ini] = self::split($n);
        $last = trim(preg_replace(['/\s+((?:\p{Lu}\.?\s?-?){1,3})$/u', '/^\p{Lu}\.\s*/u'], '', $n) ?? $n);
        $last = mb_convert_case(mb_strtolower($last), MB_CASE_TITLE);
        return ($ini !== '' ? mb_strtoupper($ini) . '. ' : '') . $last;
    }

    /** Classement final de la saison en championnat (« 9ème (39 pts) ») et parcours en coupe. */
    public static function season(string $season): array
    {
        $out = [];
        foreach (self::raw()['bilans'] as $b) {
            if (($b['season'] ?? '') === $season && !isset($out[$b['competition']])) {
                $out[$b['competition']] = $b; // la première ligne de chaque compétition est le total
            }
        }
        return $out;
    }

    /** Noms des bilans sans fiche au musée, par minutes jouées. */
    public static function unlinked(int $n = 60): array
    {
        $links = self::links();
        $acc = [];
        foreach (self::rows() as $r) {
            if (!empty($links[$r['player']])) {
                continue;
            }
            $acc[$r['player']] ??= ['name' => $r['player'], 'minutes' => 0, 'matches' => 0, 'seasons' => []];
            $acc[$r['player']]['minutes'] += $r['minutes'];
            $acc[$r['player']]['matches'] += $r['matches'];
            $acc[$r['player']]['seasons'][$r['season']] = true;
        }
        usort($acc, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
        return ['count' => count($acc), 'linked' => count(array_filter($links)), 'names' => count($links), 'list' => array_slice($acc, 0, $n)];
    }
}
