<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;

/**
 * Reprise des années 1928-1969 depuis fcsmstory.com (site WordPress public) : lecture par l’API
 * REST, découpage des pages de saison en matchs (date, lieu,
 * adversaire, compétition, score, composition, buteurs, récit) et classement des autres pages
 * (matchs racontés à part, tournois et coupes, joueurs, articles).
 *
 * Seuls les faits sont gardés tels quels ; les récits passent par la réécriture (FcsmRewrite)
 * avant d'entrer dans le musée. Aucune image n'est reprise.
 */
final class FcsmStory
{
    public const BASE = 'https://fcsmstory.com';
    /** Dossier de travail (remplaçable par les essais automatiques). */
    public static string $dir = STORAGE_PATH . '/import/fcsmstory';
    public const FIRST = 1928;
    public const LAST = 1969;
    /** Lieux des matchs à domicile du club (Sochaux, puis Montbéliard ; Valentigney sous l'Occupation). */
    private const HOME_PLACES = ['sochaux', 'montbeliard', 'valentigney'];
    private const MONTHS = ['janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12];
    /** Pages du site sans rapport avec l'histoire du club, ou qui débordent sur toutes les époques. */
    private const SKIP = ['accueil', 'blog', 'contact', 'contact-2', 'politique-de-confidentialite', 'les-plus-grands-matchs',
        'etienne-mattler', 'detail-tous-matchs-officiels-fcsm', 'les-affluences', 'les-associations-de-supporters',
        'plus-jeunes-buteurs', 'recap-detaille-des-championnats-joues', 'repertoire-de-tous-les-sochaliens',
        'resultats-du-fcsm-en-coupe-de-france', 'statistiques-du-fc-sochaux-montbeliard-en-coupe-de-france',
        'records-de-buts-sur-1-match-au-fcsm', 'les-sochaliens-et-lequipe-de-france', 'le-fcsm-et-le-podium',
        'la-victoire-la-plus-large-du-fc-sochaux-montbeliard-en-ligue-1-fcsm-story', 'la-coupe-de-la-ligue', 'la-coupe-deurope'];

    // ------------------------------------------------------------------ lecture du site

    /** Télécharge les pages et articles (API REST, 100 par appel) dans storage/import/fcsmstory/raw.json. */
    public static function fetch(?callable $get = null): array
    {
        $get ??= static function (string $url): string {
            $ctx = stream_context_create(['http' => ['timeout' => 40, 'header' => "User-Agent: SochauxRetro-Musee/1.0\r\nAccept: application/json\r\n"]]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) {
                throw new \RuntimeException('fcsmstory.com ne répond pas (' . $url . ').');
            }
            return $body;
        };
        $out = [];
        foreach (['pages', 'posts'] as $kind) {
            for ($page = 1; $page <= 20; $page++) {
                $rows = json_decode($get(self::BASE . "/wp-json/wp/v2/$kind?per_page=100&page=$page&_fields=id,slug,link,title,content,date,modified"), true);
                if (!is_array($rows) || !$rows || isset($rows['code'])) {
                    break;
                }
                foreach ($rows as $r) {
                    $out[] = ['kind' => $kind === 'pages' ? 'page' : 'post', 'id' => (int) $r['id'], 'slug' => (string) $r['slug'], 'link' => (string) $r['link'],
                        'title' => html_entity_decode(strip_tags((string) ($r['title']['rendered'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'html' => (string) ($r['content']['rendered'] ?? ''), 'modified' => (string) ($r['modified'] ?? '')];
                }
                if (count($rows) < 100) {
                    break;
                }
            }
        }
        if (!$out) {
            throw new \RuntimeException('Aucune page lue sur fcsmstory.com.');
        }
        JsonStore::write(self::$dir . '/raw.json', ['at' => date('c'), 'items' => $out]);
        return $out;
    }

    /** @return list<array> pages lues lors du dernier téléchargement */
    public static function raw(): array
    {
        return (array) (JsonStore::read(self::$dir . '/raw.json', [])['items'] ?? []);
    }

    // ------------------------------------------------------------------ classement

    /**
     * Classe les pages : season (page de saison), match (match raconté à part), tournament
     * (tournoi, coupe), player (portrait), article (récit). Hors 1928-1969 ou sans rapport : skip.
     */
    public static function classify(array $item): string
    {
        $slug = $item['slug'];
        $t = mb_strtolower(\App\Data\Names::ascii($item['title']));
        if (in_array($slug, self::SKIP, true) || trim(strip_tags($item['html'])) === '') {
            return 'skip';
        }
        if (str_starts_with($t, 'saison')) {
            return 'season';
        }
        $years = self::years($item['title'] . ' ' . $slug);
        if ($years && (min($years) < self::FIRST || min($years) > self::LAST)) {
            return 'skip';
        }
        if (preg_match('/^(\d{1,2}|1er) (' . implode('|', array_keys(self::MONTHS)) . ') (19\d\d) /u', $t)
            && preg_match('/sochaux/u', $t) && $item['kind'] === 'page') {
            return 'match';
        }
        if (preg_match('/\b(coupe|tournoi|challenge)\b/u', $t)) {
            return 'tournament';
        }
        // Portrait : titre en « Prénom NOM » (le nom en capitales), sans date.
        if (!$years && preg_match('/^(?:\p{Lu}[\p{L}\'’.-]*\s+)+[\p{Lu}][\p{Lu}\'’ -]{2,}$/u', trim($item['title']))) {
            return 'player';
        }
        return $years || $item['kind'] === 'post' ? 'article' : 'skip';
    }

    /** @return list<int> années 1900-2099 citées */
    public static function years(string $s): array
    {
        preg_match_all('/\b(19\d\d|20\d\d)\b/', $s, $m);
        return array_map('intval', $m[1]);
    }

    // ------------------------------------------------------------------ découpage des saisons

    /** Texte brut d'un contenu WordPress : une ligne par paragraphe, sans balises. */
    public static function text(string $html): string
    {
        $html = preg_replace('#<(script|style|figure|figcaption)\b.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<(br|/p|/h\d|/li|/tr|/div|/blockquote)\b[^>]*>#i', "\n", $html) ?? '';
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = str_replace(["\u{00A0}", "\r"], [' ', ''], $t);
        $lines = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/[ \t]+/u', ' ', $l) ?? ''), explode("\n", $t)), fn ($l) => $l !== ''));
        // Pied de page du site (articles récents, commentaires, archives) : coupé.
        foreach ($lines as $i => $l) {
            if (preg_match('/^(Copyright\s*)?©\s*\d{4}/u', $l) || preg_match('/^Copyright\b/u', $l)) {
                $lines = array_slice($lines, 0, $i);
                break;
            }
        }
        return implode("\n", $lines);
    }

    /** Ligne d'en-tête d'un match : « Le 20 Septembre 1942 à Montbéliard, défaite 3 à 0 Fives ». */
    private const HEAD = '/^Le (\d{1,2}|1er) (\p{L}+) (\d{4}) à ([^,]+), (.+)$/u';

    /**
     * Découpe une page de saison : le texte d'introduction (contexte, effectif, bilan) et la liste
     * des matchs. @return array{intro:string,outro:string,matches:list<array>}
     */
    public static function season(string $html, string $season): array
    {
        $lines = explode("\n", self::text($html));
        $blocks = [];
        $intro = [];
        $cur = null;
        foreach ($lines as $l) {
            if (preg_match(self::HEAD, $l)) {
                if ($cur) {
                    $blocks[] = $cur;
                }
                $cur = [$l];
            } elseif ($cur !== null) {
                $cur[] = $l;
            } else {
                $intro[] = $l;
            }
        }
        if ($cur) {
            $blocks[] = $cur;
        }
        $matches = [];
        $outro = [];
        foreach ($blocks as $b) {
            $m = self::match($b, $season);
            if ($m) {
                // Le texte de saison qui suit le dernier match (bilan, classement) n'est pas un récit de match.
                $matches[] = $m;
            }
        }
        // Bilan de fin de saison : paragraphes du dernier bloc après une ligne de titre « Bilan », « Classement »…
        if ($matches) {
            $last = &$matches[count($matches) - 1];
            $k = null;
            $rl = $last['report_lines'];
            foreach ($rl as $i => $l) {
                if (preg_match('/^(Bilan|Classement|Le classement|Liste des|Statistiques|En résumé|Les buteurs)\b/iu', $l)) {
                    $k = $i;
                    break;
                }
                // Liste des joueurs de la saison (« Wartel Paul » / « (46 matchs) ») : titre éventuel compris.
                if (preg_match('/^\(\d+ matchs?\b/u', $rl[$i + 1] ?? '')) {
                    $k = $i > 0 && mb_strlen($rl[$i - 1]) < 80 && !preg_match('/[.!?]$/u', $rl[$i - 1]) ? $i - 1 : $i;
                    break;
                }
            }
            if ($k !== null) {
                $outro = array_slice($last['report_lines'], $k);
                $last['report_lines'] = array_slice($last['report_lines'], 0, $k);
            }
            unset($last);
        }
        foreach ($matches as &$m) {
            $m['report'] = implode("\n", $m['report_lines']);
            unset($m['report_lines']);
        }
        unset($m);
        return ['intro' => implode("\n", $intro), 'outro' => implode("\n", $outro), 'matches' => $matches];
    }

    /** Un bloc de match → faits structurés + récit d'origine (à réécrire). */
    public static function match(array $lines, string $season): ?array
    {
        if (!preg_match(self::HEAD, $lines[0], $h)) {
            return null;
        }
        $month = self::MONTHS[strtolower(\App\Data\Names::ascii($h[2]))] ?? null;
        if (!$month) {
            return null;
        }
        $day = $h[1] === '1er' ? 1 : (int) $h[1];
        $year = (int) $h[3];
        if (!checkdate($month, $day, $year) || $year < self::FIRST || $year > self::LAST) {
            return null;
        }
        $place = trim($h[4]);
        $rest = trim($h[5]);
        $m = ['date' => sprintf('%04d-%02d-%02d', $year, $month, $day), 'season' => $season, 'place' => $place,
            'home' => in_array(strtolower(\App\Data\Names::ascii(preg_replace('/\s.*$/u', '', $place) ?? '')), self::HOME_PLACES, true),
            'round' => '', 'stadium' => '', 'result' => null, 'us' => null, 'them' => null, 'opponent' => '', 'note' => '', 'forfeit' => false,
            'competition' => '', 'club' => '', 'lineup' => [], 'scorers' => [], 'report_lines' => []];
        // Stade précisé avant le résultat (« à Paris, stade Buffalo, victoire 9 à 2 … »).
        if (preg_match('/^((?:stade|parc|terrain|vélodrome)[^,]*), (.+)$/iu', $rest, $r)) {
            $m['stadium'] = trim($r[1]);
            $rest = $r[2];
        }
        // Tour de coupe en tête (« 32èmes, victoire 2 à 1 … »).
        if (preg_match('/^([^,]*(?:èmes?|ème|finale|tour|1\/\d|quarts?|demi|huiti|seizi)[^,]*), (.+)$/iu', $rest, $r) && !preg_match('/^(victoire|défaite|nul|match)/iu', $rest)) {
            $m['round'] = trim($r[1]);
            $rest = $r[2];
        }
        // Note entre parenthèses en fin d'en-tête (« (amical) », « (a.p.) »).
        if (preg_match('/^(.*?)\s*\(([^)]*)\)\s*$/u', $rest, $r)) {
            $m['note'] = trim($r[2]);
            $rest = trim($r[1]);
        }
        // Résultat : « victoire 3 à 1 Lille », « défaite 4 0 C.A Mulhouse », « victoire 4 a 3 Belfort »,
        // « nul Vieux Charmont », « victoire à Bethoncourt », « victoire sur tapis vert RCFC ».
        if (preg_match('/^(victoire|défaite|defaite|match nul|nul)\b\s*(sur tapis vert|par forfait|forfait)?\s*(?:(\d+)\s*(?:à|a|-)?\s*(\d+))?\s*(?:à\s+)?(.*)$/iu', $rest, $r)) {
            $kind = strtolower(\App\Data\Names::ascii($r[1]));
            $m['opponent'] = trim($r[5]);
            if ($r[2] !== '') {
                $m['forfeit'] = true;
                $m['result'] = str_starts_with($kind, 'victoire') ? 'V' : 'D';
            } elseif ($r[3] !== '' && $r[4] !== '') {
                $a = (int) $r[3];
                $b = (int) $r[4];
                [$m['us'], $m['them']] = match (true) {
                    str_starts_with($kind, 'victoire') => [max($a, $b), min($a, $b)],
                    str_starts_with($kind, 'defaite') => [min($a, $b), max($a, $b)],
                    default => [$a, $b],
                };
                $m['result'] = $m['us'] > $m['them'] ? 'V' : ($m['us'] < $m['them'] ? 'D' : 'N');
            } else {
                $m['result'] = str_starts_with($kind, 'victoire') ? 'V' : (str_starts_with($kind, 'defaite') ? 'D' : 'N');
            }
        } else {
            $m['opponent'] = $rest;
        }
        // Lignes suivantes : compétition, note « * », composition, buteurs, puis le récit.
        $i = 1;
        $n = count($lines);
        if ($i < $n && !preg_match('/^(FC Sochaux|Buts|résumé|\*)/iu', $lines[$i]) && mb_strlen($lines[$i]) < 90) {
            $m['competition'] = trim($lines[$i]);
            $i++;
        }
        for (; $i < $n; $i++) {
            $l = $lines[$i];
            if (preg_match('/^\*\s*(.+)$/u', $l, $r)) {
                $m['note'] = trim($m['note'] . ' ' . $r[1]);
            } elseif (preg_match('/^(FC Sochaux(?: Montbéliard| Valentigney)?|F\.?C\.?S\.?V\.?)\s*:\s*(.*)$/iu', $l, $r) && !$m['lineup']) {
                $m['club'] = trim($r[1]);
                $m['lineup'] = self::lineup($r[2]);
            } elseif (preg_match('/^Buts? (?:FC Sochaux[^:]*|FCS[^:]*|Sochaux[^:]*)\s*:\s*(.*)$/iu', $l, $r)) {
                $m['scorers'] = self::scorers($r[1]);
            } elseif (preg_match('/^r[ée]sum[ée]\s*:?$/iu', $l)) {
                $m['report_lines'] = array_slice($lines, $i + 1);
                break;
            } else {
                $m['report_lines'][] = $l;
            }
        }
        $m['report_lines'] = array_values(array_filter($m['report_lines'], fn ($l) => !preg_match('/^(FC Sochaux|F\.?C\.?S)/u', $l) || mb_strlen($l) > 140));
        // Adversaire absent de l'en-tête (deux équipes du club jouaient parfois le même jour) :
        // celui que nomme le récit, sinon l'équipe du lieu quand le match se jouait à l'extérieur.
        if ($m['opponent'] === '') {
            $first = implode(' ', array_slice($m['report_lines'], 0, 3));
            if (preg_match('/\b(?:bat|battu par|contre|reçoit|recevait|et l[ae’\']|face à)\s+(?:l[ae’\'] ?)?((?:[A-Z][\w.,’\'-]*\s?){1,4}?)\s+(?:par|\d)/u', $first, $r)
                && !preg_match('/sochaux|montb[ée]liard/iu', $r[1])) {
                $m['opponent'] = trim($r[1], " ,.");
            } elseif (!$m['home']) {
                $m['opponent'] = $m['place'];
            }
        }
        // Compétition absente : d'après la note de l'en-tête ou le titre du compte rendu.
        if ($m['competition'] === '') {
            $hint = $m['note'] . ' ' . ($m['report_lines'][0] ?? '');
            $m['competition'] = match (true) {
                (bool) preg_match('/\bCdF\b|coupe de france/iu', $hint) => 'Coupe de France',
                (bool) preg_match('/amical/iu', $hint) => 'Match amical',
                (bool) preg_match('/coupe sochaux/iu', $hint) => 'Coupe Sochaux',
                (bool) preg_match('/championnat|district|division|promotion|s[ée]rie/iu', $hint) => 'Championnat',
                default => '',
            };
        }
        $m['amical'] = (bool) preg_match('/amical/iu', $m['competition'] . ' ' . $m['note']);
        return $m;
    }

    /**
     * Composition dans l'ordre de l'époque (2-3-5) : gardien ; arrières ; demis ; avants.
     * @return list<array{name:string,position:string,sub:string}>
     */
    public static function lineup(string $s): array
    {
        $s = trim(preg_replace('/\s*\?\s*/u', ' ', $s) ?? '');
        if ($s === '') {
            return [];
        }
        $s = rtrim(preg_replace('/\s*\((?:c|cap\.?|capitaine)\)\s*/iu', ', ', $s) ?? $s, ' .');
        // Séparateurs hors parenthèses seulement (« Regan (Sète, Millwall) » reste un seul joueur) ;
        // deux espaces valent une virgule oubliée.
        $parts = [];
        $depth = 0;
        $cur = '';
        foreach (preg_split('//u', preg_replace('/\s+et\s+/u', ', ', preg_replace('/ {2,}/u', ', ', $s) ?? $s) ?? $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $depth += $ch === '(' ? 1 : ($ch === ')' ? -1 : 0);
            if ($depth <= 0 && ($ch === ',' || $ch === ';')) {
                $parts[] = $cur;
                $cur = '';
                $depth = 0;
            } else {
                $cur .= $ch;
            }
        }
        $parts[] = $cur;
        $names = array_values(array_filter(array_map('trim', $parts)));
        $out = [];
        foreach ($names as $k => $raw) {
            $sub = '';
            $raw = trim(preg_replace('/\((?:c|cap\.?|capitaine)\)|\b(?:absent|blessé)\b/iu', '', $raw) ?? $raw, ' .');
            $raw = trim(preg_replace('/^\([^)]*\)\s*/u', '', $raw) ?? $raw); // « (Jothmaner) Eastman » : nom barré par l'auteur
            if (preg_match('/^(.*?)\s+puis\s+(.+)$/iu', $raw, $r)) {
                $raw = trim($r[1]);
                $sub = trim($r[2]);
            } elseif (preg_match('/^(.*?)\s*\(([^)]*)\)?\s*$/u', $raw, $r)) {
                $raw = trim($r[1]);
                $sub = trim($r[2]);
            }
            if ($raw === '' || !preg_match('/\p{L}{2}/u', $raw)) {
                continue;
            }
            $pos = $k === 0 ? 'G' : ($k <= 2 ? 'D' : ($k <= 5 ? 'M' : 'A'));
            $out[] = ['name' => $raw, 'position' => $pos, 'sub' => $sub];
        }
        return $out;
    }

    /** « Kenner (4), J Laurent, Cottin 59′ » → [[name, goals, minutes]]. */
    public static function scorers(string $s): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', preg_split('/\s*,\s*|\s+et\s+/u', $s) ?: [])) as $p) {
            $mins = [];
            preg_match_all('/(\d{1,3})\s*[′\'’]/u', $p, $mm);
            $mins = $mm[1];
            $p = trim(preg_replace('/\d{1,3}\s*[′\'’]/u', '', $p) ?? '');
            $g = 1;
            if (preg_match('/^(.*?)\s*\((\d+)\)$/u', $p, $r)) {
                $p = trim($r[1]);
                $g = (int) $r[2];
            }
            if ($p !== '' && !preg_match('/^\d+$/', $p)) {
                $out[] = ['name' => $p, 'goals' => max($g, count($mins)), 'minutes' => $mins];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ analyse complète

    /** Saison « 1931-1932 » d'après le titre ou le slug de la page ; « 1943-1945 » pour la page double. */
    public static function seasonOf(array $item): ?string
    {
        $y = self::years($item['title'] . ' ' . $item['slug']);
        return $y ? $y[0] . '-' . ($y[0] + 1) : null;
    }

    /** Saison sportive d'une date (août → juillet). */
    public static function seasonFor(string $date): string
    {
        $y = (int) substr($date, 0, 4);
        return (int) substr($date, 5, 2) >= 8 ? "$y-" . ($y + 1) : ($y - 1) . "-$y";
    }

    /**
     * Tout ce qui sera repris, sans doublon interne : un match raconté sur une page de saison et sur
     * sa propre page n'en fait qu'un (même date, même adversaire). @return array
     */
    public static function analyse(?array $raw = null): array
    {
        $raw ??= self::raw();
        $seasons = [];
        $matches = [];
        $others = ['match' => [], 'tournament' => [], 'player' => [], 'article' => [], 'skip' => []];
        foreach ($raw as $item) {
            $kind = self::classify($item);
            if ($kind === 'season') {
                $first = self::seasonOf($item);
                $parsed = self::season($item['html'], (string) $first);
                foreach ($parsed['matches'] as $m) {
                    $m['season'] = self::seasonFor($m['date']);
                    $m['source'] = $item['link'];
                    // Chaque match figure deux fois sur la page (calendrier, puis récit) : on garde le bloc le plus riche.
                    $k = self::key($m);
                    $matches[$k] = isset($matches[$k]) ? self::merge($matches[$k], $m) : $m;
                }
                $seasons[] = ['title' => $item['title'], 'link' => $item['link'], 'season' => $first, 'intro' => $parsed['intro'], 'outro' => $parsed['outro'],
                    'count' => count($parsed['matches'])];
            } else {
                $others[$kind][] = ['title' => $item['title'], 'link' => $item['link'], 'slug' => $item['slug'], 'kind' => $item['kind'], 'chars' => mb_strlen(self::text($item['html']))];
            }
        }
        // Matchs racontés à part : rattachés au match de la saison s'il existe, sinon nouveaux.
        foreach ($others['match'] as &$o) {
            $o['date'] = self::dateOfTitle($o['title']);
            $o['merged'] = false;
            foreach ($matches as $k => $m) {
                if ($o['date'] && $m['date'] === $o['date']) {
                    $matches[$k]['extra_sources'][] = $o['link'];
                    $o['merged'] = true;
                    $o['key'] = $k;
                }
            }
        }
        unset($o);
        ksort($matches);
        return ['seasons' => $seasons, 'matches' => array_values($matches), 'others' => $others];
    }

    /** Deux blocs du même match : le plus riche sert de base, l'autre complète ce qui lui manque. */
    private static function merge(array $a, array $b): array
    {
        [$base, $other] = self::weight($b) > self::weight($a) ? [$b, $a] : [$a, $b];
        foreach (['competition', 'round', 'stadium', 'note', 'club'] as $f) {
            if ((string) $base[$f] === '' && (string) $other[$f] !== '') {
                $base[$f] = $other[$f];
            }
        }
        foreach (['lineup', 'scorers'] as $f) {
            if (!$base[$f] && $other[$f]) {
                $base[$f] = $other[$f];
            }
        }
        if ($base['us'] === null && $other['us'] !== null) {
            [$base['us'], $base['them'], $base['result']] = [$other['us'], $other['them'], $other['result']];
        }
        $base['amical'] = $base['amical'] || $other['amical'];
        return $base;
    }

    /** Richesse d'un bloc de match : composition, buteurs, longueur du récit. */
    private static function weight(array $m): int
    {
        return count($m['lineup']) * 50 + count($m['scorers']) * 20 + mb_strlen((string) $m['report']) + ($m['competition'] !== '' ? 30 : 0);
    }

    public static function key(array $m): string
    {
        return $m['date'] . '|' . \App\Data\Names::clubKey((string) $m['opponent']);
    }

    /** Date d'un titre « 13 Mai 1934 Nîmes Sochaux ». */
    public static function dateOfTitle(string $t): ?string
    {
        if (preg_match('/^(\d{1,2}|1er) (\p{L}+) (\d{4})/u', $t, $r)) {
            $mo = self::MONTHS[strtolower(\App\Data\Names::ascii($r[2]))] ?? null;
            $d = $r[1] === '1er' ? 1 : (int) $r[1];
            if ($mo && checkdate($mo, $d, (int) $r[3])) {
                return sprintf('%04d-%02d-%02d', (int) $r[3], $mo, $d);
            }
        }
        return null;
    }
}
