<?php
declare(strict_types=1);

namespace App\Front;

use App\Core\PhpCache;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;

/**
 * « L'effectif de la saison » en page d'album Panini : une vignette par joueur entré en jeu
 * (titulaire, ou remplaçant entré), calculée depuis les compositions des fiches matchs.
 *
 * Le décompte (matchs, buts, minutes, poste) est mis en cache par saison dans
 * storage/cache/album/, invalidé à chaque nouveau calcul de Derived (empreinte built()).
 * La fiche liée (nom, photo, visibilité) est relue à l'affichage dans l'index : une fiche
 * masquée ou ajoutée apparaît tout de suite, sans recompter les matchs.
 */
final class SeasonAlbum
{
    public const ORDER = ['G' => 0, 'D' => 1, 'M' => 2, 'A' => 3, 'X' => 4];

    /**
     * Données brutes (sans fiche résolue) : joueurs triés par poste puis matchs, entraîneur principal.
     * @return array{players:list<array>,coach:?array}
     */
    public static function raw(string $season): array
    {
        $file = STORAGE_PATH . '/cache/album/' . preg_replace('/[^0-9-]/', '', $season) . '.php';
        $built = Derived::built();
        $c = PhpCache::read($file);
        if ($c && ($c['built'] ?? null) === $built && ($c['v'] ?? 0) === 2) { // v2 : amicaux comptés à part
            return $c['data'];
        }
        $data = self::compute($season);
        try {
            PhpCache::write($file, ['built' => $built, 'v' => 2, 'data' => $data]);
        } catch (\Throwable) {
            // Cache en lecture seule : on sert le calcul sans l'enregistrer.
        }
        return $data;
    }

    /** @return array{players:list<array>,coach:?array} */
    public static function compute(string $season): array
    {
        $M = Derived::part('matches');
        $ids = Derived::part('seasons')[$season]['matches'] ?? [];
        $squad = [];
        $coaches = [];
        foreach ($ids as $mid) {
            $x = $M[$mid] ?? null;
            if (!$x || !$x['v']) {
                continue;
            }
            // Matchs amicaux comptés à part : les chiffres de l'album sont ceux des matchs officiels.
            $amical = ($x['comp'] ?? '') === 'Amical';
            $doc = Fiches::get((int) $mid);
            $apps = Derived::lineupLinks((int) $mid);
            $minByPid = [];
            foreach ($apps as $ap) {
                if (($ap[6] ?? '') === 'player') {
                    $minByPid[$ap[0]] = (int) $ap[3];
                }
            }
            $seen = [];
            foreach ($doc['match']['lineup']['rows'] ?? [] as $r) {
                $name = trim((string) ($r['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $pos = (string) ($r['position'] ?? '');
                $pid = $r['person_id'] ?? null;
                if (!$pid) {
                    foreach ($apps as $ap) {
                        $s = Index::get($ap[0]);
                        if ($ap[8] === $pos && $s && Names::lineupLastName($name) === implode(' ', Names::tokens($s['p']['last'] ?? ''))) {
                            $pid = $ap[0];
                            break;
                        }
                    }
                }
                $key = $pid ? 'p' . $pid : 'n' . Names::personKey($name);
                if ($pos === 'E') {
                    $coaches[$key] ??= ['pid' => $pid ? (int) $pid : null, 'name' => Names::display($name), 'n' => 0];
                    $coaches[$key]['n']++;
                    continue;
                }
                $played = $pos !== 'R' || (($r['sub_in'] ?? null) !== null && $r['sub_in'] !== '');
                if (!$played || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $squad[$key] ??= ['pid' => $pid ? (int) $pid : null, 'name' => Names::display($name), 'mj' => 0, 'goals' => 0, 'min' => 0, 'amic' => 0, 'pos' => []];
                if ($amical) {
                    $squad[$key]['amic']++;
                    continue;
                }
                $squad[$key]['mj']++;
                $squad[$key]['goals'] += count($r['goals'] ?? []);
                if ($pid && isset($minByPid[$pid])) {
                    $squad[$key]['min'] += $minByPid[$pid];
                }
                if (isset(self::ORDER[$pos])) {
                    $squad[$key]['pos'][$pos] = ($squad[$key]['pos'][$pos] ?? 0) + 1;
                }
            }
        }
        $players = [];
        foreach ($squad as $p) {
            arsort($p['pos']);
            $p['line'] = array_key_first($p['pos']) ?? 'X';
            unset($p['pos']);
            $players[] = $p;
        }
        usort($players, fn ($a, $b) => self::ORDER[$a['line']] <=> self::ORDER[$b['line']] ?: $b['mj'] <=> $a['mj'] ?: strcmp($a['name'], $b['name']));
        uasort($coaches, fn ($a, $b) => $b['n'] <=> $a['n']);
        return ['players' => $players, 'coach' => $coaches ? reset($coaches) : null];
    }

    /**
     * Album prêt à afficher : vignettes numérotées, fiche visible résolue (lien, photo), groupées par poste.
     * null si la saison n'a aucune composition.
     */
    public static function build(string $season): ?array
    {
        $raw = self::raw($season);
        if (!$raw['players']) {
            return null;
        }
        $resolve = function (array $p): array {
            $s = $p['pid'] ? Index::get($p['pid']) : null;
            $s = $s && Index::visible($s) ? $s : null;
            return $p + [
                'stuck' => (bool) $s,
                'display' => $s['p']['name'] ?? $p['name'],
                'href' => $s ? url($s['path']) : null,
                'image' => $s['image'] ?? null,
            ];
        };
        $groups = [];
        $no = 0;
        $hasMin = false;
        foreach ($raw['players'] as $p) {
            $p = $resolve($p) + ['no' => ++$no];
            $hasMin = $hasMin || $p['min'] > 0;
            $groups[$p['line']][] = $p;
        }
        $coach = $raw['coach'] ? $resolve($raw['coach']) + ['no' => ++$no] : null;
        return ['groups' => $groups, 'count' => $no - ($coach ? 1 : 0), 'coach' => $coach, 'minutes' => $hasMin];
    }
}
