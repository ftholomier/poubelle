<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Core\Settings;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Seeds;
use App\Front\Community;
use App\Front\Fiche;

/**
 * Les Après-midi Bonal : chaque mois, un kit souvenirs à imprimer en gros caractères, pour
 * partager les grandes heures du club avec les anciens supporters (en famille, au club, à la
 * médiathèque, en maison de retraite) : le grand match d'il y a N ans, « Vous les
 * reconnaissez ? », le quiz des anciens, « Racontez-nous ». Le match est choisi
 * automatiquement (ou par les historiens : data/collections/souvenirs.json).
 *
 * « Ils y étaient » : les témoignages validés et rattachés à une fiche de match s'affichent
 * sur cette fiche (index storage/inbox/temoignages.json, refait à chaque décision).
 */
final class Souvenirs
{
    public const INDEX = STORAGE_PATH . '/inbox/temoignages.json';
    /** Âge (en années) qui touche le plus les anciens supporters : bonus de choix. */
    public const GOLDEN = [30, 60];
    /** Fichiers utilisés (modifiables pour les tests) : index des témoignages, dossier des contributions. */
    public static string $index = self::INDEX;
    public static string $inbox = Community::INBOX;
    /** Choix des historiens imposés (tests) ; null : data/collections/souvenirs.json. */
    public static ?array $choices = null;

    private static function choices(): array
    {
        return self::$choices ?? (array) Collections::get('souvenirs', []);
    }

    public static function validMonth(string $ym): bool
    {
        return (bool) preg_match('/^(19|20)\d{2}-(0[1-9]|1[0-2])$/', $ym);
    }

    /** Mois en toutes lettres : « octobre 2026 ». */
    public static function monthLabel(string $ym): string
    {
        return preg_replace('/^1er |^\d+ /u', '', date_fr($ym . '-01'));
    }

    // ------------------------------------------------------------------ le match du mois

    /**
     * Matchs joués ce mois-là les années passées, du plus marquant au moins marquant :
     * intérêt (Rétro-Direct), anniversaire rond, âge cher aux anciens (30 à 60 ans).
     */
    public static function candidates(string $ym, int $limit = 6): array
    {
        [$year, $month] = array_map('intval', explode('-', $ym));
        $out = [];
        foreach (Derived::get()['matches'] ?? [] as $id => $dm) {
            if (empty($dm['v']) || !preg_match('/^(\d{4})-(\d{2})-\d{2}$/', (string) ($dm['date'] ?? ''), $d) || (int) $d[2] !== $month || (int) $d[1] >= $year || (int) ($dm['hl'] ?? 0) < 3) {
                continue;
            }
            $s = Index::get((int) $id);
            if (!$s) {
                continue;
            }
            $ago = $year - (int) $d[1];
            $score = RetroDirect::interest($dm, $s) + (in_array($ago, RetroDirect::ROUND, true) ? 10 : 0)
                + ($ago >= self::GOLDEN[0] && $ago <= self::GOLDEN[1] ? 8 : 0) + (!empty($s['image']) && !Index::isPlaceholderImage($s['image']) ? 4 : -10);
            $out[] = ['id' => (int) $id, 'ago' => $ago, 'score' => round($score, 1), 's' => $s, 'dm' => $dm];
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $a['id'] <=> $b['id']);
        return array_slice($out, 0, $limit);
    }

    /** Le match du mois : choix des historiens s'il est valable, sinon le plus marquant. */
    public static function match(string $ym): ?array
    {
        $pick = (int) (self::choices()[$ym]['match'] ?? 0);
        $cands = self::candidates($ym, 30);
        foreach ($cands as $c) {
            if ($c['id'] === $pick) {
                return $c + ['chosen' => true];
            }
        }
        if ($pick && ($s = Index::get($pick)) && $s['type'] === 'match' && Index::visible($s)) {
            $dm = Derived::match($pick) ?? [];
            return ['id' => $pick, 'ago' => (int) substr($ym, 0, 4) - (int) substr((string) ($dm['date'] ?? $ym), 0, 4), 'score' => 0, 's' => $s, 'dm' => $dm, 'chosen' => true];
        }
        return $cands[0] ?? null;
    }

    /** Choix des historiens pour un mois (0 : automatique), avec un mot d'introduction facultatif. */
    public static function choose(string $ym, int $matchId, string $intro, ?array $user): void
    {
        $all = (array) Collections::get('souvenirs', []);
        if ($matchId || trim($intro) !== '') {
            $all[$ym] = array_filter(['match' => $matchId ?: null, 'intro' => mb_substr(trim($intro), 0, 500), 'by' => (string) ($user['name'] ?? ''), 'at' => date('c')], fn ($v) => $v !== null && $v !== '');
        } else {
            unset($all[$ym]);
        }
        ksort($all);
        Collections::save('souvenirs', $all, $user, 'Kit souvenirs : ' . self::monthLabel($ym));
    }

    // ------------------------------------------------------------------ le kit

    /**
     * Contenu du kit d'un mois : match (fiche, récit), photos à reconnaître, quiz, questions
     * pour raconter, adresse de retour.
     */
    public static function kit(string $ym): ?array
    {
        $c = self::match($ym);
        if (!$c) {
            return null;
        }
        $doc = Fiche::localizeDoc(Fiches::get($c['id']) ?? []);
        $m = $doc['match'] ?? [];
        $year = (int) substr((string) ($m['date'] ?? ''), 0, 4);
        $rows = Fiche::lineupRows($doc, $m['lineup']['rows'] ?? []);
        $intro = trim((string) (self::choices()[$ym]['intro'] ?? ''));
        $story = [];
        foreach ($m['highlights'] ?? [] as $h) {
            $t = trim((string) preg_replace('/\s+/u', ' ', plain((string) ($h['text'] ?? ''))));
            if ($t !== '' && preg_match('/^\d/', (string) ($h['minute'] ?? ''))) {
                $story[] = ['min' => (string) $h['minute'], 'text' => $t, 'goal' => !empty($h['goal'])];
            }
        }
        // Les buts d'abord, puis des actions, dans l'ordre du match : 8 au plus (la page fait le reste).
        $keep = array_slice(array_filter($story, fn ($x) => $x['goal']), 0, 8, true);
        foreach ($story as $i => $x) {
            if (count($keep) >= 6) {
                break;
            }
            $keep[$i] = $x;
        }
        ksort($keep);
        $address = trim(plain_text((string) (Settings::get('legal.address', '') ?: Settings::get('dons.org_address', ''))));
        return [
            'ym' => $ym, 'month' => self::monthLabel($ym), 'match' => $c, 'doc' => $doc, 'm' => $m, 'year' => $year,
            'intro' => $intro, 'story' => array_values($keep), 'breve' => self::firstBreve($m),
            'faces' => self::faces($rows, $year, $ym),
            'quiz' => self::quiz($ym, $doc, $rows),
            'prompts' => self::prompts(),
            'contribute' => base_url() . url('/souvenir/' . $c['id'] . '/'),
            'address' => $address, 'email' => (string) (Settings::get('legal.email', '') ?: Settings::get('general.contact_email', '')),
        ];
    }

    private static function firstBreve(array $m): string
    {
        foreach ($m['breves'] ?? [] as $b) {
            $t = trim((string) preg_replace('/\s+/u', ' ', plain(is_array($b) ? (string) ($b['text'] ?? '') : (string) $b)));
            if ($t !== '') {
                return mb_strlen($t) > 420 ? rtrim(mb_substr($t, 0, 417)) . '…' : $t;
            }
        }
        return '';
    }

    /** « Vous les reconnaissez ? » : six joueurs de l'époque avec une vraie photo (ceux du match d'abord). */
    public static function faces(array $rows, int $year, string $ym): array
    {
        $tot = Derived::get()['person_totals'] ?? [];
        $picked = [];
        $add = function (?array $s) use (&$picked) {
            if ($s && $s['type'] === 'personne' && Index::visible($s) && !empty($s['image']) && !Index::isPlaceholderImage($s['image']) && !isset($picked[$s['id']])) {
                $picked[$s['id']] = ['id' => (int) $s['id'], 'name' => (string) ($s['p']['name'] ?: $s['title']), 'image' => $s['image'], 'line' => $s['p']['line'] ?? null];
            }
        };
        foreach ($rows as $r) {
            if (count($picked) >= 6) {
                break;
            }
            if (in_array($r['position'] ?? '', ['G', 'D', 'M', 'A', 'R'], true) && !empty($r['pid'])) {
                $add(Index::get((int) $r['pid']));
            }
        }
        if (count($picked) < 6 && $year) {
            $era = array_filter(Index::published('personne'), fn ($s) => in_array('joueur', $s['p']['roles'] ?? [], true)
                && ($s['p']['arrival'] ?? 9999) <= $year + 1 && ($s['p']['departure'] ?? 0) >= $year - 1);
            usort($era, fn ($a, $b) => ($tot[$b['id']]['matches'] ?? 0) <=> ($tot[$a['id']]['matches'] ?? 0));
            foreach ($era as $s) {
                if (count($picked) >= 6) {
                    break;
                }
                $add($s);
            }
        }
        // Mélangés (toujours de la même façon pour un mois donné), pour que l'ordre n'aide pas.
        $list = array_values($picked);
        $rnd = new \Random\Randomizer(new \Random\Engine\Mt19937(crc32('visages|' . $ym)));
        return $list ? $rnd->shuffleArray($list) : [];
    }

    /** Le quiz des anciens : deux questions sur le match du mois, puis celles du quiz du site. */
    public static function quiz(string $ym, array $doc, array $rows): array
    {
        $rnd = new \Random\Randomizer(new \Random\Engine\Mt19937(crc32('quiz|' . $ym)));
        $m = $doc['match'] ?? [];
        $sh = !empty($m['sochaux_home']);
        $home = (string) ($m['home']['name'] ?? '');
        $away = (string) ($m['away']['name'] ?? '');
        $out = [];
        // Qui a ouvert le score pour Sochaux ?
        $sochaux = $sh ? $home : $away;
        foreach (RetroDirect::textGoals($m, $home, $away) as $g) {
            if ($g['side'] === ($sh ? 0 : 1) && $g['who'] !== '') {
                $others = [];
                foreach ($rows as $r) {
                    // Nom de famille de la fiche du joueur (« Manac'h »), sinon celui de la composition.
                    $ps = !empty($r['pid']) ? Index::get((int) $r['pid']) : null;
                    $n = trim((string) ($ps['p']['last'] ?? '')) ?: mb_convert_case(mb_strtolower((string) ($r['short'] ?? '')), MB_CASE_TITLE);
                    if (in_array($r['position'] ?? '', ['D', 'M', 'A'], true) && $n !== '' && mb_stripos($g['who'], $n) === false && mb_stripos($n, $g['who']) === false) {
                        $others[$n] = true;
                    }
                }
                $others = array_keys($others);
                if (count($others) >= 2) {
                    $wrong = array_slice($rnd->shuffleArray($others), 0, 2);
                    $choices = $rnd->shuffleArray(array_merge([$g['who']], $wrong));
                    $out[] = ['q' => t('Ce jour-là, qui a marqué le premier but de {team} ?', ['team' => $sochaux]), 'a' => $choices,
                        'c' => array_search($g['who'], $choices, true), 'fact' => t('{who}, à la {min} minute.', ['who' => $g['who'], 'min' => Chiffres::nth((int) $g['m'][0])])];
                }
                break;
            }
        }
        // Combien de spectateurs ?
        $sp = (int) ($m['spectators'] ?? 0);
        if ($sp >= 1000 && $sp <= 90000) {
            $round = fn (float $x) => (int) (round($x / 500) * 500);
            $choices = array_values(array_unique([$round($sp), $round($sp * 0.55), $round($sp * 1.7)]));
            if (count($choices) === 3) {
                $fmt = fn ($n) => number_format($n, 0, ',', ' ');
                $right = $fmt($round($sp));
                $choices = $rnd->shuffleArray(array_map($fmt, $choices));
                $out[] = ['q' => t('Environ combien de spectateurs y avait-il au stade ?'), 'a' => $choices, 'c' => array_search($right, $choices, true),
                    'fact' => t('{n} spectateurs exactement.', ['n' => $fmt($sp)])];
            }
        }
        // Le quiz du site (questions actives), tirées pour le mois.
        $pool = array_values(array_filter((array) Collections::get('quiz', Seeds::quiz()), fn ($q) => ($q['active'] ?? true) && !empty($q['q']) && count($q['a'] ?? []) >= 3));
        $pool = array_map(fn ($q) => Collections::loc($q, ['q', 'a', 'fact']), $pool);
        foreach ($rnd->shuffleArray($pool) as $q) {
            if (count($out) >= 6) {
                break;
            }
            $a = array_values($q['a']);
            $right = $a[(int) $q['c']] ?? null;
            if ($right === null) {
                continue;
            }
            // Trois réponses au plus, la bonne comprise : plus lisible sur papier.
            $wrong = array_values(array_filter($a, fn ($x) => $x !== $right));
            $choices = array_merge([$right], array_slice($rnd->shuffleArray($wrong), 0, 2));
            sort($choices, SORT_NATURAL);
            $out[] = ['q' => (string) $q['q'], 'a' => $choices, 'c' => array_search($right, $choices, true), 'fact' => (string) ($q['fact'] ?? '')];
        }
        return $out;
    }

    /** Questions pour faire naître les souvenirs (page « Racontez-nous »). */
    public static function prompts(): array
    {
        return [
            t('Étiez-vous au stade ce jour-là, ou à l’écoute de la radio ? Avec qui ?'),
            t('Quel joueur de cette époque vous a le plus marqué, et pourquoi ?'),
            t('Comment alliez-vous au stade Bonal ? Où vous placiez-vous ?'),
            t('Gardez-vous un billet, une photo, une écharpe ou un fanion de ces années-là ?'),
        ];
    }

    // ------------------------------------------------------------------ « Ils y étaient »

    /** Tous les témoignages publiés, par fiche de match. @return array<int,list<array>> */
    public static function allTestimonies(): array
    {
        $d = JsonStore::read(self::$index, []);
        return is_array($d) ? $d : [];
    }

    /** Témoignages publiés sur la fiche d'un match. @return list<array{name:string,text:string,at:string}> */
    public static function testimonies(int $matchId): array
    {
        $d = JsonStore::read(self::$index, []);
        return is_array($d) ? array_values((array) ($d[$matchId] ?? [])) : [];
    }

    /** Refait l'index des témoignages publiés (après chaque décision sur une contribution). */
    public static function reindex(): int
    {
        $idx = [];
        foreach (glob(self::$inbox . '/contributions/*/contribution.json') ?: [] as $f) {
            $c = JsonStore::read($f, null);
            if (!is_array($c) || ($c['status'] ?? '') !== 'valide' || empty($c['public']) || empty($c['fiche_id'])) {
                continue;
            }
            $text = trim((string) ($c['public_text'] ?? $c['description'] ?? ''));
            if ($text === '') {
                continue;
            }
            $idx[(int) $c['fiche_id']][] = ['ticket' => (string) $c['ticket'], 'name' => (string) ($c['public_name'] ?? self::shortName((string) ($c['name'] ?? ''))),
                'text' => mb_substr($text, 0, 1500), 'at' => (string) ($c['handled']['at'] ?? $c['at'] ?? '')];
        }
        foreach ($idx as &$list) {
            usort($list, fn ($a, $b) => strcmp($a['at'], $b['at']));
        }
        unset($list);
        JsonStore::write(self::$index, $idx);
        return array_sum(array_map('count', $idx));
    }

    /** « Jean-Pierre Martin » → « Jean-Pierre M. » (signature publique par défaut). */
    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        if (count($parts) < 2) {
            return trim($name);
        }
        $last = array_pop($parts);
        return implode(' ', $parts) . ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.';
    }
}
