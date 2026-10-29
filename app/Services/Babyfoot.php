<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Club;
use App\Core\JsonStore;
use App\Core\Memo;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
use App\Front\Palmares;

/**
 * Le babyfoot du musée (/interactif/babyfoot/) : le jeu tourne dans le navigateur (3D, physique,
 * ordinateur, règles : app/Resources/babyfoot/) ; le serveur prépare les adversaires et les grands
 * matchs à rejouer (faits tirés des fiches : date, score, composition), et garde le tableau d'honneur.
 *
 * Tableau d'honneur : nombre de victoires contre l'ordinateur, par niveau (Débutant, Amateur,
 * Ligue 2, Ligue 1), de la semaine et de toujours, par compte supporter (carnet) sous son pseudo du
 * championnat du club-house ; sans compte, on joue sans classement. Chaque partie reçoit au départ
 * un jeton signé (heure, niveau, durée de jeu) : un résultat n'est accepté qu'une fois par jeton,
 * avec un score possible et une durée vraisemblable.
 */
final class Babyfoot
{
    /** Dossier des victoires (remplacé par les tests). */
    public static string $dir = STORAGE_PATH . '/babyfoot';
    public const TOP = 20;
    /** Niveaux : en Débutant, les barres du joueur se placent seules (il n'a qu'à tirer) ; au-dessus, il les glisse lui-même. */
    public const LEVELS = ['Débutant', 'Amateur', 'Ligue 2', 'Ligue 1'];
    /** Durée réelle minimale (s) d'un match gagné : premier à 5 buts, 2 × 3 minutes. */
    public const MIN_GOALS = 25;
    public const MIN_TIME = 300;

    /**
     * Couleurs des adversaires (maillot, short) et sigle : seulement les clubs dont les couleurs sont
     * connues avec certitude. Les autres jouent en blanc et gris, sans couleurs inventées. Aucun logo.
     */
    public const COLORS = [
        'metz' => ['#7B1E2E', '#F2F2F2', 'FCM'],
        'saint-etienne' => ['#0A7A3B', '#F2F2F2', 'ASSE'],
        'nantes' => ['#F2D411', '#0A6B3A', 'FCN'],
        'marseille' => ['#F2F2F2', '#2FA6DE', 'OM'],
        'lyon' => ['#F2F2F2', '#1A3E8C', 'OL'],
        'bordeaux' => ['#0B1F45', '#F2F2F2', 'FCGB'],
        'lens' => ['#D21F26', '#F5C200', 'RCL'],
        'monaco' => ['#D3122A', '#F2F2F2', 'ASM'],
        'paris-saint-germain' => ['#0B2A5B', '#D11F2F', 'PSG'],
        'strasbourg' => ['#0B5DAA', '#F2F2F2', 'RCSA'],
        'auxerre' => ['#F2F2F2', '#1D4A9C', 'AJA'],
        'rennes' => ['#D11F26', '#141414', 'SRFC'],
        'nice' => ['#CC1B26', '#141414', 'OGCN'],
        'montpellier' => ['#F07C1A', '#1C2F6E', 'MHSC'],
        'toulouse' => ['#5B2C83', '#F2F2F2', 'TFC'],
        'reims' => ['#D0202A', '#F2F2F2', 'SDR'],
        'bastia' => ['#1550A3', '#F2F2F2', 'SCB'],
        'le-havre' => ['#6CB4E4', '#0B2148', 'HAC'],
        'lorient' => ['#F07B16', '#141414', 'FCL'],
        'guingamp' => ['#D21F26', '#141414', 'EAG'],
        'caen' => ['#1F3E8F', '#D21F26', 'SMC'],
        'troyes' => ['#1E4C9A', '#F2F2F2', 'ESTAC'],
        'brest' => ['#D31F2A', '#F2F2F2', 'SB29'],
        'angers' => ['#141414', '#F2F2F2', 'SCO'],
        'laval' => ['#F07C1A', '#141414', null],
        'valenciennes' => ['#D11F2A', '#F2F2F2', 'VAFC'],
        'red-star' => ['#0B7A3E', '#F2F2F2', null],
        'lille' => ['#D11F2A', '#F2F2F2', 'LOSC'],
        'ajaccio' => ['#D21F26', '#F2F2F2', 'ACA'],
        'nimes' => ['#D21F26', '#F2F2F2', null],
        'sochaux' => ['#F6C400', '#1F3FA8', 'FCSM'],
    ];
    public const NEUTRAL = ['#ECECEC', '#3A3A3A'];

    /** Réglages du jeu pour ce club : titre, sigle, maillot des figurines, couleur de la barre active. */
    public static function config(): array
    {
        $c = (array) Club::get('babyfoot', []);
        $kit = (array) ($c['kit'] ?? []);
        $shirt = (string) ($kit['shirt'] ?? Club::color('yellow'));
        return [
            'title' => (string) ($c['title'] ?? 'Babyfoot des ' . Club::s('nickname')),
            'short' => (string) ($c['short'] ?? Club::s('sigle')),
            'kit' => ['shirt' => $shirt, 'sleeves' => (string) ($kit['sleeves'] ?? $shirt), 'shorts' => (string) ($kit['shorts'] ?? Club::color('navy')), 'socks' => (string) ($kit['socks'] ?? $shirt)],
            'glow' => (string) ($c['glow'] ?? Club::color('yellow')),
        ];
    }

    // ------------------------------------------------------------------ adversaires

    /**
     * Adversaire : nom, nom court (tableau de score), sigle, maillot. Couleurs trop proches des
     * nôtres : on inverse maillot et short ; toujours trop proches : tenue neutre.
     */
    public static function opponent(string $club, string $name): array
    {
        $known = self::COLORS[$club] ?? null;
        [$shirt, $shorts] = $known ? [$known[0], $known[1]] : self::NEUTRAL;
        $ours = self::config()['kit']['shirt'];
        if (self::near($shirt, $ours)) {
            [$shirt, $shorts] = [$shorts, $shirt];
            if (self::near($shirt, $ours)) {
                [$shirt, $shorts] = self::NEUTRAL;
            }
        }
        $up = mb_strtoupper($name);
        $ini = $known[2] ?? null;
        if ($ini === null) {
            $ini = substr((string) preg_replace('/[^A-Z]/', '', strtoupper(Names::ascii($name))), 0, 3);
        }
        return [
            'id' => $club, 'name' => $name, 'short' => mb_strlen($up) <= 12 ? $up : $ini, 'ini' => $ini, 'known' => $known !== null,
            'kit' => ['shirt' => $shirt, 'sleeves' => $shirt, 'shorts' => $shorts, 'socks' => $shirt],
        ];
    }

    /** Deux couleurs trop proches pour distinguer les équipes ? (écart pondéré comme l'œil le voit) */
    private static function near(string $a, string $b): bool
    {
        $ca = sscanf(ltrim($a, '#'), '%02x%02x%02x');
        $cb = sscanf(ltrim($b, '#'), '%02x%02x%02x');
        $rm = ($ca[0] + $cb[0]) / 2;
        $d = sqrt((2 + $rm / 256) * ($ca[0] - $cb[0]) ** 2 + 4 * ($ca[1] - $cb[1]) ** 2 + (2 + (255 - $rm) / 256) * ($ca[2] - $cb[2]) ** 2);
        return $d < 140;
    }

    /** Les adversaires les plus souvent rencontrés en match officiel (sélecteur « Adversaire »). */
    public static function opponents(int $n = 16): array
    {
        $count = [];
        $names = [];
        foreach (Derived::part('matches') as $dm) {
            if (empty($dm['v']) || ($dm['comp'] ?? '') === 'Amical' || empty($dm['club'])) {
                continue;
            }
            $count[$dm['club']] = ($count[$dm['club']] ?? 0) + 1;
            $names[$dm['club']][(string) $dm['opp']] = ($names[$dm['club']][(string) $dm['opp']] ?? 0) + 1;
        }
        arsort($count);
        $out = [];
        foreach (array_slice(array_keys($count), 0, $n) as $club) {
            // Le nom le plus courant dans les fiches (« Lens » plutôt qu'une variante isolée).
            arsort($names[$club]);
            $out[] = self::opponent((string) $club, (string) array_key_first($names[$club]));
        }
        // Musée sans matchs : quelques grands noms pour jouer quand même.
        if (!$out) {
            foreach (['marseille' => 'Marseille', 'lyon' => 'Lyon', 'saint-etienne' => 'Saint-Étienne', 'nantes' => 'Nantes', 'bordeaux' => 'Bordeaux', 'monaco' => 'Monaco'] as $id => $nm) {
                if (!Club::isUs($nm)) {
                    $out[] = self::opponent($id, $nm);
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ « Rejoue l'histoire »

    /**
     * Les grands matchs à rejouer : matchs officiels publiés les plus marquants (finales, demies,
     * grosses affiches, fiches à la une), un par adversaire, du plus ancien au plus récent.
     */
    public static function history(int $n = 10): array
    {
        // Gardé en cache tant que les fiches ne changent pas (textes et liens selon la langue).
        return Memo::get('babyfoot-histoire-' . I18n::lang() . '-' . $n, [Index::CACHE, __FILE__], Derived::built(), fn () => self::pick($n));
    }

    private static function pick(int $n): array
    {
        $cand = [];
        foreach (Derived::part('matches') as $id => $dm) {
            if (empty($dm['v']) || ($dm['comp'] ?? '') === 'Amical' || !isset($dm['us'], $dm['them']) || (int) ($dm['hl'] ?? 0) < 6 || empty($dm['club'])) {
                continue;
            }
            $cand[] = ['id' => (int) $id, 'dm' => $dm, 'score' => RetroDirect::interest($dm, Index::get((int) $id))];
        }
        usort($cand, fn ($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        $seen = [];
        foreach ($cand as $c) {
            $club = (string) $c['dm']['club'];
            if (isset($seen[$club])) {
                continue;
            }
            $m = self::match($c['id'], $c['dm']);
            if ($m) {
                $seen[$club] = true;
                $out[] = $m;
            }
            if (count($out) >= $n) {
                break;
            }
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $out;
    }

    /** Un match à rejouer : libellé, adversaire, vrai résultat (texte), lien, noms des figurines. */
    public static function match(int $id, array $dm): ?array
    {
        $s = Index::get($id);
        $doc = Fiches::get($id);
        if (!$doc || !$s) {
            return null;
        }
        $m = (array) ($s['m'] ?? []);
        $year = substr((string) $dm['date'], 0, 4);
        $round = trim((string) ($dm['round'] ?? ''));
        $cup = self::isCup((string) $dm['comp']);
        $label = self::roundLabel($round, (string) $dm['comp']);
        $pens = is_array($m['pens'] ?? null) ? (!empty($m['sh']) ? [(int) $m['pens'][0], (int) $m['pens'][1]] : [(int) $m['pens'][1], (int) $m['pens'][0]]) : null;
        $aet = (bool) preg_match('/\ba\.?\s?p\b|prol/iu', (string) ($dm['extra'] ?? ''));
        $home = (string) $dm['home'];
        $away = (string) $dm['away'];
        $sc = (array) ($m['sh_score'] ?? []);
        return [
            'id' => $id,
            'date' => (string) $dm['date'],
            'label' => $label . ' ' . $year . ' · ' . $dm['opp'],
            'short' => $label . ' ' . $year,
            'title' => trim(t($dm['label'] ?? $dm['comp']) . ($round !== '' ? ', ' . $round : '') . ', ' . date_fr((string) $dm['date']) . ' : ' . $home . ' – ' . $away . ' ' . ($sc ? $sc[0] . '-' . $sc[1] : '') . (($dm['extra'] ?? '') !== '' ? ' ' . $dm['extra'] : '')),
            'opp' => self::opponent((string) $dm['club'], (string) $dm['opp']),
            'real' => ['us' => (int) $dm['us'], 'them' => (int) $dm['them'], 'result' => (string) ($dm['result'] ?? ''), 'text' => self::realText($dm, $pens, $aet, Palmares::isFinal($round))],
            'golden' => $cup,
            'url' => url((string) $dm['path']),
            'names' => self::names($doc),
        ];
    }

    private static function isCup(string $comp): bool
    {
        return in_array($comp, ['Coupe de France', 'Coupe de la Ligue', "Coupe d'Europe", 'Barrages', 'Coupes diverses'], true);
    }

    /** « Finale », « Demi-finale », « 8e de finale », « 7e tour », « Championnat »… */
    public static function roundLabel(string $round, string $comp): string
    {
        $r = mb_strtolower(Names::ascii($round));
        if (Palmares::isFinal($round)) {
            return t('Finale');
        }
        foreach (['/^(1\/2|demi)/' => 'Demi-finale', '/^(1\/4|quart)/' => 'Quart de finale', '/^1\/8/' => '8e de finale', '/^1\/16/' => '16e de finale', '/^1\/32/' => '32e de finale', '/^1\/64/' => '64e de finale'] as $re => $l) {
            if (preg_match($re, $r)) {
                return t($l);
            }
        }
        if (preg_match('/^(\d+)e\s*tour/', $r, $mm)) {
            return t('{n}e tour', ['n' => $mm[1]]);
        }
        return $comp === 'Championnat' ? t('Championnat') : t($comp);
    }

    /** Le vrai résultat, en une phrase de faits (date, score, prolongation, tirs au but). */
    public static function realText(array $dm, ?array $pens, bool $aet, bool $final): string
    {
        $d = date_fr((string) $dm['date']);
        $s = $dm['us'] . '-' . $dm['them'];
        $won = ($dm['result'] ?? '') === 'V';
        if ($pens) {
            $p = $pens[0] . '-' . $pens[1];
            $after = $aet ? t('{s} après prolongation', ['s' => $s]) : $s;
            if ($final) {
                return $won ? t('En vrai, le {d} : {a}, puis la Coupe gagnée aux tirs au but ({p}).', ['d' => $d, 'a' => $after, 'p' => $p])
                    : t('En vrai, le {d} : {a}, puis la Coupe perdue aux tirs au but ({p}).', ['d' => $d, 'a' => $after, 'p' => $p]);
            }
            return $won ? t('En vrai, le {d} : {a}, puis la qualification aux tirs au but ({p}).', ['d' => $d, 'a' => $after, 'p' => $p])
                : t('En vrai, le {d} : {a}, puis l’élimination aux tirs au but ({p}).', ['d' => $d, 'a' => $after, 'p' => $p]);
        }
        $what = $won ? t('victoire {s}', ['s' => $s]) : (($dm['result'] ?? '') === 'D' ? t('défaite {s}', ['s' => $s]) : t('match nul {s}', ['s' => $s]));
        return $aet ? t('En vrai, le {d} : {w} après prolongation.', ['d' => $d, 'w' => $what]) : t('En vrai, le {d} : {w}.', ['d' => $d, 'w' => $what]);
    }

    /**
     * Noms au dos des figurines de notre équipe : les onze titulaires de la feuille de match (gardien,
     * puis défenseurs, milieux, attaquants), répartis sur le gardien (1), la défense (2), le milieu
     * (5) et l'attaque (3). Numéro seulement s'il figure sur la feuille. Feuille incomplète : rien.
     * @return array<int,list<array{name:string,num:?int}>>|null indexé par barre (0, 1, 3, 5)
     */
    public static function names(array $doc): ?array
    {
        $groups = ['G' => [], 'D' => [], 'M' => [], 'A' => []];
        foreach ((array) ($doc['match']['lineup']['rows'] ?? []) as $r) {
            $pos = (string) ($r['position'] ?? '');
            if (!isset($groups[$pos])) {
                continue;
            }
            $parts = preg_split('/\s+/u', trim((string) ($r['name'] ?? ''))) ?: [];
            $upper = array_filter($parts, fn ($p) => mb_strlen($p) > 1 && mb_strtoupper($p) === $p && preg_match('/\p{L}/u', $p));
            if (!$upper) {
                return null;
            }
            $num = preg_match('/^\d{1,2}$/', trim((string) ($r['number'] ?? ''))) ? (int) $r['number'] : null;
            $groups[$pos][] = ['name' => implode(' ', $upper), 'num' => $num];
        }
        $all = [...$groups['G'], ...$groups['D'], ...$groups['M'], ...$groups['A']];
        if (count($groups['G']) !== 1 || count($all) !== 11) {
            return null;
        }
        return [0 => array_slice($all, 0, 1), 1 => array_slice($all, 1, 2), 3 => array_slice($all, 3, 5), 5 => array_slice($all, 8, 3)];
    }

    // ------------------------------------------------------------------ jetons de partie

    /** Jeton d'une partie : heure de début, niveau (0-3), durée (b : premier à 5, t : chrono), numéro, signature. */
    public static function token(int $level, string $format, ?int $now = null): string
    {
        $body = ($now ?? time()) . '.' . max(0, min(count(self::LEVELS) - 1, $level)) . ($format === 'buts' ? 'b' : 't') . '.' . bin2hex(random_bytes(8));
        return $body . '.' . substr(hash_hmac('sha256', $body, site_key('babyfoot')), 0, 32);
    }

    /** @return array{t:int,level:int,format:string,n:string}|null jeton valable (signé, de moins de 2 h) */
    public static function check(string $tok, ?int $now = null): ?array
    {
        if (!preg_match('/^(\d{9,11})\.([0-3])([bt])\.([a-f0-9]{16})\.([a-f0-9]{32})$/', $tok, $m)) {
            return null;
        }
        $sig = substr(hash_hmac('sha256', $m[1] . '.' . $m[2] . $m[3] . '.' . $m[4], site_key('babyfoot')), 0, 32);
        $age = ($now ?? time()) - (int) $m[1];
        return hash_equals($sig, $m[5]) && $age >= 0 && $age < 2 * 3600 ? ['t' => (int) $m[1], 'level' => (int) $m[2], 'format' => $m[3] === 'b' ? 'buts' : 'temps', 'n' => $m[4]] : null;
    }

    /** Résultat possible pour ce jeton ? Message d'erreur, ou null. */
    public static function plausible(int $gf, int $ga, array $g, ?int $now = null): ?string
    {
        $dur = ($now ?? time()) - $g['t'];
        $max = $g['format'] === 'buts' ? 5 : 40;
        if ($gf < 0 || $ga < 0 || $gf > $max || $ga > $max || ($g['format'] === 'buts' && max($gf, $ga) !== 5)) {
            return t('Résultat invalide.');
        }
        if ($dur < ($g['format'] === 'buts' ? self::MIN_GOALS : self::MIN_TIME)) {
            return t('Match trop court pour le tableau d’honneur.');
        }
        return null;
    }

    // ------------------------------------------------------------------ victoires

    public static function week(?int $ts = null): string
    {
        return date('o-\WW', $ts ?? time());
    }

    private static function file(string $board): string
    {
        return self::$dir . '/' . ($board === 'all' ? 'records' : 'semaine-' . $board) . '.json';
    }

    /**
     * Enregistre un match contre l'ordinateur (victoire ou non : on compte les matchs joués).
     * Le jeton ne sert qu'une fois. @return string|null erreur
     */
    public static function record(string $cid, string $pseudo, int $gf, int $ga, string $tok, ?int $now = null): ?string
    {
        $now ??= time();
        $g = self::check($tok, $now);
        if (!$g) {
            return t('Partie expirée : rejouez pour entrer au tableau d’honneur.');
        }
        if ($err = self::plausible($gf, $ga, $g, $now)) {
            return $err;
        }
        $win = $gf > $ga ? 1 : 0;
        $lv = (string) $g['level'];
        $add = function (array $d) use ($cid, $pseudo, $win, $lv, $now): array {
            $old = $d['levels'][$lv][$cid] ?? ['wins' => 0, 'games' => 0, 'at' => ''];
            $d['levels'][$lv][$cid] = ['pseudo' => $pseudo, 'wins' => (int) $old['wins'] + $win, 'games' => (int) $old['games'] + 1,
                'at' => $win ? date('c', $now) : (string) $old['at']];
            return $d;
        };
        $used = false;
        JsonStore::update(self::file(self::week($now)), function ($d) use ($g, $now, $add, &$used) {
            $d = is_array($d) ? $d : ['levels' => [], 'used' => []];
            if (isset($d['used'][$g['n']])) {
                $used = true;
                return $d;
            }
            $d['used'][$g['n']] = $now;
            return $add($d);
        }, []);
        if (!$used) {
            JsonStore::update(self::file('all'), fn ($d) => $add(is_array($d) ? $d : ['levels' => []]), []);
        }
        if ($used) {
            return t('Ce résultat est déjà enregistré.');
        }
        QuizChampionship::touch($cid);
        return null;
    }

    /**
     * Classement d'un niveau : 'all' (de toujours) ou une semaine ('2026-W44'). Seuls les comptes
     * confirmés et non retirés du championnat y figurent, avec au moins une victoire.
     * @return list<array{rank:int,cid:string,pseudo:string,wins:int,games:int}>
     */
    public static function ranking(int $level, string $board, int $n = self::TOP): array
    {
        $d = JsonStore::read(self::file($board));
        $rows = [];
        foreach ((array) ($d['levels'][(string) $level] ?? []) as $cid => $r) {
            $p = QuizChampionship::player((string) $cid);
            if ((int) $r['wins'] > 0 && QuizChampionship::listed($p, (string) $cid)) {
                $rows[] = ['cid' => (string) $cid, 'pseudo' => (string) ($p['pseudo'] ?? $r['pseudo']), 'wins' => (int) $r['wins'], 'games' => (int) $r['games'], 'at' => (string) ($r['at'] ?? '')];
            }
        }
        // À victoires égales : le moins de matchs joués, puis le premier arrivé.
        usort($rows, fn ($a, $b) => [$b['wins'], $a['games'], $a['at']] <=> [$a['wins'], $b['games'], $b['at']]);
        $out = [];
        foreach ($rows as $i => $r) {
            $out[] = ['rank' => $i + 1] + $r;
        }
        return $n > 0 ? array_slice($out, 0, $n) : $out;
    }

    public static function rankOf(int $level, string $board, string $cid): ?int
    {
        foreach (self::ranking($level, $board, 0) as $r) {
            if ($r['cid'] === $cid) {
                return $r['rank'];
            }
        }
        return null;
    }

    /**
     * Joueurs classés (tous niveaux), pour la modération du back-office : victoires de la semaine
     * et de toujours. @return array<string,array{pseudo:string,week:int,all:int}>
     */
    public static function players(): array
    {
        $out = [];
        foreach (['all' => 'all', self::week() => 'week'] as $board => $col) {
            foreach ([0, 1, 2] as $lv) {
                foreach (self::ranking($lv, $board, 0) as $r) {
                    $out[$r['cid']] ??= ['pseudo' => $r['pseudo'], 'week' => 0, 'all' => 0];
                    $out[$r['cid']][$col] += $r['wins'];
                }
            }
        }
        uasort($out, fn ($a, $b) => $b['all'] <=> $a['all']);
        return $out;
    }

    /** Lignes envoyées au navigateur (sans les numéros de compte). */
    public static function publicRows(array $rows, ?string $me): array
    {
        return array_map(fn ($r) => ['rank' => $r['rank'], 'pseudo' => $r['pseudo'], 'wins' => $r['wins'], 'me' => $me !== null && $r['cid'] === $me], $rows);
    }

    /** Compte supprimé : effacé de toutes les semaines et des records. */
    public static function forget(string $cid): void
    {
        foreach ([...(glob(self::$dir . '/semaine-*.json') ?: []), self::file('all')] as $f) {
            if (is_file($f) && str_contains((string) file_get_contents($f), '"' . $cid . '"')) {
                JsonStore::update($f, function ($d) use ($cid) {
                    foreach ((array) ($d['levels'] ?? []) as $lv => $rows) {
                        unset($d['levels'][$lv][$cid]);
                    }
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
