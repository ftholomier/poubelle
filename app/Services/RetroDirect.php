<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
use App\Front\Fiche;

/**
 * Rétro-Direct : un grand match du musée rejoué en direct, minute par minute, le jour de son
 * anniversaire et à l'heure choisie (programme dans data/collections/retrodirect.json).
 *
 * La chronologie (temps forts, buts et score, remplacements, cartons, mi-temps) est calculée
 * à partir de la fiche ; c'est le navigateur qui la déroule à l'horloge du serveur : aucune
 * charge pendant le direct, sauf les réactions et le compteur de spectateurs (storage/retro/).
 * N'importe quel match ayant assez de temps forts peut aussi se revivre en accéléré.
 */
final class RetroDirect
{
    /** Anniversaires « ronds » proposés au programme. */
    public const ROUND = [10, 20, 25, 30, 40, 50, 60, 70, 75, 80, 90, 100];
    public const REACTIONS = ['but' => '⚽', 'bravo' => '👏', 'wow' => '😱'];
    /** Après le coup de sifflet final, le direct reste ouvert (réactions, « J'y étais ! »), en secondes. */
    public const AFTER = 15 * 60;
    /** Événements datés (temps forts, buts) nécessaires pour rejouer un match. */
    public const MIN_EVENTS = 4;
    public static string $dir = STORAGE_PATH . '/retro';
    /** Programme imposé (tests) ; null : data/collections/retrodirect.json. */
    public static ?array $entries = null;
    private static array $timelines = [];

    private static function entries(): array
    {
        return self::$entries ?? (array) Collections::get('retrodirect', []);
    }

    // ------------------------------------------------------------------ programme

    /**
     * Directs programmés, du plus ancien au plus lointain : id, date, time, start, whistle (coup
     * de sifflet final), end (fin de l'après-match), intro, state (avenir, direct, termine), s (résumé de la fiche).
     * $exact = false : durée estimée sans relire la fiche (bandeau du site, API des réactions).
     */
    public static function program(?int $now = null, bool $exact = true): array
    {
        $now ??= time();
        $out = [];
        foreach (self::entries() as $e) {
            $id = (int) ($e['id'] ?? 0);
            $s = $id ? Index::get($id) : null;
            $date = (string) ($e['date'] ?? '');
            if (!$s || $s['type'] !== 'match' || !Index::visible($s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $time = self::validTime((string) ($e['time'] ?? '')) ?? '20:00';
            $start = (int) strtotime("$date $time");
            // Durée exacte (chronologie) quand le direct est proche, estimation sinon.
            $len = $exact && abs($start - $now) < 2 * 86400 ? (self::timelineFor($id)['end'] ?: self::duration($s)) : self::duration($s);
            $end = $start + $len + self::AFTER;
            $out[] = ['id' => $id, 'date' => $date, 'time' => $time, 'start' => $start, 'whistle' => $start + $len, 'end' => $end,
                'intro' => (string) ($e['intro'] ?? ''), 'intro_en' => (string) ($e['intro_en'] ?? ''), 'by' => (string) ($e['by'] ?? ''),
                'state' => $now < $start ? 'avenir' : ($now < $end ? 'direct' : 'termine'), 's' => $s];
        }
        usort($out, fn ($a, $b) => $a['start'] <=> $b['start']);
        return $out;
    }

    /**
     * Durée estimée d'un direct (secondes, sans l'après-match) d'après le résumé de la fiche,
     * temps additionnel compris ; prolongation reconnue sous toutes ses formes (« a.p », « ap », « tab »).
     */
    public static function duration(array $s): int
    {
        $m = $s['m'] ?? [];
        $x = (string) ($m['extra'] ?? '');
        $pens = !empty($m['pens']) || stripos($x, 'tab') !== false;
        $aet = $pens || $x === 'ap' || preg_match('/a\.?\s*p\b/i', $x);
        return (45 + 15 + 45 + 8 + ($aet ? 5 + 30 + 3 : 0) + ($pens ? 3 + 8 : 0)) * 60;
    }

    /** Le direct d'un match : en cours, sinon le prochain, sinon le dernier passé. */
    public static function entryFor(int $id, ?int $now = null, bool $exact = true): ?array
    {
        $mine = array_values(array_filter(self::program($now, $exact), fn ($e) => $e['id'] === $id));
        foreach ($mine as $e) {
            if ($e['state'] !== 'termine') {
                return $e;
            }
        }
        return $mine ? end($mine) : null;
    }

    /** Le direct en cours, sinon le prochain (bandeau du site). */
    public static function next(?int $now = null): ?array
    {
        foreach (self::program($now, false) as $e) {
            if ($e['state'] !== 'termine') {
                return $e;
            }
        }
        return null;
    }

    public static function validTime(string $t): ?string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : null;
    }

    /** Ajoute (ou remplace) un direct au programme. */
    public static function add(int $id, string $date, string $time, string $intro, ?array $user, string $introEn = ''): void
    {
        $list = array_values(array_filter((array) Collections::get('retrodirect', []), fn ($e) => !((int) ($e['id'] ?? 0) === $id && ($e['date'] ?? '') === $date)));
        $list[] = array_filter(['id' => $id, 'date' => $date, 'time' => self::validTime($time) ?? '20:00', 'intro' => mb_substr(trim($intro), 0, 400),
            'intro_en' => mb_substr(trim($introEn), 0, 400), 'by' => (string) ($user['name'] ?? ''), 'at' => date('c')], fn ($v) => $v !== '');
        usort($list, fn ($a, $b) => strcmp($a['date'] . ($a['time'] ?? ''), $b['date'] . ($b['time'] ?? '')));
        Collections::save('retrodirect', $list, $user, 'Rétro-Direct programmé');
    }

    public static function remove(int $id, string $date, ?array $user): bool
    {
        $all = (array) Collections::get('retrodirect', []);
        $list = array_values(array_filter($all, fn ($e) => !((int) ($e['id'] ?? 0) === $id && ($e['date'] ?? '') === $date)));
        if (count($list) === count($all)) {
            return false;
        }
        Collections::save('retrodirect', $list, $user, 'Rétro-Direct retiré');
        return true;
    }

    /** Intérêt d'un match pour un direct (résumé calculé de Derived) : temps forts, coupes, écart, affluence… */
    public static function interest(array $dm, ?array $s = null): float
    {
        $cup = in_array($dm['comp'] ?? '', ['Coupe de France', 'Coupe de la Ligue', "Coupe d'Europe", 'Barrages'], true);
        $big = (bool) preg_match('/^(finale|1\/2|demi)/i', trim((string) ($dm['round'] ?? '')));
        $us = (int) ($dm['us'] ?? 0);
        $them = (int) ($dm['them'] ?? 0);
        return min(20, (int) ($dm['hl'] ?? 0))
            + ($cup ? 10 : 0) + ($big ? 12 : 0)
            + ($us - $them >= 3 ? 6 : 0) + ($us + $them >= 5 ? 4 : 0)
            + min(10, (int) ($dm['spectators'] ?? 0) / 2500)
            + (($dm['result'] ?? '') === 'V' ? 4 : 0)
            + (!empty($s['a_la_une']) ? 6 : 0)
            - (($dm['comp'] ?? '') === 'Amical' ? 20 : 0);
    }

    /**
     * Anniversaires ronds des $days prochains jours (10, 20, 25… ans), les plus intéressants,
     * remis dans l'ordre des dates ; ceux déjà au programme sont écartés.
     */
    public static function suggestions(int $days = 90, ?int $now = null, int $limit = 30): array
    {
        $now ??= time();
        $taken = [];
        foreach (self::program($now) as $e) {
            $taken[$e['id'] . '|' . $e['date']] = true;
        }
        $today = date('Y-m-d', $now);
        $out = [];
        foreach (Derived::part('matches') as $id => $dm) {
            if (empty($dm['v']) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) ($dm['date'] ?? ''), $d) || (int) ($dm['hl'] ?? 0) < 5) {
                continue;
            }
            // Prochain anniversaire de ce match (29 février : le 28 les années ordinaires).
            $year = (int) date('Y', $now);
            for ($i = 0; $i < 2; $i++, $year++) {
                $md = $d[2] . '-' . $d[3];
                if ($md === '02-29' && !checkdate(2, 29, $year)) {
                    $md = '02-28';
                }
                $ann = "$year-$md";
                if ($ann >= $today) {
                    break;
                }
            }
            $ago = $year - (int) $d[1];
            $ts = strtotime($ann);
            if (!$ts || $ts > $now + $days * 86400 || !in_array($ago, self::ROUND, true) || isset($taken[$id . '|' . $ann])) {
                continue;
            }
            $s = Index::get((int) $id);
            if (!$s) {
                continue;
            }
            $out[] = ['id' => (int) $id, 'date' => $ann, 'ago' => $ago, 'score' => round(self::interest($dm, $s) + ($ago >= 50 ? 5 : 0), 1), 'hl' => (int) $dm['hl'], 's' => $s, 'dm' => $dm];
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);
        $keep = array_slice($out, 0, $limit);
        usort($keep, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: $b['score'] <=> $a['score']);
        return $keep;
    }

    /** Grands matchs à revivre en accéléré (page du Rétro-Direct), un par adversaire. */
    public static function classics(int $limit = 8): array
    {
        $out = [];
        foreach (Derived::part('matches') as $id => $dm) {
            if (!empty($dm['v']) && (int) ($dm['hl'] ?? 0) >= 8 && ($s = Index::get((int) $id))) {
                $out[] = ['id' => (int) $id, 'score' => self::interest($dm, $s), 's' => $s, 'dm' => $dm];
            }
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp((string) $b['dm']['date'], (string) $a['dm']['date']));
        $seen = [];
        $keep = [];
        foreach ($out as $o) {
            $k = (string) ($o['dm']['club'] ?? $o['dm']['opp'] ?? '');
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $keep[] = $o;
            if (count($keep) >= $limit) {
                break;
            }
        }
        return $keep;
    }

    // ------------------------------------------------------------------ adresses

    /** Dernier segment de l'adresse de la fiche : /interactif/retro-direct/{slug}/ */
    public static function slug(array $s): string
    {
        return basename(rtrim((string) ($s['path'] ?? ''), '/'));
    }

    public static function url(array $s): string
    {
        return url('/interactif/retro-direct/' . self::slug($s) . '/');
    }

    public static function bySlug(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9-]{3,160}$/', $slug)) {
            return null;
        }
        foreach (Index::published('match') as $s) {
            if (self::slug($s) === $slug) {
                return $s;
            }
        }
        return null;
    }

    /** Assez d'événements datés pour un direct ? */
    public static function playable(array $doc): bool
    {
        $n = 0;
        foreach ($doc['match']['highlights'] ?? [] as $h) {
            $n += self::minute((string) ($h['minute'] ?? '')) ? 1 : 0;
        }
        if ($n < self::MIN_EVENTS) {
            $n += count(self::textGoals($doc['match'] ?? [], (string) ($doc['match']['home']['name'] ?? ''), (string) ($doc['match']['away']['name'] ?? '')));
        }
        return $n >= self::MIN_EVENTS;
    }

    // ------------------------------------------------------------------ chronologie du match

    public static function timelineFor(int $id): array
    {
        $key = $id . (I18n::isEn() ? '-en' : '');
        if (!isset(self::$timelines[$key])) {
            $doc = Fiches::get($id);
            self::$timelines[$key] = $doc && ($doc['type'] ?? '') === 'match' ? self::timeline(Fiche::localizeDoc($doc))
                : ['events' => [], 'end' => 0, 'final' => null, 'pens' => null, 'aet' => false, 'marks' => []];
        }
        return self::$timelines[$key];
    }

    /**
     * Événements datés en secondes depuis le coup d'envoi : temps forts, buts (avec le score et
     * le camp), remplacements, cartons, mi-temps, prolongation, tirs au but, fin. Une minute de
     * match = une minute réelle ; 15 minutes de pause à la mi-temps, 5 avant la prolongation.
     * @return array{events:list<array>,end:int,final:?array,pens:?array,aet:bool,marks:array}
     */
    public static function timeline(array $doc): array
    {
        $m = (array) ($doc['match'] ?? []);
        $home = (string) ($m['home']['name'] ?? '');
        $away = (string) ($m['away']['name'] ?? '');
        $final = isset($m['score']['home'], $m['score']['away']) && is_numeric($m['score']['home']) && is_numeric($m['score']['away'])
            ? [(int) $m['score']['home'], (int) $m['score']['away']] : null;
        $pens = isset($m['score']['pens']['home'], $m['score']['pens']['away']) ? [(int) $m['score']['pens']['home'], (int) $m['score']['pens']['away']] : null;

        // 1. Ce qui est daté : temps forts, buteurs, remplacements, cartons.
        $hl = [];
        foreach ($m['highlights'] ?? [] as $h) {
            if (!($x = self::minute((string) ($h['minute'] ?? '')))) {
                continue;
            }
            $goal = !empty($h['goal']);
            $score = $goal && preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', (string) ($h['score'] ?? ''), $sc) ? [(int) $sc[1], (int) $sc[2]] : null;
            $hl[] = ['m' => $x, 'goal' => $goal, 'text' => self::plainText((string) ($h['text'] ?? '')), 'score' => $score];
        }
        $text = self::textGoals($m, $home, $away);
        $ins = $outs = $cards = [];
        foreach (Fiche::lineupRows($doc, $m['lineup']['rows'] ?? []) as $r) {
            $name = ($r['position'] ?? '') === 'E' ? '' : self::displayName($r);
            if ($name === '') {
                continue;
            }
            if ($x = self::minute((string) ($r['sub_in'] ?? ''))) {
                $ins[] = ['m' => $x, 'who' => $name, 'href' => $r['href'] ?? null];
            }
            if ($x = self::minute((string) ($r['sub_out'] ?? ''))) {
                $outs[] = ['m' => $x, 'who' => $name];
            }
            foreach (['yellow', 'red'] as $k) {
                foreach ((array) ($r[$k] ?? []) as $c) {
                    if ($x = self::minute((string) $c)) {
                        $cards[] = ['m' => $x, 'type' => $k, 'who' => $name, 'href' => $r['href'] ?? null];
                    }
                }
            }
        }

        // 2. Prolongation ? (indiquée, ou tirs au but avec des minutes au-delà de la 90e)
        $dated = array_merge($hl, $text, $ins, $outs, $cards);
        $plain = 0;
        foreach ($dated as $e) {
            if ($e['m'][1] === 0) {
                $plain = max($plain, $e['m'][0]);
            }
        }
        $aet = !empty($m['score']['aet']) || stripos((string) ($m['score']['extra'] ?? ''), 'a.p') !== false || ($pens !== null && $plain > 90);
        // Au-delà de la fin du temps de jeu : temps additionnel (« 93 » = 90+3).
        $norm = function (array $x) use ($aet): array {
            [$min, $ex] = $x;
            $cap = $aet ? 120 : 90;
            return $min > $cap ? [$cap, $min - $cap + $ex] : [$min, $ex];
        };
        $x1 = $x2 = $x3 = 0;
        foreach ($dated as $e) {
            [$min, $ex] = $norm($e['m']);
            if ($min <= 45) {
                $x1 = max($x1, $ex);
            } elseif ($min <= 90) {
                $x2 = max($x2, $ex);
            } else {
                $x3 = max($x3, $ex);
            }
        }
        $h1 = (45 + $x1) * 60;
        $k2 = $h1 + 15 * 60;
        $h2 = $k2 + (45 + $x2) * 60;
        $k3 = $h2 + 5 * 60;
        $h3 = $aet ? $k3 + (30 + $x3) * 60 : $h2;
        $tab = $pens ? $h3 + 3 * 60 : null;
        $end = $pens ? $tab + 8 * 60 : $h3;
        $at = function (array $x) use ($norm, $k2, $k3): int {
            [$min, $ex] = $norm($x);
            $t = match (true) {
                $min <= 45 => ($min - 0.5 + $ex) * 60,
                $min <= 90 => $k2 + ($min - 45.5 + $ex) * 60,
                default => $k3 + ($min - 90.5 + $ex) * 60,
            };
            return max(5, (int) round($t));
        };
        $label = function (array $x) use ($norm): string {
            [$min, $ex] = $norm($x);
            return $min . ($ex ? '+' . $ex : '');
        };

        // 3. Les buts : temps forts si leurs scores mènent au score final, sinon ligne des buteurs.
        $total = $final ? $final[0] + $final[1] : null;
        $hlGoals = array_values(array_filter($hl, fn ($e) => $e['goal']));
        $mode = $hlGoals ? 'hl' : 'text';
        $flip = false;
        if ($total !== null) {
            $complete = count($hlGoals) === $total && !in_array(null, array_column($hlGoals, 'score'), true);
            $last = $hlGoals ? end($hlGoals)['score'] : [0, 0];
            if ($complete && $last === $final) {
                $mode = 'hl';
            } elseif ($complete && $last === [$final[1], $final[0]]) {
                $mode = 'hl';
                $flip = true; // score noté du point de vue de Sochaux
            } elseif (count($text) === $total) {
                $mode = 'text';
            }
        }
        $ev = [['t' => 0, 'type' => 'kickoff']];
        $goals = [];
        $usedText = [];
        foreach ($hl as $e) {
            if ($e['goal'] && $mode === 'hl') {
                // Le buteur et son camp, si la ligne des buteurs les donne pour cette minute.
                $who = null;
                foreach ($text as $i => $g) {
                    if (!isset($usedText[$i]) && $g['m'] === $e['m']) {
                        $usedText[$i] = true;
                        $who = $g;
                        break;
                    }
                }
                $sc = $e['score'] && $flip ? [$e['score'][1], $e['score'][0]] : $e['score'];
                $goals[] = ['t' => $at($e['m']), 'min' => $label($e['m']), 'text' => $e['text'], 'score' => $sc, 'side' => $who['side'] ?? null, 'who' => $who['who'] ?? ''];
            } else {
                // En mode « buteurs », le récit d'un but reste un temps fort, repris par le but de la même minute.
                $ev[] = ['t' => $at($e['m']), 'min' => $label($e['m']), 'type' => 'action', 'text' => $e['text'], '_m' => $e['goal'] ? $e['m'] : null];
            }
        }
        if ($mode === 'text') {
            foreach ($text as $g) {
                $story = '';
                foreach ($ev as $i => $x) {
                    if (($x['_m'] ?? null) === $g['m']) {
                        $story = $x['text'];
                        unset($ev[$i]);
                        break;
                    }
                }
                $goals[] = ['t' => $at($g['m']), 'min' => $label($g['m']), 'text' => $story, 'score' => null, 'side' => $g['side'], 'who' => $g['who']];
            }
        }
        foreach ($ev as &$x) {
            unset($x['_m']);
        }
        unset($x);
        // Score après chaque but, et camp du buteur.
        usort($goals, fn ($a, $b) => $a['t'] <=> $b['t']);
        $cur = [0, 0];
        foreach ($goals as $g) {
            if ($g['score'] !== null) {
                $g['side'] ??= $g['score'][0] > $cur[0] ? 0 : ($g['score'][1] > $cur[1] ? 1 : null);
                $cur = $g['score'];
            } elseif ($g['side'] !== null) {
                $cur[$g['side']]++;
                $g['score'] = $cur;
            } else {
                $g['score'] = $cur;
            }
            $text = trim((string) preg_replace('/\s*\(\s*\d+\s*-\s*\d+\s*\)\s*\.?\s*$/u', '', $g['text'])); // « … (1-0) » : le score est déjà affiché
            $ev[] = ['t' => $g['t'], 'min' => $g['min'], 'type' => 'goal', 'text' => $text, 'who' => $g['who'], 'side' => $g['side'], 'score' => $g['score']];
        }

        // 4. Remplacements : l'entrant et le sortant de la même minute (à deux minutes près).
        // Une sortie sans entrée est le plus souvent une erreur de saisie : elle n'est pas annoncée.
        foreach ([0, 1, 2] as $gap) {
            foreach ($ins as $k => $in) {
                if (isset($in['out'])) {
                    continue;
                }
                foreach ($outs as $i => $o) {
                    if ($o['m'][1] === $in['m'][1] && abs($o['m'][0] - $in['m'][0]) === $gap) {
                        $ins[$k]['out'] = $o['who'];
                        unset($outs[$i]);
                        break;
                    }
                }
            }
        }
        foreach ($ins as $in) {
            $ev[] = ['t' => $at($in['m']), 'min' => $label($in['m']), 'type' => 'sub', 'who' => $in['who'], 'out' => $in['out'] ?? null, 'href' => $in['href']];
        }
        foreach ($cards as $c) {
            $ev[] = ['t' => $at($c['m']), 'min' => $label($c['m']), 'type' => $c['type'], 'who' => $c['who'], 'href' => $c['href']];
        }

        // 5. Les grands moments du match.
        $ev[] = ['t' => $h1, 'type' => 'halftime'];
        $ev[] = ['t' => $k2, 'type' => 'kickoff2'];
        if ($aet) {
            $ev[] = ['t' => $h2, 'type' => 'fulltime90'];
            $ev[] = ['t' => $k3, 'type' => 'extratime'];
        }
        if ($pens) {
            $ev[] = ['t' => $tab, 'type' => 'pens'];
        }
        $ev[] = ['t' => $end, 'type' => 'fulltime', 'score' => $final, 'pens' => $pens];
        $order = ['kickoff' => 0, 'action' => 1, 'goal' => 2, 'sub' => 3, 'yellow' => 4, 'red' => 4, 'halftime' => 5, 'kickoff2' => 6, 'fulltime90' => 7, 'extratime' => 8, 'pens' => 9, 'fulltime' => 10];
        usort($ev, fn ($a, $b) => $a['t'] <=> $b['t'] ?: ($order[$a['type']] ?? 5) <=> ($order[$b['type']] ?? 5));
        return ['events' => array_values($ev), 'end' => $end, 'final' => $final, 'pens' => $pens, 'aet' => $aet,
            'marks' => ['halftime' => $h1, 'kickoff2' => $k2, 'fulltime90' => $h2, 'extratime' => $aet ? $k3 : null, 'et_end' => $h3, 'pens' => $tab, 'end' => $end]];
    }

    /** « 37 », « 45+2 », « 90 + 3' » → [minute, temps additionnel] ; null si illisible. */
    public static function minute(string $s): ?array
    {
        // « 90+2 », « 90+2' » ou « 90'+2 »
        if (!preg_match('/^\s*(\d{1,3})\s*[\'’]?\s*(?:\+\s*(\d{1,2}))?\s*[\'’]?\s*$/u', $s, $x) || (int) $x[1] > 130) {
            return null;
        }
        return [(int) $x[1], (int) ($x[2] ?? 0)];
    }

    /** Buts de la ligne des buteurs (« Sauzée 37' et 61' » pour Sochaux), avec le camp (0 domicile, 1 extérieur). */
    public static function textGoals(array $m, string $home, string $away): array
    {
        $out = [];
        foreach ($m['goals'] ?? [] as $g) {
            $team = (string) ($g['team'] ?? '');
            $side = self::same($team, $home) ? 0 : (self::same($team, $away) ? 1 : null);
            if ($side === null) {
                continue;
            }
            $txt = (string) preg_replace('/\([^)]*\)/u', ' ', (string) ($g['scorers'] ?? ''));
            preg_match_all("/([^,;0-9'’]*?)\s*(\d{1,3}\s*['’]\s*\+\s*\d{1,2}(?!\d)|\d{1,3}(?:\s*\+\s*\d{1,2})?\s*(?=['’]))['’]?/u", $txt, $all, PREG_SET_ORDER);
            $who = '';
            foreach ($all as $x) {
                $name = trim((string) preg_replace('/^(?:et|and|puis)\b\s*/iu', '', trim($x[1])), " .:-–\t");
                if ($name !== '') {
                    $who = $name;
                }
                if ($mm = self::minute($x[2])) {
                    $out[] = ['m' => $mm, 'side' => $side, 'who' => $who];
                }
            }
        }
        return $out;
    }

    /** Nom affiché d'une ligne de composition : celui de la fiche du joueur s'il est mieux accentué. */
    private static function displayName(array $r): string
    {
        $d = trim((string) ($r['display'] ?? $r['name'] ?? ''));
        $s = !empty($r['pid']) ? Index::get((int) $r['pid']) : null;
        $p = trim((string) ($s['p']['name'] ?? ''));
        if ($p !== '' && Names::personKey($p) === Names::personKey($d)) {
            $acc = fn (string $x) => strlen($x) - mb_strlen($x);
            return $acc($p) >= $acc($d) ? $p : $d;
        }
        return $d;
    }

    private static function same(string $a, string $b): bool
    {
        $n = fn ($s) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
        return $a !== '' && $b !== '' && ($n($a) === $n($b) || str_contains($n($a), $n($b)) || str_contains($n($b), $n($a)));
    }

    private static function plainText(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', plain($html)));
    }

    // ------------------------------------------------------------------ public : réactions et spectateurs

    private static function file(int $id, string $date): string
    {
        return self::$dir . "/$id-$date.json";
    }

    /** Signal de présence (toutes les 30 s ; oublié après 75 s), identifiant aléatoire tiré par le navigateur. */
    public static function presence(int $id, string $date, string $token): array
    {
        $token = preg_match('/^[a-z0-9]{8,24}$/', $token) ? $token : '';
        $now = time();
        return self::counts(JsonStore::update(self::file($id, $date), function ($d) use ($token, $now) {
            $d = is_array($d) ? $d : [];
            $p = array_filter((array) ($d['presence'] ?? []), fn ($t) => (int) $t > $now - 75);
            if ($token !== '' && (isset($p[$token]) || count($p) < 5000)) {
                $p[$token] = $now;
            }
            $d['presence'] = $p;
            $d['peak'] = max((int) ($d['peak'] ?? 0), count($p));
            return $d;
        }, []));
    }

    /** Réaction pendant le direct : ⚽ 👏 😱. */
    public static function react(int $id, string $date, string $kind): array
    {
        return self::counts(JsonStore::update(self::file($id, $date), function ($d) use ($kind) {
            $d = is_array($d) ? $d : [];
            if (isset(self::REACTIONS[$kind])) {
                $d['reactions'][$kind] = (int) ($d['reactions'][$kind] ?? 0) + 1;
            }
            return $d;
        }, []));
    }

    /** « J'y étais ! » : supporters présents au stade ce jour-là (par match, direct ou non). */
    public static function etais(int $id): int
    {
        $d = JsonStore::read(self::$dir . '/etais.json', []);
        return (int) (is_array($d) ? ($d[$id] ?? 0) : 0);
    }

    public static function addEtais(int $id): int
    {
        $n = 0;
        JsonStore::update(self::$dir . '/etais.json', function ($d) use ($id, &$n) {
            $d = is_array($d) ? $d : [];
            $d[$id] = $n = (int) ($d[$id] ?? 0) + 1;
            return $d;
        }, []);
        return $n;
    }

    /** Compteurs publics d'un direct : spectateurs connectés, pic, réactions. */
    public static function counts(?array $d): array
    {
        $d ??= [];
        $now = time();
        $r = [];
        foreach (array_keys(self::REACTIONS) as $k) {
            $r[$k] = (int) ($d['reactions'][$k] ?? 0);
        }
        return [
            'viewers' => count(array_filter((array) ($d['presence'] ?? []), fn ($t) => (int) $t > $now - 75)),
            'peak' => (int) ($d['peak'] ?? 0),
            'reactions' => $r,
        ];
    }

    public static function stats(int $id, string $date): array
    {
        $d = JsonStore::read(self::file($id, $date), []);
        return self::counts(is_array($d) ? $d : []);
    }
}
