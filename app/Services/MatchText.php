<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\Collections;
use App\Data\Names;

/**
 * Cohérence des textes d'une fiche de match avec son en-tête : une fiche dont les textes
 * racontent un autre match (compte rendu copié d'un autre match, fiche dupliquée dont seul
 * l'en-tête a été changé) est repérée — l'adversaire n'y est jamais nommé, d'autres clubs le
 * sont. Signalée dans Qualité ; l'IA ne la raconte pas tant qu'elle n'est pas corrigée.
 */
final class MatchText
{
    /** Mots qui ne désignent pas un club à eux seuls (« Sporting », « Stade »…). */
    private const GENERIC = ['football', 'club', 'stade', 'racing', 'sporting', 'olympique', 'olympic', 'union', 'athletic', 'athletico',
        'association', 'sportive', 'sport', 'sports', 'real', 'royal', 'etoile', 'star', 'city', 'united', 'saint', 'sainte', 'ville',
        'avenir', 'jeunesse', 'esperance', 'entente', 'amicale', 'reserve', 'equipe', 'national', 'nationale', 'ligue', 'coupe', 'france'];
    /** Texte trop court pour juger (en mots). */
    private const MIN_WORDS = 60;
    private const MONTHS = ['janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12];
    private const WEEKDAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    /** Écart de date au-delà duquel changer la date d'une fiche remplie, c'est changer de match (jours). */
    private const SAME_MATCH_DAYS = 2;

    private static ?array $clubs = null;

    /**
     * En-tête d'un match tel qu'il s'affiche (site, PDF, assistant) : la date et le tour en toutes
     * lettres repris de l'ancien site ne sont gardés que s'ils concordent avec la date, la compétition
     * et la journée saisies ; sinon la date et le tour saisis sont affichés. Un en-tête resté d'un autre
     * match ne s'affiche plus, et la journée saisie au back-office s'affiche aussi sur les fiches sans
     * texte d'origine. En anglais, date et journée viennent toujours des champs saisis.
     */
    public static function header(array $doc, ?bool $en = null): array
    {
        $m = $doc['match'] ?? null;
        if (!is_array($m)) {
            return $doc;
        }
        $en ??= I18n::isEn();
        $date = self::str($m['date'] ?? '');
        if (self::dateIssue($m) || ($en && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))) {
            $doc['match']['date_text'] = ''; // date en toutes lettres d'après la date saisie (date_fr)
        }
        // Journée saisie : « J15 », « 1/8e » (traduites en anglais) ; autre tour : tel que saisi.
        $coded = (bool) preg_match('/^(?:J\d+$|1\s*\/)/i', trim(self::str($m['round'] ?? '')));
        if (trim(self::str($m['round_text'] ?? '')) === '' || self::roundIssue($m) || ($en && $coded)) {
            $doc['match']['round_text'] = self::roundLabel($m, $en);
        }
        return $doc;
    }

    private static function str(mixed $v): string
    {
        return is_scalar($v) ? (string) $v : '';
    }

    /**
     * Date en toutes lettres de l'ancien site (« Vendredi 21 aout 2026 ») qui ne concorde pas avec la
     * date saisie : gravité, repère et message ; null si elle concorde (ou s'il n'y a rien à comparer).
     * @return ?array{sev:string,ref:string,msg:string}
     */
    public static function dateIssue(array $m): ?array
    {
        $date = self::str($m['date'] ?? '');
        $text = trim(self::str($m['date_text'] ?? ''));
        if ($text === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $d) || !checkdate((int) $d[2], (int) $d[3], (int) $d[1])) {
            return null;
        }
        $fr = "$d[3]/$d[2]/$d[1]";
        $a = Names::ascii($text);
        $parsed = preg_match('/(\d{1,2})(?:er)?\s+([a-z]+)\s+(\d{4})/', $a, $x) && isset(self::MONTHS[$x[2]]) && checkdate(self::MONTHS[$x[2]], (int) $x[1], (int) $x[3])
            ? sprintf('%04d-%02d-%02d', $x[3], self::MONTHS[$x[2]], $x[1]) : null;
        if ($parsed === null) {
            return ['sev' => 'basse', 'ref' => 'illisible', 'msg' => "Date en toutes lettres illisible (« $text ») : le site affiche la date de la fiche ($fr)"];
        }
        if ($parsed !== $date) {
            return ['sev' => 'moyenne', 'ref' => 'ecart', 'msg' => "Date en toutes lettres (« $text ») ≠ date de la fiche ($fr) : le site affiche la date de la fiche, à vérifier"];
        }
        $day = (int) date('N', (int) strtotime($date));
        $w = array_search((string) preg_replace('/[^a-z].*$/s', '', $a), self::WEEKDAYS, true);
        if ($w !== false && $w + 1 !== $day) {
            return ['sev' => 'basse', 'ref' => 'jour', 'msg' => "Jour de la semaine incohérent dans « $text » : le $fr était un " . self::WEEKDAYS[$day - 1] . ' (jour ou date à vérifier ; le site affiche le bon jour)'];
        }
        return null;
    }

    /**
     * Tour en toutes lettres de l'ancien site qui contredit la fiche : journée de championnat pour un
     * match amical (en-tête resté d'un autre match), journée éloignée de celle saisie, autre division,
     * autre tour de coupe. Gravité, repère et message ; null s'il concorde.
     * @return ?array{sev:string,ref:string,msg:string}
     */
    public static function roundIssue(array $m): ?array
    {
        $text = trim(self::str($m['round_text'] ?? ''));
        if ($text === '') {
            return null;
        }
        $t = Names::ascii($text);
        $round = trim(self::str($m['round'] ?? ''));
        $label = trim(self::str($m['competition_label'] ?? ''));
        if (str_contains(Names::ascii(self::str($m['competition'] ?? '') . ' ' . $label), 'amical')
            && str_contains($t, 'journee') && preg_match('/\b(?:ligue|championnat|division|national|[ld][123])\b/', $t)) {
            return ['sev' => 'moyenne', 'ref' => 'amical', 'msg' => "Tour en toutes lettres « $text » pour un match amical : en-tête resté d’un autre match ?"];
        }
        // Une ou deux journées d'écart : match en retard (journée officielle dans le texte, ordre
        // des matchs dans « J12 ») ; au-delà, le tour d'un autre match.
        if (preg_match('/^J0*(\d+)$/i', $round, $r) && preg_match('/\b(\d+)\s*(?:e|eme|er|ere|re)?\s*journee/', $t, $x) && abs((int) $x[1] - (int) $r[1]) > 2) {
            return ['sev' => 'moyenne', 'ref' => 'journee', 'msg' => "Tour en toutes lettres « $text » ≠ journée de la fiche ($round)"];
        }
        $lv = self::level(Names::ascii($label));
        $lt = self::level($t);
        if ($lv !== null && $lt !== null && $lv !== $lt) {
            return ['sev' => 'moyenne', 'ref' => 'division', 'msg' => "Tour en toutes lettres « $text » pour un match de $label"];
        }
        $cr = self::cupRound(Names::ascii($round));
        $ct = self::cupRound($t);
        if ($cr && $ct && $cr[0] === $ct[0] && $cr[1] !== $ct[1]) {
            return ['sev' => 'moyenne', 'ref' => 'coupe', 'msg' => "Tour en toutes lettres « $text » ≠ tour de la fiche ($round)"];
        }
        return null;
    }

    /**
     * Tour affiché d'après la journée ou le tour saisi : « J15 » → « 15e journée », « 1/8e aller » →
     * « 8e de finale aller » ; rien quand il redit la compétition (« Amical »).
     */
    public static function roundLabel(array $m, bool $en = false): string
    {
        $r = trim(self::str($m['round'] ?? ''));
        foreach ([$m['competition_label'] ?? '', $m['competition'] ?? ''] as $c) {
            if ($r === '' || mb_strtolower(trim(self::str($c))) === mb_strtolower($r)) {
                return '';
            }
        }
        if (preg_match('/^J0*(\d+)$/i', $r, $x)) {
            $n = (int) $x[1];
            return $en ? "Matchday $n" : ($n === 1 ? '1re' : $n . 'e') . ' journée';
        }
        if (preg_match('/^1\s*\/\s*(\d+)\s*(?:es|e|è|ème)?(?=\s|$)\s*(.*)$/u', $r, $x)) {
            $n = (int) $x[1];
            $leg = trim($x[2]);
            if ($en) {
                $leg = strtr($leg, ['aller' => 'first leg', 'retour' => 'second leg']);
                return trim(($n === 2 ? 'Semi-final' : ($n === 4 ? 'Quarter-final' : 'Round of ' . 2 * $n)) . ($leg !== '' ? ', ' . $leg : ''));
            }
            return trim(($n === 2 ? 'demi-finale' : ($n === 4 ? 'quart de finale' : $n . 'e de finale')) . ' ' . $leg);
        }
        return $r;
    }

    /**
     * Fiche de match déjà remplie dont on change l'adversaire ou la date : réutiliser la fiche d'un
     * match pour un autre laisserait ses textes, sa composition et ses photos sous l'en-tête du
     * nouveau match. Changements (pour l'historique) et message à confirmer ; null pour une fiche
     * vide, un premier remplissage ou une date corrigée de deux jours au plus.
     * @return ?array{changes:list<string>,text:string}
     */
    public static function switched(array $before, array $after): ?array
    {
        $a = $before['match'] ?? null;
        $b = $after['match'] ?? null;
        if (!is_array($a) || !is_array($b)) {
            return null;
        }
        $opp = fn (array $m) => trim(self::str(($m['sochaux_home'] ?? true) ? ($m['away']['name'] ?? '') : ($m['home']['name'] ?? '')));
        [$o1, $o2] = [$opp($a), $opp($b)];
        $changes = $said = [];
        // Même club sous une autre graphie (« Paris SG », « Paris Saint-Germain », variantes du référentiel) : pas un autre match.
        $sameClub = fn () => Names::clubKey($o1) === Names::clubKey($o2) || self::sameClub($o1, $o2)
            || (($c1 = self::clubOf($o1)) && ($c2 = self::clubOf($o2)) && ($c1['id'] ?? 1) === ($c2['id'] ?? 2));
        if ($o1 !== '' && $o2 !== '' && !$sameClub()) {
            $changes[] = "adversaire $o1 → $o2";
            $said[] = "l’adversaire ($o1 → $o2)";
        }
        $day = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', self::str($v)) ? strtotime((string) $v) : false;
        if (($t1 = $day($a['date'] ?? '')) && ($t2 = $day($b['date'] ?? '')) && abs($t2 - $t1) > self::SAME_MATCH_DAYS * 86400 + 7200) {
            $changes[] = 'date ' . date('d/m/Y', $t1) . ' → ' . date('d/m/Y', $t2);
            $said[] = 'la date (' . date('d/m/Y', $t1) . ' → ' . date('d/m/Y', $t2) . ')';
        }
        $content = $changes ? self::content($before) : [];
        if (!$content) {
            return null;
        }
        return ['changes' => $changes, 'text' => 'Vous changez ' . implode(' et ', $said) . ' d’une fiche déjà remplie (' . implode(', ', $content) . ').'
            . ' Une fiche raconte un seul match : tout ce contenu passerait sous l’en-tête du nouveau match. S’il s’agit d’un autre match, annulez et créez une nouvelle fiche (+ Nouveau › Fiche match). S’il s’agit de corriger une erreur de saisie de ce même match, confirmez.'];
    }

    /** Ce que contient déjà une fiche de match : textes, composition, temps forts, réactions, photos. */
    private static function content(array $doc): array
    {
        $m = $doc['match'];
        $html = (string) ($doc['intro'] ?? '');
        foreach (is_array($doc['sections'] ?? null) ? $doc['sections'] : [] as $s) {
            $html .= ' ' . (is_array($s) ? (string) ($s['html'] ?? '') : '');
        }
        $count = fn ($k) => count(array_filter(is_array($m[$k] ?? null) ? $m[$k] : [], fn ($x) => trim(is_array($x) ? (string) ($x['text'] ?? '') : (string) $x) !== ''));
        $players = count(array_filter((array) ($m['lineup']['rows'] ?? []), fn ($r) => trim((string) ($r['name'] ?? '')) !== ''));
        $media = count((array) ($doc['gallery'] ?? [])) + count((array) ($doc['videos'] ?? []));
        $n = fn (int $k, string $one, string $many) => $k . ' ' . ($k > 1 ? $many : $one);
        return array_values(array_filter([
            str_word_count(self::norm(strip_tags(str_replace('<', ' <', $html)))) >= 30 ? 'des textes' : '',
            $players ? 'une composition de ' . $n($players, 'joueur', 'joueurs') : '',
            ($h = $count('highlights')) ? $n($h, 'temps fort', 'temps forts') : '',
            ($r = $count('reactions')) ? $n($r, 'réaction', 'réactions') : '',
            $media ? $n($media, 'photo ou vidéo', 'photos et vidéos') : '',
        ]));
    }

    /** Division nommée dans un libellé : 1 (D1, Ligue 1), 2 (D2, Ligue 2), 3 (National) ; null sinon. */
    private static function level(string $s): ?int
    {
        return match (true) {
            (bool) preg_match('/\b(?:d1|l1|division 1|ligue 1|premiere division)\b/', $s) => 1,
            (bool) preg_match('/\b(?:d2|l2|division 2|ligue 2|deuxieme division)\b/', $s) => 2,
            (bool) preg_match('/\b(?:national|n1|d3|division 3)\b/', $s) => 3,
            default => null,
        };
    }

    /** Tour de coupe : ['finale', 8] pour « 1/8e » ou « 8e de finale », ['tour', 6] pour « 6e tour ». */
    private static function cupRound(string $s): ?array
    {
        return match (true) {
            (bool) preg_match('/\b1\s*\/\s*(\d+)/', $s, $x), (bool) preg_match('/\b(\d+)\s*(?:e|es|eme|emes)?\s+(?:(?:aller|retour)\s+)?de\s+(?:finale|coupe)/', $s, $x) => ['finale', (int) $x[1]],
            (bool) preg_match('/\b(\d+)\s*(?:e|er|eme|ere|re)?\s+tour\b/', $s, $x) => ['tour', (int) $x[1]],
            default => null,
        };
    }

    /**
     * Message de l'alerte « texte d'un autre match ? », ou null si les textes vont avec l'en-tête.
     * Prudent, car beaucoup de fiches racontent aussi les coulisses de la semaine (transferts,
     * match précédent, observateurs en tribune) : il faut un texte assez long qui ne nomme jamais
     * l'adversaire (ni son nom, ni une variante, ni un mot distinctif de son nom), plus une preuve
     * nette — une réaction d'après-match de l'entraîneur, du gardien ou d'un joueur d'un autre
     * club, ou une journée de championnat racontée pour un match amical.
     */
    public static function otherMatch(array $doc): ?string
    {
        $m = $doc['match'] ?? null;
        if (!is_array($m)) {
            return null;
        }
        $home = trim((string) ($m['home']['name'] ?? ''));
        $away = trim((string) ($m['away']['name'] ?? ''));
        $opp = self::isSochaux($home) ? $away : (self::isSochaux($away) ? $home : '');
        if ($opp === '') {
            return null;
        }
        [$raw, $reactions] = self::texts($doc);
        // Les preuves d'abord (rares, et rapides à chercher) : la liste des clubs et le texte entier
        // ne sont examinés qu'ensuite (ouverture d'une fiche au back-office sans frais).
        $proof = [];
        $re = null;
        $load = function () use (&$re, &$ids, &$names, &$club, $opp) {
            [$re, $ids, $names] = self::clubs();
            $club = self::clubOf($opp);
        };
        $isOpp = function (string $id) use (&$club, &$names, $opp) {
            return $id === ($club['id'] ?? null) || self::sameClub($names[$id], $opp) || self::isSochaux($names[$id]);
        };
        // Réaction d'après-match de l'entraîneur, du gardien, du président ou d'un joueur d'un autre club.
        if (preg_match('/(?:entra[iî]neur|coach|gardien|pr[ée]sident|joueur)s?\s+(?:de|du|d[\'’])/iu', $reactions)) {
            $load();
            if ($re !== '' && preg_match_all('/ (entraineur|coach|gardien|president|joueur)s? (?:de |du |d )(?:l |la |le )?' . substr($re, 1, -1) . '/', self::norm($reactions), $rm, PREG_SET_ORDER)) {
                foreach ($rm as $r) {
                    $id = $ids[$r[2]] ?? null;
                    if ($id !== null && !$isOpp($id)) {
                        $who = ['entraineur' => 'de l’entraîneur', 'coach' => 'de l’entraîneur', 'gardien' => 'du gardien', 'president' => 'du président', 'joueur' => 'd’un joueur'][$r[1]];
                        $proof[] = "la réaction $who de " . $names[$id];
                        break;
                    }
                }
            }
        }
        // Une journée de championnat racontée pour un match amical.
        $comp = Names::ascii(($m['competition'] ?? '') . ' ' . ($m['competition_label'] ?? ''));
        if (str_contains($comp, 'amical') && preg_match('/\b((?:cette|la|de la)\s+\S+\s+journ[ée]e\s+(?:de\s+|du\s+)?(?:ligue|championnat|L1|L2|D1|D2|N1|national)(?:\s*\d)?)/iu', $raw, $jm)) {
            $proof[] = '« ' . trim($jm[1]) . ' » pour un match amical';
        }
        if (!$proof) {
            return null;
        }
        // Puis l'adversaire : nommé quelque part (nom, variante, mot distinctif), rien à signaler.
        $text = self::norm($raw);
        if (str_word_count($text) < self::MIN_WORDS) {
            return null;
        }
        if ($re === null) {
            $load();
        }
        $flat = str_replace(' ', '', $text);
        foreach (self::words($opp, $club) as $w) {
            if (str_contains($text, " $w ") || (strlen($w) >= 6 && str_contains($flat, str_replace(' ', '', $w)))) {
                return null;
            }
        }
        $counts = [];
        if ($re !== '' && preg_match_all($re, $text, $mm)) {
            foreach ($mm[1] as $hit) {
                $id = $ids[$hit] ?? null;
                if ($id !== null && !$isOpp($id)) {
                    $counts[$names[$id]] = ($counts[$names[$id]] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        $cited = array_map(fn ($n, $k) => $n . ($k > 1 ? " ($k fois)" : ''), array_keys($counts), $counts);
        return 'Texte d’un autre match ? Les textes ne nomment jamais ' . $opp . ' mais donnent ' . implode(' et ', $proof)
            . ($cited ? ' (clubs cités : ' . implode(', ', array_slice($cited, 0, 4)) . ')' : '')
            . ' : compte rendu copié d’un autre match ? À corriger ; l’IA ne raconte pas cette fiche en attendant.';
    }

    /** Même club sous deux graphies (« EvianTG » et « Evian TG », « Paris FC » et « ParisFC »). */
    private static function sameClub(string $a, string $b): bool
    {
        static $flat = [];
        $a = $flat[$a] ??= str_replace(' ', '', trim(self::norm($a)));
        $b = $flat[$b] ??= str_replace(' ', '', trim(self::norm($b)));
        return $a !== '' && $b !== '' && (str_contains($a, $b) || str_contains($b, $a));
    }

    /**
     * Textes de la fiche hors en-tête (introduction, sections, temps forts, réactions, brèves), et
     * à part les réactions d'après-match (champ « réactions » et sections intitulées « Réactions… »).
     * @return array{0:string,1:string}
     */
    private static function texts(array $doc): array
    {
        $m = $doc['match'];
        $plain = fn (string $h) => html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>'], ' ', $h)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $all = [(string) ($doc['intro'] ?? '')];
        $reactions = [];
        foreach (is_array($doc['sections'] ?? null) ? $doc['sections'] : [] as $s) {
            $h = is_array($s) ? (string) ($s['html'] ?? '') : '';
            $all[] = $h;
            if (is_array($s) && preg_match('/r[ée]action/iu', (string) ($s['title'] ?? ''))) {
                $reactions[] = $h;
            }
        }
        foreach (['highlights', 'reactions', 'breves'] as $k) {
            foreach (is_array($m[$k] ?? null) ? $m[$k] : [] as $x) {
                $all[] = $t = is_array($x) ? (string) ($x['text'] ?? '') : (string) $x;
                if ($k === 'reactions') {
                    $reactions[] = $t;
                }
            }
        }
        return [$plain(implode(' ', $all)), $plain(implode(' ', $reactions))];
    }

    private static function norm(string $s): string
    {
        return ' ' . trim((string) preg_replace('/[^a-z0-9]+/', ' ', Names::ascii($s))) . ' ';
    }

    private static function isSochaux(string $name): bool
    {
        static $seen = [];
        return $seen[$name] ??= str_contains(Names::ascii($name), 'sochaux');
    }

    /** Club du référentiel désignant ce nom (nom ou variante, à l'espace près), sinon null. */
    private static function clubOf(string $name): ?array
    {
        static $byName = null;
        if ($byName === null) {
            $byName = [];
            foreach (Collections::get('clubs') as $c) {
                foreach (array_merge([(string) ($c['name'] ?? '')], (array) ($c['aliases'] ?? [])) as $v) {
                    $byName[str_replace(' ', '', trim(self::norm((string) $v)))] ??= $c;
                }
            }
        }
        return $byName[str_replace(' ', '', trim(self::norm($name)))] ?? null;
    }

    /** Façons de nommer l'adversaire : son nom, ses variantes, et chacun de leurs mots distinctifs. */
    private static function words(string $name, ?array $club): array
    {
        $out = [];
        foreach (array_merge([$name], $club ? array_merge([(string) ($club['name'] ?? '')], (array) ($club['aliases'] ?? [])) : []) as $v) {
            $v = trim(self::norm((string) $v));
            if ($v === '') {
                continue;
            }
            $out[] = $v;
            foreach (explode(' ', $v) as $w) {
                if (strlen($w) >= 4 && !in_array($w, self::GENERIC, true)) {
                    $out[] = $w;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Expression qui trouve les clubs du référentiel dans un texte normalisé (noms et variantes,
     * les plus longs d'abord), et les tables nom trouvé => identifiant, identifiant => nom.
     * @return array{0:string,1:array<string,string>,2:array<string,string>}
     */
    private static function clubs(): array
    {
        if (self::$clubs !== null) {
            return self::$clubs;
        }
        $ids = $names = [];
        foreach (Collections::get('clubs') as $c) {
            $id = (string) ($c['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $names[$id] = (string) ($c['name'] ?? $id);
            foreach (array_merge([(string) ($c['name'] ?? '')], (array) ($c['aliases'] ?? [])) as $v) {
                $v = trim(self::norm((string) $v));
                // Un nom fait d'un seul mot générique (« Stade », « Union ») ne désigne personne.
                if ($v !== '' && strlen($v) >= 3 && !in_array($v, self::GENERIC, true)) {
                    $ids[$v] ??= $id;
                }
            }
        }
        $keys = array_keys($ids);
        usort($keys, fn ($a, $b) => strlen($b) <=> strlen($a));
        $re = $keys ? '/(?<= )(' . implode('|', array_map(fn ($k) => preg_quote($k, '/'), $keys)) . ')(?= )/' : '';
        return self::$clubs = [$re, $ids, $names];
    }
}
