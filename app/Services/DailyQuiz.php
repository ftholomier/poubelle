<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Défi du jour : 10 questions en solo sur le téléphone, les mêmes pour tout le monde dans la
 * journée, une seule tentative par jour et par compte (le compte du carnet du supporter, voir
 * Carnet et QuizChampionship pour le pseudo). Classements du jour, du mois et de la saison,
 * séparés du championnat du club-house. On peut jouer sans compte, mais sans classement.
 *
 * Les questions viennent de QuizLive::questions() (quiz du site + fiches de match, sans IA),
 * tirées une fois par jour avec une graine fixe (même tirage en français et en anglais) et
 * gardées dans storage/defi/{date}.json avec les résultats du jour. Les bonnes réponses ne
 * quittent jamais le serveur avant que le joueur ait répondu ; le temps est mesuré côté serveur.
 *
 * Fichiers : storage/defi/{date}.json (questions par langue, résultats {id: {pts, good, ms, at,
 * grid}}), storage/defi/{date}/{joueur}.json (partie en cours), storage/defi/totals.json
 * (mois, saisons, séries de jours d'affilée).
 */
final class DailyQuiz
{
    public static string $dir = STORAGE_PATH . '/defi';
    public const COUNT = 10;
    public const DURATION = 20;
    private const GRACE = 800;

    public static function today(?int $ts = null): string
    {
        return date('Y-m-d', $ts ?? time());
    }

    private static function now(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private static function dayFile(string $date): string
    {
        return self::$dir . '/' . $date . '.json';
    }

    private static function playFile(string $date, string $key): string
    {
        return self::$dir . '/' . $date . '/' . $key . '.json';
    }

    private static function totalsFile(): string
    {
        return self::$dir . '/totals.json';
    }

    /** Questions du jour dans la langue demandée (tirées au premier appel). @return list<array> */
    public static function questions(string $date, string $lang): array
    {
        $lang = $lang === 'en' ? 'en' : 'fr';
        JsonStore::forget(self::dayFile($date));
        $d = JsonStore::read(self::dayFile($date), []) ?: [];
        if (!empty($d['q'][$lang])) {
            return $d['q'][$lang];
        }
        $d = JsonStore::update(self::dayFile($date), function ($d) use ($date, $lang) {
            $d = is_array($d) ? $d : [];
            if (empty($d['q'][$lang])) {
                $prev = I18n::lang();
                I18n::set($lang);
                mt_srand(crc32('defi-' . $date . site_key('defi')));
                try {
                    $d['q'][$lang] = QuizLive::questions(self::COUNT, 'mix');
                } finally {
                    mt_srand();
                    I18n::set($prev);
                }
            }
            $d['results'] ??= [];
            return $d;
        }, []);
        return $d['q'][$lang];
    }

    /** Clé d'un joueur : le compte (id du carnet) ou un invité (empreinte de son jeton). */
    public static function key(?string $cid, string $guestToken = ''): ?string
    {
        if ($cid !== null && preg_match('/^[a-f0-9]{16}$/', $cid)) {
            return $cid;
        }
        return preg_match('/^[a-f0-9]{32}$/', $guestToken) ? 'g' . substr(hash('sha256', $guestToken), 0, 20) : null;
    }

    public static function play(string $date, string $key): ?array
    {
        JsonStore::forget(self::playFile($date, $key));
        $p = JsonStore::read(self::playFile($date, $key), null);
        return is_array($p) ? $p : null;
    }

    /** Résultat du jour d'un compte (déjà joué), ou null. */
    public static function result(string $date, string $cid): ?array
    {
        JsonStore::forget(self::dayFile($date));
        return (JsonStore::read(self::dayFile($date), []) ?: [])['results'][$cid] ?? null;
    }

    /**
     * Commence la partie du jour (ou la reprend). Un compte qui a déjà joué aujourd'hui retrouve
     * son résultat. @return array état de la partie (voir state())
     */
    public static function start(string $date, string $key, string $lang): array
    {
        $qs = self::questions($date, $lang);
        $now = self::now();
        JsonStore::update(self::playFile($date, $key), function ($p) use ($now, $lang, $key) {
            if (is_array($p)) {
                return $p;
            }
            return ['key' => $key, 'lang' => $lang === 'en' ? 'en' : 'fr', 'i' => 0, 't0' => $now, 'answers' => [], 'score' => 0, 'done' => false];
        }, null);
        return self::state($date, $key);
    }

    /**
     * Compte ouvert sur un appareil où l'on a déjà joué aujourd'hui en invité : il reprend cette
     * partie, hors classement (les bonnes réponses ont déjà été vues). Sans cela, il suffirait de
     * jouer en invité puis de créer un compte pour rejouer en connaissant les réponses.
     */
    public static function adopt(string $date, string $guestKey, string $cid): void
    {
        if ($guestKey === $cid || self::play($date, $cid) || self::result($date, $cid)) {
            return;
        }
        $g = self::play($date, $guestKey);
        if (!$g) {
            return;
        }
        JsonStore::update(self::playFile($date, $cid), fn ($p) => is_array($p) ? $p : ['key' => $cid, 'unranked' => true] + $g, null);
    }

    /** Partie de la veille encore en cours (commencée avant minuit) : on la laisse finir. */
    public static function unfinished(string $date, string $key): bool
    {
        $p = self::play($date, $key);
        return $p !== null && !$p['done'];
    }

    /** État vu par le joueur : question en cours (sans la bonne réponse), ou verdict, ou fin. */
    public static function state(string $date, string $key): array
    {
        $p = self::play($date, $key);
        if (!$p) {
            return ['phase' => 'intro'];
        }
        $qs = self::questions($date, $p['lang']);
        $i = (int) $p['i'];
        $out = ['n' => count($qs), 'i' => $i, 'score' => (int) $p['score'], 'duration' => self::DURATION, 'unranked' => !empty($p['unranked']),
            'grid' => array_map(fn ($a) => $a[2] > 0 ? 1 : 0, $p['answers'])];
        if ($p['done']) {
            return $out + ['phase' => 'end', 'good' => array_sum($out['grid'])];
        }
        $q = $qs[$i];
        $out['q'] = ['q' => $q['q'], 'a' => $q['a']];
        if (isset($p['answers'][$i])) {
            [$choice, $ms, $pts] = $p['answers'][$i];
            return $out + ['phase' => 'verdict', 'choice' => $choice, 'c' => (int) $q['c'], 'fact' => (string) ($q['fact'] ?? ''), 'pts' => $pts, 'last' => $i >= count($qs) - 1];
        }
        return $out + ['phase' => 'question', 'left' => max(0, (int) $p['t0'] + self::DURATION * 1000 - self::now())];
    }

    /** Répond à la question en cours (-1 : temps écoulé). Le temps est celui du serveur. */
    public static function answer(string $date, string $key, int $choice): array
    {
        $now = self::now();
        JsonStore::update(self::playFile($date, $key), function ($p) use ($date, $choice, $now) {
            if (!is_array($p) || $p['done'] || isset($p['answers'][(int) $p['i']])) {
                return $p;
            }
            $q = self::questions($date, $p['lang'])[(int) $p['i']];
            $ms = max(0, $now - (int) $p['t0']);
            $ok = $choice >= 0 && $choice === (int) $q['c'] && $ms <= self::DURATION * 1000 + self::GRACE;
            $pts = $ok ? QuizLive::points(min($ms, self::DURATION * 1000), self::DURATION) : 0;
            $p['answers'][(int) $p['i']] = [$choice < 0 || $choice >= count($q['a']) ? -1 : $choice, $ms, $pts];
            $p['score'] += $pts;
            return $p;
        }, null);
        return self::state($date, $key);
    }

    /**
     * Question suivante, ou fin de partie. Fin : le résultat d'un compte entre aux classements
     * (une seule fois). @return array état, avec 'final' en fin de partie
     */
    public static function next(string $date, string $key, ?string $cid): array
    {
        $now = self::now();
        $finished = false;
        $p = JsonStore::update(self::playFile($date, $key), function ($p) use ($date, $now, &$finished) {
            if (!is_array($p) || $p['done']) {
                return $p;
            }
            $n = count(self::questions($date, $p['lang']));
            $i = (int) $p['i'];
            if (!isset($p['answers'][$i])) {
                if ($now < (int) $p['t0'] + self::DURATION * 1000) {
                    return $p; // la question court encore
                }
                $p['answers'][$i] = [-1, $now - (int) $p['t0'], 0];
            }
            if ($i >= $n - 1) {
                $p['done'] = true;
                $finished = true;
            } else {
                $p['i'] = $i + 1;
                $p['t0'] = $now;
            }
            return $p;
        }, null);
        if ($finished && $cid !== null && $key === $cid && empty($p['unranked'])) {
            self::record($date, $cid, $p);
        }
        return self::state($date, $key);
    }

    /** Résultat d'un compte : classement du jour, du mois, de la saison, série. */
    private static function record(string $date, string $cid, array $p): void
    {
        $good = count(array_filter($p['answers'], fn ($a) => $a[2] > 0));
        $ms = array_sum(array_map(fn ($a) => min((int) $a[1], self::DURATION * 1000), $p['answers']));
        $grid = implode('', array_map(fn ($a) => $a[2] > 0 ? '1' : '0', $p['answers']));
        $new = false;
        JsonStore::update(self::dayFile($date), function ($d) use ($cid, $p, $good, $ms, $grid, &$new) {
            $d = is_array($d) ? $d : [];
            if (!isset($d['results'][$cid])) {
                $d['results'][$cid] = ['pts' => (int) $p['score'], 'good' => $good, 'ms' => $ms, 'at' => date('H:i'), 'grid' => $grid];
                $new = true;
            }
            return $d;
        }, []);
        if (!$new) {
            return;
        }
        $month = substr($date, 0, 7);
        $season = QuizChampionship::season((int) strtotime($date . ' 12:00'));
        JsonStore::update(self::totalsFile(), function ($t) use ($cid, $p, $good, $date, $month, $season) {
            $t = is_array($t) ? $t : [];
            foreach ([['months', $month], ['seasons', $season]] as [$k, $period]) {
                $s = $t[$k][$period][$cid] ?? ['pts' => 0, 'days' => 0, 'perfect' => 0, 'best' => 0];
                $s['pts'] += (int) $p['score'];
                $s['days']++;
                $s['perfect'] += $good === count($p['answers']) ? 1 : 0;
                $s['best'] = max($s['best'], (int) $p['score']);
                $t[$k][$period][$cid] = $s;
            }
            $st = $t['streaks'][$cid] ?? ['cur' => 0, 'best' => 0, 'last' => ''];
            $st['cur'] = $st['last'] === date('Y-m-d', strtotime($date . ' -1 day')) ? $st['cur'] + 1 : 1;
            $st['best'] = max($st['best'], $st['cur']);
            $st['last'] = $date;
            $t['streaks'][$cid] = $st;
            return $t;
        }, []);
        QuizChampionship::touch($cid);
    }

    /** Série de jours d'affilée d'un compte (0 si elle est rompue). */
    public static function streak(string $cid, ?string $today = null): array
    {
        $today ??= self::today();
        JsonStore::forget(self::totalsFile());
        $st = (JsonStore::read(self::totalsFile(), []) ?: [])['streaks'][$cid] ?? ['cur' => 0, 'best' => 0, 'last' => ''];
        $alive = in_array($st['last'], [$today, date('Y-m-d', strtotime($today . ' -1 day'))], true);
        return ['cur' => $alive ? (int) $st['cur'] : 0, 'best' => (int) $st['best']];
    }

    /**
     * Classement : $period 'jour' (date), 'mois' (Y-m) ou 'saison' (2026-2027).
     * @return list<array{id:string,pseudo:string,pts:int,rank:int,good?:int,ms?:int,days?:int,grid?:string}>
     */
    public static function ranking(string $period, string $value): array
    {
        $players = QuizChampionship::data()['players'];
        $rows = [];
        if ($period === 'jour') {
            JsonStore::forget(self::dayFile($value));
            foreach ((JsonStore::read(self::dayFile($value), []) ?: [])['results'] ?? [] as $id => $r) {
                if (QuizChampionship::listed($players[$id] ?? null, (string) $id)) {
                    $rows[] = ['id' => (string) $id, 'pseudo' => (string) $players[$id]['pseudo']] + $r;
                }
            }
            usort($rows, fn ($a, $b) => [$b['pts'], $a['ms'], $a['pseudo']] <=> [$a['pts'], $b['ms'], $b['pseudo']]);
            $key = fn ($r) => [$r['pts'], $r['ms']];
        } else {
            JsonStore::forget(self::totalsFile());
            $t = JsonStore::read(self::totalsFile(), []) ?: [];
            foreach ($t[$period === 'mois' ? 'months' : 'seasons'][$value] ?? [] as $id => $r) {
                if (QuizChampionship::listed($players[$id] ?? null, (string) $id)) {
                    $rows[] = ['id' => (string) $id, 'pseudo' => (string) $players[$id]['pseudo']] + $r;
                }
            }
            usort($rows, fn ($a, $b) => [$b['pts'], $b['days'], $a['pseudo']] <=> [$a['pts'], $a['days'], $b['pseudo']]);
            $key = fn ($r) => [$r['pts'], $r['days']];
        }
        $prev = null;
        $rank = 0;
        foreach ($rows as $k => $r) {
            if ($key($r) !== $prev) {
                $rank = $k + 1;
                $prev = $key($r);
            }
            $rows[$k]['rank'] = $rank;
        }
        return $rows;
    }

    public static function rankOf(string $period, string $value, string $cid): ?array
    {
        foreach (self::ranking($period, $value) as $r) {
            if ($r['id'] === $cid) {
                return $r;
            }
        }
        return null;
    }

    /** Nombre de joueurs classés du jour. */
    public static function players(string $date): int
    {
        JsonStore::forget(self::dayFile($date));
        return count((JsonStore::read(self::dayFile($date), []) ?: [])['results'] ?? []);
    }

    /** Compte supprimé : effacé des résultats et des classements. */
    public static function forget(string $cid): void
    {
        foreach (glob(self::$dir . '/????-??-??.json') ?: [] as $f) {
            if (str_contains((string) file_get_contents($f), '"' . $cid . '"')) {
                JsonStore::update($f, function ($d) use ($cid) {
                    unset($d['results'][$cid]);
                    return $d;
                }, []);
            }
        }
        if (is_file(self::totalsFile())) {
            JsonStore::update(self::totalsFile(), function ($t) use ($cid) {
                foreach (['months', 'seasons'] as $k) {
                    foreach ($t[$k] ?? [] as $p => $x) {
                        unset($t[$k][$p][$cid]);
                    }
                }
                unset($t['streaks'][$cid]);
                return $t;
            }, []);
        }
    }

    /** Ménage du cron : parties en cours de plus de 2 jours (les résultats restent). */
    public static function purge(): int
    {
        $n = 0;
        $limit = date('Y-m-d', strtotime('-2 days'));
        foreach (glob(self::$dir . '/????-??-??', GLOB_ONLYDIR) ?: [] as $d) {
            if (basename($d) < $limit) {
                foreach (glob($d . '/*') ?: [] as $f) {
                    @unlink($f);
                    $n++;
                }
                @rmdir($d);
            }
        }
        return $n;
    }
}
