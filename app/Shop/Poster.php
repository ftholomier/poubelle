<?php
declare(strict_types=1);

namespace App\Shop;

use App\Data\Derived;
use App\Data\Fiches;
use App\Services\AiCosts;
use App\Services\FicheAudio;
use App\Services\Gemini;

/**
 * Poster souvenir d'un match : une composition en mosaïque (affiche et score, onze de départ sur le
 * terrain, film du match, tribunes, citations, anecdote, chiffre, récit, saison), générée d'après
 * la fiche du musée et dédicacée au client (« pour Prénom Nom »). Le poster est un calque
 * « poster » d'un modèle de la boutique : il devient des formes vectorielles comme les autres
 * calques, donc le même dessin sert à l'aperçu et au PDF de l'imprimeur.
 *
 * Champs du client : poster_match (identifiant de la fiche du match, choisi dans la liste de
 * propositions), poster_prenom, poster_nom ; _poster_no (numéro de la pièce, à la commande).
 *
 * Les blocs viennent des données structurées de la fiche (score, compo, temps forts, chiffre clé).
 * L'IA, si elle est disponible, choisit en plus l'anecdote, les citations, l'enjeu et condense le
 * récit ; tout ce qu'elle propose est vérifié contre la fiche (citations mot pour mot, nombres et
 * noms propres présents dans la source) et mis en cache par match (storage/shop/posters/).
 */
final class Poster
{
    public const FIELD = 'poster_match';
    public const FIELDS = ['poster_match' => 'Votre match', 'poster_prenom' => 'Prénom', 'poster_nom' => 'Nom'];
    /** Exemple de l'éditeur et de la vitrine : finale de la Coupe de France 1988. */
    public const SAMPLE = '14188';
    private const VERSION = 1;

    private const NAVY = '#0E1F4D';
    private const BLUE = '#094687';
    private const ROI = '#1E3FA8';
    private const BOX = '#13286A';
    private const LINE = '#2F3F75';
    private const YELLOW = '#F6C400';
    private const CREAM = '#F3EDDF';

    /** @var array<string,?array> */
    private static array $cache = [];

    // ------------------------------------------------------------------ choix du match

    /** Le modèle a-t-il un calque poster ? */
    public static function isFor(array $model): bool
    {
        foreach ($model['faces'] as $f) {
            foreach ($f['layers'] as $l) {
                if (($l['type'] ?? '') === 'poster') {
                    return true;
                }
            }
        }
        return false;
    }

    /** Genre du poster d'un modèle : « match » ou « joueur » ('' sans calque poster). */
    public static function kind(array $model): string
    {
        foreach ($model['faces'] as $f) {
            foreach ($f['layers'] as $l) {
                if (($l['type'] ?? '') === 'poster') {
                    return ($l['kind'] ?? '') === 'joueur' ? 'joueur' : 'match';
                }
            }
        }
        return '';
    }

    /** Champ du sujet du poster (match ou joueur) d'un modèle. */
    public static function fieldOf(array $model): string
    {
        return self::kind($model) === 'joueur' ? PlayerPoster::FIELD : self::FIELD;
    }

    /** Sujet en vente comme poster de ce genre. */
    public static function eligibleFor(string $kind, string $id): bool
    {
        return $kind === 'joueur' ? PlayerPoster::eligible($id) : self::eligible($id);
    }

    /** Libellé du sujet (match ou joueur), null s'il n'existe pas. */
    public static function subject(string $kind, string $id): ?string
    {
        if ($kind === 'joueur') {
            $d = PlayerPoster::data($id);
            return $d ? PlayerPoster::label($d) : null;
        }
        $d = self::data($id);
        return $d ? self::label($d['dm']) : null;
    }

    /** Prépare le contenu IA du sujet (une fois). */
    public static function prepare(string $kind, string $id): bool
    {
        return $kind === 'joueur' ? PlayerPoster::enrich($id) : self::enrich($id);
    }

    public static function prepared(string $kind, string $id): bool
    {
        return (bool) ($kind === 'joueur' ? PlayerPoster::enriched($id) : self::enriched($id));
    }

    /** Match en vente comme poster : fiche publiée avec un score et assez de matière. */
    public static function eligible(string $id): bool
    {
        return self::data($id) !== null;
    }

    /** Libellé d'un match pour la liste de propositions. */
    public static function label(array $dm): string
    {
        $comp = implode(' · ', array_filter([(string) ($dm['label'] ?: $dm['comp']), (string) ($dm['round'] ?? '')]));
        return $dm['home'] . ' ' . self::score($dm) . ' ' . $dm['away'] . ' · ' . ($comp !== '' ? $comp . ' · ' : '') . TonMatch::frDate((string) $dm['date']);
    }

    /**
     * Propositions pour la saisie « Votre match » : équipes, compétition, tour, année ou date ;
     * les grands matchs d'abord. @return list<array{id:string,label:string}>
     */
    public static function search(string $q, int $limit = 12): array
    {
        $words = array_values(array_filter(preg_split('/[\s,\/·-]+/u', mb_strtolower(\App\Data\Names::ascii(trim($q)))) ?: [], fn ($w) => $w !== ''));
        if (!$words) {
            return [];
        }
        $hits = [];
        foreach (self::matches() as $dm) {
            $hay = mb_strtolower(\App\Data\Names::ascii(implode(' ', [$dm['home'], $dm['away'], $dm['comp'], $dm['label'] ?? '', $dm['round'] ?? '', $dm['date'], $dm['season'] ?? '',
                TonMatch::frDate((string) $dm['date']), $dm['us'] . '-' . $dm['them'], $dm['them'] . '-' . $dm['us']])));
            foreach ($words as $w) {
                // « 88 » vaut « 1988 ».
                if (!str_contains($hay, $w) && !(preg_match('/^\d{2}$/', $w) && str_contains($hay, ' 19' . $w . '-')) && !(preg_match('/^\d{2}$/', $w) && str_contains($hay, ' 20' . $w . '-'))) {
                    continue 2;
                }
            }
            $hits[] = $dm;
        }
        usort($hits, fn ($a, $b) => ((int) ($b['hl'] ?? 0) <=> (int) ($a['hl'] ?? 0)) ?: strcmp((string) $b['date'], (string) $a['date']));
        $out = [];
        foreach ($hits as $dm) {
            if (self::data((string) $dm['id'])) {
                $out[] = ['id' => (string) $dm['id'], 'label' => self::label($dm)];
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }

    /** @return list<array> matchs publiés et datés */
    private static function matches(): array
    {
        return array_values(array_filter(Derived::part('matches'), fn ($m) => !empty($m['v']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['date'] ?? ''))));
    }

    private static function dm(string $id): ?array
    {
        foreach (self::matches() as $m) {
            if ((string) $m['id'] === $id) {
                return $m;
            }
        }
        return null;
    }

    private static function score(array $dm): string
    {
        return $dm['sh'] ? $dm['us'] . '-' . $dm['them'] : $dm['them'] . '-' . $dm['us'];
    }

    // ------------------------------------------------------------------ contenu

    /**
     * Tout le contenu du poster d'un match (null : pas de poster possible pour ce match).
     * @return array|null
     */
    public static function data(string $id): ?array
    {
        if (!ctype_digit($id)) {
            return null;
        }
        if (array_key_exists($id, self::$cache)) {
            return self::$cache[$id];
        }
        $dm = self::dm($id);
        $doc = $dm ? Fiches::get((int) $id) : null;
        $mt = (array) ($doc['match'] ?? []);
        if (!$dm || !$doc || !Fiches::isVisible($doc) || !isset($mt['score']['home'])) {
            return self::$cache[$id] = null;
        }
        $film = self::film($mt);
        $pitch = null;
        try {
            $pitch = \App\Front\Fiche::matchData($doc)['vars']['pitch'] ?? null;
        } catch (\Throwable) {
        }
        $players = array_values(array_filter((array) ($pitch['players'] ?? []), fn ($p) => isset($p['x'], $p['y'])));
        // Assez de matière pour remplir l'affiche : la compo, le film du match et un texte.
        $source = self::source($doc);
        if (count($players) < 11 || count($film) < 3 || mb_strlen($source) < 300) {
            return self::$cache[$id] = null;
        }
        $home = (array) ($mt['home'] ?? []);
        $away = (array) ($mt['away'] ?? []);
        $pens = $mt['score']['pens'] ?? null;
        $extra = !empty($mt['score']['aet']) ? 'après prolongation' : '';
        if (is_array($pens)) {
            $extra = trim($extra . ($extra ? ' · ' : '') . $pens['home'] . '-' . $pens['away'] . ' aux tirs au but');
        }
        $d = [
            'id' => $id, 'dm' => $dm,
            'kicker' => implode(' · ', array_filter([(string) ($mt['competition_label'] ?? $dm['label'] ?? ''), (string) ($mt['round'] ?? ''), self::stadium((string) ($mt['stadium'] ?? ''))])),
            'home' => (string) ($home['name'] ?? $dm['home']), 'away' => (string) ($away['name'] ?? $dm['away']),
            'sh' => (int) $mt['score']['home'], 'sa' => (int) $mt['score']['away'],
            'when' => (string) ($mt['date_text'] ?? TonMatch::frDate((string) $dm['date'])), 'extra' => $extra,
            'date' => (string) $dm['date'], 'formation' => (string) ($pitch['formation'] ?? ''),
            'players' => count($players) >= 11 ? array_slice($players, 0, 11) : [],
            'coach' => self::coach($doc), 'film' => $film, 'spectators' => (int) ($mt['spectators'] ?? $dm['spectators'] ?? 0),
            'figure' => self::figure($doc), 'quotes' => self::quotes($doc), 'season' => self::season($dm, $mt),
            'recit' => self::recitText($doc), 'anecdote' => null, 'enjeu' => '',
            'opp_level' => (string) ($dm['sh'] ? ($away['level'] ?? '') : ($home['level'] ?? '')),
        ];
        $ai = self::enriched($id);
        if ($ai) {
            $d['anecdote'] = $ai['anecdote'] ?? null;
            $d['enjeu'] = (string) ($ai['enjeu'] ?? '');
            if (!empty($ai['quotes'])) {
                // Celles de l'IA d'abord (avec la fonction de chacun), puis les autres de la fiche.
                $norm = fn (string $t) => mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', \App\Data\Names::ascii($t)));
                $merged = $ai['quotes'];
                foreach ($d['quotes'] as $q) {
                    $dup = false;
                    foreach ($ai['quotes'] as $a) {
                        $dup = $dup || str_contains($norm($q['text']), $norm($a['text'])) || str_contains($norm($a['text']), $norm($q['text']));
                    }
                    if (!$dup) {
                        foreach ($ai['quotes'] as $a) {
                            if ($norm($a['who']) === $norm($q['who']) && $a['role'] !== '') {
                                $q['role'] = $a['role'];
                            }
                        }
                        $merged[] = $q;
                    }
                }
                $d['quotes'] = $merged;
            }
            if (($ai['recit'] ?? '') !== '') {
                $d['recit'] = $ai['recit'];
            }
        }
        return self::$cache[$id] = $d;
    }

    /** « Parc des Princes Paris (75) » → « Parc des Princes ». */
    private static function stadium(string $s): string
    {
        return trim((string) preg_replace('/\s*\(\d+\)\s*$/', '', $s));
    }

    /** Texte d'une fiche (rubriques et résumé), sans balises : la source de l'IA et des vérifications. */
    public static function source(array $doc): string
    {
        $t = (string) ($doc['intro'] ?? '');
        foreach ((array) ($doc['sections'] ?? []) as $s) {
            $t .= "\n" . ($s['title'] ?? '') . "\n" . (string) preg_replace('/<\/(li|p)>/i', "\n", (string) ($s['html'] ?? ''));
        }
        foreach ((array) ($doc['match']['highlights'] ?? []) as $h) {
            $t .= "\n" . ($h['minute'] ?? '') . "' " . ($h['text'] ?? '');
        }
        foreach (['reactions', 'breves', 'header_extra'] as $k) {
            foreach ((array) ($doc['match'][$k] ?? []) as $x) {
                $t .= "\n" . (is_array($x) ? implode(' ', array_filter($x, 'is_string')) : (string) $x);
            }
        }
        $t .= "\n" . (string) (((array) ($doc['key_figure'] ?? []))['text'] ?? '');
        return trim(html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Phrase propre : sans tweet, lien, mot-dièse ni mention recopiés dans la fiche. */
    public static function tidy(string $t): string
    {
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Après un score « (0-1) », la suite est souvent un tweet recopié.
        if (preg_match('/^(.*?\(\d+-\d+\))/su', $t, $m) && mb_strlen($t) - mb_strlen($m[1]) > 20) {
            $t = $m[1];
        }
        $t = (string) preg_replace(['/\s*(pic\.twitter\.com|https?:\/\/|—\s|#\w|@\w).*$/su', '/\s+/u'], ['', ' '], $t);
        return trim($t, " \t\n-–—");
    }

    /** Temps forts : buts d'abord, puis les autres, dans l'ordre des minutes, au plus 9. @return list<array{min:string,text:string,goal:bool}> */
    private static function film(array $mt): array
    {
        $all = [];
        foreach ((array) ($mt['highlights'] ?? []) as $h) {
            $text = self::tidy((string) ($h['text'] ?? ''));
            $min = preg_replace('/[^0-9+]/', '', (string) ($h['minute'] ?? ''));
            if ($text === '' || $min === '') {
                continue;
            }
            if (mb_strlen($text) > 120) {
                // La première phrase, sinon une coupe au mot.
                $text = preg_match('/^(.{30,118}?[.!])\s/u', $text, $m) ? $m[1] : rtrim(mb_substr($text, 0, 116), ' ,;') . '…';
            }
            $all[] = ['min' => $min . "'", 'text' => $text, 'goal' => !empty($h['goal'])];
        }
        $goals = array_keys(array_filter($all, fn ($x) => $x['goal']));
        $others = array_values(array_diff(array_keys($all), $goals));
        $keep = $goals;
        $room = max(0, 12 - count($goals));
        if ($others && $room) {
            // Les autres temps forts, répartis sur tout le match.
            $step = count($others) / $room;
            for ($i = 0; $i < min($room, count($others)); $i++) {
                $keep[] = $others[(int) floor($i * $step)];
            }
        }
        sort($keep);
        $out = array_map(fn ($i) => $all[$i], array_values(array_unique($keep)));
        $s = (array) ($mt['score'] ?? []);
        $home = (string) ($mt['home']['name'] ?? '');
        $away = (string) ($mt['away']['name'] ?? '');
        if (!empty($s['aet'])) {
            $out[] = ['min' => "120'", 'text' => 'Toujours ' . $s['home'] . '-' . $s['away'] . ' après la prolongation.', 'goal' => false, 'end' => true];
        }
        if (is_array($s['pens'] ?? null)) {
            $win = $s['pens']['home'] > $s['pens']['away'] ? $home : $away;
            $out[] = ['min' => 'TAB', 'text' => $win . ' l’emporte ' . max($s['pens']['home'], $s['pens']['away']) . ' à ' . min($s['pens']['home'], $s['pens']['away']) . ' aux tirs au but.', 'goal' => false, 'end' => true];
        }
        return $out;
    }

    private static function coach(array $doc): string
    {
        foreach ((array) ($doc['match']['lineup']['rows'] ?? []) as $r) {
            if (strtoupper((string) ($r['position'] ?? '')) === 'E' && trim((string) ($r['name'] ?? '')) !== '') {
                return \App\Data\Names::display((string) $r['name']);
            }
        }
        return '';
    }

    /** « TAKAC Sylvester » → « Sylvester Takac ». */
    private static function person(string $n): string
    {
        $p = preg_split('/\s+/u', trim($n)) ?: [];
        $up = [];
        while ($p && mb_strtoupper($p[0]) === $p[0] && mb_strlen($p[0]) > 1) {
            $up[] = mb_convert_case(array_shift($p), MB_CASE_TITLE);
        }
        return trim(implode(' ', $p) . ' ' . implode(' ', $up));
    }

    /** @return array{n:string,text:string}|null */
    private static function figure(array $doc): ?array
    {
        $k = (array) ($doc['key_figure'] ?? []);
        $n = trim((string) ($k['number'] ?? ''));
        $t = self::tidy((string) ($k['text'] ?? ''));
        if ($n === '' || $t === '' || mb_strlen($n) > 6) {
            return null;
        }
        // « 3ème défaite… » : le chiffre est déjà dans le grand rond.
        $t = (string) preg_replace('/^' . preg_quote($n, '/') . '\s*(e|è|ème|eme|er|ère)?\s+/iu', '', $t);
        return ['n' => $n, 'text' => mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1)];
    }

    /**
     * Citations de la fiche : « Déclaration de X : "…" », « Pour X : "…" ». Mot pour mot.
     * @return list<array{who:string,role:string,text:string,side:string}>
     */
    private static function quotes(array $doc): array
    {
        $out = [];
        foreach ((array) ($doc['sections'] ?? []) as $s) {
            preg_match_all('/<li>(.*?)<\/li>/su', (string) ($s['html'] ?? ''), $lis);
            foreach ($lis[1] as $li) {
                $t = trim(html_entity_decode(strip_tags($li), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (preg_match('/^(?:D[ée]claration d[e’\']\s*|Pour\s+|Selon\s+)([\p{Lu}][\p{L}’\'.-]+(?:\s+[\p{Lu}][\p{L}’\'.-]+){0,2})\s*:\s*[«"“]\s*(.+?)\s*[»"”]\.?\s*$/su', $t, $m)) {
                    $q = trim($m[2], " .\t\n");
                    if (mb_strlen($q) >= 20) {
                        $out[] = ['who' => $m[1], 'role' => '', 'text' => $q . '.', 'side' => ''];
                    }
                }
            }
        }
        return $out;
    }

    /** Bilan de la saison en championnat. @return array{season:string,level:string,w:int,d:int,l:int,gf:int,ga:int}|null */
    private static function season(array $dm, array $mt): ?array
    {
        $w = $dr = $l = $gf = $ga = 0;
        foreach (self::matches() as $m) {
            if (($m['season'] ?? '') === ($dm['season'] ?? '') && $m['comp'] === 'Championnat') {
                $r = (string) $m['result'];
                $r === 'V' ? $w++ : ($r === 'D' ? $l++ : $dr++);
                $gf += (int) $m['us'];
                $ga += (int) $m['them'];
            }
        }
        if ($w + $dr + $l < 10) {
            return null;
        }
        $lvl = (string) ($dm['sh'] ? ($mt['home']['level'] ?? '') : ($mt['away']['level'] ?? ''));
        $level = match (true) {
            (bool) preg_match('/^D(\d)$/', $lvl, $x) => 'en Division ' . $x[1],
            (bool) preg_match('/^L(\d)$/', $lvl, $x) => 'en Ligue ' . $x[1],
            default => 'en championnat',
        };
        return ['season' => (string) $dm['season'], 'level' => $level, 'w' => $w, 'd' => $dr, 'l' => $l, 'gf' => $gf, 'ga' => $ga];
    }

    /** Le récit : le résumé audio du musée, sinon les rubriques de la fiche. */
    private static function recitText(array $doc): string
    {
        $t = '';
        try {
            $t = (string) (FicheAudio::current($doc, 'fr')['text'] ?? '');
        } catch (\Throwable) {
        }
        if (mb_strlen($t) < 300) {
            $t = '';
            foreach ((array) ($doc['sections'] ?? []) as $s) {
                $t .= ' ' . html_entity_decode(strip_tags((string) preg_replace('/<\/li>/i', '. ', (string) ($s['html'] ?? ''))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $t = self::tidy((string) preg_replace('/\s*(pic\.twitter\.com|https?:\/\/)\S*/u', '', $t));
        return trim((string) preg_replace(['/\.\s*\./u', '/\s+/u'], ['.', ' '], $t));
    }

    // ------------------------------------------------------------------ enrichissement par l'IA

    private static function cacheFile(string $id): string
    {
        return STORAGE_PATH . '/shop/posters/' . $id . '.json';
    }

    /** Ce que l'IA a déjà préparé pour ce match (vérifié), ou null. */
    public static function enriched(string $id): ?array
    {
        $c = \App\Core\JsonStore::read(self::cacheFile($id));
        return is_array($c) && ($c['v'] ?? 0) === self::VERSION ? $c : null;
    }

    /**
     * Prépare (une fois par match) l'anecdote, les citations, l'enjeu et le récit condensé.
     * Renvoie false si l'IA n'est pas disponible ou a échoué : le poster se passe alors de ces blocs.
     */
    public static function enrich(string $id): bool
    {
        if (self::enriched($id)) {
            return true;
        }
        $doc = ctype_digit($id) ? Fiches::get((int) $id) : null;
        if (!$doc || !self::data($id) || !Gemini::ready()) {
            return false;
        }
        $src = self::source($doc);
        $audio = self::recitText($doc);
        $system = 'Tu prépares le contenu d’un poster souvenir d’un match du FC Sochaux-Montbéliard pour le musée Sochaux Rétro. '
            . 'Tu n’utilises QUE les faits de la fiche fournie : aucun nom, nombre, date ou fait qui n’y figure pas. Français soigné, ton factuel et vivant, sans question, sans « Le saviez-vous ».';
        $prompt = "FICHE DU MATCH :\n" . mb_substr($src, 0, 9000) . "\n\nRÉSUMÉ AUDIO :\n" . mb_substr($audio, 0, 4000) . "\n\n"
            . "Réponds en JSON : {\"anecdote\":{\"titre\":\"3 à 5 mots, accrocheur\",\"texte\":\"une anecdote marquante de la fiche (prime, record, coulisses…), 160 à 230 caractères\"},"
            . "\"citations\":[{\"qui\":\"Prénom Nom\",\"role\":\"fonction (entraîneur de Metz, défenseur sochalien…)\",\"texte\":\"citation recopiée MOT POUR MOT depuis la fiche, 40 à 150 caractères (tu peux en prendre un extrait)\",\"camp\":\"sochaux|adversaire|autre\"}] (au plus 3, uniquement de vraies déclarations de la fiche ; liste vide sinon),"
            . "\"enjeu\":\"le contexte avant le match en une phrase, 120 à 200 caractères\","
            . "\"recit\":\"le récit du match condensé, 900 à 1400 caractères, au passé, sans minute à minute exhaustif\"}";
        try {
            $r = Gemini::generate([['role' => 'user', 'parts' => [['text' => $prompt]]]], $system, ['max_tokens' => 2200, 'json' => true, 'for' => 'boutique-poster', 'ref' => 'fiche:' . $id, 'timeout' => 60]);
        } catch (\Throwable) {
            return false;
        }
        $j = json_decode((string) preg_replace('/^```(?:json)?|```$/m', '', trim($r['text'])), true);
        if (!is_array($j)) {
            return false;
        }
        $out = ['v' => self::VERSION, 'at' => date('c')];
        $an = (array) ($j['anecdote'] ?? []);
        $at = trim((string) ($an['texte'] ?? ''));
        if ($at !== '' && mb_strlen($at) <= 260 && self::grounded($at, $src)) {
            $out['anecdote'] = ['title' => mb_substr(trim((string) ($an['titre'] ?? 'L’anecdote')), 0, 40), 'text' => $at];
        }
        $norm = fn (string $s) => mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', \App\Data\Names::ascii($s)));
        $q = [];
        foreach (array_slice((array) ($j['citations'] ?? []), 0, 3) as $c) {
            $t = trim((string) ($c['texte'] ?? ''), " \"«»“”");
            $who = trim((string) ($c['qui'] ?? ''));
            // Mot pour mot : la citation doit se retrouver dans la fiche (sans la ponctuation).
            if ($t !== '' && $who !== '' && mb_strlen($t) <= 170 && str_contains($norm($src), $norm($t)) && self::grounded($who, $src)) {
                $q[] = ['who' => $who, 'role' => mb_substr(trim((string) ($c['role'] ?? '')), 0, 50), 'text' => $t, 'side' => (string) ($c['camp'] ?? '')];
            }
        }
        if ($q) {
            $out['quotes'] = $q;
        }
        $en = trim((string) ($j['enjeu'] ?? ''));
        if ($en !== '' && mb_strlen($en) <= 230 && self::grounded($en, $src)) {
            $out['enjeu'] = $en;
        }
        $rc = trim((string) ($j['recit'] ?? ''));
        if (mb_strlen($rc) >= 400 && mb_strlen($rc) <= 1700 && self::grounded($rc, $src . "\n" . $audio)) {
            $out['recit'] = $rc;
        }
        \App\Core\JsonStore::write(self::cacheFile($id), $out);
        unset(self::$cache[$id]);
        return true;
    }

    /** Tous les nombres et noms propres du texte figurent dans la source. */
    public static function grounded(string $t, string $src): bool
    {
        return !Anecdotes::foreignNames($t, $src) && !array_diff(Anecdotes::numbers($t), Anecdotes::numbers($src));
    }

    /** Numéro de pièce d'un article commandé (« 0001 », « 0002 »…), attribué une seule fois. */
    public static function number(string $ref): string
    {
        $no = '';
        \App\Core\JsonStore::update(STORAGE_PATH . '/shop/posters-numeros.json', function ($all) use ($ref, &$no) {
            $all = is_array($all) ? $all : [];
            $all[$ref] ??= count($all) + 1;
            $no = sprintf('%04d', $all[$ref]);
            return $all;
        });
        return $no;
    }

    // ------------------------------------------------------------------ dessin

    /**
     * Calques (mm) du poster dans le cadre du calque. $values : poster_match, poster_prenom,
     * poster_nom, _poster_no. Sans match valable : le match d'exemple.
     * @return list<array>
     */
    public static function layers(array $frame, array $values): array
    {
        $id = (string) ($values[self::FIELD] ?? '');
        $d = self::data($id) ?? self::data(self::SAMPLE);
        if (!$d) {
            return [];
        }
        $pour = trim(mb_substr(trim((string) ($values['poster_prenom'] ?? '')), 0, 30) . ' ' . mb_substr(trim((string) ($values['poster_nom'] ?? '')), 0, 30));
        $no = preg_replace('/[^0-9A-Z-]/', '', strtoupper((string) ($values['_poster_no'] ?? '')));
        return self::fit((new PosterLayout($d, $pour !== '' ? $pour : 'Prénom Nom', $no))->build(), $frame);
    }

    /** Calques dessinés pour l'A3 (297 × 420), mis à l'échelle et centrés dans le cadre du calque. */
    public static function fit(array $L, array $frame): array
    {
        $fw = max(50.0, (float) ($frame['w'] ?? 297));
        $fh = max(70.0, (float) ($frame['h'] ?? 420));
        $k = min($fw / 297, $fh / 420);
        $ox = (float) ($frame['x'] ?? 0) + ($fw - 297 * $k) / 2;
        $oy = (float) ($frame['y'] ?? 0) + ($fh - 420 * $k) / 2;
        foreach ($L as &$l) {
            $l['x'] = round($ox + $l['x'] * $k, 3);
            $l['y'] = round($oy + $l['y'] * $k, 3);
            $l['w'] = round($l['w'] * $k, 3);
            if (isset($l['h'])) {
                $l['h'] = round($l['h'] * $k, 3);
            }
            if (isset($l['size'])) {
                $l['size'] = round($l['size'] * $k, 3);
                $l['min'] = round(($l['min'] ?? 2) * $k, 3);
            }
            if (isset($l['sw'])) {
                $l['sw'] = round($l['sw'] * $k, 3);
            }
            if (isset($l['r'])) {
                $l['r'] = round($l['r'] * $k, 3);
            }
        }
        return $L;
    }
}
