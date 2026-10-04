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

    private static ?array $clubs = null;

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
        [$re, $ids, $names] = self::clubs();
        $club = self::clubOf($opp);
        $isOpp = fn (string $id) => $id === ($club['id'] ?? null) || self::sameClub($names[$id], $opp) || self::isSochaux($names[$id]);
        // Les preuves d'abord (rares, et rapides à chercher) : le texte entier n'est examiné qu'ensuite.
        $proof = [];
        // Réaction d'après-match de l'entraîneur, du gardien, du président ou d'un joueur d'un autre club.
        if ($re !== '' && preg_match('/(?:entra[iî]neur|coach|gardien|pr[ée]sident|joueur)s?\s+(?:de|du|d[\'’])/iu', $reactions)
            && preg_match_all('/ (entraineur|coach|gardien|president|joueur)s? (?:de |du |d )(?:l |la |le )?' . substr($re, 1, -1) . '/', self::norm($reactions), $rm, PREG_SET_ORDER)) {
            foreach ($rm as $r) {
                $id = $ids[$r[2]] ?? null;
                if ($id !== null && !$isOpp($id)) {
                    $who = ['entraineur' => 'de l’entraîneur', 'coach' => 'de l’entraîneur', 'gardien' => 'du gardien', 'president' => 'du président', 'joueur' => 'd’un joueur'][$r[1]];
                    $proof[] = "la réaction $who de " . $names[$id];
                    break;
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
