<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Core\JsonStore;

/**
 * Le flipper du musée (/interactif/flipper/) : le jeu tourne dans le navigateur (3D, physique,
 * règles : app/Resources/flipper/) ; le serveur garde le tableau d'honneur.
 *
 * Tableau d'honneur : meilleur score de la semaine et de toujours, par compte supporter (carnet)
 * sous son pseudo du championnat du club-house ; sans compte, on joue sans classement. Chaque
 * partie reçoit au départ un jeton signé (heure de début) : un score n'est accepté qu'une fois par
 * jeton, après au moins 10 secondes de jeu et sans dépasser un rythme de points vraisemblable.
 */
final class Flipper
{
    /** Dossier des scores (remplacé par les tests). */
    public static string $dir = STORAGE_PATH . '/flipper';
    public const TOP = 20;
    /** Points par seconde au plus (au-delà, le score est refusé), plus une marge fixe. */
    public const MAX_RATE = 120000;
    public const MARGIN = 3000000;
    public const MAX_SCORE = 2000000000;

    /** Réglages du jeu pour ce club (textes du décor, noms des rampes et de l'orbite, lettres des cibles, année des couloirs). */
    public static function config(): array
    {
        $c = (array) Club::get('flipper', []);
        $mascot = Club::s('mascot');
        return [
            'title' => (string) ($c['title'] ?? 'Le Flipper du ' . $mascot),
            'letters' => strtoupper((string) preg_replace('/[^A-Za-z]/', '', (string) ($c['letters'] ?? Club::s('team')))) ?: 'LION',
            'digits' => substr((string) preg_replace('/\D/', '', (string) ($c['digits'] ?? Club::s('founded'))), 0, 4),
            'ramp' => (string) ($c['ramp'] ?? 'Rampe ' . Club::s('stadium_short')),
            'ramp2' => (string) ($c['ramp2'] ?? 'Rampe des jeunes'),
            'orbit' => (string) ($c['orbit'] ?? 'Tour d’honneur'),
            'bumpers' => (string) ($c['bumpers'] ?? 'Les bumpers'),
            'cap' => (string) ($c['cap'] ?? strtoupper($mascot)),
            'kickback' => (string) ($c['kickback'] ?? 'Centre de formation'),
            'roar' => (string) ($c['roar'] ?? 'Les bumpers s’emballent !'),
        ];
    }

    // ------------------------------------------------------------------ jetons de partie

    /** Jeton d'une partie : heure de début, numéro tiré au hasard, signature. */
    public static function token(?int $now = null): string
    {
        $body = ($now ?? time()) . '.' . bin2hex(random_bytes(8));
        return $body . '.' . substr(hash_hmac('sha256', $body, site_key('flipper')), 0, 32);
    }

    /** @return array{t:int,n:string}|null jeton valable (signé, de moins de 4 h) */
    public static function check(string $tok, ?int $now = null): ?array
    {
        if (!preg_match('/^(\d{9,11})\.([a-f0-9]{16})\.([a-f0-9]{32})$/', $tok, $m)) {
            return null;
        }
        $sig = substr(hash_hmac('sha256', $m[1] . '.' . $m[2], site_key('flipper')), 0, 32);
        $age = ($now ?? time()) - (int) $m[1];
        return hash_equals($sig, $m[3]) && $age >= 0 && $age < 4 * 3600 ? ['t' => (int) $m[1], 'n' => $m[2]] : null;
    }

    /** Score vraisemblable pour une partie commencée à $start ? Message d'erreur, ou null. */
    public static function plausible(int $score, int $start, ?int $now = null): ?string
    {
        $dur = ($now ?? time()) - $start;
        if ($score < 0 || $score > self::MAX_SCORE || $score % 10 !== 0) {
            return t('Score invalide.');
        }
        if ($dur < 10) {
            return t('Partie trop courte pour le tableau d’honneur.');
        }
        return $score > self::MARGIN + $dur * self::MAX_RATE ? t('Score invalide.') : null;
    }

    // ------------------------------------------------------------------ scores

    public static function week(?int $ts = null): string
    {
        return date('o-\WW', $ts ?? time());
    }

    private static function file(string $board): string
    {
        return self::$dir . '/' . ($board === 'all' ? 'records' : 'semaine-' . $board) . '.json';
    }

    /**
     * Enregistre le score d'un compte (meilleur de la semaine et de toujours). Le jeton ne sert
     * qu'une fois. @return string|null erreur
     */
    public static function record(string $cid, string $pseudo, int $score, string $tok, ?int $now = null): ?string
    {
        $now ??= time();
        $g = self::check($tok, $now);
        if (!$g) {
            return t('Partie expirée : rejouez pour entrer au tableau d’honneur.');
        }
        if ($err = self::plausible($score, $g['t'], $now)) {
            return $err;
        }
        $used = false;
        JsonStore::update(self::file(self::week($now)), function ($d) use ($cid, $pseudo, $score, $g, $now, &$used) {
            $d = is_array($d) ? $d : ['scores' => [], 'used' => []];
            if (isset($d['used'][$g['n']])) {
                $used = true;
                return $d;
            }
            $d['used'][$g['n']] = $now;
            $old = $d['scores'][$cid] ?? null;
            $d['scores'][$cid] = ['pseudo' => $pseudo, 'score' => max($score, (int) ($old['score'] ?? 0)), 'games' => (int) ($old['games'] ?? 0) + 1,
                'at' => $score > (int) ($old['score'] ?? -1) ? date('c', $now) : ($old['at'] ?? date('c', $now))];
            return $d;
        }, []);
        if ($used) {
            return t('Ce score est déjà enregistré.');
        }
        QuizChampionship::touch($cid);
        JsonStore::update(self::file('all'), function ($d) use ($cid, $pseudo, $score, $now) {
            $d = is_array($d) ? $d : ['scores' => []];
            if ($score > (int) ($d['scores'][$cid]['score'] ?? -1)) {
                $d['scores'][$cid] = ['pseudo' => $pseudo, 'score' => $score, 'at' => date('c', $now)];
            } else {
                $d['scores'][$cid]['pseudo'] = $pseudo;
            }
            // 500 meilleurs comptes gardés.
            if (count($d['scores']) > 500) {
                uasort($d['scores'], fn ($a, $b) => $b['score'] <=> $a['score']);
                $d['scores'] = array_slice($d['scores'], 0, 500, true);
            }
            return $d;
        }, []);
        return null;
    }

    /**
     * Classement : 'all' (de toujours) ou une semaine ('2026-W44'). Seuls les comptes confirmés
     * (lien de l'e-mail ouvert) et non retirés du championnat y figurent. @return list<array{rank:int,cid:string,pseudo:string,score:int}>
     */
    public static function ranking(string $board, int $n = self::TOP): array
    {
        $d = JsonStore::read(self::file($board));
        $rows = [];
        foreach ((array) ($d['scores'] ?? []) as $cid => $r) {
            $p = QuizChampionship::player((string) $cid);
            if (QuizChampionship::listed($p, (string) $cid)) {
                $rows[] = ['cid' => (string) $cid, 'pseudo' => (string) ($p['pseudo'] ?? $r['pseudo']), 'score' => (int) $r['score'], 'at' => (string) ($r['at'] ?? '')];
            }
        }
        usort($rows, fn ($a, $b) => [$b['score'], $a['at']] <=> [$a['score'], $b['at']]);
        $out = [];
        foreach ($rows as $i => $r) {
            $out[] = ['rank' => $i + 1] + $r;
        }
        return $n > 0 ? array_slice($out, 0, $n) : $out;
    }

    public static function rankOf(string $board, string $cid): ?int
    {
        foreach (self::ranking($board, 0) as $r) {
            if ($r['cid'] === $cid) {
                return $r['rank'];
            }
        }
        return null;
    }

    /** Lignes envoyées au navigateur (sans les numéros de compte). */
    public static function publicRows(array $rows, ?string $me): array
    {
        return array_map(fn ($r) => ['rank' => $r['rank'], 'pseudo' => $r['pseudo'], 'score' => $r['score'], 'me' => $me !== null && $r['cid'] === $me], $rows);
    }

    // ------------------------------------------------------------------ « Le saviez-vous ? »

    /** Faits tirés des chiffres du musée, pour l'écran à points. @return list<string> */
    public static function facts(int $max = 40): array
    {
        $out = [];
        $founded = (string) Club::s('founded_date');
        if ($founded !== '') {
            $out[] = mb_strtoupper(mb_substr($l = t('{club_le} est né le {d}.', ['d' => date_fr($founded)]), 0, 1)) . mb_substr($l, 1);
        }
        $all = Chiffres::cached();
        if ($all) {
            $stats = array_values(Chiffres::flat($all));
            shuffle($stats);
            foreach ($stats as $s) {
                $who = count((array) ($s['who'] ?? [])) === 1 ? (string) ($s['who'][0]['name'] ?? '') : '';
                $value = trim($s['value'] . ' ' . $s['unit']);
                $line = ($who !== '' ? $s['label'] . ' : ' . $who . ', ' . $value : $s['label'] . ' : ' . $value) . '. ' . $s['text'];
                $line = trim((string) preg_replace('/\s+/u', ' ', strip_tags($line)));
                if (mb_strlen($line) <= 150) {
                    $out[] = $line;
                }
                if (count($out) >= $max) {
                    break;
                }
            }
        }
        return $out;
    }

    /** Compte supprimé : effacé de toutes les semaines et des records. */
    public static function forget(string $cid): void
    {
        foreach ([...(glob(self::$dir . '/semaine-*.json') ?: []), self::file('all')] as $f) {
            if (is_file($f) && str_contains((string) file_get_contents($f), '"' . $cid . '"')) {
                JsonStore::update($f, function ($d) use ($cid) {
                    unset($d['scores'][$cid]);
                    return $d;
                }, []);
            }
        }
    }

    /** Ménage du cron : semaines de plus de 6 mois. */
    public static function purge(): int
    {
        $n = 0;
        $limit = self::week(time() - 183 * 86400);
        foreach (glob(self::$dir . '/semaine-*.json') ?: [] as $f) {
            if (substr(basename($f, '.json'), 8) < $limit) {
                @unlink($f);
                @unlink($f . '.lock');
                $n++;
            }
        }
        return $n;
    }
}
