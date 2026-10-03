<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\PhpCache;
use App\Data\Categories;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
use App\Front\Site;

/**
 * Moteur de recherche interne, sans base de données.
 *
 * Index plein texte compact (storage/cache/search.php, servi par OPcache) :
 * pour chaque fiche visible, le titre, les noms (personne, équipes, surnom) et le
 * texte, en minuscules sans accents. Recherche ET sur les mots (OU en secours),
 * années et saisons reconnues, pondération titre > noms > texte.
 */
final class Search
{
    private const CACHE = STORAGE_PATH . '/cache/search.php';
    private const BODY_MAX = 12000;
    private const STOP = ['le', 'la', 'les', 'de', 'des', 'du', 'd', 'l', 'un', 'une', 'et', 'a', 'au', 'aux', 'en', 'the', 'of', 'and', 'vs', 'contre', 'x', 'fc', 'sur', 'pour', 'par'];

    private static ?array $docs = null;

    // ------------------------------------------------------------------ index

    /** Texte indexé d'une fiche. */
    public static function entry(array $doc): array
    {
        $names = [];
        $parts = [(string) ($doc['intro'] ?? '')];
        foreach ($doc['sections'] ?? [] as $s) {
            $parts[] = (string) ($s['title'] ?? '');
            $parts[] = (string) ($s['html'] ?? '');
        }
        $parts[] = (string) ($doc['key_figure']['number'] ?? '') . ' ' . ($doc['key_figure']['text'] ?? '');
        foreach ($doc['gallery'] ?? [] as $g) {
            $parts[] = ($g['caption'] ?? '') . ' ' . ($g['credit'] ?? '');
        }
        foreach ($doc['tables'] ?? [] as $tb) {
            foreach (array_slice($tb['rows'] ?? [], 0, 60) as $r) {
                $parts[] = implode(' ', array_map('strval', $r));
            }
        }
        if (isset($doc['match'])) {
            $m = $doc['match'];
            $names[] = ($m['home']['name'] ?? '') . ' ' . ($m['away']['name'] ?? '') . ' ' . ($m['event'] ?? '');
            $parts[] = implode(' ', array_filter([$m['competition'] ?? '', $m['competition_label'] ?? '', $m['round_text'] ?? '', $m['stadium'] ?? '', $m['referee'] ?? '', $m['goals_text'] ?? '', $m['date_text'] ?? '', $m['season'] ?? '']));
            foreach ($m['lineup']['rows'] ?? [] as $r) {
                $parts[] = Names::display((string) $r['name']);
            }
        }
        if (isset($doc['personne'])) {
            $p = $doc['personne'];
            $names[] = ($p['display_name'] ?? '') . ' ' . ($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '') . ' ' . ($p['nickname'] ?? '');
            foreach ($p['fiche'] ?? [] as $r) {
                $parts[] = ($r['label'] ?? '') . ' ' . $r['value'];
            }
            $parts[] = implode(' ', $p['honours'] ?? []) . ' ' . implode(' ', $p['then'] ?? []);
        }
        if (isset($doc['article'])) {
            $parts[] = ($doc['article']['heading'] ?? '') . ' ' . ($doc['article']['subtitle'] ?? '');
        }
        foreach ($doc['categories'] ?? [] as $c) {
            $parts[] = Categories::label($c);
        }
        $body = self::norm(implode(' ', array_map(fn ($x) => html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', $x)), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $parts)));
        $en = $doc['i18n']['en'] ?? [];
        return [
            't' => self::norm((string) $doc['title']),
            'n' => self::norm(implode(' ', $names)),
            'b' => mb_substr($body, 0, self::BODY_MAX),
            'e' => self::norm(($en['title'] ?? '') . ' ' . implode(' ', array_map(fn ($s) => strip_tags((string) ($s['html'] ?? '')), $en['sections'] ?? []))),
            'y' => self::years($doc),
        ];
    }

    /** Années associées (date du match, saison, passage au club). */
    private static function years(array $doc): array
    {
        $y = [];
        if (!empty($doc['match']['date'])) {
            $y[] = (int) substr($doc['match']['date'], 0, 4);
        }
        if (!empty($doc['match']['season'])) {
            $y[] = (int) substr($doc['match']['season'], 0, 4);
            $y[] = (int) substr($doc['match']['season'], 5, 4);
        }
        if (!empty($doc['article']['season'])) {
            $y[] = (int) substr($doc['article']['season'], 0, 4);
            $y[] = (int) substr($doc['article']['season'], 5, 4);
        }
        $p = $doc['personne'] ?? null;
        if ($p) {
            $a = (int) substr((string) ($p['arrival']['iso'] ?? $p['arrival_coach']['iso'] ?? '0'), 0, 4);
            $b = (int) substr((string) ($p['departure_coach']['iso'] ?? $p['departure']['iso'] ?? '0'), 0, 4);
            if ($a && $b && $b >= $a && $b - $a < 40) {
                $y = array_merge($y, range($a, $b));
            } elseif ($a) {
                $y[] = $a;
            }
        }
        return array_values(array_unique(array_filter($y)));
    }

    public static function norm(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Names::ascii($s)));
    }

    public static function rebuild(): int
    {
        self::$changes = [];
        self::$docs = PhpCache::update(self::CACHE, fn () => self::scan());
        return count(self::$docs);
    }

    /** @return array<int,array> entrées de toutes les fiches visibles, lues sur le disque */
    private static function scan(): array
    {
        $docs = [];
        foreach (Fiches::all() as $doc) {
            $s = Index::get((int) $doc['id']);
            if (!$s || !Index::visible($s) || ($doc['type'] ?? '') === 'page' && empty($doc['path'])) {
                continue;
            }
            $docs[(int) $doc['id']] = self::entry($doc);
        }
        return $docs;
    }

    private static int $defer = 0;
    /** @var array<int,?array> modifications pas encore écrites (null = retirée de la recherche) */
    private static array $changes = [];

    /** Mode « lot » : les mises à jour sont écrites une seule fois à la fin (actions groupées). */
    public static function defer(bool $on): void
    {
        if ($on) {
            self::$defer++;
            return;
        }
        self::$defer = max(0, self::$defer - 1);
        if (self::$defer === 0) {
            self::flush();
        }
    }

    public static function put(array $doc): void
    {
        $s = Index::get((int) $doc['id']);
        self::change((int) $doc['id'], $s && Index::visible($s) ? self::entry($doc) : null);
    }

    public static function remove(int $id): void
    {
        self::change($id, null);
    }

    private static function change(int $id, ?array $entry): void
    {
        self::$changes[$id] = $entry;
        if (self::$docs !== null) {
            if ($entry === null) {
                unset(self::$docs[$id]);
            } else {
                self::$docs[$id] = $entry;
            }
        }
        if (self::$defer === 0) {
            self::flush();
        }
    }

    /** Écrit les modifications sur l'index de recherche relu sous verrou (rien n'est écrasé). */
    private static function flush(): void
    {
        if (!self::$changes) {
            return;
        }
        $changes = self::$changes;
        self::$changes = [];
        self::$docs = PhpCache::update(self::CACHE, function (?array $docs) use ($changes) {
            if ($docs === null) {
                return self::scan();
            }
            foreach ($changes as $id => $e) {
                if ($e === null) {
                    unset($docs[$id]);
                } else {
                    $docs[$id] = $e;
                }
            }
            return $docs;
        });
    }

    private static function docs(): array
    {
        if (self::$docs === null) {
            $d = is_file(self::CACHE) ? include self::CACHE : null;
            if (!is_array($d)) {
                self::rebuild();
                $d = self::$docs ?? [];
            }
            self::$docs = $d;
        }
        return self::$docs;
    }

    // ------------------------------------------------------------------ requête

    /**
     * @return array{q:string, total:int, counts:array<string,int>, items:list<array>, years:list<int>, season:?string}
     */
    public static function query(string $q, ?string $type = null, int $limit = 20, int $offset = 0): array
    {
        $q = trim(mb_substr($q, 0, 120));
        $out = ['q' => $q, 'total' => 0, 'counts' => [], 'items' => [], 'years' => [], 'season' => null, 'tokens' => []];
        if ($q === '') {
            return $out;
        }
        $norm = self::norm($q);
        // Saison « 1987-1988 », « 1987/88 », « 87-88 »
        if (preg_match('/\b((?:19|20)?\d{2})\s*[-\/]\s*((?:19|20)?\d{2})\b/', $q, $sm)) {
            $a = (int) (strlen($sm[1]) === 2 ? ((int) $sm[1] > 27 ? '19' : '20') . $sm[1] : $sm[1]);
            $b = (int) (strlen($sm[2]) === 2 ? substr((string) $a, 0, 2) . $sm[2] : $sm[2]);
            if ($b === $a + 1) {
                $out['season'] = "$a-$b";
                $out['years'] = [$a, $b];
                $norm = trim(str_replace(self::norm($sm[0]), ' ', $norm));
            }
        }
        $tokens = [];
        foreach (explode(' ', $norm) as $t) {
            if ($t === '' || in_array($t, self::STOP, true)) {
                continue;
            }
            if (preg_match('/^(19|20)\d{2}$/', $t)) {
                $out['years'][] = (int) $t;
                continue;
            }
            $tokens[] = $t;
        }
        $tokens = array_values(array_unique($tokens));
        // « Sochaux » est partout : il n'est obligatoire que s'il est seul.
        $required = count($tokens) > 1 ? array_values(array_diff($tokens, ['sochaux'])) : $tokens;
        $years = array_values(array_unique($out['years']));
        $phrase = self::norm($q);
        $totals = Derived::get()['person_totals'] ?? [];

        $scored = self::score($tokens, $required, $years, $phrase, $out['season'], $totals, true);
        if (!$scored && count($required) > 1) {
            $scored = self::score($tokens, $required, $years, $phrase, $out['season'], $totals, false);
        }
        arsort($scored);
        foreach (array_keys($scored) as $id) {
            $s = Index::get($id);
            if (!$s) {
                continue;
            }
            $out['counts'][$s['type']] = ($out['counts'][$s['type']] ?? 0) + 1;
        }
        $ids = array_keys($scored);
        if ($type) {
            $ids = array_values(array_filter($ids, fn ($id) => (Index::get($id)['type'] ?? '') === $type));
        }
        $out['total'] = count($ids);
        foreach (array_slice($ids, $offset, $limit) as $id) {
            $s = Index::get($id);
            if ($s) {
                $out['items'][] = $s + ['score' => $scored[$id]];
            }
        }
        $out['tokens'] = $tokens;
        return $out;
    }

    /** @return array<int,float> */
    private static function score(array $tokens, array $required, array $years, string $phrase, ?string $season, array $totals, bool $all): array
    {
        $res = [];
        $en = I18n::isEn();
        foreach (self::docs() as $id => $d) {
            $score = 0.0;
            $hits = 0;
            $nameHit = false;
            foreach ($tokens as $t) {
                $inT = self::has($d['t'], $t);
                $inN = $d['n'] !== '' && self::has($d['n'], $t);
                $nameHit = $nameHit || $inN || $inT;
                $inB = !$inT && !$inN && (str_contains($d['b'], $t) || ($en && str_contains($d['e'], $t)));
                if ($inT || $inN || $inB) {
                    if (in_array($t, $required, true)) {
                        $hits++;
                    }
                    $score += ($inT ? 12 : 0) + ($inN ? 14 : 0) + ($inB ? 2 + min(3, substr_count($d['b'], ' ' . $t)) : 0);
                    if ($inT && preg_match('/(^| )' . preg_quote($t, '/') . '( |$)/', $d['t'])) {
                        $score += 6;
                    }
                }
            }
            if ($required && ($all ? $hits < count($required) : $hits === 0)) {
                continue;
            }
            if ($years) {
                $ok = $season ? (in_array($years[0], $d['y'], true) && in_array($years[1], $d['y'], true)) : (bool) array_intersect($years, $d['y']);
                if (!$ok) {
                    continue;
                }
                $score += 10;
            }
            if (!$tokens && !$years) {
                continue;
            }
            if ($phrase !== '' && str_contains($d['t'], $phrase)) {
                $score += 30;
            }
            // Notoriété (nombre de matchs) : seulement quand le nom correspond à la recherche.
            if ($nameHit && isset($totals[$id])) {
                $score += min(10, ($totals[$id]['matches'] ?? 0) / 30 + ($totals[$id]['coached'] ?? 0) / 30);
            }
            // À score égal, le plus récent d'abord.
            $res[$id] = $score + (($d['y'][0] ?? 1900) - 1900) / 100000;
        }
        return $res;
    }

    /** Le mot (ou son début) figure dans le texte. */
    private static function has(string $text, string $t): bool
    {
        return str_starts_with($text, $t) || str_contains($text, ' ' . $t);
    }

    // ------------------------------------------------------------------ affichage

    /** Extrait du texte autour du premier mot trouvé, mots surlignés (HTML sûr). */
    public static function snippet(array $summary, array $tokens, int $len = 220): string
    {
        $doc = Fiches::get((int) $summary['id']);
        $text = $summary['excerpt'];
        if ($doc) {
            $parts = [(string) ($doc['intro'] ?? '')];
            foreach ($doc['sections'] ?? [] as $s) {
                $parts[] = (string) $s['html'];
            }
            $full = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>'], ' ', implode(' ', $parts))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($full !== '') {
                $ascii = Names::ascii($full);
                $pos = false;
                foreach ($tokens as $t) {
                    $p = strpos($ascii, $t);
                    if ($p !== false && ($pos === false || $p < $pos)) {
                        $pos = $p;
                    }
                }
                // Positions en octets du texte ASCII ≈ caractères du texte d'origine.
                $start = $pos === false ? 0 : max(0, $pos - 70);
                $text = ($start > 0 ? '…' : '') . mb_substr($full, $start, $len) . (mb_strlen($full) > $start + $len ? '…' : '');
            }
        }
        $html = e($text);
        foreach ($tokens as $t) {
            if (mb_strlen($t) < 2) {
                continue;
            }
            $html = preg_replace_callback('/\p{L}*' . self::accentInsensitive($t) . '\p{L}*/iu', fn ($m) => '<mark>' . $m[0] . '</mark>', $html) ?? $html;
        }
        return $html;
    }

    private static function accentInsensitive(string $t): string
    {
        $map = ['a' => '[aàâäáãå]', 'e' => '[eéèêë]', 'i' => '[iîïíì]', 'o' => '[oôöóòõ]', 'u' => '[uùûüú]', 'c' => '[cç]', 'n' => '[nñ]', 'y' => '[yÿý]'];
        $out = '';
        foreach (str_split($t) as $ch) {
            $out .= $map[$ch] ?? preg_quote($ch, '/');
        }
        return $out;
    }

    /** Libellés « type » et « méta » d'un résultat. */
    public static function describe(array $s): array
    {
        $type = match ($s['type']) {
            'match' => t('Match'),
            'personne' => match ($s['p']['roles'][0] ?? 'joueur') {
                'entraineur' => t('Entraîneur'),
                'dirigeant' => t('Dirigeant'),
                'personnage' => t('Personnage'),
                default => t('Joueur'),
            },
            'objet' => t('Objet'),
            'moment' => t('Moment'),
            default => ($s['a']['kind'] ?? '') === 'bilan_saison' ? t('Bilan de saison') : t('Article'),
        };
        if ($s['type'] === 'match') {
            $m = $s['m'];
            $label = $m['home'] && $m['away'] ? $m['home'] . ' – ' . $m['away'] . (is_array($m['sh_score']) ? ' ' . $m['sh_score'][0] . '-' . $m['sh_score'][1] : '') : ($m['event'] ?: $s['title']);
            $meta = trim(($m['label'] ?: $m['competition']) . ' · ' . date_num($m['date']), ' ·');
        } elseif ($s['type'] === 'personne') {
            $p = $s['p'];
            $label = $p['name'];
            $meta = trim(($p['position'] ? ucfirst((string) $p['position']) : '') . (($p['arrival'] ?? null) ? ' · ' . $p['arrival'] . (($p['departure'] ?? null) && $p['departure'] !== $p['arrival'] ? '-' . $p['departure'] : '') : ''), ' ·');
        } else {
            $label = $s['title'];
            $cat = Categories::primaryOf($s['categories']);
            $meta = $cat ? t(Categories::label($cat)) : '';
        }
        return ['type' => $type, 'label' => $label, 'meta' => $meta];
    }

    /** Suggestions de la recherche plein écran. */
    public static function suggest(string $q): array
    {
        $q = trim($q);
        $out = [];
        if ($q === '') {
            foreach (array_slice(Derived::onThisDay(), 0, 2) as $x) {
                $out[] = ['type' => t('Ce jour-là'), 'label' => Site::matchLabel($x), 'meta' => date_num($x['date']), 'href' => url($x['path'])];
            }
            foreach ([[t('Saisons'), t('Toutes les saisons du club'), '/saisons/'], [t('Face-à-face'), t('Le bilan contre chaque adversaire'), '/face-a-face/'], [t('Records'), t('Les plus larges victoires, les plus capés…'), '/records/'], [t('Quiz'), t('Êtes-vous incollable sur le FCSM ?'), '/interactif/quiz/']] as [$type, $label, $href]) {
                $out[] = ['type' => $type, 'label' => $label, 'meta' => '', 'href' => url($href)];
            }
            return $out;
        }
        $r = self::query($q, null, 10);
        // Saison reconnue : page de la saison en premier
        if ($r['season'] && isset(Derived::get()['seasons'][$r['season']])) {
            $n = count(Derived::get()['seasons'][$r['season']]['matches'] ?? []);
            $out[] = ['type' => t('Saison'), 'label' => t('Saison') . ' ' . $r['season'], 'meta' => $n . ' ' . t('matchs'), 'href' => url('/matchs/' . $r['season'] . '/')];
        } elseif (count($r['years']) === 1 && !$r['tokens']) {
            $y = $r['years'][0];
            foreach ([($y - 1) . '-' . $y, $y . '-' . ($y + 1)] as $se) {
                if (isset(Derived::get()['seasons'][$se])) {
                    $out[] = ['type' => t('Saison'), 'label' => t('Saison') . ' ' . $se, 'meta' => count(Derived::get()['seasons'][$se]['matches'] ?? []) . ' ' . t('matchs'), 'href' => url('/matchs/' . $se . '/')];
                }
            }
        }
        // Adversaire reconnu : face-à-face
        $key = Names::clubKey($q);
        if ($key !== '' && $key !== 'sochaux' && isset(Derived::get()['clubs'][$key])) {
            $c = Derived::get()['clubs'][$key];
            $out[] = ['type' => t('Face-à-face'), 'label' => 'Sochaux × ' . \App\Front\Fiche::clubName($key), 'meta' => $c['count'] . ' ' . t('matchs') . ' · ' . $c['v'] . 'V ' . $c['n'] . 'N ' . $c['d'] . 'D', 'href' => url('/face-a-face/' . $key . '/')];
        }
        foreach ($r['items'] as $s) {
            $d = self::describe($s);
            $out[] = $d + ['href' => url($s['path'])];
        }
        return array_slice($out, 0, 12);
    }
}
