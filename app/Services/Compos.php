<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;

/**
 * Contrôle des compositions d'équipe (feuilles de match) d'après plusieurs sources :
 * – Transfermarkt, lu directement (calendrier de la saison puis rapport de match) ;
 * – pari-et-gagne.com, footballdatabase.eu, worldfootball.net, fcsmstory.com : pages trouvées
 *   et lues par Gemini avec la recherche Google (seules les pages réellement consultées comptent) ;
 * – presse d'époque (Gallica) pour les matchs anciens : recherche Trouvailles habituelle.
 * Rien n'est écrit dans les fiches : les écarts partent dans Trouvailles (champ « feuille »),
 * l'historien valide ou écarte. Une fiche se contrôle seule (bouton) ou en file (tâche planifiée).
 */
final class Compos
{
    public const SOURCES = [
        'transfermarkt' => 'Transfermarkt',
        'footballdatabase' => 'footballdatabase.eu',
        'pari-et-gagne' => 'pari-et-gagne.com (par l’IA)',
        'worldfootball' => 'worldfootball.net (par l’IA)',
        'fcsmstory' => 'FCSM Story (par l’IA)',
        'gallica' => 'Presse d’époque (Gallica)',
    ];
    /** Domaines des sources lues par Gemini. */
    public const WEB = ['pari-et-gagne' => 'pari-et-gagne.com', 'worldfootball' => 'worldfootball.net', 'fcsmstory' => 'fcsmstory.com'];
    private const FDB = 'https://www.footballdatabase.eu';
    private const FDB_CLUB = '19-sochaux'; // FC Sochaux-Montbéliard chez footballdatabase.eu
    public const ORIGIN = 'compos';
    /** Sources lues directement (fiables) : cochées d'office. */
    public const DEFAULT_SOURCES = ['transfermarkt', 'footballdatabase', 'gallica'];
    private const TM = 'https://www.transfermarkt.fr';
    private const TM_CLUB = 750; // FC Sochaux-Montbéliard chez Transfermarkt
    private const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
    private const DIR = STORAGE_PATH . '/compos';
    /** Une page Transfermarkt toutes les 4 secondes au plus (courtoisie, pas de blocage). */
    private const PAUSE = 4;

    /** Essais : remplace le téléchargement (url → html) et Gemini. */
    public static ?\Closure $get = null;
    /** Raison du dernier échec d'une source web (note affichée). */
    private static string $why = '';

    // ------------------------------------------------------------------ état et file

    public static function state(): array
    {
        return (array) JsonStore::read(self::DIR . '/etat.json', []) + ['queue' => [], 'done' => [], 'on' => false, 'sources' => self::DEFAULT_SOURCES, 'last' => null];
    }

    /** Lance le contrôle d'une liste de fiches (file de la tâche planifiée). */
    public static function start(array $ids, array $sources): int
    {
        $sources = array_values(array_intersect($sources, array_keys(self::SOURCES))) ?: array_keys(self::SOURCES);
        $n = 0;
        JsonStore::update(self::DIR . '/etat.json', function ($s) use ($ids, $sources, &$n) {
            $s = (is_array($s) ? $s : []) + ['queue' => [], 'done' => []];
            $q = array_flip(array_map('intval', $s['queue']));
            foreach ($ids as $id) {
                if (!isset($q[(int) $id])) {
                    $s['queue'][] = (int) $id;
                    $n++;
                }
            }
            $s['on'] = true;
            $s['sources'] = $sources;
            return $s;
        }, []);
        return $n;
    }

    public static function stop(bool $clear = false): void
    {
        JsonStore::update(self::DIR . '/etat.json', function ($s) use ($clear) {
            $s = is_array($s) ? $s : [];
            $s['on'] = false;
            if ($clear) {
                $s['queue'] = [];
            }
            return $s;
        }, []);
    }

    /** Tâche planifiée : quelques matchs par passage (2 minutes au plus). */
    public static function tick(int $seconds = 110): ?string
    {
        $s = self::state();
        if (empty($s['on']) || !$s['queue']) {
            return null;
        }
        $t0 = time();
        $done = 0;
        $added = 0;
        while (time() - $t0 < $seconds) {
            $id = null;
            JsonStore::update(self::DIR . '/etat.json', function ($st) use (&$id) {
                $st = (is_array($st) ? $st : []) + ['queue' => []];
                $id = $st['queue'] ? (int) array_shift($st['queue']) : null;
                if (!$st['queue']) {
                    $st['on'] = false;
                }
                return $st;
            }, []);
            if (!$id) {
                break;
            }
            try {
                $r = self::check($id, (array) $s['sources']);
                $added += $r['added'];
            } catch (\Throwable $e) {
                $r = ['added' => 0, 'error' => $e->getMessage(), 'sources' => []];
            }
            self::remember($id, $r);
            $done++;
            // Gemini surchargé pour une source : le match repasse en fin de file (trois essais au plus).
            if (array_filter((array) ($r['sources'] ?? []), fn ($x) => str_contains((string) $x['note'], 'surchargé'))) {
                JsonStore::update(self::DIR . '/etat.json', function ($st) use ($id) {
                    $st = is_array($st) ? $st : [];
                    $n = (int) ($st['retry'][(string) $id] ?? 0);
                    if ($n < 3) {
                        $st['retry'][(string) $id] = $n + 1;
                        $st['queue'][] = $id;
                        $st['on'] = true;
                    }
                    return $st;
                }, []);
                sleep(10);
            }
        }
        return $done ? "$done match(s) contrôlé(s), $added écart(s) envoyé(s) dans Trouvailles" : null;
    }

    /** Résultat du dernier contrôle d'une fiche (écran et fiche). */
    public static function remember(int $id, array $r): void
    {
        JsonStore::update(self::DIR . '/etat.json', function ($st) use ($id, $r) {
            $st = is_array($st) ? $st : [];
            $st['done'][(string) $id] = ['at' => date('c'), 'added' => (int) ($r['added'] ?? 0), 'error' => $r['error'] ?? null,
                'sources' => array_map(fn ($x) => ['key' => (string) ($x['key'] ?? ''), 'ok' => (bool) $x['ok'], 'note' => (string) $x['note']], (array) ($r['sources'] ?? []))];
            $st['last'] = date('c');
            return $st;
        }, []);
    }

    public static function last(int $id): ?array
    {
        return self::state()['done'][(string) $id] ?? null;
    }

    /** Matchs publiés avec une composition, du plus ancien au plus récent (filtre de saison). */
    public static function candidates(string $season = '', bool $redo = false): array
    {
        $done = self::state()['done'];
        $out = [];
        foreach (Index::published('match') as $e) {
            if ($season !== '' && ($e['m']['season'] ?? '') !== $season) {
                continue;
            }
            if (!$redo && isset($done[(string) $e['id']])) {
                continue;
            }
            $out[(string) ($e['m']['date'] ?? '') . '|' . $e['id']] = (int) $e['id'];
        }
        ksort($out);
        return array_values($out);
    }

    // ------------------------------------------------------------------ contrôle d'une fiche

    /**
     * Contrôle une fiche avec les sources choisies et envoie les écarts dans Trouvailles.
     * @return array{added:int, sources:list<array{key:string,ok:bool,note:string}>}
     */
    public static function check(int $id, ?array $sources = null, ?string $html = null): array
    {
        $doc = Fiches::get($id);
        if (!$doc || ($doc['type'] ?? '') !== 'match') {
            throw new \RuntimeException('Ce n’est pas une fiche de match.');
        }
        $m = (array) $doc['match'];
        if (empty($m['date'])) {
            throw new \RuntimeException('Match sans date : impossible de le retrouver dans les sources.');
        }
        $sources = $sources ?: self::DEFAULT_SOURCES;
        $ours = self::ours($m);
        $report = [];
        $items = [];
        $found = [];
        foreach ($sources as $key) {
            try {
                if ($key === 'transfermarkt') {
                    $ext = $html !== null ? self::fromHtml($html) : self::transfermarkt($m);
                    $ext ? $found[] = $ext : null;
                    $report[] = ['key' => $key, 'ok' => (bool) $ext, 'note' => $ext ? ($ext['players'] ? count($ext['players']) . ' joueurs lus' : 'match trouvé, sans composition') : (in_array($m['competition'] ?? '', ['amical', 'Amical'], true) || preg_match('/amical/i', (string) ($m['competition_label'] ?? '')) ? 'match amical : Transfermarkt ne liste que les matchs officiels' : 'match introuvable dans le calendrier de la saison')];
                } elseif ($key === 'footballdatabase') {
                    $ext = self::footballdatabase($m);
                    $ext ? $found[] = $ext : null;
                    $report[] = ['key' => $key, 'ok' => (bool) $ext, 'note' => $ext ? ($ext['players'] ? count($ext['players']) . ' joueurs lus' : 'match trouvé, sans composition') : 'match introuvable dans le calendrier de la saison'];
                } elseif (isset(self::WEB[$key])) {
                    self::$why = 'pas de feuille trouvée';
                    $ext = self::web($doc, $key);
                    $ext ? $found[] = $ext : null;
                    $report[] = ['key' => $key, 'ok' => (bool) $ext, 'note' => $ext ? count($ext['players']) . ' joueurs lus' : self::$why];
                } elseif ($key === 'gallica') {
                    if ((int) substr((string) $m['date'], 0, 4) > Trouvailles::GALLICA_LAST_YEAR) {
                        $report[] = ['key' => $key, 'ok' => false, 'note' => 'match après ' . Trouvailles::GALLICA_LAST_YEAR . ' : pas de presse numérisée'];
                        continue;
                    }
                    $r = Trouvailles::search($id, ['gallica']);
                    $report[] = ['key' => $key, 'ok' => true, 'note' => 'recherche dans la presse lancée (' . (int) ($r['added'] ?? 0) . ' trouvaille(s))'];
                }
            } catch (\Throwable $e) {
                $report[] = ['key' => $key, 'ok' => false, 'note' => 'erreur : ' . mb_substr($e->getMessage(), 0, 160)];
            }
        }
        foreach ($found as $ext) {
            if (count(array_filter($ext['players'], fn ($p) => $p['pos'] !== 'R')) < 7) {
                continue; // composition trop partielle pour juger
            }
            $diff = self::diff($ours, $ext['players']);
            if (!$diff) {
                continue;
            }
            $src = [['label' => $ext['label'], 'url' => $ext['url'], 'snippet' => 'Écarts : ' . implode(' · ', array_slice($diff, 0, 12)), 'kind' => self::ORIGIN]];
            $items[] = ['field' => 'feuille', 'value' => self::lines(self::merged($ours, $ext), $ext['coach'] ?? ''), 'current' => self::lines($ours, self::coach($m)),
                'type' => 'divergence', 'origin' => self::ORIGIN, 'sources' => $src];
        }
        $added = $items ? Trouvailles::propose($id, $items) : 0;
        return ['added' => $added, 'sources' => $report];
    }

    // ------------------------------------------------------------------ format commun

    /**
     * Composition du musée au format commun :
     * [name (« NOM Prénom »), pos (G/D/M/A/R), in, out, goals[], yellow[], red[], cap, num, key].
     */
    public static function ours(array $m): array
    {
        $out = [];
        foreach ((array) ($m['lineup']['rows'] ?? []) as $r) {
            if (($r['position'] ?? '') === 'E' || trim((string) ($r['name'] ?? '')) === '') {
                continue;
            }
            $out[] = ['name' => (string) $r['name'], 'pos' => (string) ($r['position'] ?: 'M'), 'in' => self::min($r['sub_in'] ?? null), 'out' => self::min($r['sub_out'] ?? null),
                'goals' => array_map([self::class, 'min'], (array) ($r['goals'] ?? [])), 'yellow' => array_map([self::class, 'min'], (array) ($r['yellow'] ?? [])),
                'red' => array_map([self::class, 'min'], (array) ($r['red'] ?? [])), 'cap' => !empty($r['captain']), 'num' => $r['number'] ?? null, 'key' => self::key((string) $r['name'])];
        }
        return $out;
    }

    private static function coach(array $m): string
    {
        foreach ((array) ($m['lineup']['rows'] ?? []) as $r) {
            if (($r['position'] ?? '') === 'E') {
                return (string) $r['name'];
            }
        }
        return '';
    }

    /** Minute « 90+2 », « 45 », ou null. */
    public static function min(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || !preg_match('/^(\d{1,3})(?:\s*\'?\s*\+\s*(\d{1,2}))?/', $v, $x)) {
            return null;
        }
        return $x[1] . (isset($x[2]) && $x[2] !== '' ? '+' . $x[2] : '');
    }

    /** Clé de rapprochement : nom de famille sans accents ni casse (« PETEREYNS »). */
    public static function key(string $name): string
    {
        $n = trim($name);
        $last = preg_match('/\p{Lu}{2,}/u', $n) ? Names::lineupLastName($n) : (string) (($w = preg_split('/\s+/u', $n) ?: [$n]) ? $w[count($w) - 1] : $n);
        return mb_strtolower(Names::ascii(preg_replace('/[^\p{L}\s]/u', '', $last) ?? $last));
    }

    /** Joueur d'une source (« Frédéric Petereyns ») au format du musée (« PETEREYNS Frédéric »). */
    private static function museumName(string $n): string
    {
        $n = trim(preg_replace('/\s+/u', ' ', $n) ?? $n);
        return preg_match('/\p{Lu}{2,}/u', $n) ? $n : FcsmImport::lineupName($n);
    }

    /** Écarts lisibles entre la fiche et une source. @return list<string> */
    public static function diff(array $ours, array $ext): array
    {
        $o = [];
        foreach ($ours as $p) {
            $o[$p['key']] = $p;
        }
        $e = [];
        foreach ($ext as $p) {
            $e[$p['key']] = $p;
        }
        $out = [];
        $starter = fn ($p) => $p['pos'] !== 'R';
        $played = fn ($p) => $p['pos'] !== 'R' || $p['in'] !== null;
        foreach ($e as $k => $p) {
            if (!isset($o[$k])) {
                if ($played($p)) {
                    $out[] = 'manque ' . Names::display($p['name']) . ($starter($p) ? ' (titulaire)' : ' (entré' . ($p['in'] ? ' ' . $p['in'] . "'" : '') . ')');
                }
                continue;
            }
            $q = $o[$k];
            if ($starter($p) !== $starter($q)) {
                $out[] = Names::display($q['name']) . ($starter($p) ? ' titulaire selon la source' : ' remplaçant selon la source');
            }
            if ($p['in'] !== null && $q['in'] !== null && self::far($p['in'], $q['in'])) {
                $out[] = Names::display($q['name']) . " entré à {$p['in']}' (fiche : {$q['in']}')";
            }
            if ($p['out'] !== null && $q['out'] !== null && self::far($p['out'], $q['out'])) {
                $out[] = Names::display($q['name']) . " sorti à {$p['out']}' (fiche : {$q['out']}')";
            }
            if ($p['goals'] && count($p['goals']) !== count($q['goals'])) {
                $out[] = Names::display($q['name']) . ' : ' . count($p['goals']) . ' but(s) selon la source, ' . count($q['goals']) . ' sur la fiche';
            }
            if ($p['red'] && !$q['red']) {
                $out[] = Names::display($q['name']) . ' expulsé selon la source';
            }
        }
        foreach ($o as $k => $q) {
            if (!isset($e[$k]) && $played($q)) {
                $out[] = Names::display($q['name']) . ' absent de la source';
            }
        }
        return $out;
    }

    private static function far(string $a, string $b): bool
    {
        return abs((int) $a - (int) $b) > 2;
    }

    /**
     * Composition corrigée : celle de la source, avec l'orthographe, le poste précis, le numéro et le
     * capitanat de la fiche quand le joueur y figure déjà.
     */
    private static function merged(array $ours, array $ext): array
    {
        $o = [];
        foreach ($ours as $p) {
            $o[$p['key']] = $p;
        }
        $out = [];
        foreach ($ext['players'] as $p) {
            $q = $o[$p['key']] ?? null;
            if ($p['pos'] === 'R' && $p['in'] === null) {
                continue; // remplaçant resté sur le banc : la fiche ne les liste pas
            }
            $out[] = array_merge($p, [
                'name' => $q['name'] ?? self::museumName($p['name']),
                'pos' => $p['pos'] === 'R' ? 'R' : (($q && $q['pos'] !== 'R') ? $q['pos'] : $p['pos']),
                'cap' => $q['cap'] ?? false,
                'num' => $p['num'] ?? ($q['num'] ?? null),
                'yellow' => $p['yellow'] ?: ($q['yellow'] ?? []),
                'goals' => $p['goals'] ?: ($q['goals'] ?? []),
            ]);
        }
        return $out;
    }

    /** Composition en lignes lisibles et modifiables (une par joueur), relues par apply(). */
    public static function lines(array $players, string $coach = ''): string
    {
        $order = ['G' => 0, 'D' => 1, 'M' => 2, 'A' => 3, 'R' => 4];
        usort($players, fn ($a, $b) => [$order[$a['pos']] ?? 2, (int) ($a['in'] ?? 0)] <=> [$order[$b['pos']] ?? 2, (int) ($b['in'] ?? 0)]);
        $out = [];
        foreach ($players as $p) {
            $d = [];
            if (!empty($p['cap'])) {
                $d[] = 'cap.';
            }
            if (!empty($p['num'])) {
                $d[] = 'n° ' . $p['num'];
            }
            foreach ((array) $p['goals'] as $g) {
                $d[] = 'but' . ($g ? " $g'" : '');
            }
            if ($p['in'] !== null) {
                $d[] = "entré {$p['in']}'";
            }
            if ($p['out'] !== null) {
                $d[] = "sorti {$p['out']}'";
            }
            foreach ((array) $p['yellow'] as $y) {
                $d[] = 'jaune' . ($y ? " $y'" : '');
            }
            foreach ((array) $p['red'] as $r) {
                $d[] = 'rouge' . ($r ? " $r'" : '');
            }
            $out[] = $p['pos'] . ' · ' . $p['name'] . ($d ? ' · ' . implode(', ', $d) : '');
        }
        if ($coach !== '') {
            $out[] = 'E · ' . self::museumName($coach);
        }
        return implode("\n", $out);
    }

    /**
     * Écrit une composition (lignes de lines()) dans la fiche : joueurs déjà reliés à leur fiche
     * gardés (même nom de famille), le reste remis à neuf.
     */
    public static function apply(array &$m, string $value): string
    {
        $known = [];
        foreach ((array) ($m['lineup']['rows'] ?? []) as $r) {
            $known[self::key((string) ($r['name'] ?? ''))] = $r;
        }
        $rows = [];
        foreach (preg_split('/\R/u', $value) ?: [] as $line) {
            $parts = array_map('trim', explode('·', $line));
            if (count($parts) < 2 || !preg_match('/^(G|D|M|A|R|E)$/u', $parts[0]) || $parts[1] === '') {
                continue;
            }
            [$pos, $name] = [$parts[0], $parts[1]];
            $det = mb_strtolower($parts[2] ?? '');
            $mins = function (string $word) use ($det): array {
                preg_match_all('/' . $word . '\s*(\d{1,3}(?:\s*\+\s*\d{1,2})?)?\'?/u', $det, $x);
                return array_map(fn ($v) => str_replace(' ', '', (string) $v), $x[1]);
            };
            $one = fn (string $word) => preg_match('/' . $word . '\s*(\d{1,3}(?:\s*\+\s*\d{1,2})?)/u', $det, $x) ? str_replace(' ', '', $x[1]) : null;
            $old = $known[self::key($name)] ?? [];
            $goals = array_values(array_filter($mins('but'), fn ($v) => $v !== '') ?: array_fill(0, count($mins('but')), ''));
            $yellow = array_values(array_filter($mins('jaune'), fn ($v) => $v !== ''));
            $red = array_values(array_filter($mins('rouge'), fn ($v) => $v !== ''));
            $in = $one('entré');
            $out = $one('sorti');
            $num = preg_match('/n°\s*(\d{1,2})/u', $det, $x) ? (int) $x[1] : ($old['number'] ?? null);
            $rows[] = [
                'position' => $pos, 'name' => $name, 'number' => $num, 'extra' => $old['extra'] ?? null, 'captain' => str_contains($det, 'cap.'),
                'goals' => $goals, 'own_goals' => $old['own_goals'] ?? [], 'goals_text' => implode(', ', array_map(fn ($g) => $g !== '' ? "$g'" : 'but', $goals)),
                'sub_in' => $in, 'sub_out' => $out, 'sub_text' => trim(($in !== null ? "Entrée $in'" : '') . ' ' . ($out !== null ? "Sortie $out'" : '')),
                'yellow' => $yellow, 'red' => $red, 'cards_text' => trim(implode(' ', array_map(fn ($y) => "J $y'", $yellow)) . ' ' . implode(' ', array_map(fn ($r) => "R $r'", $red))),
                'person_id' => $old['person_id'] ?? null,
            ];
        }
        if (count(array_filter($rows, fn ($r) => !in_array($r['position'], ['R', 'E'], true))) < 7) {
            throw new \RuntimeException('Composition attendue : au moins sept titulaires, une ligne par joueur (« D · NOM Prénom · entré 63\' »).');
        }
        $m['lineup']['rows'] = $rows;
        $m['lineup']['source_table'] = null;
        return 'composition : ' . count($rows) . ' lignes';
    }

    // ------------------------------------------------------------------ Transfermarkt

    /** Feuille Transfermarkt du match, ou null s'il est introuvable. */
    public static function transfermarkt(array $m): ?array
    {
        $date = (string) $m['date'];
        $y = (int) substr($date, 0, 4);
        $mo = (int) substr($date, 5, 2);
        $season = $mo >= 7 ? $y : $y - 1;
        if ($saved = self::tmSaved($date, $season)) {
            return $saved;
        }
        try {
            return self::tmDirect($m, $date, $season, $mo);
        } catch (\RuntimeException $e) {
            if (!str_contains($e->getMessage(), 'refuse') || !Gemini::ready()) {
                throw $e;
            }
            // Dernier essai : la page lue par Google (Gemini, lecture d'adresse).
            try {
                return self::tmGemini($m, $date, $season, $mo);
            } catch (\Throwable) {
                throw $e;
            }
        }
    }

    /** footballdatabase.eu, lu directement : calendrier de la saison du club, puis page du match. */
    public static function footballdatabase(array $m): ?array
    {
        $date = (string) $m['date'];
        $y = (int) substr($date, 0, 4);
        $season = (int) substr($date, 5, 2) >= 7 ? $y : $y - 1;
        $id = null;
        foreach ([$season, $season - 1, $season + 1] as $sid) {
            $label = $sid . '-' . ($sid + 1);
            $html = self::page(self::FDB . '/fr/club/equipe/' . self::FDB_CLUB . '/' . $label, 'fdb-s-' . $sid, 86400 * 7, '/fr/match/resume/', 'footballdatabase.eu');
            foreach (preg_split('/<tr[\s>]/', $html) ?: [] as $tr) {
                if (preg_match('#/fr/match/resume/(\d+-[a-z0-9_-]+)#', $tr, $l) && preg_match('#(\d{2})/(\d{2})/(\d{4})#', strip_tags($tr), $d)
                    && abs(strtotime("$d[3]-$d[2]-$d[1]") - strtotime($date)) <= 86400) {
                    $id = $l[1];
                    break 2;
                }
            }
        }
        if (!$id) {
            return null;
        }
        $url = self::FDB . '/fr/match/resume/' . $id;
        return ['label' => 'footballdatabase.eu · feuille de match', 'url' => $url] + self::fdbParse(self::page($url, 'fdb-m-' . (int) $id, 86400 * 30, 'Titulaires', 'footballdatabase.eu'));
    }

    /** Page de match footballdatabase.eu : titulaires et remplaçants de Sochaux, minutes, buts, cartons, entraîneur. */
    public static function fdbParse(string $html): array
    {
        $team = null;
        // Les deux équipes : blocs « toggleteam1 » et « toggleteam2 », chacun avec son nom.
        foreach ([1, 2] as $t) {
            if (preg_match('#section titulaires toggleteam' . $t . '.{0,400}?<p class="team">([^<]+)</p>#s', $html, $x) && stripos($x[1], 'sochaux') !== false) {
                $team = $t;
            }
        }
        if ($team === null) {
            return ['players' => [], 'coach' => ''];
        }
        $players = [];
        $mins = fn (string $cls, string $td) => preg_match_all("#<span class='$cls'>([0-9+]+)</span>#", $td, $mm) ? array_map(fn ($v) => (string) self::min($v), $mm[1]) : [];
        preg_match_all('#<div class="section titulaires toggleteam' . $team . '">(.*?)(?=<div class="section |<div class="row")#s', $html, $blocks);
        foreach ($blocks[1] as $bi => $block) {
            $bench = $bi > 0 || str_contains($block, 'Remplaçants');
            foreach (preg_split('/<tr>/', $block) ?: [] as $tr) {
                if (!str_contains($tr, 'class="position"') || !preg_match("#href='/fr/joueur/details/[^']+'>([^<]+)</a>#", $tr, $n)) {
                    continue;
                }
                $tds = preg_split('#</td>#', $tr) ?: [];
                $events = (string) ($tds[4] ?? '');
                $pos = preg_match('#class="position">([GDMA])<#', $tr, $pp) ? $pp[1] : 'M';
                $in = $mins('subin', $events)[0] ?? null;
                $name = trim(html_entity_decode($n[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $players[] = ['name' => $name, 'pos' => $bench ? 'R' : $pos, 'in' => $in, 'out' => $mins('subout', $events)[0] ?? null,
                    'goals' => $mins('goal', $events), 'yellow' => $mins('yellowcard', $events), 'red' => $mins('redcard', $events),
                    'cap' => false, 'num' => preg_match('#<td>\s*(\d{1,2})\s*$#', (string) ($tds[0] ?? ''), $nn) ? (int) $nn[1] : null, 'key' => self::key($name)];
            }
        }
        $coach = preg_match('#section entraineur toggleteam' . $team . '.*?href=\'/fr/joueur/details/[^\']+\'>([^<]+)</a>#s', $html, $c) ? trim(html_entity_decode($c[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
        return ['players' => $players, 'coach' => $coach];
    }

    /** Relevé Transfermarkt déjà fait et livré avec le site (app/Resources/compos), s'il couvre ce match. */
    private static function tmSaved(string $date, int $season): ?array
    {
        foreach ([$season, $season - 1] as $sid) {
            $f = APP_ROOT . '/app/Resources/compos/tm-' . $sid . '.json';
            foreach (is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [] as $g) {
                if (abs(strtotime($g['date']) - strtotime($date)) <= 86400 && count($g['players']) >= 11) {
                    $players = array_map(fn ($p) => $p + ['in' => null, 'out' => null, 'goals' => [], 'yellow' => [], 'red' => [], 'num' => null, 'cap' => false, 'key' => self::key((string) $p['name'])], $g['players']);
                    return ['label' => 'Transfermarkt · rapport de match', 'url' => self::TM . '/spielbericht/index/spielbericht/' . $g['id'], 'players' => $players, 'coach' => (string) $g['coach']];
                }
            }
        }
        return null;
    }

    /** Page de rapport de match Transfermarkt enregistrée par l'historien (Ctrl+S) et déposée sur la fiche. */
    private static function fromHtml(string $html): array
    {
        if (!str_contains($html, 'transfermarkt') || !str_contains($html, '/verein/' . self::TM_CLUB)) {
            throw new \RuntimeException('ce fichier n’est pas un rapport de match Transfermarkt du FC Sochaux');
        }
        $url = preg_match('#https://www\.transfermarkt\.[a-z.]+/[^"\s]*/spielbericht/(?:index/spielbericht/)?\d+#', $html, $u) ? $u[0] : self::TM;
        return ['label' => 'Transfermarkt · rapport de match (page déposée)', 'url' => $url] + self::tmParse($html);
    }

    private static function tmDirect(array $m, string $date, int $season, int $mo): ?array
    {
        $tmId = null;
        foreach ([$season, $mo === 7 ? $season - 1 : null] as $sid) {
            if ($sid === null) {
                continue;
            }
            foreach (self::tmSeason($sid) as $g) {
                if (abs(strtotime($g['date']) - strtotime($date)) <= 86400 * 1) {
                    $tmId = $g['id'];
                    break 2;
                }
            }
        }
        if (!$tmId) {
            return null;
        }
        $html = self::page(self::TM . '/spielbericht/index/spielbericht/' . $tmId, 'tm-m-' . $tmId, 86400 * 30);
        $r = self::tmParse($html);
        return ['label' => 'Transfermarkt · rapport de match', 'url' => self::TM . '/spielbericht/index/spielbericht/' . $tmId] + $r;
    }

    /** Calendrier d'une saison chez Transfermarkt (gardé une semaine). @return list<array{date:string,id:int}> */
    private static function tmSeason(int $sid): array
    {
        $html = self::page(self::TM . '/fc-sochaux-montbeliard/spielplan/verein/' . self::TM_CLUB . '/saison_id/' . $sid, 'tm-s-' . $sid, 86400 * 7);
        $out = [];
        $months = ['janv' => 1, 'févr' => 2, 'mars' => 3, 'avr' => 4, 'mai' => 5, 'juin' => 6, 'juil' => 7, 'août' => 8, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'déc' => 12];
        foreach (preg_split('/<tr[\s>]/', $html) ?: [] as $tr) {
            if (!preg_match('#/spielbericht/index/spielbericht/(\d+)#', $tr, $id)) {
                continue;
            }
            $txt = html_entity_decode(strip_tags($tr), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/(\d{1,2})\s+(janv|févr|mars|avr|mai|juin|juil|août|sept|oct|nov|déc)\.?\s+(\d{4})/u', $txt, $d)) {
                $out[] = ['date' => sprintf('%04d-%02d-%02d', (int) $d[3], $months[$d[2]], (int) $d[1]), 'id' => (int) $id[1]];
            } elseif (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $txt, $d)) {
                $out[] = ['date' => "$d[3]-$d[2]-$d[1]", 'id' => (int) $id[1]];
            }
        }
        return $out;
    }

    /** Minute d'une horloge Transfermarkt (image en mosaïque de 36 px, 10 minutes par ligne). */
    private static function tmMinute(string $li): ?string
    {
        if (!preg_match('/sb-sprite-uhr-klein"\s+style="background-position:\s*(-?\d+)px\s+(-?\d+)px;?"\s*>\s*([^<]*)</u', $li, $x)) {
            return null;
        }
        $m = (int) round(abs((int) $x[1]) / 36) + 1 + (int) round(abs((int) $x[2]) / 36) * 10;
        $extra = trim(html_entity_decode($x[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \u{a0}");
        if ($m > 120 || $m < 1) {
            return null;
        }
        return preg_match('/\+\s*(\d+)/', $extra, $e) ? $m . '+' . $e[1] : (string) $m;
    }

    /**
     * Lit un rapport de match Transfermarkt : joueurs du FCSM (titulaires, remplaçants, minutes,
     * buts, cartons) et entraîneur. Deux mises en page : liste par poste (matchs anciens) ou
     * schéma tactique avec numéros et banc (matchs récents).
     */
    public static function tmParse(string $html): array
    {
        $html = (string) preg_replace('/<img[^>]*>/', '', $html);
        $club = '/verein/' . self::TM_CLUB . '/';
        // Boîtes des deux équipes : celle qui porte le lien du FCSM.
        $box = '';
        if (preg_match_all('/aufstellung-unterueberschrift-mannschaft.*?(?=aufstellung-unterueberschrift-mannschaft|<div class="clearer")/s', $html, $bx)) {
            foreach ($bx[0] as $b) {
                if (preg_match('#sb-vereinslink" href="[^"]*' . preg_quote($club, '#') . '#', $b)) {
                    $box = $b;
                    break;
                }
            }
        }
        $players = [];
        $add = function (string $name, string $pos, ?int $num, ?string $slug) use (&$players) {
            $name = trim(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($name === '') {
                return;
            }
            $players[] = ['name' => $name, 'pos' => $pos, 'in' => null, 'out' => null, 'goals' => [], 'yellow' => [], 'red' => [], 'cap' => false, 'num' => $num, 'key' => self::key($name), 'slug' => $slug];
        };
        $coach = '';
        if (preg_match('#profil/trainer/\d+"[^>]*>([^<]+)<#', $box, $c) || preg_match('#title="([^"]+)"[^>]*href="[^"]*/profil/trainer/#', $box, $c)) {
            $coach = trim(html_entity_decode($c[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (str_contains($box, 'formation-player-container')) {
            // Schéma tactique : poste déduit de la hauteur sur le terrain.
            preg_match_all('#formation-player-container" style="top:\s*([\d.]+)%[^"]*">\s*<div class="tm-shirt-number[^"]*">\s*(\d*)\s*</div>.*?href="/([a-z0-9-]+)/profil/spieler/\d+"[^>]*>([^<]+)<#s', $box, $fp, PREG_SET_ORDER);
            foreach ($fp as $f) {
                $top = (float) $f[1];
                $pos = $top >= 75 ? 'G' : ($top >= 55 ? 'D' : ($top >= 30 ? 'M' : 'A'));
                // Nom complet tiré de l'adresse (« maxime-hautbois ») quand la page n'affiche que le nom.
                $full = mb_convert_case(str_replace('-', ' ', $f[3]), MB_CASE_TITLE);
                $shown = trim(html_entity_decode($f[4], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $name = Names::ascii(mb_strtolower($shown)) === Names::ascii(mb_strtolower((string) (($w = explode(' ', $full)) ? $w[count($w) - 1] : ''))) ? preg_replace('/\S+$/u', $shown, $full) : $full;
                $add((string) $name, $pos, $f[2] !== '' ? (int) $f[2] : null, $f[3]);
            }
            if (preg_match('#<table class="ersatzbank">(.*?)</table>#s', $box, $bench)) {
                preg_match_all('#tm-shirt-number[^"]*">\s*(\d*)\s*<.*?title="([^"]+)"\s+href="/([a-z0-9-]+)/profil/spieler#s', $bench[1], $bp, PREG_SET_ORDER);
                foreach ($bp as $b) {
                    $add($b[2], 'R', $b[1] !== '' ? (int) $b[1] : null, $b[3]);
                }
            }
        } else {
            $labels = ['Gardien' => 'G', 'Défenseur' => 'D', 'Milieu' => 'M', 'Attaquant' => 'A'];
            if (preg_match_all('#<tr>\s*<td><b>([^<]+)</b></td>\s*<td>(.*?)</td>\s*</tr>#s', $box, $rows, PREG_SET_ORDER)) {
                foreach ($rows as $r) {
                    $pos = null;
                    foreach ($labels as $l => $p) {
                        if (str_starts_with(trim($r[1]), $l)) {
                            $pos = $p;
                        }
                    }
                    if (!$pos) {
                        continue;
                    }
                    preg_match_all('#title="([^"]+)"\s+href="/([a-z0-9-]+)/profil/spieler#', $r[2], $pp, PREG_SET_ORDER);
                    foreach ($pp as $p) {
                        $add($p[1], $pos, null, $p[2]);
                    }
                }
            }
        }
        // Événements du FCSM : buts, remplacements, cartons (l'écusson de chaque ligne dit l'équipe).
        $bySlug = [];
        foreach ($players as $i => $p) {
            $bySlug[$p['slug']] = $i;
        }
        $find = function (string $slug) use (&$players, &$bySlug, $add): int {
            if (!isset($bySlug[$slug])) {
                $add(mb_convert_case(str_replace('-', ' ', $slug), MB_CASE_TITLE), 'R', null, $slug);
                $bySlug[$slug] = count($players) - 1;
            }
            return $bySlug[$slug];
        };
        foreach (['sb-tore' => 'goal', 'sb-wechsel' => 'sub', 'sb-karten' => 'card'] as $sec => $kind) {
            if (!preg_match('#id="' . $sec . '">(.*?)(?=<div class="box"|id="sb-|$)#s', $html, $sx)) {
                continue;
            }
            foreach (preg_split('#<li class="sb-aktion#', $sx[1]) ?: [] as $li) {
                if (!str_contains($li, $club)) {
                    continue;
                }
                $min = self::tmMinute($li);
                if ($kind === 'goal') {
                    if (str_contains($li, 'Contre son camp') || str_contains($li, 'contre son camp')) {
                        continue;
                    }
                    if (preg_match('#class="sb-aktion-aktion">\s*<a title="[^"]*" class="wichtig" href="/([a-z0-9-]+)/#', $li, $g)) {
                        $players[$find($g[1])]['goals'][] = $min ?? '';
                    }
                } elseif ($kind === 'sub') {
                    if (preg_match('#sb-aktion-wechsel-ein">.*?href="/([a-z0-9-]+)/#s', $li, $in)) {
                        $players[$find($in[1])]['in'] = $min;
                        $players[$find($in[1])]['pos'] = 'R';
                    }
                    if (preg_match('#sb-aktion-wechsel-aus">.*?href="/([a-z0-9-]+)/#s', $li, $out)) {
                        $players[$find($out[1])]['out'] = $min;
                    }
                } elseif (preg_match('#class="wichtig" href="/([a-z0-9-]+)/#', $li, $c)) {
                    $i = $find($c[1]);
                    if (str_contains($li, 'sb-rot') || str_contains($li, 'sb-gelbrot')) {
                        $players[$i]['red'][] = $min ?? '';
                    } else {
                        $players[$i]['yellow'][] = $min ?? '';
                    }
                }
            }
        }
        foreach ($players as &$p) {
            unset($p['slug']);
        }
        unset($p);
        return ['players' => $players, 'coach' => $coach];
    }

    // ------------------------------------------------------------------ autres sites (Gemini + Google)

    /** Feuille d'un autre site, trouvée et lue par Gemini avec la recherche Google, ou null. */
    /** Transfermarkt lu par Google : calendrier de la saison (gardé une semaine), puis rapport du match. */
    private static function tmGemini(array $m, string $date, int $season, int $mo): ?array
    {
        $tmId = null;
        foreach ([$season, $mo === 7 ? $season - 1 : null] as $sid) {
            if ($sid === null) {
                continue;
            }
            $f = self::DIR . '/cache/tm-g-s-' . $sid . '.json';
            $list = is_file($f) && filemtime($f) > time() - 86400 * 7 ? (array) json_decode((string) file_get_contents($f), true) : null;
            if ($list === null) {
                $url = self::TM . '/fc-sochaux-montbeliard/spielplan/verein/' . self::TM_CLUB . '/saison_id/' . $sid;
                $r = self::ai("Lis la page $url (calendrier du FC Sochaux-Montbéliard, saison $sid-" . ($sid + 1) . '). Donne TOUS les matchs listés avec leur date et le numéro du lien « /spielbericht/index/spielbericht/NUMÉRO » (rapport de match). '
                    . 'Réponds en JSON seul : {"matchs": [{"date": "AAAA-MM-JJ", "id": NUMÉRO}]}. N’invente rien.', null, [['url_context' => new \stdClass()]], (int) ($m['id'] ?? 0));
                $d = self::json($r['text']);
                $list = [];
                foreach ((array) ($d['matchs'] ?? []) as $g) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($g['date'] ?? '')) && (int) ($g['id'] ?? 0) > 0) {
                        $list[] = ['date' => $g['date'], 'id' => (int) $g['id']];
                    }
                }
                if (!$list) {
                    throw new \RuntimeException('Transfermarkt bloque le serveur, et Google n’a pas pu lire le calendrier de la saison');
                }
                @mkdir(dirname($f), 0775, true);
                file_put_contents($f, json_encode($list));
            }
            foreach ($list as $g) {
                if (abs(strtotime($g['date']) - strtotime($date)) <= 86400) {
                    $tmId = (int) $g['id'];
                    break 2;
                }
            }
        }
        if (!$tmId) {
            return null;
        }
        $url = self::TM . '/spielbericht/index/spielbericht/' . $tmId;
        $r = self::ai("Lis la page $url (rapport de match Transfermarkt). " . self::ASK, null, [['url_context' => new \stdClass()]], (int) ($m['id'] ?? 0));
        $d = self::json($r['text']);
        if (!$d || empty($d['joueurs'])) {
            return ['label' => 'Transfermarkt · rapport de match (lu par Google)', 'url' => $url, 'players' => [], 'coach' => ''];
        }
        return ['label' => 'Transfermarkt · rapport de match (lu par Google)', 'url' => $url] + self::players($d);
    }

    /** Demande commune : la composition de Sochaux en JSON. */
    private const ASK = 'Réponds en JSON seul : {"trouve": bool, "url": "adresse de la page", "entraineur": "Prénom Nom" | null, "joueurs": [{"nom": "Prénom Nom", "poste": "G|D|M|A|R", "entre": minute|null, "sorti": minute|null, "buts": [minutes], "jaunes": [minutes], "rouges": [minutes]}]}. '
        . 'Seulement les joueurs du FC Sochaux-Montbéliard ; « R » pour un remplaçant (avec sa minute d’entrée s’il est entré) ; n’invente rien : si la page ne donne pas la composition, {"trouve": false}.';

    /**
     * Appel Gemini avec relances : quand le modèle est surchargé (« high demand », quota), on attend
     * puis on réessaie, d'abord le même modèle, puis un modèle plus léger.
     */
    private static function ai(string $text, ?string $system, array $tools, int $ref): array
    {
        if (!Gemini::ready()) {
            throw new \RuntimeException('il faut une clé Gemini (Réglages › Assistant IA)');
        }
        $models = array_values(array_unique([WebCheck::model(), 'gemini-2.5-flash']));
        $last = null;
        foreach ([[$models[0], 0], [$models[0], 6], [$models[1] ?? $models[0], 3], [$models[1] ?? $models[0], 15]] as [$model, $wait]) {
            if ($wait) {
                sleep($wait);
            }
            try {
                return Gemini::generate([['role' => 'user', 'text' => $text]], $system, ['for' => 'trouvailles', 'model' => $model, 'max_tokens' => 4096, 'temperature' => 0.2, 'timeout' => 120,
                    'json' => false, 'raw' => true, 'ref' => 'fiche:' . $ref, 'tools' => $tools]);
            } catch (\RuntimeException $e) {
                $last = $e;
                if (!self::busy($e->getMessage())) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('Gemini surchargé, match remis dans la file (' . mb_substr((string) $last?->getMessage(), 0, 60) . ')');
    }

    public static function busy(string $msg): bool
    {
        return (bool) preg_match('/high demand|overload|unavailable|try again|quota|surcharg|503|429/i', $msg);
    }

    private static function json(string $t): ?array
    {
        $t = trim(preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim($t)) ?? '');
        if (($a = strpos($t, '{')) !== false && ($b = strrpos($t, '}')) > $a) {
            $t = substr($t, $a, $b - $a + 1);
        }
        $d = json_decode($t, true);
        return is_array($d) ? $d : null;
    }

    /** Joueurs au format commun depuis la réponse JSON de l'IA. */
    private static function players(array $d): array
    {
        $players = [];
        foreach ((array) ($d['joueurs'] ?? []) as $j) {
            $name = trim((string) ($j['nom'] ?? ''));
            if ($name === '') {
                continue;
            }
            $pos = in_array($j['poste'] ?? '', ['G', 'D', 'M', 'A', 'R'], true) ? $j['poste'] : 'M';
            $players[] = ['name' => $name, 'pos' => $pos, 'in' => self::min($j['entre'] ?? null), 'out' => self::min($j['sorti'] ?? null),
                'goals' => array_values(array_filter(array_map([self::class, 'min'], (array) ($j['buts'] ?? [])))), 'yellow' => array_values(array_filter(array_map([self::class, 'min'], (array) ($j['jaunes'] ?? [])))),
                'red' => array_values(array_filter(array_map([self::class, 'min'], (array) ($j['rouges'] ?? [])))), 'cap' => false, 'num' => null, 'key' => self::key($name)];
        }
        return ['players' => $players, 'coach' => (string) ($d['entraineur'] ?? '')];
    }

    public static function web(array $doc, string $key): ?array
    {
        $domain = self::WEB[$key];
        $m = $doc['match'];
        $sh = (bool) ($m['sochaux_home'] ?? true);
        $opp = (string) ($sh ? ($m['away']['name'] ?? '') : ($m['home']['name'] ?? ''));
        $system = "Tu aides les historiens du musée Sochaux Rétro. Cherche avec Google, UNIQUEMENT sur le site $domain, la feuille du match décrit (composition du FC Sochaux-Montbéliard). "
            . "Réponds en JSON seul : {\"trouve\": bool, \"url\": \"adresse de la page sur $domain\", \"entraineur\": \"Prénom Nom\" | null, \"joueurs\": [{\"nom\": \"Prénom Nom\", \"poste\": \"G|D|M|A|R\", \"entre\": minute|null, \"sorti\": minute|null, \"buts\": [minutes], \"jaunes\": [minutes], \"rouges\": [minutes]}]}. "
            . "Seulement les joueurs de Sochaux ; « R » pour un remplaçant (avec sa minute d'entrée s'il est entré) ; n'invente rien : si la page n'existe pas ou ne donne pas la composition, {\"trouve\": false}.";
        $text = 'Match : ' . ($sh ? "Sochaux – $opp" : "$opp – Sochaux") . ', le ' . date('d/m/Y', strtotime((string) $m['date'])) . ' (' . ($m['competition_label'] ?: $m['competition'] ?? '') . ')'
            . (isset($m['score']['home']) ? ', score ' . $m['score']['home'] . '-' . $m['score']['away'] : '');
        if (self::$get) {
            $r = ['text' => (string) (self::$get)('gemini:' . $key . ':' . $text), 'raw' => []];
        } else {
            $r = self::ai($text, $system, [['google_search' => new \stdClass()]], (int) $doc['id']);
        }
        $d = self::json((string) ($r['text'] ?? ''));
        if (!is_array($d)) {
            self::$why = 'réponse illisible de l’IA';
            return null;
        }
        if (empty($d['trouve']) || empty($d['joueurs'])) {
            self::$why = 'Google ne trouve pas la page du match sur ce site';
            return null;
        }
        // La page doit être sur le site demandé et avoir été réellement consultée (ancrage Google).
        $url = (string) ($d['url'] ?? '');
        $grounded = array_filter(array_map(fn ($c) => (string) ($c['web']['uri'] ?? '') . ' ' . (string) ($c['web']['title'] ?? ''), (array) ($r['raw']['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [])));
        $onSite = fn (string $s) => stripos($s, $domain) !== false;
        if (!self::$get && !array_filter($grounded, $onSite)) {
            self::$why = 'composition annoncée par l’IA mais aucune page du site réellement consultée : écartée par prudence';
            return null;
        }
        if (!$onSite($url)) {
            $url = 'https://' . $domain . '/';
        }
        return ['label' => self::SOURCES[$key], 'url' => $url] + self::players($d);
    }

    // ------------------------------------------------------------------ téléchargement

    /** Essai d'accès direct du serveur à chaque site source : [site => [ok, détail]]. */
    public static function probe(): array
    {
        $out = [];
        $relay = trim((string) \App\Core\Settings::get('compos.relay', ''));
        $tests = ['Transfermarkt' => self::TM . '/fc-sochaux-montbeliard/startseite/verein/' . self::TM_CLUB, 'worldfootball.net' => 'https://www.worldfootball.net/teams/fc-sochaux/',
            'footballdatabase.eu' => 'https://www.footballdatabase.eu/fr/club/equipe/19-sochaux', 'pari-et-gagne.com' => 'https://www.pari-et-gagne.com/', 'fcsmstory.com' => 'https://fcsmstory.com/'];
        foreach ($tests as $site => $url) {
            $b = self::fetch($url, 25);
            $ok = strlen($b) > 5000 && stripos($b, 'sochaux') !== false;
            $line = ($ok ? 'accessible' : 'bloqué ou vide') . ' (' . self::$diag . ', ' . round(strlen($b) / 1024) . ' Ko)';
            if (!$ok && $relay !== '' && $site === 'Transfermarkt') {
                $b = self::fetch(str_replace('{url}', rawurlencode($url), $relay), 90);
                $ok = strlen($b) > 5000 && stripos($b, 'sochaux') !== false;
                $line .= ' ; par le relais : ' . ($ok ? 'accessible' : 'échec') . ' (' . self::$diag . ')';
            }
            $out[$site] = [$ok, $line];
        }
        return $out;
    }

    /** Ce que le dernier téléchargement a reçu (code, titre de la page), pour comprendre un blocage. */
    private static string $diag = '';

    /** Téléchargement avec les en-têtes d'un vrai navigateur (et ses cookies, gardés entre deux pages). */
    private static function fetch(string $url, int $timeout = 40): string
    {
        @mkdir(self::DIR . '/cache', 0775, true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_ENCODING => '', CURLOPT_COOKIEJAR => self::DIR . '/cache/.cookies', CURLOPT_COOKIEFILE => self::DIR . '/cache/.cookies', CURLOPT_USERAGENT => self::UA,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8', 'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
                'Cache-Control: no-cache', 'Referer: https://www.transfermarkt.fr/', 'Sec-Fetch-Dest: document', 'Sec-Fetch-Mode: navigate', 'Sec-Fetch-Site: same-origin', 'Upgrade-Insecure-Requests: 1']]);
        $b = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $title = is_string($b) && preg_match('/<title>([^<]{0,80})/i', $b, $t) ? trim(html_entity_decode($t[1])) : '';
        self::$diag = 'code ' . $code . ($title !== '' ? ', page « ' . $title . ' »' : '') . ($err !== '' ? ', ' . $err : '');
        return is_string($b) ? $b : '';
    }

    /** Page gardée sur disque ($ttl secondes) ; une demande toutes les PAUSE secondes au plus. */
    private static function page(string $url, string $cacheKey, int $ttl, string $marker = '/spielbericht/', string $site = 'Transfermarkt'): string
    {
        $f = self::DIR . '/cache/' . $cacheKey . '.html';
        // Une vraie page Transfermarkt contient des liens de rapports de match ; sinon c'est une page
        // de blocage (anti-robots, consentement) : jamais gardée, signalée comme erreur.
        $real = fn (string $b) => str_contains($b, $marker);
        if (is_file($f) && filemtime($f) > time() - $ttl && $real($c = (string) file_get_contents($f))) {
            return $c;
        }
        if (self::$get) {
            $b = (string) (self::$get)($url);
        } else {
            $lock = self::DIR . '/cache/.last';
            @mkdir(dirname($lock), 0775, true);
            $wait = self::PAUSE - (time() - (int) @filemtime($lock));
            if ($wait > 0) {
                sleep(min($wait, self::PAUSE));
            }
            @touch($lock);
            $b = self::fetch($url, 45);
            if (!$real($b)) {
                // Bloqué : on repasse par le service relais s'il est réglé (adresses non bloquées).
                $relay = trim((string) \App\Core\Settings::get('compos.relay', ''));
                if ($relay !== '') {
                    $b = self::fetch(str_replace('{url}', rawurlencode($url), $relay), 90);
                }
            }
        }
        if (!$real($b)) {
            @unlink($f);
            $hint = trim((string) \App\Core\Settings::get('compos.relay', '')) === '' ? ' ; réglez un service relais dans Contenus › Contrôle des compositions' : ' ; le service relais n’a pas pu la lire non plus';
            throw new \RuntimeException($site . ' refuse la lecture depuis le serveur (' . self::$diag . ')' . $hint);
        }
        @mkdir(dirname($f), 0775, true);
        file_put_contents($f, $b);
        return $b;
    }
}
