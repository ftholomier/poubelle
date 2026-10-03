<?php
declare(strict_types=1);

namespace App\Data;

/**
 * Lecture des cellules d'une composition saisies en texte libre :
 * buts « 33', 78' », « ⚽ 45'+2 s.p. », « 31' csc », « x2 » ; remplacements « ↑ 46' ↓ 80' »,
 * « Entrée 60' » ; cartons « J 35' R 80' », « 🟨 47' ».
 * Une seule règle, utilisée par la reprise WordPress et par le back-office.
 * (Classe autonome : chargée aussi par les scripts de reprise, sans le reste de l'application.)
 */
final class Lineup
{
    /** Une minute : « 33' », « 45'+2 », « 90+3' », « 12ème ». */
    private const MINUTE = "(\\d{1,3})\\s*(?:['’]{1,2}|ème|e(?![a-zé]))?(?:\\s*\\+\\s*(\\d{1,2})\\s*['’]?)?";

    /**
     * Minutes d'un texte, avec le texte qui précède chacune (depuis la minute précédente)
     * et celui qui la suit (jusqu'à la suivante).
     * @return list<array{min:string, before:string, after:string}>
     */
    private static function tokens(string $s): array
    {
        if (!preg_match_all('/' . self::MINUTE . '/u', $s, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $out = [];
        $prevEnd = 0;
        foreach ($m as $i => $x) {
            $start = $x[0][1];
            $end = $start + strlen($x[0][0]);
            $next = $m[$i + 1][0][1] ?? strlen($s);
            $out[] = [
                'min' => $x[1][0] . (isset($x[2]) && $x[2][0] !== '' ? '+' . $x[2][0] : ''),
                'before' => substr($s, $prevEnd, $start - $prevEnd),
                'after' => substr($s, $end, $next - $end),
            ];
            $prevEnd = $end;
        }
        return $out;
    }

    /** @return list<string> */
    public static function minutes(string $s): array
    {
        return array_column(self::tokens($s), 'min');
    }

    /**
     * Buts du joueur et buts contre son camp (comptés pour l'adversaire).
     * Minute inconnue : chaîne vide (« ⚽ » seul, « x2 »). « TàB » (tirs au but) : aucun but.
     * @return array{goals: list<string>, own: list<string>}
     */
    public static function goals(string $s): array
    {
        $t = trim($s);
        if ($t === '' || preg_match('/^(t\s*\.?\s*[aà]\s*\.?\s*b\s*\.?|tab)$/iu', $t)) {
            return ['goals' => [], 'own' => []];
        }
        if (preg_match('/^[x×]\s*(\d{1,2})$/u', $t, $m)) {
            return ['goals' => array_fill(0, (int) $m[1], ''), 'own' => []];
        }
        $goals = $own = [];
        foreach (self::tokens($t) as $tk) {
            if (preg_match('/c\s*\.?\s*s\s*\.?\s*c|contre son camp/iu', $tk['after'])) {
                $own[] = $tk['min'];
            } else {
                $goals[] = $tk['min'];
            }
        }
        for ($balls = mb_substr_count($t, '⚽'); $balls > count($goals) + count($own);) {
            $goals[] = '';
        }
        return ['goals' => $goals, 'own' => $own];
    }

    /**
     * Entrée et sortie : chaque minute prend le sens du repère qui la précède.
     * @return array{in: ?string, out: ?string}
     */
    public static function subs(string $s): array
    {
        $in = $out = null;
        foreach (self::tokens($s) as $tk) {
            if (preg_match('/↑|🔺|⬆|ent[rée]|rentr/iu', $tk['before'])) {
                $in ??= $tk['min'];
            } elseif (preg_match('/↓|🔻|⬇|sor|rempl/iu', $tk['before'])) {
                $out ??= $tk['min'];
            }
        }
        return ['in' => $in, 'out' => $out];
    }

    /**
     * Cartons : une minute suit la couleur annoncée avant elle (« J 9' 9' R 80' »).
     * Minute sans couleur connue : ignorée (le texte d'origine reste affiché).
     * @return array{yellow: list<string>, red: list<string>}
     */
    public static function cards(string $s): array
    {
        $yellow = $red = [];
        $color = null;
        foreach (self::tokens($s) as $tk) {
            if (preg_match('/🟥|(?<!\pL)C?R(?!\pL)|rouge|carton r/iu', $tk['before'])) {
                $color = 'r';
            } elseif (preg_match('/🟨|(?<!\pL)C?J(?!\pL)|jaune|carton j/iu', $tk['before'])) {
                $color = 'y';
            }
            if ($color === 'r') {
                $red[] = $tk['min'];
            } elseif ($color === 'y') {
                $yellow[] = $tk['min'];
            }
        }
        return ['yellow' => $yellow, 'red' => $red];
    }

    /** Champs calculés d'une ligne à partir de ses trois textes. */
    public static function parse(string $goalsText, string $subText, string $cardsText): array
    {
        $g = self::goals($goalsText);
        $sub = self::subs($subText);
        $c = self::cards($cardsText);
        return [
            'goals' => $g['goals'],
            'own_goals' => $g['own'],
            'sub_in' => $sub['in'],
            'sub_out' => $sub['out'],
            'yellow' => $c['yellow'],
            'red' => $c['red'],
        ];
    }
}
