<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Media;
use App\Data\Names;
use App\Data\Paths;

/**
 * Boîte à idées des 100 moments : l'IA propose, les historiens décident.
 * - Sommaire : l'IA lit un catalogue des fiches publiées du musée, époque par époque (matchs
 *   marquants, joueurs, entraîneurs et dirigeants, articles, objets) et propose des idées de
 *   moments, chacune appuyée sur des fiches de ce catalogue : une idée sans source est écartée
 *   d'office, l'IA n'invente rien. Une piste précise (« les années 1930 », « les supporters »)
 *   et le remplacement d'une idée (« Autre idée ») suivent les mêmes règles.
 * - Tri par les historiens : retenir, modifier, écarter (la raison est rappelée à l'IA), ajouter
 *   leurs propres idées.
 * - Premier jet : pour une idée retenue, l'IA rédige le récit à partir des seules fiches sources
 *   et crée la fiche « Moment » en statut « À relire », avec ses sources et les points à vérifier.
 *   L'IA ne date ni ne publie jamais rien : la validation (date de parution) est humaine, et l'IA
 *   ne touche plus à un moment une fois rédigé.
 * Idées : data/collections/moments-idees.json. Coûts : usage « moments » (écran Coûts IA).
 */
final class MomentIdeas
{
    public const FILE = DATA_PATH . '/collections/moments-idees.json';
    /** Fichier des idées (un autre pour les tests). */
    public static string $file = self::FILE;
    public const STATES = ['proposee' => 'Proposée', 'retenue' => 'Retenue', 'redigee' => 'Rédigée', 'ecartee' => 'Écartée'];
    public const THEMES = ['match' => 'Match', 'joueur' => 'Joueur, entraîneur', 'club' => 'Vie du club', 'stade' => 'Stade', 'supporters' => 'Supporters', 'formation' => 'Formation', 'autre' => 'Autre'];
    /** Époques du sommaire : [première année, dernière année, idées demandées] ; [0, 0] : sujets transversaux. */
    public const ERAS = [[1928, 1939, 14], [1940, 1959, 12], [1960, 1969, 10], [1970, 1979, 12], [1980, 1989, 18], [1990, 1999, 18], [2000, 2009, 20], [2010, 2019, 18], [2020, 2028, 12], [0, 0, 16]];
    /** Fiches sources lues pour un premier jet. */
    private const MAX_SOURCES = 6;
    /** Faux Gemini des tests : fn (list<[contents, system, opt]>) => list<résultat de Gemini::generate ou ['error' => …]>. */
    public static $ai = null;

    // ------------------------------------------------------------------ idées

    /** @return list<array> idées, de la plus ancienne à la plus récente (années), puis par titre */
    public static function all(): array
    {
        $ideas = array_values((array) ((JsonStore::read(self::$file, []) ?: [])['ideas'] ?? []));
        usort($ideas, fn ($a, $b) => [(int) ($a['year'] ?? 9999), Names::slug((string) $a['title'])] <=> [(int) ($b['year'] ?? 9999), Names::slug((string) $b['title'])]);
        return $ideas;
    }

    public static function get(string $id): ?array
    {
        $all = (JsonStore::read(self::$file, []) ?: [])['ideas'] ?? [];
        return isset($all[$id]) && is_array($all[$id]) ? $all[$id] : null;
    }

    /** @return list<array> derniers appels à l'IA (sommaire, pistes) */
    public static function runs(): array
    {
        return array_reverse(array_values((array) ((JsonStore::read(self::$file, []) ?: [])['runs'] ?? [])));
    }

    /**
     * Idée nettoyée (champs saisis par l'équipe ou proposés par l'IA) ; erreur si elle est
     * inutilisable. @return array{0:?array,1:?string}
     */
    public static function clean(array $in): array
    {
        $title = trim(mb_substr((string) preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')), 0, 120));
        if ($title === '') {
            return [null, 'Donnez un titre à l’idée.'];
        }
        $date = trim((string) ($in['date'] ?? ''));
        $date = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && $date >= '1928-01-01' && $date <= date('Y-m-d') ? $date : null;
        $year = (int) ($in['year'] ?? 0);
        $year = $date ? (int) substr($date, 0, 4) : ($year >= 1928 && $year <= (int) date('Y') ? $year : null);
        $sources = [];
        foreach ((array) ($in['sources'] ?? []) as $sid) {
            $sid = (int) (is_array($sid) ? ($sid['id'] ?? 0) : $sid);
            $s = $sid ? Index::get($sid) : null;
            if ($s && $s['status'] !== 'corbeille' && $s['type'] !== 'moment' && !in_array($sid, $sources, true)) {
                $sources[] = $sid;
            }
        }
        $image = trim((string) ($in['image'] ?? ''));
        $image = $image !== '' && Media::get($image) ? $image : self::imageFor($sources);
        return [[
            'title' => $title,
            'year' => $year,
            'date' => $date,
            'why' => trim(mb_substr((string) preg_replace('/[ \t]+/u', ' ', (string) ($in['why'] ?? '')), 0, 700)),
            'theme' => isset(self::THEMES[$in['theme'] ?? '']) ? (string) $in['theme'] : 'autre',
            'sources' => array_slice($sources, 0, 8),
            'image' => $image,
        ], null];
    }

    /** Photo de l'idée : celle d'une fiche source, de préférence créditée et sûre (murs de photos). */
    public static function imageFor(array $sources): ?string
    {
        $fallback = null;
        foreach ($sources as $sid) {
            $img = Index::get((int) $sid)['image'] ?? null;
            if (!$img || Index::isPlaceholderImage($img)) {
                continue;
            }
            if (PhotoWall::reason($img) === null) {
                return $img;
            }
            $fallback ??= $img;
        }
        return $fallback;
    }

    /** Ajoute une idée de l'équipe (origine « equipe ») ou de l'IA. */
    public static function add(array $in, ?array $user, string $origin = 'equipe', string $request = ''): array
    {
        [$idea, $err] = self::clean($in);
        if ($err) {
            throw new \InvalidArgumentException($err);
        }
        $idea += [
            'id' => 'i' . bin2hex(random_bytes(5)),
            'state' => $origin === 'ia' ? 'proposee' : 'retenue',
            'reason' => '',
            'origin' => $origin,
            'request' => mb_substr($request, 0, 200),
            'by' => $origin === 'ia' ? 'IA' : (string) ($user['name'] ?? 'Équipe'),
            'at' => date('c'),
            'fiche' => null,
        ];
        self::store(function (array $d) use ($idea) {
            $d['ideas'][$idea['id']] = $idea;
            return $d;
        });
        return $idea;
    }

    /** Modifie une idée (titre, année, date, pourquoi, thème, sources, photo). */
    public static function edit(string $id, array $in, ?array $user): array
    {
        $cur = self::get($id);
        if (!$cur) {
            throw new \InvalidArgumentException('Idée introuvable.');
        }
        [$idea, $err] = self::clean($in + $cur);
        if ($err) {
            throw new \InvalidArgumentException($err);
        }
        $new = array_merge($cur, $idea, ['updated_by' => (string) ($user['name'] ?? 'Équipe'), 'updated_at' => date('c')]);
        self::store(function (array $d) use ($id, $new) {
            $d['ideas'][$id] = $new;
            return $d;
        });
        return $new;
    }

    /** Retenir, écarter (avec une raison, rappelée à l'IA), remettre parmi les propositions. */
    public static function setState(string $id, string $state, string $reason, ?array $user): array
    {
        $cur = self::get($id);
        if (!$cur || !in_array($state, ['proposee', 'retenue', 'ecartee'], true)) {
            throw new \InvalidArgumentException('Idée introuvable.');
        }
        if ($cur['state'] === 'redigee' && self::ficheOf($cur)) {
            throw new \InvalidArgumentException('Cette idée a déjà sa fiche : modifiez ou mettez à la corbeille la fiche elle-même.');
        }
        $cur = array_merge($cur, ['state' => $state, 'reason' => $state === 'ecartee' ? mb_substr(trim($reason), 0, 300) : '', 'updated_by' => (string) ($user['name'] ?? 'Équipe'), 'updated_at' => date('c')]);
        self::store(function (array $d) use ($id, $cur) {
            $d['ideas'][$id] = $cur;
            return $d;
        });
        return $cur;
    }

    /** Idée écrite par l'équipe (« Écrire moi-même ») : rattachée à sa fiche, marquée « rédigée ». */
    public static function linkFiche(string $id, int $fiche, ?array $user): void
    {
        self::store(function (array $d) use ($id, $fiche, $user) {
            if (isset($d['ideas'][$id]) && !self::ficheOf($d['ideas'][$id])) {
                $d['ideas'][$id] = array_merge($d['ideas'][$id], ['state' => 'redigee', 'fiche' => $fiche, 'updated_by' => (string) ($user['name'] ?? 'Équipe'), 'updated_at' => date('c')]);
            }
            return $d;
        });
    }

    /** Fiche rédigée d'une idée, si elle existe encore (hors corbeille). */
    public static function ficheOf(array $idea): ?array
    {
        $s = !empty($idea['fiche']) ? Index::get((int) $idea['fiche']) : null;
        return $s && $s['status'] !== 'corbeille' ? $s : null;
    }

    private static function store(callable $fn): void
    {
        JsonStore::update(self::$file, function ($d) use ($fn) {
            $d = is_array($d) ? $d : [];
            $d['ideas'] = (array) ($d['ideas'] ?? []);
            $d['runs'] = (array) ($d['runs'] ?? []);
            return $fn($d);
        }, []);
    }

    /**
     * Idées, idées retenues, rédigées et moments datés par décennie : les trous se voient.
     * @return array<int,array{ideas:int,kept:int,written:int,dated:int}>
     */
    public static function coverage(): array
    {
        $out = [];
        for ($d = 1920; $d <= 2020; $d += 10) {
            $out[$d] = ['ideas' => 0, 'kept' => 0, 'written' => 0, 'dated' => 0];
        }
        foreach (self::all() as $i) {
            $d = $i['year'] ? intdiv((int) $i['year'], 10) * 10 : null;
            if ($d === null || !isset($out[$d]) || $i['state'] === 'ecartee') {
                continue;
            }
            $out[$d]['ideas']++;
            $out[$d]['kept'] += in_array($i['state'], ['retenue', 'redigee'], true) ? 1 : 0;
            $out[$d]['written'] += $i['state'] === 'redigee' ? 1 : 0;
        }
        foreach (Moments::all() as $r) {
            $d = $r['year'] ? intdiv((int) $r['year'], 10) * 10 : null;
            if ($d !== null && isset($out[$d]) && $r['date'] !== null) {
                $out[$d]['dated']++;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ catalogue des sources

    /**
     * Catalogue d'une époque pour l'IA (une ligne par fiche publiée, son numéro en tête) : matchs les
     * plus marquants, personnes actives à l'époque, articles et objets qui en parlent. Époque
     * [0, 0] : sujets transversaux (articles, objets, grandes figures, très grands matchs).
     * @return array{lines:list<string>,ids:list<int>}
     */
    public static function catalog(int $from, int $to): array
    {
        $all = $from === 0;
        $dm = Derived::part('matches');
        $tot = Derived::part('person_totals');
        $year = fn (?int $matchId) => $matchId && ($m = Index::get($matchId)) ? (int) substr((string) ($m['m']['date'] ?? ''), 0, 4) : null;
        $in = fn (?int $a, ?int $b) => $a !== null && $a <= $to && ($b ?? $a) >= $from;
        $lines = [];

        $matches = [];
        foreach (Index::published('match') as $id => $s) {
            $d = $dm[$id] ?? null;
            $y = (int) substr((string) ($d['date'] ?? ''), 0, 4);
            if ($d && ($all || ($y >= $from && $y <= $to)) && ($d['comp'] ?? '') !== 'Amical') {
                $matches[$id] = self::weight($d, $s);
            }
        }
        arsort($matches);
        foreach (array_slice($matches, 0, $all ? 15 : 40, true) as $id => $w) {
            $lines[$id] = self::matchLine((int) $id, $dm[$id], Index::get((int) $id));
        }

        $people = [];
        foreach (Index::published('personne') as $id => $s) {
            $t = $tot[$id] ?? [];
            $a = $s['p']['arrival'] ?: $year(isset($t['first']) ? (int) $t['first'] : null);
            $b = $s['p']['departure'] ?: ($year(isset($t['last']) ? (int) $t['last'] : null) ?? $a);
            if (!$all && !$in($a, $b)) {
                continue;
            }
            $roles = (array) $s['p']['roles'];
            $people[$id] = [(int) ($t['matches'] ?? 0) + 2 * (int) ($t['goals'] ?? 0) + (int) ($t['coached'] ?? 0)
                + ($s['p']['legend'] ? 200 : 0) + ($a && $b ? 8 * max(0, $b - $a) : 0)
                + (array_intersect($roles, ['dirigeant', 'personnage']) ? 60 : 0) + (in_array('entraineur', $roles, true) ? 30 : 0), $a, $b];
        }
        uasort($people, fn ($x, $y) => $y[0] <=> $x[0]);
        foreach (array_slice($people, 0, $all ? 30 : 45, true) as $id => [$w, $a, $b]) {
            $lines[$id] = self::personLine((int) $id, Index::get((int) $id), $tot[$id] ?? [], $a, $b);
        }

        $docs = 0;
        foreach (Index::published() as $id => $s) {
            if (!in_array($s['type'], ['article', 'page', 'objet'], true) || $docs >= ($all ? 80 : 15)) {
                continue;
            }
            preg_match_all('/\b(19[2-9]\d|20[0-2]\d)\b/', $s['title'] . ' ' . ($s['a']['season'] ?? '') . ' ' . ($s['o']['year'] ?? ''), $ys);
            $ys = array_map('intval', $ys[1]);
            if (!$all && (!$ys || !$in(min($ys), max($ys)))) {
                continue;
            }
            $lines[$id] = '#' . $id . ' · ' . ($s['type'] === 'objet' ? 'objet' : 'article') . ' · ' . $s['title'] . (!empty($s['o']['year']) ? ' · ' . $s['o']['year'] : '') . ($s['excerpt'] !== '' ? ' · ' . mb_substr($s['excerpt'], 0, 160) : '');
            $docs++;
        }
        return ['lines' => array_values($lines), 'ids' => array_map('intval', array_keys($lines))];
    }

    /** Poids d'un match pour le catalogue : finales, demies, Europe, barrages, à la une, temps forts, affluence. */
    public static function weight(array $d, array $s): float
    {
        $comp = (string) ($d['comp'] ?? '');
        $round = mb_strtolower(trim((string) ($d['round'] ?? '')));
        $europe = $comp === "Coupe d'Europe";
        $cup = in_array($comp, ['Coupe de France', 'Coupe de la Ligue'], true) || $europe;
        $w = (str_starts_with($round, 'finale') ? 40 : 0) + (preg_match('/^(1\/2|demi)/', $round) ? 25 : 0)
            + ($cup && preg_match('/^(1\/4|quart|1\/8|huiti)/', $round) ? 10 : 0) + ($europe ? 20 : 0) + ($comp === 'Barrages' ? 15 : 0)
            + (!empty($s['a_la_une']) ? 12 : 0) + min(10, (int) ($d['hl'] ?? 0) / 2) + min(10, (int) ($d['spectators'] ?? 0) / 3000);
        // Large score, sauf contre les amateurs des premiers tours de coupe.
        if (abs((int) ($d['us'] ?? 0) - (int) ($d['them'] ?? 0)) >= 4 && !str_contains($round, 'tour')) {
            $w += 5;
        }
        return (float) $w;
    }

    private static function matchLine(int $id, array $d, ?array $s): string
    {
        $score = ($d['sh'] ?? true) ? 'Sochaux ' . (int) $d['us'] . '-' . (int) $d['them'] . ' ' . $d['away'] : $d['home'] . ' ' . (int) $d['them'] . '-' . (int) $d['us'] . ' Sochaux';
        $parts = ['#' . $id, 'match', date('d/m/Y', (int) strtotime((string) $d['date'])), trim(($d['label'] ?: $d['comp']) . ' ' . ($d['round'] ?? '')), $score . (!empty($d['extra']) ? ' (' . $d['extra'] . ')' : '')];
        if (!empty($d['spectators'])) {
            $parts[] = number_format((int) $d['spectators'], 0, ',', ' ') . ' spectateurs';
        }
        if (!empty($s['excerpt'])) {
            $parts[] = mb_substr((string) $s['excerpt'], 0, 140);
        }
        return implode(' · ', $parts);
    }

    private static function personLine(int $id, array $s, array $t, ?int $a, ?int $b): string
    {
        $parts = ['#' . $id, 'personne', $s['p']['name'], implode(', ', (array) $s['p']['roles']) . (!empty($s['p']['position']) ? ' (' . $s['p']['position'] . ')' : '')];
        if ($a) {
            $parts[] = $a . ($b && $b !== $a ? '–' . $b : '');
        }
        if (!empty($t['matches'])) {
            $parts[] = (int) $t['matches'] . ' matchs, ' . (int) ($t['goals'] ?? 0) . ' buts';
        }
        if (!empty($t['coached'])) {
            $parts[] = (int) $t['coached'] . ' matchs comme entraîneur';
        }
        if (!empty($s['p']['legend'])) {
            $parts[] = 'légende du club';
        }
        if (!empty($s['excerpt'])) {
            $parts[] = mb_substr((string) $s['excerpt'], 0, 120);
        }
        return implode(' · ', $parts);
    }

    // ------------------------------------------------------------------ propositions de l'IA

    private const SYSTEM_IDEAS = <<<'TXT'
Tu es historien du FC Sochaux-Montbéliard (FCSM), club fondé le 20 mai 1928. Pour son centenaire, le musée en ligne Sochaux Rétro publie « 100 ans, 100 moments » : cent moments marquants de l'histoire du club, chacun raconté dans un court récit. Tu proposes des idées de moments aux historiens du musée, qui choisiront.

Règles absolues :
- Chaque idée s'appuie sur une à cinq fiches du CATALOGUE (leurs numéros #) : ce sont tes seules sources. N'invente aucun fait, aucun score, aucune date, aucun nom qui n'y figure pas. Si la matière manque, propose moins d'idées plutôt que d'inventer.
- Un moment est un événement ou une histoire précise (un match, un exploit, une première, un record, une arrivée ou un départ, une figure du club, un drame, une tradition), pas un thème vague.
- Varie les sujets : matchs, joueurs et entraîneurs, vie du club, stade, supporters, formation ; joies et drames.
- Ne propose ni une idée de la liste DÉJÀ PROPOSÉES, ni une idée ÉCARTÉE par les historiens (leurs raisons sont données).

Réponds en JSON : une liste d'idées, chacune avec
- title : titre court et vivant (80 caractères au plus), sans date au début ;
- year : année de l'événement ;
- date : date exacte AAAA-MM-JJ si le catalogue la donne (match), sinon chaîne vide ;
- why : deux ou trois phrases qui disent pourquoi ce moment compte, d'après les fiches ;
- theme : match, joueur, club, stade, supporters, formation ou autre ;
- sources : numéros des fiches du catalogue qui le racontent (1 à 5).
TXT;

    private static function ideasSchema(): array
    {
        return [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING'],
                    'year' => ['type' => 'INTEGER'],
                    'date' => ['type' => 'STRING'],
                    'why' => ['type' => 'STRING'],
                    'theme' => ['type' => 'STRING', 'enum' => array_keys(self::THEMES)],
                    'sources' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
                ],
                'required' => ['title', 'year', 'why', 'theme', 'sources'],
            ],
        ];
    }

    /** Ce que l'IA doit éviter : idées déjà là, idées écartées et pourquoi (de l'époque, ou toutes). */
    private static function memory(int $from, int $to): string
    {
        $seen = [];
        $gone = [];
        foreach (self::all() as $i) {
            $y = (int) ($i['year'] ?? 0);
            if ($from && ($y < $from || $y > $to)) {
                continue;
            }
            $line = '- ' . $i['title'] . ($y ? ' (' . $y . ')' : '');
            if ($i['state'] === 'ecartee') {
                $gone[] = $line . ($i['reason'] !== '' ? ' : ' . $i['reason'] : '');
            } else {
                $seen[] = $line;
            }
        }
        foreach (Moments::all() as $r) {
            $y = (int) ($r['year'] ?? 0);
            if (!$from || ($y >= $from && $y <= $to)) {
                $seen[] = '- ' . $r['title'] . ($y ? ' (' . $y . ')' : '') . ' [déjà écrit]';
            }
        }
        return "DÉJÀ PROPOSÉES :\n" . ($seen ? implode("\n", array_slice($seen, 0, 300)) : '(aucune)') . "\n\nÉCARTÉES :\n" . ($gone ? implode("\n", array_slice($gone, 0, 200)) : '(aucune)');
    }

    private static function job(string $task, array $catalog, int $from, int $to): array
    {
        $user = $task . "\n\nCATALOGUE :\n" . implode("\n", $catalog['lines']) . "\n\n" . self::memory($from, $to);
        return [[['role' => 'user', 'text' => $user]], self::SYSTEM_IDEAS, ['for' => 'moments', 'ref' => 'moments:idees', 'json' => true, 'schema' => self::ideasSchema(), 'temperature' => 0.7, 'max_tokens' => 8000, 'timeout' => 120]];
    }

    /** Appels à Gemini (en parallèle s'il y en a plusieurs), ou au faux Gemini des tests. */
    private static function call(array $jobs): array
    {
        if (self::$ai !== null) {
            return (self::$ai)($jobs);
        }
        if (!Gemini::ready()) {
            throw new \RuntimeException('Aucune clé API Gemini : réglez-la dans Système › Réglages › Intelligence artificielle.');
        }
        return count($jobs) === 1 ? [self::attempt($jobs[0])] : Gemini::generateMany($jobs);
    }

    private static function attempt(array $job): array
    {
        try {
            return Gemini::generate(...$job);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage(), 'code' => $e->getCode()];
        }
    }

    /**
     * Idées lues dans une réponse de l'IA : seules restent celles qui citent des fiches du catalogue
     * donné ; les doublons (même titre, ou mêmes sources qu'une idée existante) sont écartés.
     * @param list<int> $allowed fiches du catalogue
     * @return list<array> idées nettoyées (sans identifiant)
     */
    public static function parseIdeas(string $text, array $allowed, ?array $existing = null): array
    {
        $data = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', $text)), true);
        if (is_array($data) && isset($data['ideas']) && is_array($data['ideas'])) {
            $data = $data['ideas'];
        }
        if (!is_array($data) || !array_is_list($data)) {
            return [];
        }
        $allowed = array_flip(array_map('intval', $allowed));
        $existing ??= self::all();
        $titles = array_flip(array_map(fn ($i) => Names::slug((string) $i['title']), $existing));
        // Doublon : même titre, ou mêmes fiches sources pour la même année (un joueur peut avoir
        // plusieurs moments, à des années différentes).
        $sourceSets = array_flip(array_map(fn ($i) => self::sourceKey((array) $i['sources']) . '|' . (int) ($i['year'] ?? 0), array_filter($existing, fn ($i) => !empty($i['sources']))));
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['sources'] = array_values(array_filter(array_map('intval', (array) ($row['sources'] ?? [])), fn ($id) => isset($allowed[$id])));
            if (!$row['sources']) {
                continue; // sans fiche du catalogue : rien ne l'appuie
            }
            [$idea] = self::clean($row);
            if (!$idea || !$idea['sources'] || mb_strlen($idea['why']) < 20) {
                continue;
            }
            // Année absente ou douteuse : celle du match cité.
            if (!$idea['year'] && ($m = Index::get($idea['sources'][0])) && $m['type'] === 'match') {
                $idea['year'] = (int) substr((string) ($m['m']['date'] ?? ''), 0, 4) ?: null;
            }
            $t = Names::slug($idea['title']);
            $k = self::sourceKey($idea['sources']) . '|' . (int) ($idea['year'] ?? 0);
            if (isset($titles[$t]) || isset($sourceSets[$k])) {
                continue;
            }
            $titles[$t] = true;
            $sourceSets[$k] = true;
            $out[] = $idea;
        }
        return $out;
    }

    private static function sourceKey(array $ids): string
    {
        $ids = array_map('intval', $ids);
        sort($ids);
        return implode(',', $ids);
    }

    /**
     * Sommaire : l'IA propose des idées pour chaque époque (et des sujets transversaux), toutes en
     * même temps. @return array{added:int,eras:int,failed:list<string>}
     */
    public static function propose(?array $user): array
    {
        $jobs = [];
        $catalogs = [];
        foreach (self::ERAS as [$from, $to, $n]) {
            $cat = self::catalog($from, $to);
            if (!$cat['lines']) {
                continue;
            }
            $what = $from ? 'PÉRIODE : ' . $from . ' à ' . min($to, (int) date('Y')) . '.' : 'SUJETS TRANSVERSAUX : la vie du club, le stade Bonal, les supporters, la formation des Lionceaux, les grandes figures, toutes époques confondues.';
            $jobs[] = self::job($what . ' Propose jusqu’à ' . $n . ' idées de moments.', $cat, $from, $to);
            $catalogs[] = [$cat, $from ? $from . '-' . $to : 'transversal'];
        }
        return self::collect($jobs, $catalogs, 'sommaire', '', $user);
    }

    /** Piste précise demandée par les historiens (« les années 1930 », « les supporters »…). */
    public static function ask(string $query, ?array $user): array
    {
        $query = trim(mb_substr((string) preg_replace('/\s+/u', ' ', $query), 0, 200));
        if ($query === '') {
            throw new \InvalidArgumentException('Dites quelle piste explorer (une époque, un sujet, un joueur…).');
        }
        // Catalogue entier : toutes les époques et les sujets transversaux, sans doublon.
        $lines = [];
        foreach (self::ERAS as [$from, $to]) {
            $c = self::catalog($from, $to);
            $lines += array_combine($c['ids'], $c['lines']);
        }
        $cat = ['lines' => array_values($lines), 'ids' => array_keys($lines)];
        $job = self::job('PISTE DEMANDÉE PAR LES HISTORIENS : « ' . $query . ' ». Propose jusqu’à 10 idées de moments sur cette piste, seulement si le catalogue le permet.', $cat, 0, 0);
        return self::collect([$job], [[$cat, 'piste']], 'piste', $query, $user);
    }

    /** « Autre idée » : l'idée est écartée et l'IA en propose une autre de la même époque. */
    public static function replace(string $id, ?array $user): array
    {
        $idea = self::get($id);
        if (!$idea) {
            throw new \InvalidArgumentException('Idée introuvable.');
        }
        self::setState($id, 'ecartee', 'Remplacée par une autre idée', $user);
        $y = (int) ($idea['year'] ?? 0);
        $era = [0, 0];
        foreach (self::ERAS as [$from, $to]) {
            if ($from && $y >= $from && $y <= $to) {
                $era = [$from, $to];
            }
        }
        $cat = self::catalog(...$era);
        $job = self::job('Les historiens ont écarté l’idée « ' . $idea['title'] . ' ». Propose 1 autre idée de moment' . ($era[0] ? ' de la même période (' . $era[0] . ' à ' . $era[1] . ')' : '') . ', différente de celle-ci.', $cat, ...$era);
        return self::collect([$job], [[$cat, 'autre']], 'autre', $idea['title'], $user, 1);
    }

    private static function collect(array $jobs, array $catalogs, string $kind, string $request, ?array $user, int $max = 0): array
    {
        if (!$jobs) {
            return ['added' => 0, 'eras' => 0, 'failed' => []];
        }
        @set_time_limit(300);
        $answers = self::call($jobs);
        $added = 0;
        $failed = [];
        $model = '';
        foreach ($answers as $i => $a) {
            [$cat, $label] = $catalogs[$i];
            if (!empty($a['error']) || !isset($a['text'])) {
                $failed[] = $label . ' : ' . mb_substr((string) ($a['error'] ?? 'réponse vide'), 0, 120);
                continue;
            }
            $model = (string) ($a['model'] ?? $model);
            foreach (self::parseIdeas((string) $a['text'], $cat['ids']) as $idea) {
                if ($max && $added >= $max) {
                    break;
                }
                self::add($idea, $user, 'ia', $kind === 'piste' ? $request : '');
                $added++;
            }
        }
        self::store(function (array $d) use ($kind, $request, $user, $added, $failed, $model, $jobs) {
            $d['runs'][] = ['at' => date('c'), 'by' => (string) ($user['name'] ?? 'Équipe'), 'kind' => $kind, 'request' => $request, 'jobs' => count($jobs), 'added' => $added, 'failed' => count($failed), 'model' => $model];
            $d['runs'] = array_slice($d['runs'], -50);
            return $d;
        });
        return ['added' => $added, 'eras' => count($jobs) - count($failed), 'failed' => $failed];
    }

    // ------------------------------------------------------------------ premier jet

    private const SYSTEM_DRAFT = <<<'TXT'
Tu rédiges pour le musée en ligne Sochaux Rétro le récit de l'un des « 100 ans, 100 moments » du centenaire du FC Sochaux-Montbéliard. Les historiens du musée reliront et corrigeront ton texte avant toute publication.

Règles absolues :
- N'utilise que les faits du DOSSIER (fiches du musée). N'invente rien : ni score, ni date, ni nom, ni citation, ni chiffre. Si un détail manque, raconte sans lui.
- Récit vivant et précis, en français, pour le grand public : 250 à 450 mots, 3 à 5 paragraphes, phrases plutôt courtes, sans titre intermédiaire, sans émoji, sans parler du musée, des fiches ni de l'IA.
- Liste dans « checks » chaque fait qu'un historien doit vérifier avant publication : chiffres, dates, noms propres, scores, affirmations fortes (« premier », « record »…), et tout point où les fiches se contredisent.

Réponds en JSON : title (titre du moment, 80 caractères au plus), hook (une ou deux phrases d'accroche), paragraphs (les paragraphes du récit), caption (légende de la photo, 140 caractères au plus, si une photo est indiquée, sinon vide), checks (faits à vérifier), sources_used (numéros des fiches réellement utilisées).
TXT;

    private static function draftSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'title' => ['type' => 'STRING'],
                'hook' => ['type' => 'STRING'],
                'paragraphs' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'caption' => ['type' => 'STRING'],
                'checks' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'sources_used' => ['type' => 'ARRAY', 'items' => ['type' => 'INTEGER']],
            ],
            'required' => ['title', 'hook', 'paragraphs', 'checks', 'sources_used'],
        ];
    }

    /** Premier jet lu dans la réponse de l'IA (null si inutilisable). @param list<int> $allowed fiches du dossier */
    public static function parseDraft(string $text, array $allowed): ?array
    {
        $d = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', $text)), true);
        if (!is_array($d)) {
            return null;
        }
        $clean = fn ($s, int $max) => trim(mb_substr((string) preg_replace('/\s+/u', ' ', strip_tags((string) $s)), 0, $max));
        $paragraphs = array_values(array_filter(array_map(fn ($p) => $clean($p, 2000), (array) ($d['paragraphs'] ?? [])), fn ($p) => $p !== ''));
        $title = $clean($d['title'] ?? '', 120);
        if ($title === '' || !$paragraphs || mb_strlen(implode(' ', $paragraphs)) < 200) {
            return null;
        }
        $allowed = array_map('intval', $allowed);
        $used = array_values(array_intersect(array_map('intval', (array) ($d['sources_used'] ?? [])), $allowed));
        return [
            'title' => $title,
            'hook' => $clean($d['hook'] ?? '', 400),
            'paragraphs' => array_slice($paragraphs, 0, 8),
            'caption' => $clean($d['caption'] ?? '', 160),
            'checks' => array_slice(array_values(array_filter(array_map(fn ($c) => $clean($c, 300), (array) ($d['checks'] ?? [])), fn ($c) => $c !== '')), 0, 20),
            'sources' => $used ?: $allowed,
        ];
    }

    /** Dossier remis à l'IA : chaque fiche source décrite comme pour la recherche sur le web. */
    public static function dossier(array $idea): array
    {
        $parts = [];
        $ids = [];
        foreach (array_slice((array) $idea['sources'], 0, self::MAX_SOURCES) as $sid) {
            $doc = Fiches::get((int) $sid);
            if ($doc && Fiches::isVisible($doc)) {
                $parts[] = 'FICHE #' . (int) $sid . "\n" . WebCheck::describe($doc);
                $ids[] = (int) $sid;
            }
        }
        return [$parts, $ids];
    }

    /**
     * Fiche « Moment » du premier jet : statut « À relire », récit (accroche en introduction,
     * paragraphes), photo de l'idée et sa légende, fiches liées ; la provenance (modèle, sources,
     * points à vérifier) n'est montrée que dans le back-office.
     */
    public static function draftDoc(array $idea, array $draft, string $model, ?array $user): array
    {
        $img = $idea['image'] ?? null;
        // Photo au crédit douteux (DR, agence, site web, sans crédit) : un point de plus à vérifier.
        $why = $img ? PhotoWall::reason($img) : null;
        if ($why !== null && preg_match('/crédit|sans crédit/u', $why)) {
            $credit = trim((string) (Media::get($img)['credit'] ?? ''));
            $draft['checks'][] = 'Photo : ' . $why . ($credit !== '' ? ' (« ' . $credit . ' »)' : '') . ' : droits à vérifier, ou choisir une autre photo.';
        }
        $doc = Fiches::blank('moment');
        $doc['title'] = $draft['title'];
        $doc['status'] = 'relire';
        $doc['intro'] = $draft['hook'] !== '' ? '<p>' . e($draft['hook']) . '</p>' : '';
        $doc['sections'] = [['title' => '', 'html' => implode("\n", array_map(fn ($p) => '<p>' . e($p) . '</p>', $draft['paragraphs']))]];
        $doc['seo']['description'] = mb_strimwidth($draft['hook'], 0, 160, '…');
        $doc['moment']['year'] = $idea['year'] ?? null;
        $doc['moment']['event_date'] = $idea['date'] ?? null;
        $doc['moment']['linked'] = $draft['sources'];
        if ($img) {
            $doc['featured_image'] = $img;
            $doc['gallery'] = [['image' => $img, 'caption' => $draft['caption'], 'credit' => (string) (Media::get($img)['credit'] ?? ''), 'caption_raw' => '']];
        }
        $doc['moment']['ai'] = [
            'at' => date('c'), 'model' => $model, 'idea' => (string) ($idea['id'] ?? ''), 'by' => (string) ($user['name'] ?? 'Équipe'),
            'sources' => $draft['sources'], 'checks' => $draft['checks'], 'caption' => $draft['caption'],
        ];
        $doc['path'] = Paths::unique(Paths::suggest($doc), -1);
        $doc['slug'] = basename(rtrim($doc['path'], '/'));
        return $doc;
    }

    /**
     * Premier jet d'une idée retenue : fiche « Moment » en statut « À relire », avec les sources et
     * les points à vérifier (visibles dans le back-office seulement). @return int numéro de la fiche
     */
    public static function draft(string $id, ?array $user): int
    {
        $idea = self::get($id);
        if (!$idea) {
            throw new \InvalidArgumentException('Idée introuvable.');
        }
        if ($idea['state'] === 'ecartee') {
            throw new \InvalidArgumentException('Cette idée est écartée : retenez-la d’abord.');
        }
        if ($s = self::ficheOf($idea)) {
            throw new \InvalidArgumentException('Le premier jet existe déjà : « ' . $s['title'] . ' » (l’IA ne réécrit pas un moment déjà rédigé).');
        }
        [$parts, $ids] = self::dossier($idea);
        if (!$parts) {
            throw new \InvalidArgumentException('Aucune fiche publiée parmi les sources de cette idée : ajoutez-en une avant de demander un premier jet.');
        }
        $img = $idea['image'] ?? null;
        $ask = "MOMENT À RACONTER : « " . $idea['title'] . " »" . ($idea['year'] ? ' (' . $idea['year'] . ')' : '') . "\nPOURQUOI : " . $idea['why']
            . ($img ? "\nPHOTO : " . trim((string) (Media::get($img)['caption'] ?? '')) . ' (crédit : ' . trim((string) (Media::get($img)['credit'] ?? '')) . ')' : "\nPHOTO : aucune")
            . "\n\nDOSSIER :\n\n" . implode("\n\n", $parts);
        @set_time_limit(180);
        $answer = self::call([[[['role' => 'user', 'text' => $ask]], self::SYSTEM_DRAFT, ['for' => 'moments', 'ref' => 'moments:idee:' . $id, 'json' => true, 'schema' => self::draftSchema(), 'temperature' => 0.5, 'max_tokens' => 4096, 'timeout' => 120]]])[0] ?? [];
        if (!empty($answer['error']) || !isset($answer['text'])) {
            throw new \RuntimeException('L’IA n’a pas répondu : ' . mb_substr((string) ($answer['error'] ?? 'réponse vide'), 0, 160) . '. Réessayez dans un instant.');
        }
        $draft = self::parseDraft((string) $answer['text'], $ids);
        if (!$draft) {
            throw new \RuntimeException('Le premier jet reçu est inutilisable (trop court ou mal formé). Réessayez.');
        }
        $doc = self::draftDoc($idea, $draft, (string) ($answer['model'] ?? ''), $user);
        $saved = Fiches::save($doc, ['name' => 'IA, à la demande de ' . (string) ($user['name'] ?? 'l’équipe')], 'Premier jet rédigé par l’IA à partir de ' . count($draft['sources']) . ' fiche' . (count($draft['sources']) > 1 ? 's' : '') . ' : à relire');
        $fid = (int) $saved['id'];
        self::store(function (array $d) use ($id, $fid, $user) {
            if (isset($d['ideas'][$id])) {
                $d['ideas'][$id] = array_merge($d['ideas'][$id], ['state' => 'redigee', 'fiche' => $fid, 'updated_by' => (string) ($user['name'] ?? 'Équipe'), 'updated_at' => date('c')]);
            }
            return $d;
        });
        return $fid;
    }
}
