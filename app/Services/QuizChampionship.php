<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Championnat du club-house : classement par saison (1er août – 31 juillet) des joueurs du quiz
 * en direct qui ont un compte supporter (le carnet du supporter : e-mail et lien sécurisé, voir
 * Carnet). Les invités jouent, mais ne comptent pas.
 *
 * Chaque partie comptée (au moins 3 joueurs, pas « amicale ») rapporte des points selon le rang
 * dans la partie : 10, 8, 6, 5, 4, 3, 2, puis 1 point de participation pour tous. Ainsi une
 * partie de 6 questions pèse autant qu'une partie de 30.
 *
 * Un seul fichier : storage/quiz-championnat.json
 *   players : {id du carnet: {pseudo, since, last, banned}}
 *   seasons : {"2026-2027": {games: {clé de partie: {date, players, counted}}, scores: {id: {pts, games, wins, podiums, best}}}}
 */
final class QuizChampionship
{
    public static string $file = STORAGE_PATH . '/quiz-championnat.json';
    /** Points selon le rang dans la partie (en plus du point de participation). */
    public const SCALE = [10, 8, 6, 5, 4, 3, 2];
    /** Joueurs au minimum (invités compris) pour qu'une partie compte. */
    public const MIN_PLAYERS = 3;

    public static function data(): array
    {
        JsonStore::forget(self::$file);
        $d = JsonStore::read(self::$file, []);
        return (is_array($d) ? $d : []) + ['players' => [], 'seasons' => []];
    }

    /** Saison d'une date (« 2026-2027 » du 1er août 2026 au 31 juillet 2027). */
    public static function season(?int $ts = null): string
    {
        $ts ??= time();
        $y = (int) date('Y', $ts);
        return (int) date('n', $ts) >= 8 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
    }

    /** @return list<string> saisons ayant au moins un score, la plus récente d'abord */
    public static function seasons(): array
    {
        $s = array_keys(array_filter(self::data()['seasons'], fn ($x) => !empty($x['scores'])));
        rsort($s);
        return $s;
    }

    public static function player(string $id): ?array
    {
        return self::data()['players'][$id] ?? null;
    }

    public static function has(string $id): bool
    {
        return isset(self::data()['players'][$id]);
    }

    private static function norm(string $pseudo): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($pseudo)));
    }

    /** Pseudo du championnat (unique, 2 à 20 caractères). @return string|null message d'erreur */
    public static function setPseudo(string $id, string $pseudo): ?string
    {
        $pseudo = QuizLive::cleanName($pseudo);
        if (mb_strlen($pseudo) < 2) {
            return t('Choisissez un pseudo de 2 à 20 caractères.');
        }
        $err = null;
        JsonStore::update(self::$file, function ($d) use ($id, $pseudo, &$err) {
            $d = (is_array($d) ? $d : []) + ['players' => [], 'seasons' => []];
            foreach ($d['players'] as $pid => $p) {
                if ((string) $pid !== $id && self::norm((string) $p['pseudo']) === self::norm($pseudo)) {
                    $err = t('Ce pseudo est déjà pris au championnat : choisissez-en un autre.');
                    return $d;
                }
            }
            $d['players'][$id] = ['pseudo' => $pseudo, 'since' => $d['players'][$id]['since'] ?? date('Y-m-d'),
                'last' => $d['players'][$id]['last'] ?? date('Y-m-d'), 'banned' => $d['players'][$id]['banned'] ?? false];
            return $d;
        }, []);
        return $err;
    }

    /** Le pseudo est-il libre (pour ce compte) ? */
    public static function free(string $pseudo, string $id = ''): bool
    {
        foreach (self::data()['players'] as $pid => $p) {
            if ((string) $pid !== $id && self::norm((string) $p['pseudo']) === self::norm($pseudo)) {
                return false;
            }
        }
        return true;
    }

    /** Back-office : exclure du classement (ou réintégrer). */
    public static function ban(string $id, bool $on): void
    {
        JsonStore::update(self::$file, function ($d) use ($id, $on) {
            if (isset($d['players'][$id])) {
                $d['players'][$id]['banned'] = $on;
            }
            return $d;
        }, []);
    }

    /** Back-office : pseudo déplacé remplacé par « Joueur 1234 » (le joueur peut en choisir un autre). */
    public static function resetPseudo(string $id): void
    {
        JsonStore::update(self::$file, function ($d) use ($id) {
            if (isset($d['players'][$id])) {
                $d['players'][$id]['pseudo'] = 'Joueur ' . substr((string) hexdec(substr($id, 0, 6)), -4);
            }
            return $d;
        }, []);
    }

    /** Compte supporter supprimé : il disparaît du championnat (toutes saisons). */
    public static function forget(string $id): void
    {
        if (!is_file(self::$file)) {
            return;
        }
        JsonStore::update(self::$file, function ($d) use ($id) {
            if (!is_array($d)) {
                return $d;
            }
            unset($d['players'][$id]);
            foreach ($d['seasons'] ?? [] as $s => $x) {
                unset($d['seasons'][$s]['scores'][$id]);
            }
            return $d;
        }, []);
    }

    /**
     * Classement d'une saison : [{id, pseudo, pts, games, wins, podiums, best, rank}], exclus retirés.
     * Départage : points, victoires, podiums, puis moins de parties jouées.
     */
    public static function ranking(?string $season = null, ?array $d = null): array
    {
        $d ??= self::data();
        $season ??= self::season();
        $rows = [];
        foreach ($d['seasons'][$season]['scores'] ?? [] as $id => $s) {
            $p = $d['players'][$id] ?? null;
            if ($p && empty($p['banned'])) {
                $rows[] = ['id' => (string) $id, 'pseudo' => (string) $p['pseudo']] + $s;
            }
        }
        usort($rows, fn ($a, $b) => [$b['pts'], $b['wins'], $b['podiums'], $a['games'], $a['pseudo']] <=> [$a['pts'], $a['wins'], $a['podiums'], $b['games'], $b['pseudo']]);
        $prev = null;
        $rank = 0;
        foreach ($rows as $k => $r) {
            $key = [$r['pts'], $r['wins'], $r['podiums']];
            if ($key !== $prev) {
                $rank = $k + 1;
                $prev = $key;
            }
            $rows[$k]['rank'] = $rank;
        }
        return $rows;
    }

    /** Rang d'un compte dans la saison, ou null. */
    public static function rankOf(string $id, ?string $season = null): ?array
    {
        foreach (self::ranking($season) as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        return null;
    }

    /** Points d'un rang de partie. */
    public static function points(int $rank): int
    {
        return (self::SCALE[$rank - 1] ?? 0) + 1;
    }

    /**
     * Enregistre une partie terminée (une seule fois par partie). @return array{counted:bool,moves:array<string,array{0:?int,1:?int}>}
     * moves : pour chaque compte de la partie, son rang au championnat avant et après.
     */
    public static function record(array $g): array
    {
        $key = $g['code'] . '-' . $g['created'];
        $ranks = QuizLive::ranking($g);
        $mine = [];
        foreach ($ranks as $r) {
            $cid = (string) ($g['players'][$r[0]]['cid'] ?? '');
            if ($cid !== '' && !isset($mine[$cid])) {
                $mine[$cid] = $r[3];
            }
        }
        if (!empty($g['friendly']) || count($g['players']) < self::MIN_PLAYERS || !$mine) {
            return ['counted' => false, 'moves' => []];
        }
        $season = self::season((int) $g['created']);
        $moves = [];
        $done = false;
        JsonStore::update(self::$file, function ($d) use ($key, $season, $mine, $g, &$moves, &$done) {
            $d = (is_array($d) ? $d : []) + ['players' => [], 'seasons' => []];
            if (isset($d['seasons'][$season]['games'][$key])) {
                $done = true;
                return $d;
            }
            $before = [];
            foreach (self::ranking($season, $d) as $r) {
                $before[$r['id']] = $r['rank'];
            }
            $d['seasons'][$season] ??= ['games' => [], 'scores' => []];
            $counted = 0;
            foreach ($mine as $cid => $rank) {
                if (!isset($d['players'][$cid]) || !empty($d['players'][$cid]['banned'])) {
                    continue;
                }
                $s = $d['seasons'][$season]['scores'][$cid] ?? ['pts' => 0, 'games' => 0, 'wins' => 0, 'podiums' => 0, 'best' => 0];
                $s['pts'] += self::points($rank);
                $s['games']++;
                $s['wins'] += $rank === 1 ? 1 : 0;
                $s['podiums'] += $rank <= 3 ? 1 : 0;
                $s['best'] = $s['best'] ? min($s['best'], $rank) : $rank;
                $d['seasons'][$season]['scores'][$cid] = $s;
                $d['players'][$cid]['last'] = date('Y-m-d');
                $counted++;
            }
            $d['seasons'][$season]['games'][$key] = ['date' => date('Y-m-d H:i'), 'players' => count($g['players']), 'counted' => $counted];
            foreach (self::ranking($season, $d) as $r) {
                if (isset($mine[$r['id']])) {
                    $moves[$r['id']] = [$before[$r['id']] ?? null, $r['rank']];
                }
            }
            return $d;
        }, []);
        return ['counted' => !$done && $moves !== [], 'moves' => $moves];
    }

    /** Chiffres d'une saison (back-office, page publique). */
    public static function stats(?string $season = null): array
    {
        $s = self::data()['seasons'][$season ?? self::season()] ?? ['games' => [], 'scores' => []];
        return ['games' => count($s['games']), 'players' => count($s['scores'])];
    }
}
