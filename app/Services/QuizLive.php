<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Index;
use App\Data\Seeds;

/**
 * Quiz du club-house : une partie en direct façon jeu télévisé. Un grand écran (lien secret de
 * l'animateur) affiche les questions, les joueurs répondent sur leur téléphone (code à 5 chiffres
 * ou QR code), classement après chaque question et podium final.
 *
 * Une partie = storage/quizlive/{code}.json. Pas de websocket : écran et téléphones interrogent
 * l'état toutes les secondes. Le passage de la question à la réponse se calcule à la lecture
 * (temps écoulé ou tout le monde a répondu) : rien ne tourne en tâche de fond.
 *
 * Questions : la moitié tirée du quiz du site, l'autre fabriquée à partir des fiches de match
 * (score, année, adversaire, buteur), sans IA. Partie effacée 24 h après la dernière activité.
 */
final class QuizLive
{
    /** Dossier des parties (remplacé par les tests). */
    public static string $dir = STORAGE_PATH . '/quizlive';
    public const DURATIONS = [15, 20, 30];
    public const MAX_PLAYERS = 200;
    /** Délai de grâce (ms) pour une réponse partie juste avant la fin du compte à rebours. */
    private const GRACE = 600;

    private static function file(string $code): string
    {
        return self::$dir . '/' . $code . '.json';
    }

    public static function validCode(string $code): bool
    {
        return (bool) preg_match('/^\d{5}$/', $code);
    }

    private static function now(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    // ------------------------------------------------------------------ parties

    /** @return array{code:string,key:string} */
    public static function create(int $count, int $duration, string $lang, string $mix, string $by = '', bool $friendly = false): array
    {
        $count = max(3, min(30, $count));
        $duration = in_array($duration, self::DURATIONS, true) ? $duration : 20;
        $lang = $lang === 'en' ? 'en' : 'fr';
        $prev = I18n::lang();
        I18n::set($lang);
        try {
            $questions = self::questions($count, $mix);
        } finally {
            I18n::set($prev);
        }
        do {
            $code = (string) random_int(10000, 99999);
        } while (is_file(self::file($code)));
        $key = bin2hex(random_bytes(12));
        JsonStore::write(self::file($code), [
            'code' => $code, 'key' => $key, 'created' => time(), 'by' => $by, 'lang' => $lang, 'duration' => $duration, 'friendly' => $friendly,
            'questions' => $questions, 'phase' => 'lobby', 'idx' => -1, 't0' => 0, 'players' => [], 'answers' => [], 'v' => 1,
        ]);
        return ['code' => $code, 'key' => $key];
    }

    public static function get(string $code): ?array
    {
        if (!self::validCode($code)) {
            return null;
        }
        $g = JsonStore::read(self::file($code));
        return is_array($g) ? $g : null;
    }

    public static function delete(string $code): void
    {
        if (self::validCode($code)) {
            JsonStore::delete(self::file($code));
            @unlink(self::file($code) . '.lock');
        }
    }

    /** Ménage du cron : parties sans activité depuis 24 h. */
    public static function purge(): int
    {
        $n = 0;
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            if ((@filemtime($f) ?: time()) < time() - 86400) {
                @unlink($f);
                @unlink($f . '.lock');
                $n++;
            }
        }
        return $n;
    }

    public static function isHost(?array $g, string $key): bool
    {
        return $g !== null && $key !== '' && hash_equals((string) $g['key'], $key);
    }

    /** Parties en cours (back-office), les plus récentes d'abord. @return list<array> */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::$dir . '/*.json') ?: [] as $f) {
            $g = JsonStore::read($f);
            if (is_array($g) && isset($g['code'])) {
                $out[] = ['code' => $g['code'], 'key' => $g['key'], 'created' => (int) $g['created'], 'by' => (string) ($g['by'] ?? ''),
                    'lang' => $g['lang'], 'n' => count($g['questions']), 'duration' => $g['duration'], 'players' => count($g['players']),
                    'friendly' => !empty($g['friendly']), 'counted' => $g['counted'] ?? null,
                    'phase' => self::phase($g), 'idx' => (int) $g['idx']];
            }
        }
        usort($out, fn ($a, $b) => $b['created'] <=> $a['created']);
        return $out;
    }

    // ------------------------------------------------------------------ déroulé

    /** Phase réelle : une question se ferme d'elle-même (temps écoulé ou tout le monde a répondu). */
    public static function phase(array $g, ?int $now = null): string
    {
        if ($g['phase'] !== 'question') {
            return $g['phase'];
        }
        $now ??= self::now();
        if ($now >= $g['t0'] + $g['duration'] * 1000) {
            return 'reveal';
        }
        $n = count($g['players']);
        return $n > 0 && count($g['answers'][(string) $g['idx']] ?? []) >= $n ? 'reveal' : 'question';
    }

    /**
     * Bouton « Suivant » de l'animateur : salle d'attente → question → (réponse) → classement →
     * question suivante… → podium. Pendant une question, il la ferme tout de suite.
     */
    public static function next(string $code): ?array
    {
        $g = JsonStore::update(self::file($code), function ($g) {
            if (!is_array($g)) {
                return $g;
            }
            $now = self::now();
            $phase = self::phase($g, $now);
            $last = (int) $g['idx'] >= count($g['questions']) - 1;
            if ($phase === 'question' && $now < $g['t0']) {
                return $g; // double appui pendant les 3 s de lecture : la question n'est pas encore ouverte
            }
            if ($phase === 'question') {
                // Fermer la question maintenant (« tout le monde a répondu » de l'animateur).
                $g['phase'] = 'reveal';
                $g['closed'] = $now;
            } elseif ($phase === 'lobby' || $phase === 'board') {
                $g['idx']++;
                $g['phase'] = 'question';
                $g['t0'] = $now + 3000; // 3 s pour lire la question avant d'ouvrir les réponses
                unset($g['closed']);
            } elseif ($phase === 'reveal') {
                $g['phase'] = $last ? 'end' : 'board';
            }
            $g['v']++;
            return $g;
        });
        // Partie terminée : elle compte au championnat (une seule fois).
        if (is_array($g) && $g['phase'] === 'end' && !array_key_exists('counted', $g)) {
            $r = QuizChampionship::record($g);
            $g = JsonStore::update(self::file($code), function ($g) use ($r) {
                if (is_array($g)) {
                    $g['counted'] = $r['counted'];
                    $g['moves'] = $r['moves'];
                    $g['v']++;
                }
                return $g;
            });
        }
        return $g;
    }

    public static function kick(string $code, string $pid): void
    {
        JsonStore::update(self::file($code), function ($g) use ($pid) {
            if (is_array($g) && isset($g['players'][$pid])) {
                unset($g['players'][$pid]);
                foreach ($g['answers'] as $i => $a) {
                    unset($g['answers'][$i][$pid]);
                }
                $g['v']++;
            }
            return $g;
        });
    }

    /**
     * Le joueur a ouvert, sur cet appareil, le lien reçu par e-mail : sa partie compte pour son
     * compte, sous son pseudo du championnat. @return bool vrai si le joueur a été rattaché
     */
    public static function claim(string $code, string $pid, string $cid, string $pseudo): bool
    {
        $ok = false;
        JsonStore::update(self::file($code), function ($g) use ($pid, $cid, $pseudo, &$ok) {
            $p = is_array($g) ? ($g['players'][$pid] ?? null) : null;
            if (!$p || ($p['claim'] ?? null) !== $cid || $g['phase'] === 'end') {
                return $g;
            }
            foreach ($g['players'] as $k => $o) {
                if ((string) $k !== $pid && ($o['cid'] ?? null) === $cid) {
                    return $g; // ce compte joue déjà sur un autre appareil
                }
            }
            unset($g['players'][$pid]['claim']);
            $g['players'][$pid]['cid'] = $cid;
            if ($pseudo !== '') {
                $g['players'][$pid]['name'] = $pseudo;
            }
            $g['v']++;
            $ok = true;
            return $g;
        });
        return $ok;
    }

    public static function cleanName(string $name): string
    {
        $name = (string) preg_replace('/[\p{C}<>"\\\\]+/u', '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        return mb_substr($name, 0, 20);
    }

    /**
     * Rejoindre une partie. $cid : compte supporter (carnet) ouvert sur l'appareil, qui joue pour le
     * championnat sous son pseudo ; un même compte qui revient (autre appareil, page rechargée sans
     * jeton) reprend sa place. $claim : compte dont le lien vient d'être envoyé par e-mail ; la
     * partie lui sera rattachée quand il l'ouvrira sur cet appareil (voir claim()).
     * @return array{pid:string,tok:string,name:string}|string jeton du joueur, ou message d'erreur
     */
    public static function join(string $code, string $name, ?string $cid = null, ?string $claim = null): array|string
    {
        $name = self::cleanName($name);
        if (mb_strlen($name) < 2) {
            return t('Choisissez un pseudo de 2 à 20 caractères.');
        }
        $pid = bin2hex(random_bytes(5));
        $tok = bin2hex(random_bytes(12));
        $err = null;
        $final = $name;
        JsonStore::update(self::file($code), function ($g) use (&$pid, $tok, $name, $cid, $claim, &$err, &$final) {
            if (!is_array($g)) {
                $err = t('Cette partie n’existe pas ou est terminée.');
                return $g;
            }
            if ($g['phase'] === 'end') {
                $err = t('Cette partie est terminée.');
                return $g;
            }
            if ($cid !== null) {
                foreach ($g['players'] as $k => $p) {
                    if (($p['cid'] ?? null) === $cid) {
                        $pid = (string) $k; // même compte : il reprend sa place (le jeton change)
                        $final = $p['name'];
                        $g['players'][$k]['tok'] = hash('sha256', $tok);
                        $g['v']++;
                        return $g;
                    }
                }
            }
            if (count($g['players']) >= self::MAX_PLAYERS) {
                $err = t('La partie est complète.');
                return $g;
            }
            $taken = array_map(fn ($p) => mb_strtolower($p['name']), $g['players']);
            $n = 2;
            while (in_array(mb_strtolower($final), $taken, true)) {
                $final = mb_substr($name, 0, 17) . ' ' . $n++;
            }
            $g['players'][$pid] = ['name' => $final, 'score' => 0, 'tok' => hash('sha256', $tok), 'joined' => time()]
                + ($cid !== null ? ['cid' => $cid] : []) + ($claim !== null ? ['claim' => $claim] : []);
            $g['v']++;
            return $g;
        });
        return $err ?? ['pid' => $pid, 'tok' => $tok, 'name' => $final];
    }

    public static function player(?array $g, string $pid, string $tok): ?array
    {
        $p = $g['players'][$pid] ?? null;
        return $p && $tok !== '' && hash_equals((string) $p['tok'], hash('sha256', $tok)) ? $p : null;
    }

    /** Points d'une bonne réponse : de 1000 (immédiate) à 500 (dernière seconde). */
    public static function points(int $ms, int $duration): int
    {
        return (int) round(500 + 500 * max(0, 1 - $ms / ($duration * 1000)));
    }

    /** @return array{ok:bool,error?:string} */
    public static function answer(string $code, string $pid, string $tok, int $choice): array
    {
        $res = ['ok' => false, 'error' => t('Trop tard !')];
        JsonStore::update(self::file($code), function ($g) use ($pid, $tok, $choice, &$res) {
            if (!is_array($g) || !self::player($g, $pid, $tok)) {
                $res = ['ok' => false, 'error' => t('Rejoignez d’abord la partie.')];
                return $g;
            }
            $now = self::now();
            $i = (string) $g['idx'];
            $q = $g['questions'][(int) $g['idx']] ?? null;
            if ($g['phase'] !== 'question' || !$q || $now < $g['t0'] || $now > $g['t0'] + $g['duration'] * 1000 + self::GRACE || isset($g['closed'])) {
                return $g;
            }
            if (isset($g['answers'][$i][$pid])) {
                $res = ['ok' => true];
                return $g;
            }
            if ($choice < 0 || $choice >= count($q['a'])) {
                $res = ['ok' => false, 'error' => t('Réponse invalide.')];
                return $g;
            }
            $ms = max(0, $now - (int) $g['t0']);
            $pts = $choice === (int) $q['c'] ? self::points($ms, (int) $g['duration']) : 0;
            $g['answers'][$i][$pid] = [$choice, $ms, $pts];
            $g['players'][$pid]['score'] += $pts;
            $g['v']++;
            $res = ['ok' => true];
            return $g;
        });
        return $res;
    }

    /** Classement : [pid, name, score, rang] trié, ex aequo au même rang. @return list<array> */
    public static function ranking(array $g): array
    {
        $rows = [];
        foreach ($g['players'] as $pid => $p) {
            $rows[] = [(string) $pid, $p['name'], (int) $p['score']];
        }
        usort($rows, fn ($a, $b) => [$b[2], $a[1]] <=> [$a[2], $b[1]]);
        $rank = 0;
        $prev = null;
        foreach ($rows as $k => $r) {
            if ($r[2] !== $prev) {
                $rank = $k + 1;
                $prev = $r[2];
            }
            $rows[$k][3] = $rank;
        }
        return $rows;
    }

    /** État vu par le grand écran. */
    public static function screenState(array $g): array
    {
        $now = self::now();
        $phase = self::phase($g, $now);
        $i = (int) $g['idx'];
        $q = $g['questions'][$i] ?? null;
        $ans = $g['answers'][(string) $i] ?? [];
        $out = ['v' => $g['v'], 'phase' => $phase, 'idx' => $i, 'n' => count($g['questions']), 'duration' => $g['duration'],
            'players' => count($g['players'])];
        if ($phase === 'lobby') {
            $out['names'] = array_values(array_map(fn ($pid, $p) => ['id' => (string) $pid, 'name' => $p['name'], 'm' => isset($p['cid'])], array_keys($g['players']), $g['players']));
            if (empty($g['friendly'])) {
                $out['champ'] = array_map(fn ($r) => ['name' => $r['pseudo'], 'pts' => $r['pts'], 'rank' => $r['rank']], array_slice(QuizChampionship::ranking(), 0, 8));
            }
        }
        if ($q && in_array($phase, ['question', 'reveal'], true)) {
            $out['q'] = ['q' => $q['q'], 'a' => $q['a'], 'kind' => $q['kind'] ?? 'quiz'];
            $out['answered'] = count($ans);
            $out['wait'] = max(0, $g['t0'] - $now);
            $out['left'] = max(0, $g['t0'] + $g['duration'] * 1000 - $now);
        }
        if ($q && $phase === 'reveal') {
            $counts = array_fill(0, count($q['a']), 0);
            foreach ($ans as $a) {
                $counts[(int) $a[0]] = ($counts[(int) $a[0]] ?? 0) + 1;
            }
            $out['q'] += ['c' => (int) $q['c'], 'fact' => (string) ($q['fact'] ?? ''), 'counts' => $counts];
        }
        if (in_array($phase, ['board', 'end'], true)) {
            $prevScores = [];
            foreach ($g['players'] as $pid => $p) {
                $prevScores[$pid] = (int) $p['score'] - (int) ($ans[$pid][2] ?? 0);
            }
            $out['top'] = array_map(fn ($r) => ['name' => $r[1], 'score' => $r[2], 'rank' => $r[3], 'gain' => $r[2] - ($prevScores[$r[0]] ?? 0)],
                array_slice(self::ranking($g), 0, $phase === 'end' ? 10 : 5));
        }
        if ($phase === 'end' && !empty($g['counted'])) {
            // Le championnat après cette partie : les dix premiers, et ceux de la soirée qui ont bougé.
            $moves = (array) ($g['moves'] ?? []);
            $out['champ'] = [];
            foreach (QuizChampionship::ranking(QuizChampionship::season((int) $g['created'])) as $r) {
                $m = $moves[$r['id']] ?? null;
                if ($r['rank'] <= 10 || $m) {
                    $out['champ'][] = ['name' => $r['pseudo'], 'pts' => $r['pts'], 'rank' => $r['rank'], 'here' => (bool) $m,
                        'up' => $m && $m[0] !== null ? $m[0] - $m[1] : null, 'new' => $m && $m[0] === null];
                }
                if (count($out['champ']) >= 12) {
                    break;
                }
            }
        }
        return $out;
    }

    /** État vu par le téléphone d'un joueur. */
    public static function playerState(array $g, string $pid): array
    {
        $now = self::now();
        $phase = self::phase($g, $now);
        $i = (int) $g['idx'];
        $q = $g['questions'][$i] ?? null;
        $mine = $g['answers'][(string) $i][$pid] ?? null;
        $out = ['v' => $g['v'], 'phase' => $phase, 'idx' => $i, 'n' => count($g['questions']), 'duration' => $g['duration'], 'name' => $g['players'][$pid]['name'] ?? '',
            'score' => (int) ($g['players'][$pid]['score'] ?? 0)];
        if (!isset($g['players'][$pid])) {
            return ['phase' => 'gone', 'v' => $g['v']];
        }
        $me = $g['players'][$pid];
        $out['member'] = isset($me['cid']);
        $out['pending'] = isset($me['claim']);
        if ($phase === 'end' && isset($me['cid']) && !QuizChampionship::confirmed((string) $me['cid'])) {
            $out['confirm'] = true; // points gardés : ils apparaîtront quand le lien de l'e-mail sera ouvert
        }
        if ($phase === 'end' && isset($me['cid'], $g['moves'][$me['cid']])) {
            $r = QuizChampionship::rankOf((string) $me['cid'], QuizChampionship::season((int) $g['created']));
            $out['champ'] = $r ? ['rank' => $r['rank'], 'pts' => $r['pts'], 'label' => t('{r} du championnat · {n} pts', ['r' => ordinal($r['rank']), 'n' => $r['pts']])] : null;
        }
        if ($q && in_array($phase, ['question', 'reveal'], true)) {
            $out['q'] = ['q' => $q['q'], 'a' => $q['a']];
            $out['answered'] = $mine !== null ? (int) $mine[0] : null;
            $out['wait'] = max(0, $g['t0'] - $now);
            $out['left'] = max(0, $g['t0'] + $g['duration'] * 1000 - $now);
        }
        if ($q && $phase === 'reveal') {
            $out['c'] = (int) $q['c'];
            $out['gain'] = (int) ($mine[2] ?? 0);
        }
        if (in_array($phase, ['reveal', 'board', 'end'], true)) {
            foreach (self::ranking($g) as $r) {
                if ($r[0] === $pid) {
                    $out['rank'] = $r[3];
                    $out['rankLabel'] = t('{r} sur {n}', ['r' => ordinal($r[3]), 'n' => count($g['players'])]);
                    break;
                }
            }
            $out['of'] = count($g['players']);
        }
        return $out;
    }

    // ------------------------------------------------------------------ questions

    /**
     * Questions de la partie : $mix = 'mix' (moitié quiz du site, moitié fiches), 'site' ou 'fiches'.
     * @return list<array{q:string,a:list<string>,c:int,fact:string,kind:string}>
     */
    public static function questions(int $count, string $mix = 'mix'): array
    {
        $site = [];
        if ($mix !== 'fiches') {
            foreach (Collections::get('quiz', Seeds::quiz()) as $q) {
                if (($q['active'] ?? true) && !empty($q['q']) && count($q['a'] ?? []) >= 2 && isset($q['a'][(int) ($q['c'] ?? 0)])) {
                    $q = Collections::loc($q, ['q', 'a', 'fact']);
                    $site[] = self::shuffle(['q' => (string) $q['q'], 'a' => array_values(array_map('strval', $q['a'])), 'c' => (int) $q['c'], 'fact' => (string) ($q['fact'] ?? ''), 'kind' => 'quiz']);
                }
            }
            shuffle($site);
        }
        $want = $mix === 'site' ? $count : ($mix === 'fiches' ? 0 : intdiv($count, 2));
        $out = array_slice($site, 0, $want);
        $out = array_merge($out, self::fromMatches($count - count($out)));
        if (count($out) < $count) {
            $out = array_merge($out, array_slice($site, $want, $count - count($out)));
        }
        shuffle($out);
        return array_slice($out, 0, $count);
    }

    /** Mélange les réponses en gardant la bonne. */
    private static function shuffle(array $q): array
    {
        $good = $q['a'][$q['c']];
        $a = $q['a'];
        shuffle($a);
        $q['a'] = array_values($a);
        $q['c'] = (int) array_search($good, $q['a'], true);
        return $q;
    }

    private static function score(array $m): string
    {
        return $m['sh'] ? $m['us'] . '–' . $m['them'] : $m['them'] . '–' . $m['us'];
    }

    private static function fixture(array $m): string
    {
        return $m['home'] . ' – ' . $m['away'];
    }

    private static function when(array $m): string
    {
        return date_fr((string) $m['date']);
    }

    private static function compLabel(array $m): string
    {
        return implode(' ', array_unique(array_filter([t((string) $m['label']), (string) $m['round']])));
    }

    /** Questions tirées des grands matchs (fiches riches, hors amicaux). @return list<array> */
    public static function fromMatches(int $n): array
    {
        if ($n < 1) {
            return [];
        }
        $pool = [];
        $opps = [];
        $seen = [];
        foreach (Derived::part('matches') as $m) {
            if (!$m['v'] || $m['comp'] === 'Amical' || (int) $m['us'] < 0 || $m['date'] === '' || $m['us'] === null) {
                continue;
            }
            $opps[(int) $m['decade']][$m['opp']] = true;
            $seen[$m['home'] . '|' . $m['away'] . '|' . self::score($m)][] = substr((string) $m['date'], 0, 4);
            if ((int) $m['hl'] >= 6) {
                $pool[] = $m;
            }
        }
        shuffle($pool);
        $scorers = Derived::part('scorers');
        $kinds = ['score', 'year', 'opp', 'scorer'];
        $out = [];
        $used = [];
        foreach ($pool as $k => $m) {
            if (count($out) >= $n) {
                break;
            }
            if (isset($used[$m['id']])) {
                continue;
            }
            $kind = $kinds[count($out) % 4];
            $q = match ($kind) {
                'score' => self::qScore($m),
                'year' => self::qYear($m, $seen),
                'opp' => self::qOpp($m, array_keys($opps[(int) $m['decade']] ?? [])),
                'scorer' => self::qScorer($m, $scorers[$m['id']] ?? []),
            };
            if ($q) {
                $used[$m['id']] = true;
                $out[] = self::shuffle($q + ['kind' => $kind]);
            }
        }
        return $out;
    }

    private static function fact(array $m): string
    {
        $bits = [self::fixture($m) . ' ' . self::score($m), self::compLabel($m), self::when($m)];
        if ((int) ($m['spectators'] ?? 0) > 0) {
            $bits[] = t('{n} spectateurs', ['n' => number_format((int) $m['spectators'], 0, ',', ' ')]);
        }
        return implode(' · ', array_filter($bits));
    }

    private static function qScore(array $m): ?array
    {
        [$h, $a] = $m['sh'] ? [(int) $m['us'], (int) $m['them']] : [(int) $m['them'], (int) $m['us']];
        $good = "{$h}–{$a}";
        $set = [$good => true];
        $tries = 0;
        while (count($set) < 4 && $tries++ < 60) {
            $dh = max(0, $h + mt_rand(-2, 2));
            $da = max(0, $a + mt_rand(-2, 2));
            $set["{$dh}–{$da}"] = true;
        }
        if (count($set) < 4) {
            return null;
        }
        $a4 = array_keys($set);
        return ['q' => t('Quel était le score du match {f} ({c}, {d}) ?', ['f' => self::fixture($m), 'c' => self::compLabel($m), 'd' => self::when($m)]),
            'a' => array_map('strval', $a4), 'c' => 0, 'fact' => self::fact($m)];
    }

    private static function qYear(array $m, array $seen): ?array
    {
        $y = (int) substr((string) $m['date'], 0, 4);
        $taken = $seen[$m['home'] . '|' . $m['away'] . '|' . self::score($m)] ?? [];
        $set = [(string) $y => true];
        $tries = 0;
        while (count($set) < 4 && $tries++ < 60) {
            $d = $y + mt_rand(-9, 9);
            if ($d !== $y && $d >= 1928 && $d <= (int) date('Y') && !in_array((string) $d, $taken, true)) {
                $set[(string) $d] = true;
            }
        }
        if (count($set) < 4) {
            return null;
        }
        return ['q' => t('En quelle année s’est joué {f}, terminé sur le score de {s} ({c}) ?', ['f' => self::fixture($m), 's' => self::score($m), 'c' => t((string) $m['label'])]),
            'a' => array_map('strval', array_keys($set)), 'c' => 0, 'fact' => self::fact($m)];
    }

    private static function qOpp(array $m, array $opps): ?array
    {
        $opps = array_values(array_filter($opps, fn ($o) => $o !== $m['opp'] && $o !== ''));
        if (count($opps) < 3 || $m['opp'] === '') {
            return null;
        }
        shuffle($opps);
        $verb = match ($m['result']) {
            'V' => t('Le {d}, le FCSM bat quel adversaire {s} ({c}) ?', ['d' => self::when($m), 's' => $m['us'] . '–' . $m['them'], 'c' => self::compLabel($m)]),
            'D' => t('Le {d}, le FCSM s’incline {s} face à quel adversaire ({c}) ?', ['d' => self::when($m), 's' => $m['us'] . '–' . $m['them'], 'c' => self::compLabel($m)]),
            default => t('Le {d}, le FCSM fait match nul {s} face à quel adversaire ({c}) ?', ['d' => self::when($m), 's' => $m['us'] . '–' . $m['them'], 'c' => self::compLabel($m)]),
        };
        return ['q' => $verb, 'a' => array_merge([(string) $m['opp']], array_slice($opps, 0, 3)), 'c' => 0, 'fact' => self::fact($m)];
    }

    private static function qScorer(array $m, array $rows): ?array
    {
        $goal = [];
        foreach ($rows as $r) {
            if (empty($r[3]) && (int) $r[0] > 0) {
                $goal[(int) $r[0]] = (string) $r[1];
            }
        }
        if (!$goal) {
            return null;
        }
        $others = [];
        foreach (Derived::lineupLinks((int) $m['id']) as $l) {
            $pid = (int) $l[0];
            if ($l[6] === 'player' && !isset($goal[$pid]) && ($p = Index::get($pid)) && Index::visible($p)) {
                $others[$pid] = (string) $p['title'];
            }
        }
        $others = array_values(array_unique(array_diff($others, $goal)));
        if (count($others) < 3) {
            return null;
        }
        shuffle($others);
        $pick = array_rand($goal);
        $p = Index::get($pick);
        if (!$p || !Index::visible($p)) {
            return null;
        }
        $q = count($goal) > 1
            ? t('Lequel de ces joueurs a marqué pour le FCSM lors de {f} {s} ({d}) ?', ['f' => self::fixture($m), 's' => self::score($m), 'd' => self::when($m)])
            : t('Qui a marqué pour le FCSM lors de {f} {s} ({d}) ?', ['f' => self::fixture($m), 's' => self::score($m), 'd' => self::when($m)]);
        return ['q' => $q, 'a' => array_merge([(string) $p['title']], array_slice($others, 0, 3)), 'c' => 0, 'fact' => self::fact($m)];
    }
}
