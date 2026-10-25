<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Temps forts d'un match repris du récit : chaque ligne « 65' : … » de la section Résumé devient un
 * temps fort (onglet « Compo et événements »), affiché minute par minute sur le site. Une ligne en
 * gras (convention des historiens pour les buts) ou portant un score « (1-0) » est un but.
 * Les temps forts déjà saisis ne sont jamais réécrits : seules les minutes absentes sont ajoutées, et
 * un temps fort de la même minute est seulement marqué « But » si le récit l'écrit en gras.
 */
final class RecitHighlights
{
    private const MINUTE = "/^(\\d{1,3})\\s*(?:['’]\\s*)?(?:\\+\\s*(\\d{1,2})\\s*['’]?)?\\s*[:\\-–]\\s*(.+)$/us";

    /** @return list<array{minute:string,text:string,goal:bool,score:?string}> */
    public static function extract(array $doc): array
    {
        $out = [];
        $prev = [0, 0];
        foreach (\App\Front\Fiche::splitResume((array) ($doc['sections'] ?? []))['sections'] as $s) {
            if (($s['key'] ?? '') !== 'resume') {
                continue;
            }
            foreach (self::blocks((string) $s['html']) as $inner) {
                $txt = self::plain($inner);
                if (!preg_match(self::MINUTE, $txt, $m)) {
                    continue;
                }
                $body = trim($m[3]);
                $score = preg_match('/\(\s*(\d{1,2})\s*-\s*(\d{1,2})\s*\)/u', $body, $sm) || preg_match('/(?<![\d\/])(\d{1,2})\s*-\s*(\d{1,2})\s*[.!]?$/u', $body, $sm)
                    ? $sm[1] . '-' . $sm[2] : null;
                // Score « net » : entre parenthèses, ou seul en fin de ligne après une phrase (« … sous la
                // barre. 0-1. »), pas glissé dans une phrase (« à deux doigts de signer le 0-2 »).
                $clear = $score !== null && (preg_match('/\(\s*\d{1,2}\s*-\s*\d{1,2}\s*\)/u', $body)
                    || preg_match('/(?:^|[.!?…:»"”)])\s*\d{1,2}\s*-\s*\d{1,2}\s*[.!]?$/u', $body));
                $out[] = [
                    'minute' => $m[1] . (($m[2] ?? '') !== '' ? '+' . $m[2] : ''),
                    'text' => mb_substr($body, 0, 1200),
                    'goal' => self::isGoal($inner, $body, $score, $clear, $prev),
                    'score' => $score,
                ];
            }
        }
        return $out;
    }

    /**
     * Temps forts complétés par le récit. @return array{0:list<array>,1:int,2:int} temps forts,
     * nombre ajoutés, nombre marqués « But »
     */
    public static function merge(array $existing, array $fromRecit): array
    {
        $existing = array_values(array_filter($existing, 'is_array'));
        if (!$existing) {
            return [$fromRecit, count($fromRecit), 0];
        }
        $added = 0;
        $goals = 0;
        $byMin = [];
        foreach ($existing as $i => $h) {
            $byMin[self::norm((string) ($h['minute'] ?? ''))][] = $i;
        }
        foreach ($fromRecit as $r) {
            $k = self::norm($r['minute']);
            if (isset($byMin[$k])) {
                // Même minute déjà saisie : le texte de l'historien reste, seul le but est reporté, et
                // seulement sur le temps fort qui raconte la même action (plusieurs faits à la même minute).
                $i = self::same($existing, $byMin[$k], $r['text']);
                if ($r['goal'] && $i !== null) {
                    if (empty($existing[$i]['goal'])) {
                        $existing[$i]['goal'] = true;
                        $goals++;
                    }
                    if (empty($existing[$i]['score']) && $r['score']) {
                        $existing[$i]['score'] = $r['score'];
                    }
                }
                continue;
            }
            // Minute absente : insérée à sa place (avant le premier temps fort plus tardif).
            $at = count($existing);
            foreach ($existing as $i => $h) {
                $m = self::norm((string) ($h['minute'] ?? ''));
                if ($m !== '' && self::cmp($m, $k) > 0) {
                    $at = $i;
                    break;
                }
            }
            array_splice($existing, $at, 0, [$r]);
            $byMin = [];
            foreach ($existing as $i => $h) {
                $byMin[self::norm((string) ($h['minute'] ?? ''))][] = $i;
            }
            $added++;
        }
        return [$existing, $added, $goals];
    }

    /** Applique au document ; true s'il a changé. */
    public static function apply(array &$doc): bool
    {
        if (($doc['type'] ?? '') !== 'match') {
            return false;
        }
        $from = self::extract($doc);
        if (!$from) {
            return false;
        }
        [$hl, $added, $goals] = self::merge((array) ($doc['match']['highlights'] ?? []), $from);
        // Garde-fou : plus de buts marqués que le score final du match → seuls les buts avec un score
        // entre parenthèses sont gardés (le gras n'était pas un but).
        $sc = $doc['match']['score'] ?? null;
        if (is_array($sc) && is_numeric($sc['home'] ?? null) && is_numeric($sc['away'] ?? null)
            && count(array_filter($hl, fn ($h) => !empty($h['goal']))) > (int) $sc['home'] + (int) $sc['away']) {
            $strict = array_map(fn ($h) => ['goal' => $h['goal'] && preg_match('/\(\s*\d{1,2}\s*-\s*\d{1,2}\s*\)/u', $h['text'])] + $h, $from);
            [$hl, $added, $goals] = self::merge((array) ($doc['match']['highlights'] ?? []), $strict);
        }
        if (!$added && !$goals) {
            return false;
        }
        $doc['match']['highlights'] = $hl;
        return true;
    }

    /**
     * Coche seulement « But » (et le score) sur les temps forts déjà présents que le récit désigne comme
     * buts : aucun temps fort ajouté ni retiré, aucun but décoché. Rien si le total dépasserait le score
     * final du match. @return int nombre de buts cochés
     */
    public static function markGoals(array &$doc): int
    {
        $existing = array_values(array_filter((array) ($doc['match']['highlights'] ?? []), 'is_array'));
        if (($doc['type'] ?? '') !== 'match' || !$existing) {
            return 0;
        }
        $have = array_flip(array_map(fn ($h) => self::norm((string) ($h['minute'] ?? '')), $existing));
        $from = array_values(array_filter(self::extract($doc), fn ($r) => $r['goal'] && isset($have[self::norm($r['minute'])])));
        if (!$from) {
            return 0;
        }
        [$hl, , $goals] = self::merge($existing, $from);
        $sc = $doc['match']['score'] ?? null;
        if (!$goals || (is_array($sc) && is_numeric($sc['home'] ?? null) && is_numeric($sc['away'] ?? null)
            && count(array_filter($hl, fn ($h) => !empty($h['goal']))) > (int) $sc['home'] + (int) $sc['away'])) {
            return 0;
        }
        $doc['match']['highlights'] = $hl;
        return $goals;
    }

    /** @return list<string> contenu de chaque élément de liste ou paragraphe (sinon chaque ligne). */
    private static function blocks(string $html): array
    {
        if (preg_match_all('#<(li|p)\b[^>]*>(.*?)</\1>#is', $html, $m) && $m[2]) {
            return $m[2];
        }
        return preg_split('#<br\s*/?>|\n#i', $html) ?: [];
    }

    private static function plain(string $html): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * But certain seulement :
     * - score net qui avance d'un but exactement depuis le score net précédent du récit (0-1 puis 0-2),
     *   gras ou non, quel que soit le commentaire (« en position de hors-jeu » non sifflé…) ; un score
     *   net inchangé n'est jamais un nouveau but (« coup de sifflet final (2-1) ») ;
     * - sinon score entre parenthèses « (1-0) », ou ligne en gras (convention des historiens) qui donne
     *   un score ou dit le but (« marque », « égalise », « trompe »…), hors but refusé ou annulé.
     * Au moindre doute : pas de but. $prev : dernier score net lu dans le récit (mis à jour ; un score
     * qui redescend, résumé recopié deux fois, repart de 0-0).
     */
    private static function isGoal(string $inner, string $body, ?string $score, bool $clear, array &$prev): bool
    {
        if ($score !== null && $clear) {
            [$a, $b] = array_map('intval', explode('-', $score));
            if ($a < $prev[0] || $b < $prev[1]) {
                $prev = [0, 0];
            }
            $step = [$a - $prev[0], $b - $prev[1]];
            $prev = [$a, $b];
            if ($step === [1, 0] || $step === [0, 1]) {
                return true;
            }
            if ($step === [0, 0]) {
                return false;
            }
        }
        $l = mb_strtolower($body);
        if (preg_match('/refus|annul|invalid|hors[- ]jeu/u', $l)) {
            return false;
        }
        if (preg_match('/\(\s*\d{1,2}\s*-\s*\d{1,2}\s*\)/u', $body)) {
            return true;
        }
        return self::bold($inner, $body) && ($score !== null || preg_match('/\bmarque|\bégalis|\bouvre le score|\bdouble la mise|\bréduit (le score|l[\'’]écart)|\btrompe|fait mouche|au fond des filets|\btransforme|contre son camp|\bcsc\b|franchit la ligne|petit filet|\blucarne|\bbut (de|du|d[\'’])|\bsigne (un|le|son)/u', $l));
    }

    /** Le texte (hors minute) est-il en gras pour l'essentiel ? */
    private static function bold(string $inner, string $body): bool
    {
        if (!preg_match_all('#<(strong|b)\b[^>]*>(.*?)</\1>#is', $inner, $bm)) {
            return false;
        }
        $bold = self::plain(implode(' ', $bm[2]));
        $bold = trim((string) preg_replace("/^\\d{1,3}\\s*(?:['’]\\s*)?(?:\\+\\s*\\d{1,2}\\s*['’]?)?\\s*[:\\-–]?\\s*/u", '', $bold));
        return $body !== '' && mb_strlen($bold) >= 0.6 * mb_strlen($body);
    }

    /** Temps fort de la même minute qui raconte la même action que $text (mots en commun), ou null. */
    private static function same(array $existing, array $idx, string $text): ?int
    {
        $words = fn (string $t) => array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($t)) ?: [], fn ($w) => mb_strlen($w) > 2));
        $b = $words($text);
        $best = null;
        $score = 0.0;
        foreach ($idx as $i) {
            $a = $words((string) ($existing[$i]['text'] ?? ''));
            if (!$a || !$b) {
                continue;
            }
            $j = count(array_intersect($a, $b)) / count(array_unique(array_merge($a, $b)));
            if ($j > $score) {
                [$best, $score] = [$i, $j];
            }
        }
        return $score >= 0.5 ? $best : null;
    }

    private static function norm(string $m): string
    {
        return (string) preg_replace('/[^\d+]/', '', $m);
    }

    private static function cmp(string $a, string $b): int
    {
        [$a1, $a2] = array_map('intval', explode('+', $a . '+0'));
        [$b1, $b2] = array_map('intval', explode('+', $b . '+0'));
        return [$a1, $a2] <=> [$b1, $b2];
    }
}
