<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;

/**
 * Données calculées à partir des fiches, sans aucune saisie :
 * liens joueurs ↔ matchs, saisons, face-à-face, bilans par compétition et par stade,
 * records, « Ce jour-là », contrôles qualité.
 *
 * Recalcul paresseux : une modification marque le cache « sale », le prochain
 * besoin le reconstruit (quelques secondes pour ~3 000 fiches).
 */
final class Derived
{
    private const CACHE = STORAGE_PATH . '/cache/derived.php';
    private const DIRTY = STORAGE_PATH . '/cache/derived.dirty';
    private static ?array $data = null;

    public const OFFICIAL_EXCLUDED = ['Amical', "Coupe d'été", 'Coupes diverses'];

    public static function markDirty(): void
    {
        $dir = dirname(self::DIRTY);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @touch(self::DIRTY);
        self::$data = null;
        self::scheduleRebuild();
    }

    /**
     * Données calculées. Si le cache est périmé, on sert la version précédente et on
     * recalcule après l'envoi de la page (le visiteur ou l'historien n'attend pas).
     */
    public static function get(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        if (is_file(self::CACHE)) {
            $d = include self::CACHE;
            if (is_array($d) && ($d['version'] ?? 0) === 4) {
                if (is_file(self::DIRTY) && filemtime(self::DIRTY) >= filemtime(self::CACHE)) {
                    self::scheduleRebuild();
                }
                return self::$data = $d;
            }
        }
        return self::$data = self::rebuildLocked();
    }

    private static bool $scheduled = false;

    public static function scheduleRebuild(): void
    {
        if (self::$scheduled || PHP_SAPI === 'cli') {
            return;
        }
        self::$scheduled = true;
        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            ignore_user_abort(true);
            set_time_limit(300);
            self::rebuildLocked();
        });
    }

    public static function isDirty(): bool
    {
        return is_file(self::DIRTY) && (!is_file(self::CACHE) || filemtime(self::DIRTY) >= filemtime(self::CACHE));
    }

    private static function rebuildLocked(): array
    {
        $dir = dirname(self::CACHE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fp = fopen(self::CACHE . '.lock', 'c');
        flock($fp, LOCK_EX);
        try {
            // Un autre processus a peut-être reconstruit pendant l'attente.
            if (is_file(self::CACHE) && (!is_file(self::DIRTY) || filemtime(self::DIRTY) < filemtime(self::CACHE))) {
                $d = include self::CACHE;
                if (is_array($d) && ($d['version'] ?? 0) === 4) {
                    return $d;
                }
            }
            return self::rebuild();
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function rebuild(): array
    {
        $t0 = microtime(true);
        $matches = [];
        $persons = [];
        $articles = [];
        $mediaRefs = [];
        foreach (Fiches::all() as $id => $doc) {
            $mediaRefs[$id] = Media::refsIn($doc);
            if (($doc['status'] ?? '') === 'corbeille') {
                continue;
            }
            $vis = Fiches::isVisible($doc);
            if ($doc['type'] === 'match') {
                $matches[$id] = $doc + ['_visible' => $vis];
            } elseif ($doc['type'] === 'personne') {
                $persons[$id] = $doc + ['_visible' => $vis];
            } elseif (in_array($doc['type'], ['article', 'page'], true)) {
                $articles[$id] = ['id' => $id, 'title' => $doc['title'], 'kind' => $doc['article']['kind'] ?? 'article', 'season' => $doc['article']['season'] ?? null, '_visible' => $vis];
            }
        }

        // ---------------------------------------------------------- personnes
        $byKey = [];
        $byLast = [];
        $byLetters = [];
        $nameKeys = []; // pid => [clé, mots] pour les rapprochements approchés
        foreach ($persons as $pid => $p) {
            $pp = $p['personne'];
            $names = array_filter(array_merge([
                $pp['display_name'] ?: $p['title'],
                trim(($pp['first_name'] ?? '') . ' ' . ($pp['last_name'] ?? '')),
                $p['title'],
                $pp['nickname'] ?? '',
            ], (array) ($pp['aliases'] ?? [])), fn ($n) => is_string($n) && trim($n) !== '');
            foreach (array_unique(array_map([Names::class, 'personKey'], $names)) as $k) {
                if ($k !== '') {
                    $byKey[$k][] = $pid;
                    $nameKeys[$pid][] = [$k, explode(' ', $k)];
                }
            }
            foreach (array_unique(array_map([Names::class, 'letterKey'], $names)) as $k) {
                if (strlen($k) >= 6) {
                    $byLetters[$k][] = $pid;
                }
            }
            $last = implode(' ', Names::tokens($pp['last_name'] ?? ''));
            if ($last !== '') {
                $byLast[$last][] = $pid;
            }
        }
        $period = function (array $p): array {
            $pp = $p['personne'];
            $years = [];
            foreach (['arrival', 'departure', 'arrival_coach', 'departure_coach', 'trial'] as $k) {
                if (!empty($pp[$k]['iso'])) {
                    $years[] = (int) substr((string) $pp[$k]['iso'], 0, 4);
                }
            }
            return $years ? [min($years) - 1, max($years) + 1] : [null, null];
        };
        $periods = array_map($period, $persons);

        $approx = [];
        $approxUsed = []; // nom de composition => [fiche, manière, nombre] : signalés aux historiens
        $resolve = function (string $name, ?string $date) use ($byKey, $byLast, $byLetters, $nameKeys, $periods, $persons, &$approx, &$approxUsed): ?int {
            $year = $date ? (int) substr($date, 0, 4) : null;
            $pick = function (array $ids) use ($year, $periods) {
                $ids = array_values(array_unique($ids));
                if (count($ids) === 1) {
                    return $ids[0];
                }
                if ($year) {
                    $in = array_values(array_filter($ids, fn ($id) => $periods[$id][0] !== null && $year >= $periods[$id][0] && $year <= $periods[$id][1]));
                    if (count($in) === 1) {
                        return $in[0];
                    }
                }
                return null;
            };
            $k = Names::personKey($name);
            if ($k !== '' && isset($byKey[$k])) {
                return $pick($byKey[$k]);
            }
            // Nom de famille seul (« PIERRE », « Fofana »), avec contrôle de période.
            $last = Names::lineupLastName($name);
            if ($last !== '' && isset($byLast[$last]) && ($pid = $pick($byLast[$last]))) {
                return $pid;
            }
            // Nom incomplet : jamais vers un joueur dont la période au club (connue) exclut la date.
            $pickNear = function (array $ids) use ($year, $periods, $pick) {
                if ($year) {
                    $ids = array_filter($ids, fn ($id) => $periods[$id][0] === null || ($year >= $periods[$id][0] && $year <= $periods[$id][1]));
                }
                return $ids ? $pick($ids) : null;
            };
            // Même nom écrit autrement : apostrophe, trait d'union, espace (« N'Diaye » / « Ndiaye »).
            $lk = Names::letterKey($name);
            if (isset($byLetters[$lk]) && ($pid = $pick($byLetters[$lk]))) {
                $approxUsed[$name] = [$pid, 'graphie', ($approxUsed[$name][2] ?? 0) + 1];
                return $pid;
            }
            if ($k === '') {
                return null;
            }
            // Rapprochements approchés, calculés une fois par nom : une lettre de différence
            // (deux pour un nom long), ou nom incomplet (« Carlao » pour « Carlao Roberto Da Cruz »).
            // Retenus seulement si un seul joueur correspond (ou un seul à cette période).
            if (!isset($approx[$k])) {
                $toks = explode(' ', $k);
                $typo = [];
                $part = [];
                $max = strlen($k) >= 15 ? 2 : (strlen($k) >= 9 ? 1 : 0);
                foreach ($nameKeys as $pid => $list) {
                    foreach ($list as [$pk, $ptoks]) {
                        if ($max && abs(strlen($pk) - strlen($k)) <= $max && levenshtein($k, $pk) <= $max) {
                            $typo[] = $pid;
                        }
                        if (count($ptoks) > count($toks) && !array_diff($toks, $ptoks) && (count($toks) > 1 || strlen($k) >= 4)) {
                            $part[] = $pid;
                        }
                    }
                }
                $approx[$k] = [array_values(array_unique($typo)), array_values(array_unique($part))];
            }
            foreach ($approx[$k] as $i => $ids) {
                if ($ids && ($pid = $i === 0 ? $pick($ids) : $pickNear($ids))) {
                    $approxUsed[$name] = [$pid, $i === 0 ? 'orthographe' : 'nom incomplet', ($approxUsed[$name][2] ?? 0) + 1];
                    return $pid;
                }
            }
            return null;
        };

        // ---------------------------------------------------------- clubs et stades
        $clubs = Collections::get('clubs', []);
        $stades = Collections::get('stades', []);
        $clubAlias = [];
        foreach ($clubs as $c) {
            $clubAlias[Names::clubKey($c['name'])] = $c['id'];
            foreach ($c['aliases'] ?? [] as $a) {
                $clubAlias[Names::clubKey($a)] = $c['id'];
            }
        }
        $stadeAlias = [];
        foreach ($stades as $s) {
            $stadeAlias[Names::stadiumKey($s['name'])] = $s['id'];
            foreach ($s['aliases'] ?? [] as $a) {
                $stadeAlias[Names::stadiumKey($a)] = $s['id'];
            }
        }
        $clubNames = [];
        $stadeNames = [];
        $newClubs = false;
        $newStades = false;

        // ---------------------------------------------------------- matchs
        $M = [];          // résumé par match
        $apps = [];       // apparitions : [person, match, goals, minutes, yellow, red, role, captain]
        $unlinked = [];   // noms de composition sans fiche
        $quality = [];
        $tableUse = [];
        $onThisDay = [];

        foreach ($matches as $mid => $doc) {
            $m = $doc['match'];
            $sochauxHome = (bool) ($m['sochaux_home'] ?? true);
            $oppName = $sochauxHome ? ($m['away']['name'] ?? '') : ($m['home']['name'] ?? '');
            $clubId = null;
            if ($oppName !== '' && !preg_match('/sochaux/iu', $oppName)) {
                $key = Names::clubKey($oppName);
                $clubId = $m['opponent_club'] ?: ($clubAlias[$key] ?? null);
                if (!$clubId) {
                    $clubId = $key;
                    $clubAlias[$key] = $clubId;
                    $clubs[] = ['id' => $clubId, 'name' => $oppName, 'aliases' => [], 'city' => '', 'country' => '', 'logo' => null, 'lat' => null, 'lng' => null, 'auto' => true];
                    $newClubs = true;
                }
                $clubNames[$clubId][$oppName] = ($clubNames[$clubId][$oppName] ?? 0) + 1;
            }
            $stadeId = null;
            if (!empty($m['stadium'])) {
                $sk = Names::stadiumKey($m['stadium']);
                $stadeId = $m['stadium_id'] ?: ($stadeAlias[$sk] ?? null);
                if (!$stadeId && $sk !== '') {
                    $stadeId = $sk;
                    $stadeAlias[$sk] = $stadeId;
                    $city = preg_match('/\(([^)]+)\)/u', $m['stadium'], $cm) ? $cm[1] : '';
                    $stades[] = ['id' => $stadeId, 'name' => trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', $m['stadium'])), 'aliases' => [], 'city' => '', 'department' => $city,
                        'country' => '', 'lat' => null, 'lng' => null, 'auto' => true];
                    $newStades = true;
                }
                if ($stadeId) {
                    $stadeNames[$stadeId][$m['stadium']] = ($stadeNames[$stadeId][$m['stadium']] ?? 0) + 1;
                }
            }

            $score = $m['score'] ?? null;
            $us = $them = null;
            if (isset($score['home'])) {
                [$us, $them] = $sochauxHome ? [$score['home'], $score['away']] : [$score['away'], $score['home']];
            }
            $date = $m['date'] ?? null;
            $decade = $date ? ((int) floor((int) substr($date, 0, 4) / 10) * 10) : null;
            $aet = (bool) ($score['aet'] ?? false);
            $M[$mid] = [
                'id' => $mid,
                'v' => $doc['_visible'],
                'path' => $doc['path'],
                'title' => $doc['title'],
                'date' => $date,
                'season' => $m['season'] ?? null,
                'decade' => $decade,
                'comp' => $m['competition'] ?? 'Championnat',
                'label' => $m['competition_label'] ?? '',
                'round' => $m['round'] ?? '',
                'home' => $m['home']['name'] ?? '',
                'away' => $m['away']['name'] ?? '',
                'sh' => $sochauxHome,
                'club' => $clubId,
                'opp' => $oppName,
                'stade' => $stadeId,
                'us' => $us,
                'them' => $them,
                'extra' => $score['extra'] ?? null,
                'result' => $m['result'] ?? null,
                // Affluence impossible (plus de 90 000 : faute de frappe) écartée des records et bilans
                'spectators' => isset($m['spectators']) && (int) $m['spectators'] > 0 && (int) $m['spectators'] <= 90000 ? (int) $m['spectators'] : null,
                'image' => $doc['featured_image'] ?? null,
                'event' => $m['event'] ?? null,
                'hl' => count($m['highlights'] ?? []),
            ];
            if ($date && $doc['_visible']) {
                $onThisDay[substr($date, 5, 5)][] = $mid;
            }

            // Composition → apparitions
            $rows = $m['lineup']['rows'] ?? [];
            if (!empty($m['lineup']['source_table'])) {
                $tableUse[$m['lineup']['source_table']][] = $mid;
            }
            $full = $aet ? 120 : 90;
            $teamGoals = 0;
            foreach ($rows as $r) {
                $pos = strtoupper((string) ($r['position'] ?? ''));
                $name = (string) ($r['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $pid = $r['person_id'] ?? null;
                if (!$pid || !isset($persons[$pid])) {
                    $pid = $resolve($name, $date);
                }
                $g = count($r['goals'] ?? []);
                $teamGoals += $g;
                $min = 0;
                $role = $pos === 'E' ? 'coach' : 'player';
                if ($role === 'player') {
                    $in = $r['sub_in'] ?? null;
                    $out = $r['sub_out'] ?? null;
                    $mi = fn ($v) => (int) explode('+', (string) $v)[0];
                    if ($pos === 'R') {
                        // Entré puis sorti : temps passé entre les deux.
                        $end = ($out !== null && $out !== '' && $mi($out) > $mi((string) $in)) ? $mi($out) : $full;
                        $min = ($in !== null && $in !== '') ? max(1, $end - $mi($in)) : 0;
                    } else {
                        $min = ($out !== null && $out !== '') ? $mi($out) : $full;
                    }
                }
                if ($role === 'player' && $pos === 'R' && $min === 0) {
                    // Remplaçant non entré : pas d'apparition, mais présence sur la feuille.
                    $role = 'bench';
                }
                if ($pid) {
                    $apps[] = [$pid, $mid, $g, $min, count($r['yellow'] ?? []), count($r['red'] ?? []), $role, (bool) ($r['captain'] ?? false), $pos];
                } elseif ($doc['_visible']) {
                    $unlinked[Names::personKey($name)]['name'] = Names::display($name);
                    $unlinked[Names::personKey($name)]['matches'][] = $mid;
                }
            }

            // Contrôles qualité du match (les buts contre son camp de l'adversaire, cités avec
            // les buteurs sochaliens, comptent pour Sochaux sans figurer dans la composition).
            foreach ($m['goals'] ?? [] as $gl) {
                if (stripos((string) ($gl['team'] ?? ''), 'sochaux') !== false) {
                    $teamGoals += preg_match_all('/c\s*\.?\s*s\s*\.?\s*c|contre son camp/iu', (string) ($gl['scorers'] ?? ''));
                }
            }
            if ($us !== null && $rows && $teamGoals > 0 && $teamGoals !== $us) {
                $quality[] = ['sev' => 'haute', 'code' => 'buts', 'msg' => "Total des buts ($us) ≠ somme des buteurs de la composition ($teamGoals)", 'id' => $mid];
            }
            if (!empty($m['date_text']) && $date) {
                $pd = self::frDate($m['date_text']);
                if ($pd && $pd !== $date) {
                    $quality[] = ['sev' => 'moyenne', 'code' => 'date', 'msg' => 'Date du titre (' . date('d/m/Y', strtotime($date)) . ') ≠ date de la fiche (' . $m['date_text'] . ')', 'id' => $mid];
                }
            }
            if ((int) ($m['spectators'] ?? 0) > 90000) {
                $quality[] = ['sev' => 'haute', 'code' => 'affluence', 'msg' => 'Affluence improbable (' . number_format((int) $m['spectators'], 0, ',', ' ') . ' spectateurs) : faute de frappe ? Elle est écartée des records.', 'id' => $mid];
            }
            if (empty($m['referee']) && $decade && $decade >= 1970 && $doc['_visible'] && ($m['competition'] ?? '') !== 'Amical') {
                $quality[] = ['sev' => 'basse', 'code' => 'arbitre', 'msg' => 'Arbitre non renseigné', 'id' => $mid];
            }
        }

        // Tableaux de composition partagés par plusieurs matchs
        foreach ($tableUse as $tid => $mids) {
            if (count($mids) > 1) {
                foreach ($mids as $mid) {
                    $others = array_map(fn ($o) => $M[$o]['title'], array_diff($mids, [$mid]));
                    $quality[] = ['sev' => 'haute', 'code' => 'tableau', 'msg' => 'Même tableau de composition que : ' . implode(', ', array_slice($others, 0, 3)), 'id' => $mid];
                }
            }
        }

        // Fiches « à venir », photos sans crédit, statistiques incohérentes
        foreach (array_merge($matches, $persons) as $id => $doc) {
            $txt = '';
            foreach ($doc['sections'] ?? [] as $s) {
                $txt .= ' ' . strip_tags((string) $s['html']);
            }
            if (preg_match('/\b(à|a) venir\b/iu', $txt)) {
                $quality[] = ['sev' => 'moyenne', 'code' => 'avenir', 'msg' => 'Fiche marquée « à venir »', 'id' => $id];
            }
            if (!empty($doc['personne']['stats']['rows'])) {
                $issue = self::checkStatsTotals($doc['personne']['stats']);
                if ($issue) {
                    $quality[] = ['sev' => 'haute', 'code' => 'stats', 'msg' => $issue, 'id' => $id];
                }
            }
        }
        // Index d'utilisation des médias (médiathèque du back-office)
        $usage = [];
        foreach ($mediaRefs as $id => $refs) {
            foreach ($refs as $rel) {
                $usage[$rel][] = (int) $id;
            }
        }
        unset($mediaRefs);
        $usage = self::collectionMediaUsage($usage);
        Media::saveUsage($usage);

        $noCredit = 0;
        foreach (Media::all() as $rel => $mm) {
            if (str_starts_with((string) ($mm['mime'] ?? ''), 'image/') && trim((string) ($mm['credit'] ?? '')) === '') {
                $noCredit++;
            }
        }

        // ---------------------------------------------------------- agrégats
        $byPerson = [];
        foreach ($apps as $a) {
            [$pid, $mid] = $a;
            $byPerson[$pid][] = $a;
        }
        // Tri chronologique des matchs de chaque personne
        foreach ($byPerson as $pid => &$list) {
            usort($list, fn ($x, $y) => strcmp((string) $M[$x[1]]['date'], (string) $M[$y[1]]['date']));
        }
        unset($list);

        $personTotals = [];
        foreach ($byPerson as $pid => $list) {
            $t = ['matches' => 0, 'goals' => 0, 'yellow' => 0, 'red' => 0, 'minutes' => 0, 'coached' => 0, 'v' => 0, 'n' => 0, 'd' => 0, 'seasons' => [], 'first' => null, 'last' => null];
            foreach ($list as [$p, $mid, $g, $min, $y, $r, $role]) {
                if (!$M[$mid]['v']) {
                    continue;
                }
                if ($role === 'coach') {
                    $t['coached']++;
                    $res = $M[$mid]['result'];
                    if ($res) {
                        $t[strtolower($res)]++;
                    }
                } elseif ($role === 'player') {
                    $t['matches']++;
                    $t['goals'] += $g;
                    $t['minutes'] += $min;
                    $t['first'] ??= $mid;
                    $t['last'] = $mid;
                }
                $t['yellow'] += $y;
                $t['red'] += $r;
                if ($M[$mid]['season']) {
                    $t['seasons'][$M[$mid]['season']] = true;
                }
            }
            $t['seasons'] = count($t['seasons']);
            $personTotals[$pid] = $t;
        }

        $seasons = [];
        foreach ($M as $mid => $x) {
            if (!$x['v'] || !$x['season']) {
                continue;
            }
            $s = &$seasons[$x['season']];
            $s['matches'][] = $mid;
            $s['comps'][$x['comp']] = ($s['comps'][$x['comp']] ?? 0) + 1;
            if ($x['comp'] === 'Championnat' && $x['label']) {
                $s['division'][$x['label']] = ($s['division'][$x['label']] ?? 0) + 1;
            }
            if ($x['result']) {
                $s['res'][$x['result']] = ($s['res'][$x['result']] ?? 0) + 1;
            }
            unset($s);
        }
        foreach ($seasons as $sk => &$s) {
            usort($s['matches'], fn ($a, $b) => strcmp((string) $M[$a]['date'], (string) $M[$b]['date']));
            $div = $s['division'] ?? [];
            arsort($div);
            $s['division'] = $div ? array_key_first($div) : null;
            $s['res'] = ($s['res'] ?? []) + ['V' => 0, 'N' => 0, 'D' => 0];
            $s['comps'] ??= [];
        }
        unset($s);
        // Effectif et buteurs par saison
        foreach ($apps as [$pid, $mid, $g, $min, , , $role, , $pos]) {
            $sk = $M[$mid]['season'];
            if (!$sk || !$M[$mid]['v'] || !isset($seasons[$sk])) {
                continue;
            }
            if ($role === 'player' || $role === 'bench') {
                $e = &$seasons[$sk]['squad'][$pid];
                $e['mj'] = ($e['mj'] ?? 0) + ($role === 'player' ? 1 : 0);
                $e['goals'] = ($e['goals'] ?? 0) + $g;
                $e['pos'][$pos] = ($e['pos'][$pos] ?? 0) + 1;
                unset($e);
            } elseif ($role === 'coach') {
                $seasons[$sk]['coaches'][$pid] = ($seasons[$sk]['coaches'][$pid] ?? 0) + 1;
            }
        }

        $clubStats = self::groupStats($M, 'club');
        $stadeStats = self::groupStats($M, 'stade');
        $compStats = self::groupStats($M, 'comp');

        // Noms canoniques des clubs et stades créés automatiquement : le plus fréquent.
        foreach ($clubs as &$c) {
            if (!empty($c['auto']) && isset($clubNames[$c['id']])) {
                arsort($clubNames[$c['id']]);
                $c['name'] = array_key_first($clubNames[$c['id']]);
                $c['aliases'] = array_values(array_diff(array_keys($clubNames[$c['id']]), [$c['name']]));
            }
        }
        unset($c);
        foreach ($stades as &$st) {
            if (!empty($st['auto']) && isset($stadeNames[$st['id']])) {
                arsort($stadeNames[$st['id']]);
                $st['name'] = trim(preg_replace('/\s*\([^)]*\)\s*/u', ' ', array_key_first($stadeNames[$st['id']])));
                $st['aliases'] = array_values(array_diff(array_keys($stadeNames[$st['id']]), [array_key_first($stadeNames[$st['id']])]));
                if ($st['id'] === 'auguste-bonal' && empty($st['lat'])) {
                    $st += ['city' => 'Montbéliard'];
                    $st['city'] = $st['city'] ?: 'Montbéliard';
                    $st['lat'] = 47.5119;
                    $st['lng'] = 6.8116;
                }
            }
        }
        unset($st);
        if ($newClubs || array_filter($clubs, fn ($c) => !empty($c['auto']))) {
            JsonStore::write(Collections::DIR . '/clubs.json', array_values($clubs));
        }
        if ($newStades || array_filter($stades, fn ($s) => !empty($s['auto']))) {
            JsonStore::write(Collections::DIR . '/stades.json', array_values($stades));
        }

        foreach ($unlinked as $k => $u) {
            $u['matches'] = array_values(array_unique($u['matches']));
            $unlinked[$k] = $u;
        }
        uasort($unlinked, fn ($a, $b) => count($b['matches']) <=> count($a['matches']));
        foreach (array_slice($unlinked, 0, 300, true) as $u) {
            $quality[] = ['sev' => 'basse', 'code' => 'nonrelie', 'msg' => "Joueur cité dans " . count($u['matches']) . " composition(s) sans fiche : {$u['name']}", 'id' => $u['matches'][0]];
        }
        // Noms reliés par rapprochement (autre graphie, faute de frappe, nom incomplet) : à vérifier.
        uasort($approxUsed, fn ($a, $b) => $b[2] <=> $a[2]);
        foreach ($approxUsed as $name => [$pid, $how, $n]) {
            $quality[] = ['sev' => 'basse', 'code' => 'rapproche', 'msg' => 'Nom « ' . Names::display($name) . " » relié par rapprochement ($how, $n composition" . ($n > 1 ? 's' : '') . ') à la fiche : ' . ($persons[$pid]['title'] ?? $pid), 'id' => $pid];
        }

        $data = [
            'version' => 4,
            'built' => date('c'),
            'duration' => round(microtime(true) - $t0, 2),
            'matches' => $M,
            'apps' => $apps,
            'person_totals' => $personTotals,
            'by_person' => array_map(fn ($l) => array_map(fn ($a) => $a[1], $l), $byPerson),
            'seasons' => $seasons,
            'clubs' => $clubStats,
            'stades' => $stadeStats,
            'comps' => $compStats,
            'on_this_day' => $onThisDay,
            'unlinked' => array_slice($unlinked, 0, 500, true),
            'quality' => $quality,
            'no_credit' => $noCredit,
            'bilans' => array_values(array_filter($articles, fn ($a) => $a['kind'] === 'bilan_saison')),
        ];
        $tmp = self::CACHE . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, '<?php return ' . var_export($data, true) . ";\n", LOCK_EX);
        rename($tmp, self::CACHE);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::CACHE, true);
        }
        @unlink(self::DIRTY);
        return $data;
    }

    /** Ajoute à l'index d'utilisation les médias des collections éditoriales et des rubriques. */
    private static function collectionMediaUsage(array $usage): array
    {
        foreach (glob(Collections::DIR . '/*.json') ?: [] as $f) {
            $name = basename($f, '.json');
            if ($name === 'geo') {
                continue;
            }
            foreach (Media::refsIn(JsonStore::read($f, [])) as $rel) {
                $usage[$rel][] = 'c:' . $name;
            }
        }
        foreach (Media::refsIn(Categories::all()) as $rel) {
            $usage[$rel][] = 'c:rubriques';
        }
        return $usage;
    }

    /** Bilans regroupés (par adversaire, stade ou compétition). */
    private static function groupStats(array $M, string $key): array
    {
        $out = [];
        foreach ($M as $mid => $x) {
            if (!$x['v'] || $x[$key] === null || $x[$key] === '') {
                continue;
            }
            $g = &$out[$x[$key]];
            $g['matches'][] = $mid;
            if ($x['result']) {
                $g[strtolower($x['result'])] = ($g[strtolower($x['result'])] ?? 0) + 1;
            }
            if ($x['us'] !== null) {
                $g['gf'] = ($g['gf'] ?? 0) + $x['us'];
                $g['ga'] = ($g['ga'] ?? 0) + $x['them'];
            }
            unset($g);
        }
        foreach ($out as &$g) {
            $g += ['v' => 0, 'n' => 0, 'd' => 0, 'gf' => 0, 'ga' => 0];
            usort($g['matches'], fn ($a, $b) => strcmp((string) $M[$a]['date'], (string) $M[$b]['date']));
            $g['count'] = count($g['matches']);
        }
        unset($g);
        return $out;
    }

    private static function checkStatsTotals(array $stats): ?string
    {
        $rows = $stats['rows'];
        $last = end($rows);
        if (!$last || !preg_match('/^total/iu', (string) ($last[0] ?? ''))) {
            return null;
        }
        $body = array_slice($rows, 0, -1);
        foreach ($last as $ci => $tot) {
            if ($ci === 0 || !is_numeric($tot)) {
                continue;
            }
            $sum = 0;
            $ok = false;
            foreach ($body as $r) {
                if (isset($r[$ci]) && is_numeric($r[$ci])) {
                    $sum += (int) $r[$ci];
                    $ok = true;
                }
            }
            if ($ok && $sum !== (int) $tot) {
                $col = $stats['headers'][$ci] ?? "colonne $ci";
                return "Statistiques : total « $col » ($tot) ≠ somme des saisons ($sum)";
            }
        }
        return null;
    }

    /** « Mardi 3 Novembre 1987 » → 1987-11-03 */
    private static function frDate(string $s): ?string
    {
        $months = ['janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12];
        $a = Names::ascii($s);
        if (preg_match('/(\d{1,2})(?:er)?\s+([a-z]+)\s+(\d{4})/', $a, $m) && isset($months[$m[2]]) && checkdate($months[$m[2]], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $months[$m[2]], $m[1]);
        }
        return null;
    }

    // ------------------------------------------------------------------ lectures

    public static function match(int $id): ?array
    {
        return self::get()['matches'][$id] ?? null;
    }

    /** @return list<array> matchs (résumés) d'une personne, avec sa ligne de composition */
    public static function personMatches(int $pid): array
    {
        $d = self::get();
        $out = [];
        foreach ($d['apps'] as $a) {
            if ($a[0] === $pid && $d['matches'][$a[1]]['v']) {
                $out[] = $d['matches'][$a[1]] + ['goals' => $a[2], 'minutes' => $a[3], 'yellow' => $a[4], 'red' => $a[5], 'role' => $a[6], 'captain' => $a[7], 'pos' => $a[8]];
            }
        }
        usort($out, fn ($x, $y) => strcmp((string) $x['date'], (string) $y['date']));
        return $out;
    }

    /** Identifiant de fiche relié à une ligne de composition (pour les liens). */
    public static function lineupLinks(int $mid): array
    {
        $out = [];
        foreach (self::get()['apps'] as $a) {
            if ($a[1] === $mid) {
                $out[] = $a;
            }
        }
        return $out;
    }

    /** Matchs joués à cette date (jour et mois), du plus récent au plus ancien. */
    public static function onThisDay(?string $mmdd = null): array
    {
        $d = self::get();
        $ids = $d['on_this_day'][$mmdd ?? date('m-d')] ?? [];
        $list = array_map(fn ($id) => $d['matches'][$id], $ids);
        usort($list, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
        return $list;
    }
}
