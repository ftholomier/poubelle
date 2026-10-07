<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;

/**
 * Trouvailles (Contenus › Trouvailles) : pour chaque match, recherche dans les archives en ligne
 * de ce qui peut compléter ou corriger sa fiche, puis propositions à valider une par une avant
 * d'être envoyées dans la fiche. Rien n'est écrit dans une fiche sans validation.
 *
 * Sources :
 * – « gallica » : la presse ancienne numérisée par la BnF (quotidiens régionaux comme Le Petit
 *   Comtois ou L'Est républicain, presse sportive comme Match l'Intran ou L'Écho des sports),
 *   pour les matchs de 1955 au plus tard. Numéros parus du jour du match à trois jours après qui
 *   citent exactement « Sochaux » et l'adversaire (recherche SRU), pages qui citent Sochaux
 *   (ContentSearch), texte de ces pages (ALTO), puis Gemini lit les passages et en tire les faits.
 * – « web » : le reste du web, par Gemini avec la recherche Google (toutes époques).
 *
 * Chaque proposition porte un champ (score, buteurs, composition, affluence, arbitre, stade,
 * récit, information, piste), sa valeur, ce que dit la fiche aujourd'hui et ses sources (journal,
 * date, page, lien). Le récit est réécrit dans le style du musée, jamais recopié.
 * storage/trouvailles/state.json : file d'attente et matchs déjà fouillés ;
 * storage/trouvailles/props/{id}.json : propositions d'un match et leur sort.
 */
final class Trouvailles
{
    public static string $dir = STORAGE_PATH . '/trouvailles';
    /** Essais automatiques : remplace les requêtes HTTP (adresse → corps). */
    public static ?\Closure $get = null;
    /** Essais automatiques : remplace Gemini (consigne, texte, options → ['text', 'raw']). */
    public static ?\Closure $ai = null;

    public const SOURCES = ['gallica' => 'Presse ancienne (Gallica, BnF)', 'web' => 'Web (Google, via Gemini)'];
    public const FIELDS = [
        'score' => 'Score', 'buteurs' => 'Buteurs', 'composition' => 'Composition', 'affluence' => 'Affluence',
        'arbitre' => 'Arbitre', 'stade' => 'Stade', 'recit' => 'Récit', 'info' => 'Information', 'piste' => 'Piste à consulter',
    ];
    /** Presse ancienne : jusqu'à cette année (domaine public, presse numérisée par Gallica). */
    public const GALLICA_LAST_YEAR = 1955;
    public const SRU = 'https://gallica.bnf.fr/SRU';
    private const UA = 'SochauxRetro-Musee/1.0 (musee du FC Sochaux-Montbeliard)';
    /** Numéros lus au plus par match, pages par numéro, caractères gardés par page. */
    private const MAX_ISSUES = 8;
    private const MAX_PAGES = 2;
    private const MAX_CHARS = 4500;
    /** Journaux lus en premier : presse de la région et presse sportive. */
    private const PREFERRED = '/comtois|franche|est r[ée]publicain|montb[ée]liard|belfort|doubs|alsace|mulhouse|besan|match|auto\b|sport|miroir|football/iu';
    private const AUTHOR = ['name' => 'Trouvailles (archives)'];

    // ------------------------------------------------------------------ état

    public static function state(): array
    {
        return (JsonStore::read(self::$dir . '/state.json', []) ?? []) + ['queue' => [], 'running' => false, 'sources' => ['gallica'], 'searched' => [], 'log' => [], 'at' => null];
    }

    private static function saveState(callable $fn): array
    {
        return JsonStore::update(self::$dir . '/state.json', function ($s) use ($fn) {
            return $fn((is_array($s) ? $s : []) + ['queue' => [], 'running' => false, 'sources' => ['gallica'], 'searched' => [], 'log' => [], 'at' => null]);
        }, []);
    }

    /** Propositions d'un match : ['id', 'at', 'items' => [...]]. */
    public static function props(int $id): array
    {
        return (JsonStore::read(self::$dir . "/props/$id.json", []) ?? []) + ['id' => $id, 'at' => null, 'items' => []];
    }

    /** Chiffres de l'écran : propositions en attente, envoyées, écartées ; matchs fouillés ; file. */
    public static function summary(): array
    {
        $out = ['attente' => 0, 'envoye' => 0, 'ecarte' => 0, 'matchs' => 0];
        foreach (glob(self::$dir . '/props/*.json') ?: [] as $f) {
            $p = JsonStore::read($f, []) ?? [];
            $has = false;
            foreach ((array) ($p['items'] ?? []) as $it) {
                $out[$it['status'] ?? 'attente'] = ($out[$it['status'] ?? 'attente'] ?? 0) + 1;
                $has = $has || ($it['status'] ?? '') === 'attente';
            }
            $out['matchs'] += $has ? 1 : 0;
        }
        $s = self::state();
        return $out + ['searched' => count($s['searched']), 'queue' => count($s['queue']), 'running' => (bool) $s['running'], 'sources' => $s['sources'], 'at' => $s['at'], 'log' => array_slice($s['log'], 0, 12)];
    }

    /**
     * Matchs qui ont des propositions, pour l'écran : [['id', 'title', 'path', 'date', 'items' => [...]]].
     * $status : attente | envoye | ecarte | tout.
     */
    public static function listing(string $status = 'attente', ?string $origin = null): array
    {
        $out = [];
        foreach (glob(self::$dir . '/props/*.json') ?: [] as $f) {
            $p = JsonStore::read($f, []) ?? [];
            $items = array_values(array_filter((array) ($p['items'] ?? []), fn ($it) => ($status === 'tout' || ($it['status'] ?? 'attente') === $status) && (!$origin || ($it['origin'] ?? '') === $origin)));
            if (!$items || !($e = Index::get((int) ($p['id'] ?? 0)))) {
                continue;
            }
            $out[] = ['id' => (int) $p['id'], 'title' => (string) $e['title'], 'path' => (string) $e['path'], 'date' => (string) ($e['m']['date'] ?? ''), 'items' => $items];
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: $a['id'] <=> $b['id']);
        return $out;
    }

    // ------------------------------------------------------------------ choix des matchs

    /**
     * Matchs à fouiller, du plus ancien au plus récent : années $from à $to ; $incomplete : seulement
     * ceux à qui il manque quelque chose (récit, composition, buteurs, affluence, arbitre, stade) ;
     * $redo : aussi ceux déjà fouillés.
     * @return list<int>
     */
    public static function candidates(int $from, int $to, bool $incomplete = true, bool $redo = false): array
    {
        $searched = self::state()['searched'];
        $list = [];
        foreach (Index::all() as $e) {
            $d = (string) ($e['m']['date'] ?? '');
            if (($e['type'] ?? '') !== 'match' || !preg_match('/^(\d{4})-\d{2}-\d{2}$/', $d, $m) || (int) $m[1] < $from || (int) $m[1] > $to) {
                continue;
            }
            if (!$redo && isset($searched[(string) $e['id']])) {
                continue;
            }
            $list[] = [$d, (int) $e['id']];
        }
        sort($list);
        $out = [];
        foreach ($list as [, $id]) {
            if ($incomplete && (!($doc = Fiches::get($id)) || !self::gaps($doc))) {
                continue;
            }
            $out[] = $id;
        }
        return $out;
    }

    /** Ce qui manque à la fiche d'un match (clés de FIELDS). */
    public static function gaps(array $doc): array
    {
        $m = (array) ($doc['match'] ?? []);
        $gaps = [];
        if (mb_strlen(self::plain(self::text($doc))) < 400) {
            $gaps[] = 'recit';
        }
        if (count((array) ($m['lineup']['rows'] ?? [])) < 7) {
            $gaps[] = 'composition';
        }
        if (trim((string) ($m['goals_text'] ?? '')) === '' && !array_filter((array) ($m['goals'] ?? []), fn ($g) => trim((string) ($g['scorers'] ?? '')) !== '')) {
            $gaps[] = 'buteurs';
        }
        if (empty($m['spectators'])) {
            $gaps[] = 'affluence';
        }
        if (trim((string) ($m['referee'] ?? '')) === '') {
            $gaps[] = 'arbitre';
        }
        if (trim((string) ($m['stadium'] ?? '')) === '') {
            $gaps[] = 'stade';
        }
        if (!is_array($m['score'] ?? null) || !isset($m['score']['home'])) {
            $gaps[] = 'score';
        }
        return $gaps;
    }

    // ------------------------------------------------------------------ file d'attente

    /** Met des matchs dans la file ; la tâche planifiée les fouille quelques-uns à chaque passage. */
    public static function start(array $ids, array $sources): int
    {
        $sources = array_values(array_intersect($sources, array_keys(self::SOURCES))) ?: ['gallica'];
        $s = self::saveState(function ($s) use ($ids, $sources) {
            $s['queue'] = array_values(array_unique(array_merge($s['queue'], array_map('intval', $ids))));
            $s['sources'] = $sources;
            $s['running'] = $s['queue'] !== [];
            return $s;
        });
        return count($s['queue']);
    }

    public static function pause(): void
    {
        self::saveState(function ($s) {
            $s['running'] = false;
            return $s;
        });
    }

    public static function clearQueue(): void
    {
        self::saveState(function ($s) {
            $s['queue'] = [];
            $s['running'] = false;
            return $s;
        });
    }

    /** Tâche planifiée : quelques matchs de la file (en 100 s au plus). */
    public static function tick(int $seconds = 100): ?string
    {
        $s = self::state();
        if (empty($s['running']) || !$s['queue']) {
            return null;
        }
        $r = self::work($seconds, 4);
        return $r['done'] . ' match(s) fouillé(s), ' . $r['found'] . ' proposition(s), ' . $r['left'] . ' restant(s)' . ($r['errors'] ? ', ' . $r['errors'] . ' erreur(s)' : '');
    }

    /** Fouille les premiers matchs de la file. */
    public static function work(int $seconds, int $max): array
    {
        $end = microtime(true) + $seconds;
        $done = $found = $errors = 0;
        while ($done + $errors < $max && microtime(true) < $end) {
            $s = self::state();
            $id = $s['queue'][0] ?? null;
            if ($id === null) {
                break;
            }
            // Retiré de la file avant la recherche : un match qui échoue ne bloque pas les suivants.
            self::saveState(function ($st) use ($id) {
                $st['queue'] = array_values(array_filter($st['queue'], fn ($x) => (int) $x !== (int) $id));
                return $st;
            });
            try {
                $found += self::search((int) $id, $s['sources'])['new'];
                $done++;
            } catch (\Throwable $e) {
                $errors++;
                self::log('Match ' . $id . ' : ' . $e->getMessage());
            }
        }
        $left = count(self::state()['queue']);
        if ($left === 0) {
            self::saveState(function ($s) {
                $s['running'] = false;
                return $s;
            });
        }
        return ['done' => $done, 'found' => $found, 'errors' => $errors, 'left' => $left];
    }

    private static function log(string $msg): void
    {
        self::saveState(function ($s) use ($msg) {
            array_unshift($s['log'], date('d/m H:i') . ' · ' . $msg);
            $s['log'] = array_slice($s['log'], 0, 40);
            return $s;
        });
    }

    // ------------------------------------------------------------------ recherche d'un match

    /**
     * Fouille les sources pour un match et ajoute les nouvelles propositions.
     * @return array{new:int, excerpts:int, messages:list<string>}
     */
    public static function search(int $id, array $sources): array
    {
        $doc = Fiches::get($id);
        if (!$doc || ($doc['type'] ?? '') !== 'match') {
            throw new \RuntimeException('fiche de match introuvable');
        }
        $f = self::facts($doc);
        $new = [];
        $messages = [];
        $excerpts = [];
        if (in_array('gallica', $sources, true) && $f['year'] && $f['year'] <= self::GALLICA_LAST_YEAR) {
            $excerpts = self::gallica($f);
            if ($excerpts) {
                $new = array_merge($new, self::fromPress($doc, $f, $excerpts));
            } else {
                $messages[] = 'Gallica : aucun journal trouvé';
            }
        }
        if (in_array('web', $sources, true)) {
            $new = array_merge($new, self::fromWeb($doc, $f));
        }
        $added = self::store($id, $new);
        self::saveState(function ($s) use ($id, $added, $sources, $excerpts) {
            $s['searched'][(string) $id] = ['at' => date('c'), 'new' => $added, 'sources' => $sources, 'excerpts' => count($excerpts)];
            $s['at'] = date('c');
            return $s;
        });
        return ['new' => $added, 'excerpts' => count($excerpts), 'messages' => $messages];
    }

    /** Ce que la fiche dit du match, pour chercher et comparer. */
    public static function facts(array $doc): array
    {
        $m = (array) ($doc['match'] ?? []);
        $sh = (bool) ($m['sochaux_home'] ?? true);
        $opp = trim((string) ($sh ? ($m['away']['name'] ?? '') : ($m['home']['name'] ?? '')));
        $date = is_string($m['date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $m['date']) ? $m['date'] : null;
        $us = $them = null;
        if (is_array($m['score'] ?? null) && isset($m['score']['home'], $m['score']['away'])) {
            [$us, $them] = $sh ? [(int) $m['score']['home'], (int) $m['score']['away']] : [(int) $m['score']['away'], (int) $m['score']['home']];
        }
        $names = array_map(fn ($r) => (string) ($r['name'] ?? ''), (array) ($m['lineup']['rows'] ?? []));
        $scorers = trim((string) ($m['goals_text'] ?? ''));
        if ($scorers === '') {
            foreach ((array) ($m['goals'] ?? []) as $g) {
                if (!preg_match('/sochaux/iu', (string) ($g['team'] ?? '')) && count((array) ($m['goals'] ?? [])) > 1) {
                    continue;
                }
                $scorers = trim((string) ($g['scorers'] ?? ''));
            }
        }
        return [
            'date' => $date, 'year' => $date ? (int) substr($date, 0, 4) : null, 'opponent' => $opp, 'home' => $sh,
            'competition' => trim((string) (($m['competition_label'] ?? '') ?: ($m['competition'] ?? ''))), 'round' => (string) ($m['round'] ?? ''),
            'us' => $us, 'them' => $them, 'score' => $us !== null ? "$us-$them" : '',
            'spectators' => !empty($m['spectators']) ? (int) $m['spectators'] : null, 'referee' => trim((string) ($m['referee'] ?? '')),
            'stadium' => trim((string) ($m['stadium'] ?? '')), 'scorers' => $scorers, 'lineup' => implode(', ', array_filter($names)),
            'text' => self::plain(self::text($doc)),
        ];
    }

    // ------------------------------------------------------------------ Gallica

    /**
     * Passages de presse sur le match : [['n', 'journal', 'date', 'page', 'url', 'snippet', 'text']].
     */
    public static function gallica(array $f): array
    {
        if (!$f['date']) {
            return [];
        }
        $kw = self::keyword((string) $f['opponent']);
        // Pages qui citent Sochaux, numéro par numéro ; d'abord celles dont l'extrait cite aussi l'adversaire.
        $cands = [];
        foreach (array_slice(self::issues($f), 0, 16) as $is) {
            try {
                $pages = self::pages($is['ark']);
            } catch (\RuntimeException) {
                continue;
            }
            if (!$pages) {
                continue;
            }
            uksort($pages, fn ($a, $b) => (int) self::mentions($pages[$b], $kw) <=> (int) self::mentions($pages[$a], $kw) ?: $a <=> $b);
            $hit = self::mentions((string) reset($pages), $kw);
            // Numéros parus après le match d'abord (compte rendu) ; le jour même, c'est l'avant-match.
            $cands[] = $is + ['pages' => $pages, 'score' => ($hit ? 2 : 0) + ($is['pref'] ? 1 : 0) + ($is['date'] > $f['date'] ? 2 : 0)];
        }
        usort($cands, fn ($a, $b) => $b['score'] <=> $a['score']);
        $out = [];
        $strong = 0;
        foreach ($cands as $is) {
            if (count($out) >= self::MAX_ISSUES) {
                break;
            }
            $text = '';
            $snippet = '';
            $first = null;
            $sure = false;
            foreach (array_slice($is['pages'], 0, self::MAX_PAGES, true) as $page => $snip) {
                try {
                    [$t, $ok] = self::passages(self::altoText(self::fetch('https://gallica.bnf.fr/RequestDigitalElement?O=' . rawurlencode($is['ark']) . '&E=ALTO&Deb=' . $page)), $kw);
                } catch (\RuntimeException) {
                    continue; // page illisible ce jour-là : les autres suffisent
                }
                if ($t === '' || (!$ok && $sure)) {
                    continue;
                }
                $first ??= $page;
                $snippet = $snippet ?: $snip;
                $sure = $sure || $ok;
                $text .= ($text !== '' ? "\n[…]\n" : '') . $t;
            }
            // Passage sans l'adversaire : seulement s'il manque encore des passages sûrs.
            if ($text === '' || (!$sure && $strong >= 3)) {
                continue;
            }
            $strong += $sure ? 1 : 0;
            $out[] = [
                'n' => count($out) + 1, 'journal' => $is['journal'], 'date' => $is['date'], 'page' => $first, 'sure' => $sure,
                'url' => 'https://gallica.bnf.fr/ark:/12148/' . $is['ark'] . '/f' . $first . '.item', 'snippet' => mb_substr($snippet, 0, 400), 'text' => mb_substr($text, 0, self::MAX_CHARS * self::MAX_PAGES),
            ];
        }
        return $out;
    }

    /** Le texte cite-t-il l'adversaire (mot-clé, sans accents ni casse) ? */
    private static function mentions(string $text, string $kw): bool
    {
        return $kw !== '' && str_contains(Names::ascii($text), Names::ascii($kw));
    }

    /** Mot de l'adversaire cherché dans la presse (le plus distinctif), ou ''. */
    public static function keyword(string $opponent): string
    {
        $stop = ['football', 'club', 'olympique', 'stade', 'racing', 'sporting', 'union', 'sportive', 'association', 'athletic', 'athletique', 'reserve', 'reserves', 'amateurs', 'equipe', 'red', 'star', 'saint', 'sur', 'les'];
        $best = '';
        foreach (preg_split('/[\s\-\'’.()]+/u', $opponent) ?: [] as $w) {
            $a = Names::ascii($w);
            if (mb_strlen($a) < 4 || in_array($a, $stop, true) || preg_match('/\d/', $a)) {
                continue;
            }
            if (mb_strlen($w) > mb_strlen($best)) {
                $best = $w;
            }
        }
        // « Red Star », « Stade Français » : pas de mot distinctif hors liste, on garde le nom entier.
        return $best !== '' ? $best : (mb_strlen($opponent) >= 4 ? $opponent : '');
    }

    /** Numéros de journaux parus du jour du match à 3 jours après, qui citent Sochaux et l'adversaire. */
    public static function issues(array $f): array
    {
        $kw = self::keyword((string) $f['opponent']);
        $out = [];
        // Une recherche par jour : sur plusieurs jours, Gallica ne renvoie qu'un numéro par journal.
        for ($k = 3; $k >= 0; $k--) {
            $day = str_replace('-', '/', date('Y-m-d', (int) strtotime($f['date'] . " +$k days")));
            $q = '(text adj "Sochaux"' . ($kw !== '' ? ' and text adj "' . str_replace('"', '', $kw) . '"' : '') . ') and (dc.type all "fascicule") and (gallicapublication_date>="'
                . $day . '" and gallicapublication_date<="' . $day . '")';
            try {
                $xml = self::fetch(self::SRU . '?operation=searchRetrieve&version=1.2&maximumRecords=30&query=' . rawurlencode($q));
            } catch (\RuntimeException) {
                continue;
            }
            foreach (array_slice(explode('<srw:record>', $xml), 1) as $r) {
                if (!preg_match('#<uri>\s*([a-z0-9]+)\s*</uri>#i', $r, $u) || !preg_match('#<dc:title>([^<]*)</dc:title>#u', $r, $t) || isset($out[$u[1]])) {
                    continue;
                }
                $journal = trim(preg_replace('/\s*[:(\[].*$/u', '', html_entity_decode($t[1], ENT_QUOTES | ENT_XML1, 'UTF-8')) ?? $t[1]);
                $out[$u[1]] = ['ark' => $u[1], 'journal' => $journal !== '' ? $journal : 'Journal', 'pref' => (bool) preg_match(self::PREFERRED, $t[1]), 'date' => str_replace('/', '-', $day)];
            }
        }
        $out = array_values($out);
        // Presse de la région et presse sportive d'abord ; à égalité, le lendemain du match d'abord.
        usort($out, fn ($a, $b) => (int) $b['pref'] <=> (int) $a['pref'] ?: ($a['date'] === $f['date']) <=> ($b['date'] === $f['date']));
        return $out;
    }

    /** Pages d'un numéro qui citent exactement « Sochaux » : [page => extrait]. */
    public static function pages(string $ark): array
    {
        $xml = self::fetch('https://gallica.bnf.fr/services/ContentSearch?ark=' . rawurlencode($ark) . '&query=' . rawurlencode('"Sochaux"'));
        $out = [];
        foreach (array_slice(explode('<item>', $xml), 1) as $it) {
            if (!preg_match('#<p_id>\s*PAG_(\d+)\s*</p_id>#', $it, $p)) {
                continue;
            }
            $c = preg_match('#<content>(.*?)</content>#s', $it, $cm) ? $cm[1] : '';
            $c = html_entity_decode(html_entity_decode($c, ENT_QUOTES | ENT_XML1, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $c = trim(preg_replace('/\s+/u', ' ', strip_tags($c)) ?? '');
            if (!isset($out[(int) $p[1]]) || mb_strlen($c) > mb_strlen($out[(int) $p[1]])) {
                $out[(int) $p[1]] = $c;
            }
        }
        return $out;
    }

    /** Texte d'une page ALTO : mots, coupures de fin de ligne recollées, blocs séparés. */
    public static function altoText(string $xml): string
    {
        if (!mb_check_encoding($xml, 'UTF-8')) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1');
        }
        $out = [];
        foreach (preg_split('#<TextBlock\b#', $xml) ?: [] as $block) {
            $words = [];
            preg_match_all('#<String\b([^>]*)/?>#', $block, $mm);
            foreach ($mm[1] as $attrs) {
                $get = fn (string $k) => preg_match('/\b' . $k . '="([^"]*)"/', $attrs, $v) ? html_entity_decode($v[1], ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
                $type = $get('SUBS_TYPE');
                if ($type === 'HypPart2') {
                    continue; // déjà recollé avec la première moitié
                }
                $w = $type === 'HypPart1' ? ($get('SUBS_CONTENT') ?? $get('CONTENT')) : $get('CONTENT');
                if ($w !== null && $w !== '') {
                    $words[] = $w;
                }
            }
            if ($words) {
                $out[] = implode(' ', $words);
            }
        }
        return implode("\n", $out);
    }

    /**
     * Passages autour de chaque « Sochaux » (1 500 caractères de part et d'autre, fusionnés) ; s'il y
     * en a qui citent aussi l'adversaire ($kw), seulement ceux-là. @return array{0:string,1:bool}
     * texte, et vrai si l'adversaire y est cité
     */
    public static function passages(string $text, string $kw = '', int $around = 1500): array
    {
        if (!preg_match_all('/so[cç]haux/iu', $text, $mm, PREG_OFFSET_CAPTURE)) {
            return ['', false];
        }
        $spans = [];
        foreach ($mm[0] as [, $at]) {
            $a = max(0, $at - $around);
            $b = min(strlen($text), $at + $around);
            if ($spans && $a <= $spans[count($spans) - 1][1]) {
                $spans[count($spans) - 1][1] = $b;
            } else {
                $spans[] = [$a, $b];
            }
        }
        $parts = array_map(fn ($sp) => mb_strcut($text, $sp[0], $sp[1] - $sp[0], 'UTF-8'), $spans);
        $with = array_values(array_filter($parts, fn ($p) => self::mentions($p, $kw)));
        return [mb_substr(trim(implode("\n[…]\n", $with ?: $parts)), 0, self::MAX_CHARS), $with !== []];
    }

    private static function fetch(string $url): string
    {
        if (self::$get) {
            return (string) (self::$get)($url);
        }
        for ($try = 1; ; $try++) {
            try {
                return self::download($url);
            } catch (\RuntimeException $e) {
                if ($try >= 2 || str_contains($e->getMessage(), 'anti-robot')) {
                    throw $e;
                }
                sleep(2);
            }
        }
    }

    private static function download(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_USERAGENT => self::UA]);
        $b = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($b) || $b === '' || $code >= 400) {
            throw new \RuntimeException('Gallica ne répond pas (' . preg_replace('/\?.*/', '', $url) . ')');
        }
        // Page anti-robot de Gallica au lieu du document demandé.
        if (str_contains(substr($b, 0, 3000), 'altcha')) {
            throw new \RuntimeException('Gallica demande une vérification anti-robot ; réessayez plus tard');
        }
        // Courtoisie : pas plus d'environ deux demandes par seconde.
        usleep(400000);
        return $b;
    }

    // ------------------------------------------------------------------ lecture par l'IA

    /** Consigne commune : ce qu'il faut tirer des sources, au format JSON. */
    private static function schema(bool $web): string
    {
        $src = $web ? '"urls": ["adresse des pages qui l\'appuient"]' : '"sources": [numéros des extraits qui l\'appuient]';
        return <<<TXT
Réponds uniquement par un objet JSON, sans texte autour :
{"concerne": true ou false (les sources parlent-elles bien de CE match ?),
 "score": {"sochaux": nombre, "adversaire": nombre, $src} ou null,
 "buteurs": {"valeur": "buteurs de Sochaux avec minute si connue, ex. « Kennedy 12e, Laurent 40e »", $src} ou null,
 "composition": {"valeur": "les onze joueurs de Sochaux dans l'ordre du journal (gardien d'abord), séparés par des virgules, noms seuls tels qu'écrits (orthographe corrigée si l'erreur de lecture est évidente)", $src} ou null,
 "affluence": {"valeur": nombre de spectateurs, $src} ou null,
 "arbitre": {"valeur": "nom de l'arbitre", $src} ou null,
 "stade": {"valeur": "nom du stade ou du terrain", $src} ou null,
 "recit": {"texte": "récit du match de 80 à 220 mots", $src} ou null,
 "infos": [{"texte": "un fait précis et utile qui n'entre pas ailleurs (blessure, anecdote, contexte, météo, recette…)", $src}],
 "pistes": [{"texte": "une page ou une archive à consulter et pourquoi", $src}]}
TXT;
    }

    private static function rules(): string
    {
        return <<<'TXT'
Règles :
– seulement ce que disent les sources : n'invente rien, ne devine rien ; une information absente reste null ;
– reste sur le match exact (même date, même adversaire) : une autre équipe de Sochaux (réserve, juniors), un autre match ou un homonyme ne compte pas ; si rien ne concerne le match, "concerne": false et tout à null ;
– les textes viennent souvent d'une lecture automatique de vieux journaux : corrige les erreurs de lecture évidentes des noms (« Matller » → « Mattler ») sans rien inventer ;
– le récit est entièrement rédigé par toi, en français, au passé, sobre et vivant, dans le style d'un musée : ne reprends jamais une phrase des sources, ni une suite de plus de cinq mots ; pas de titre, pas de citation ;
– au plus 4 infos et 3 pistes, les plus utiles.
TXT;
    }

    /** Propositions tirées de la presse ancienne. */
    private static function fromPress(array $doc, array $f, array $excerpts): array
    {
        $system = "Tu aides les historiens du musée en ligne Sochaux Rétro (histoire du FC Sochaux-Montbéliard). On te donne un match du musée et des extraits de journaux de l'époque, numérotés. Tires-en ce qui concerne ce match.\n" . self::rules() . "\n" . self::schema(false);
        $text = self::describe($f) . "\n\nEXTRAITS DE PRESSE\n";
        foreach ($excerpts as $e) {
            $text .= "\n[" . $e['n'] . '] ' . $e['journal'] . ($e['date'] ? ', ' . $e['date'] : '') . ', page ' . $e['page'] . "\n" . $e['text'] . "\n";
        }
        $r = self::ask($system, $text, ['ref' => 'fiche:' . (int) $doc['id']]);
        $d = self::decode((string) ($r['text'] ?? ''));
        if (!$d || empty($d['concerne'])) {
            return [];
        }
        $byN = [];
        foreach ($excerpts as $e) {
            $byN[$e['n']] = ['label' => $e['journal'] . ($e['date'] ? ', ' . self::frDate($e['date']) : '') . ', p. ' . $e['page'], 'url' => $e['url'], 'snippet' => $e['snippet'], 'kind' => 'gallica'];
        }
        $src = function ($x) use ($byN) {
            $out = [];
            foreach ((array) ($x['sources'] ?? []) as $n) {
                if (isset($byN[(int) $n])) {
                    $out[(int) $n] = $byN[(int) $n];
                }
            }
            return array_values($out ?: $byN);
        };
        $all = implode("\n", array_column($excerpts, 'text'));
        return self::build($f, $d, $src, 'gallica', $all);
    }

    /** Propositions trouvées sur le web (Gemini avec la recherche Google). */
    private static function fromWeb(array $doc, array $f): array
    {
        $system = "Tu aides les historiens du musée en ligne Sochaux Rétro (histoire du FC Sochaux-Montbéliard). Cherche sur le web, avec Google, ce qui est publié sur le match décrit (comptes rendus, archives de presse, sites d'histoire et de statistiques du football), sauf le site du musée lui-même (fcsochauxretro.com). Tires-en ce qui concerne ce match.\n" . self::rules() . "\n" . self::schema(true);
        $r = self::ask($system, self::describe($f), ['ref' => 'fiche:' . (int) $doc['id'], 'tools' => [['google_search' => new \stdClass()]], 'raw' => true, 'json' => false]);
        $d = self::decode((string) ($r['text'] ?? ''));
        if (!$d || empty($d['concerne'])) {
            return [];
        }
        // Pages consultées par Google (ancrage) : seules sources affichées, avec leur titre.
        $grounded = [];
        foreach ((array) ($r['raw']['candidates'][0]['groundingMetadata']['groundingChunks'] ?? []) as $c) {
            $uri = trim((string) ($c['web']['uri'] ?? ''));
            if (preg_match('#^https?://[^\s"<>]+$#i', $uri)) {
                $title = trim((string) ($c['web']['title'] ?? '')) ?: (string) parse_url($uri, PHP_URL_HOST);
                if (!preg_match('/fcsochauxretro\.com/i', $title . $uri)) {
                    $grounded[] = ['label' => mb_substr($title, 0, 120), 'url' => $uri, 'snippet' => '', 'kind' => 'web'];
                }
            }
        }
        $src = function ($x) use ($grounded) {
            $out = [];
            foreach ((array) ($x['urls'] ?? []) as $u) {
                $u = (string) $u;
                foreach ($grounded as $g) {
                    if ($u !== '' && (str_contains($g['url'], $u) || stripos($u, $g['label']) !== false || stripos($g['label'], (string) parse_url($u, PHP_URL_HOST)) !== false)) {
                        $out[$g['url']] = $g;
                    }
                }
                if (preg_match('#^https?://[^\s"<>]+$#i', $u) && !preg_match('/fcsochauxretro\.com/i', $u) && !$out) {
                    $out[$u] = ['label' => (string) parse_url($u, PHP_URL_HOST), 'url' => $u, 'snippet' => '', 'kind' => 'web'];
                }
            }
            return array_values($out ?: array_slice($grounded, 0, 5));
        };
        // Sans aucune page consultée, rien n'est proposé (une réponse sans source ne vaut rien).
        if (!$grounded) {
            return [];
        }
        return self::build($f, $d, $src, 'web', '');
    }

    /** Le match tel que le musée le connaît, pour l'IA. */
    private static function describe(array $f): string
    {
        $lines = [
            'MATCH DU MUSÉE',
            'Date : ' . ($f['date'] ? self::frDate($f['date']) : 'inconnue'),
            'Rencontre : ' . ($f['home'] ? 'Sochaux (FC Sochaux-Montbéliard) contre ' . $f['opponent'] . ', à Sochaux ou chez Sochaux' : $f['opponent'] . ' contre Sochaux (FC Sochaux-Montbéliard), chez l\'adversaire'),
            'Compétition : ' . ($f['competition'] ?: 'non renseignée') . ($f['round'] ? ' (' . $f['round'] . ')' : ''),
            'Score selon le musée (Sochaux d\'abord) : ' . ($f['score'] ?: 'non renseigné'),
            'Buteurs selon le musée : ' . ($f['scorers'] ?: 'non renseignés'),
            'Composition selon le musée : ' . ($f['lineup'] ?: 'non renseignée'),
            'Affluence : ' . ($f['spectators'] ?: 'non renseignée') . ' · Arbitre : ' . ($f['referee'] ?: 'non renseigné') . ' · Stade : ' . ($f['stadium'] ?: 'non renseigné'),
        ];
        return implode("\n", $lines);
    }

    private static function ask(string $system, string $text, array $opt): array
    {
        $opt += ['for' => 'trouvailles', 'model' => WebCheck::model(), 'max_tokens' => 4096, 'temperature' => 0.4, 'timeout' => 120, 'json' => true];
        if (self::$ai) {
            return (array) (self::$ai)($system, $text, $opt);
        }
        if (!Gemini::ready()) {
            throw new \RuntimeException('il faut une clé Gemini (Réglages › Assistant IA)');
        }
        return Gemini::generate([['role' => 'user', 'text' => $text]], $system, $opt);
    }

    private static function decode(string $t): ?array
    {
        $t = trim(preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($t)) ?? '');
        $d = json_decode($t, true);
        if (!is_array($d) && ($a = strpos($t, '{')) !== false && ($b = strrpos($t, '}')) > $a) {
            $d = json_decode(substr($t, $a, $b - $a + 1), true);
        }
        return is_array($d) ? $d : null;
    }

    // ------------------------------------------------------------------ propositions

    /**
     * Propositions à partir de la réponse de l'IA : rien de ce que la fiche dit déjà à l'identique.
     * $src : réponse → sources ; $original : texte des sources (le récit ne doit pas le recopier).
     */
    private static function build(array $f, array $d, callable $src, string $origin, string $original): array
    {
        $out = [];
        $add = function (string $field, string $value, string $current, array $sources) use (&$out, $origin) {
            $value = trim(preg_replace('/[ \t]+/u', ' ', $value) ?? $value);
            if ($value === '' || !$sources) {
                return;
            }
            if ($current !== '' && self::norm($current) === self::norm($value)) {
                return; // la fiche le dit déjà
            }
            $type = in_array($field, ['recit', 'info', 'piste'], true) ? $field : ($current === '' ? 'complement' : 'divergence');
            $out[] = ['field' => $field, 'value' => $value, 'current' => $current, 'type' => $type, 'origin' => $origin, 'sources' => $sources];
        };
        $s = $d['score'] ?? null;
        if (is_array($s) && is_numeric($s['sochaux'] ?? null) && is_numeric($s['adversaire'] ?? null)) {
            $add('score', (int) $s['sochaux'] . '-' . (int) $s['adversaire'], $f['score'], $src($s));
        }
        foreach (['buteurs' => 'scorers', 'composition' => 'lineup', 'arbitre' => 'referee', 'stade' => 'stadium'] as $k => $cur) {
            $x = $d[$k] ?? null;
            if (is_array($x) && is_scalar($x['valeur'] ?? null)) {
                $add($k, (string) $x['valeur'], (string) $f[$cur], $src($x));
            }
        }
        $x = $d['affluence'] ?? null;
        if (is_array($x) && ($n = (int) preg_replace('/\D/', '', (string) ($x['valeur'] ?? ''))) > 0 && $n <= 90000) {
            $add('affluence', (string) $n, $f['spectators'] ? (string) $f['spectators'] : '', $src($x));
        }
        $x = $d['recit'] ?? null;
        if (is_array($x) && ($t = trim((string) ($x['texte'] ?? ''))) !== '' && FicheAudio::words($t) >= 40) {
            // Récit trop proche des sources (suites de six mots reprises) : écarté.
            if ($original === '' || FcsmImport::similarity($original, $t) <= 0.08) {
                $add('recit', $t, '', $src($x));
            }
        }
        foreach (['infos' => 'info', 'pistes' => 'piste'] as $k => $field) {
            foreach (array_slice((array) ($d[$k] ?? []), 0, $field === 'info' ? 4 : 3) as $x) {
                if (is_array($x) && ($t = trim((string) ($x['texte'] ?? ''))) !== '') {
                    $add($field, mb_substr($t, 0, 600), '', $src($x));
                }
            }
        }
        return $out;
    }

    /** Ajoute les propositions nouvelles (jamais deux fois la même, même écartée). @return int ajoutées */
    private static function store(int $id, array $new): int
    {
        if (!$new) {
            return 0;
        }
        $added = 0;
        JsonStore::update(self::$dir . "/props/$id.json", function ($p) use ($id, $new, &$added) {
            $p = (is_array($p) ? $p : []) + ['id' => $id, 'at' => null, 'items' => []];
            $seen = [];
            foreach ($p['items'] as $k => $it) {
                $seen[$it['field'] . '|' . self::norm($it['value'])] = $k;
            }
            foreach ($new as $it) {
                $key = $it['field'] . '|' . self::norm($it['value']);
                if (isset($seen[$key])) {
                    // Même proposition d'une autre source : ses sources s'ajoutent.
                    $k = $seen[$key];
                    $urls = array_column($p['items'][$k]['sources'], 'url');
                    foreach ($it['sources'] as $s) {
                        if (!in_array($s['url'], $urls, true)) {
                            $p['items'][$k]['sources'][] = $s;
                        }
                    }
                    continue;
                }
                $p['items'][] = $it + ['id' => substr(sha1($key . microtime() . random_int(0, PHP_INT_MAX)), 0, 10), 'status' => 'attente', 'at' => date('c')];
                $seen[$key] = count($p['items']) - 1;
                $added++;
            }
            $p['at'] = date('c');
            return $p;
        }, []);
        return $added;
    }

    // ------------------------------------------------------------------ validation

    /**
     * Envoie une proposition dans la fiche du match (valeur modifiée par l'historien si $value).
     * @return string ce qui a été fait
     */
    public static function accept(int $id, string $itemId, ?string $value, ?array $user): string
    {
        $p = self::props($id);
        $k = array_search($itemId, array_column($p['items'], 'id'), true);
        if ($k === false) {
            throw new \RuntimeException('Proposition introuvable.');
        }
        $it = $p['items'][$k];
        if (($it['status'] ?? '') !== 'attente') {
            throw new \RuntimeException('Proposition déjà traitée.');
        }
        $value = trim((string) ($value ?? $it['value']));
        if ($value === '') {
            throw new \RuntimeException('Valeur vide.');
        }
        $doc = Fiches::fresh($id);
        if (!$doc || ($doc['type'] ?? '') !== 'match') {
            throw new \RuntimeException('Fiche introuvable.');
        }
        $done = self::apply($doc, $it['field'], $value, $it['sources'], $it['origin'] ?? 'gallica');
        self::addSources($doc, $it['sources']);
        Fiches::save($doc, $user ?? self::AUTHOR, 'Trouvaille validée (' . self::FIELDS[$it['field']] . ')');
        JsonStore::update(self::$dir . "/props/$id.json", function ($pp) use ($itemId, $value, $user) {
            foreach ($pp['items'] as &$x) {
                if ($x['id'] === $itemId) {
                    $x['status'] = 'envoye';
                    $x['sent'] = $value;
                    $x['by'] = (string) ($user['name'] ?? '');
                    $x['done_at'] = date('c');
                }
            }
            return $pp;
        }, []);
        return $done;
    }

    public static function reject(int $id, string $itemId, ?array $user): void
    {
        JsonStore::update(self::$dir . "/props/$id.json", function ($pp) use ($itemId, $user) {
            foreach ((array) ($pp['items'] ?? []) as $i => $x) {
                if ($x['id'] === $itemId && ($x['status'] ?? '') === 'attente') {
                    $pp['items'][$i]['status'] = 'ecarte';
                    $pp['items'][$i]['by'] = (string) ($user['name'] ?? '');
                    $pp['items'][$i]['done_at'] = date('c');
                }
            }
            return $pp;
        }, []);
    }

    /** Remet une proposition écartée en attente. */
    public static function restore(int $id, string $itemId): void
    {
        JsonStore::update(self::$dir . "/props/$id.json", function ($pp) use ($itemId) {
            foreach ((array) ($pp['items'] ?? []) as $i => $x) {
                if ($x['id'] === $itemId && ($x['status'] ?? '') === 'ecarte') {
                    $pp['items'][$i]['status'] = 'attente';
                }
            }
            return $pp;
        }, []);
    }

    /** Écrit la valeur dans la fiche. @return string ce qui a été fait */
    private static function apply(array &$doc, string $field, string $value, array $sources, string $origin): string
    {
        $m = &$doc['match'];
        $sh = (bool) ($m['sochaux_home'] ?? true);
        switch ($field) {
            case 'score':
                if (!preg_match('/^\s*(\d{1,2})\s*[-–à]\s*(\d{1,2})\s*$/u', $value, $s)) {
                    throw new \RuntimeException('Score attendu sous la forme « 3-1 » (Sochaux d’abord).');
                }
                [$us, $them] = [(int) $s[1], (int) $s[2]];
                $prev = is_array($m['score'] ?? null) ? $m['score'] : [];
                $m['score'] = ['home' => $sh ? $us : $them, 'away' => $sh ? $them : $us] + $prev + ['extra' => null, 'aet' => false, 'pens' => null];
                $m['score_raw'] = $m['score']['home'] . '-' . $m['score']['away'];
                $m['result'] = $us > $them ? 'V' : ($us < $them ? 'D' : 'N');
                return 'score : ' . $m['score_raw'];
            case 'affluence':
                $n = (int) preg_replace('/\D/', '', $value);
                if ($n <= 0 || $n > 90000) {
                    throw new \RuntimeException('Affluence attendue en nombre de spectateurs.');
                }
                $m['spectators'] = $n;
                $m['spectators_text'] = '';
                return 'affluence : ' . $n;
            case 'arbitre':
                $m['referee'] = $value;
                return 'arbitre : ' . $value;
            case 'stade':
                $m['stadium'] = $value;
                $m['stadium_id'] = null;
                return 'stade : ' . $value;
            case 'buteurs':
                $m['goals_text'] = $value;
                $others = array_values(array_filter((array) ($m['goals'] ?? []), fn ($g) => !preg_match('/sochaux/iu', (string) ($g['team'] ?? ''))));
                $m['goals'] = array_merge([['team' => 'Sochaux', 'scorers' => $value]], $others);
                return 'buteurs : ' . $value;
            case 'composition':
                $names = array_values(array_filter(array_map('trim', preg_split('/\s*[,;]\s*/u', $value) ?: [])));
                if (count($names) < 7) {
                    throw new \RuntimeException('Composition attendue : au moins sept noms séparés par des virgules.');
                }
                // Joueurs déjà reliés à leur fiche : le lien est gardé quand le nom de famille correspond.
                $known = [];
                foreach ((array) ($m['lineup']['rows'] ?? []) as $r) {
                    $known[Names::lineupLastName((string) ($r['name'] ?? ''))] = $r;
                }
                $rows = [];
                foreach ($names as $i => $n) {
                    $name = FcsmImport::lineupName($n);
                    $old = $known[Names::lineupLastName($name)] ?? null;
                    $rows[] = $old ?? ['position' => $i === 0 ? 'G' : '', 'name' => $name, 'number' => null, 'extra' => null, 'captain' => false, 'goals' => [], 'own_goals' => [],
                        'goals_text' => '', 'sub_in' => null, 'sub_out' => null, 'sub_text' => '', 'yellow' => [], 'red' => [], 'cards_text' => '', 'person_id' => null];
                }
                $m['lineup']['rows'] = $rows;
                $m['lineup']['source_table'] = null;
                return 'composition : ' . count($rows) . ' joueurs';
            case 'recit':
                $title = $origin === 'gallica' ? 'Dans la presse de l’époque' : 'Ce qu’en disent les sources';
                self::section($doc, $title, self::paras($value) . self::credit($sources));
                return 'récit ajouté (section « ' . $title . ' »)';
            case 'info':
                self::section($doc, 'Compléments', '<p>' . e($value) . self::credit($sources, true) . '</p>', true);
                return 'information ajoutée (section « Compléments »)';
            case 'piste':
                return 'piste notée dans les sources';
        }
        throw new \RuntimeException('Champ inconnu.');
    }

    /** Ajoute (ou complète) une section, toujours avant « Sources ». */
    private static function section(array &$doc, string $title, string $html, bool $append = false): void
    {
        $sections = (array) ($doc['sections'] ?? []);
        foreach ($sections as $i => $s) {
            if ($append && ($s['title'] ?? '') === $title) {
                $sections[$i]['html'] = ($s['html'] ?? '') . $html;
                $doc['sections'] = $sections;
                return;
            }
        }
        $at = count($sections);
        foreach ($sections as $i => $s) {
            if (($s['title'] ?? '') === 'Sources') {
                $at = $i;
                break;
            }
        }
        array_splice($sections, $at, 0, [['title' => $title, 'html' => $html]]);
        $doc['sections'] = $sections;
    }

    /** Les sources de la proposition dans la section « Sources » de la fiche (sans doublon). */
    private static function addSources(array &$doc, array $sources): void
    {
        $legacy = (array) ($doc['legacy'] ?? []);
        $known = (array) ($legacy['trouvailles'] ?? []);
        $lines = '';
        foreach ($sources as $s) {
            if (in_array($s['url'], $known, true)) {
                continue;
            }
            $known[] = $s['url'];
            $lines .= '<li><a href="' . e($s['url']) . '" rel="noopener" target="_blank">' . e($s['label']) . '</a>' . (($s['kind'] ?? '') === 'gallica' ? ' (Gallica, BnF)' : '') . '</li>';
        }
        if ($lines === '') {
            return;
        }
        $legacy['trouvailles'] = $known;
        $doc['legacy'] = $legacy;
        $sections = (array) ($doc['sections'] ?? []);
        foreach ($sections as $i => $s) {
            if (($s['title'] ?? '') === 'Sources') {
                $html = (string) ($s['html'] ?? '');
                $sections[$i]['html'] = str_contains($html, '<ul data-trouvailles>') ? str_replace('<ul data-trouvailles>', '<ul data-trouvailles>' . $lines, $html) : $html . '<ul data-trouvailles>' . $lines . '</ul>';
                $doc['sections'] = $sections;
                return;
            }
        }
        $sections[] = ['title' => 'Sources', 'html' => '<ul data-trouvailles>' . $lines . '</ul>'];
        $doc['sections'] = $sections;
    }

    private static function credit(array $sources, bool $inline = false): string
    {
        $links = implode(', ', array_map(fn ($s) => '<a href="' . e($s['url']) . '" rel="noopener" target="_blank">' . e($s['label']) . '</a>', array_slice($sources, 0, 4)));
        if ($links === '') {
            return '';
        }
        return $inline ? ' <small>(' . $links . ')</small>' : '<p><small>D’après ' . $links . '.</small></p>';
    }

    private static function paras(string $t): string
    {
        $ps = array_filter(array_map('trim', preg_split('/\n\s*\n|\n/u', trim($t)) ?: []));
        return implode('', array_map(fn ($p) => '<p>' . e($p) . '</p>', $ps));
    }

    // ------------------------------------------------------------------ outils

    /** Texte d'une fiche (intro et sections, hors « Sources »). */
    private static function text(array $doc): string
    {
        $t = (string) ($doc['intro'] ?? '');
        foreach ((array) ($doc['sections'] ?? []) as $s) {
            if (($s['title'] ?? '') !== 'Sources') {
                $t .= "\n" . ($s['html'] ?? '');
            }
        }
        return $t;
    }

    private static function plain(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /** Forme comparable d'une valeur (minuscules, sans accents ni ponctuation). */
    private static function norm(string $s): string
    {
        return implode(' ', Names::tokens($s));
    }

    public static function frDate(string $iso): string
    {
        $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            return $iso;
        }
        return ((int) $m[3] === 1 ? '1er' : (string) (int) $m[3]) . ' ' . $mois[(int) $m[2] - 1] . ' ' . $m[1];
    }
}
