<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
use App\Data\Paths;

/**
 * Import des feuilles de match de l'association (FM_*.doc(x), AM_*.doc(x) : ~4 000 matchs de 1928 à 2024),
 * lues une fois pour toutes dans app/Resources/import/feuilles-de-match.json.gz.
 *
 * plan()  : rapproche chaque feuille d'une fiche du musée (date ± 3 jours, adversaire), sans rien écrire ;
 * run()   : crée les fiches absentes (publiées, rangées dans leur saison) et envoie les écarts avec une
 *           fiche existante dans Trouvailles (score, date, lieu, affluence, arbitre, buteurs, composition),
 *           où un historien les valide ou les écarte. Une fiche existante n'est jamais modifiée directement.
 * Relancer est sans risque : chaque feuille n'est traitée qu'une fois (clé stable).
 */
final class FeuillesImport
{
    public static string $dir = STORAGE_PATH . '/import/feuilles';
    public static string $data = APP_ROOT . '/app/Resources/import/feuilles-de-match.json.gz';
    public const ORIGIN = 'feuilles';
    private const AUTHOR = ['name' => 'Feuilles de match (archives du club)'];

    // ------------------------------------------------------------------ données

    /** @return list<array> feuilles lues (avec 'key') */
    public static function sheets(): array
    {
        static $cache = null, $for = null;
        if ($cache !== null && $for === self::$data) {
            return $cache;
        }
        $for = self::$data;
        $raw = is_file(self::$data) ? gzdecode((string) file_get_contents(self::$data)) : false;
        $list = $raw ? json_decode($raw, true) : null;
        $cache = [];
        foreach (is_array($list) ? $list : [] as $s) {
            if (!is_array($s)) {
                continue;
            }
            $s['key'] = substr(sha1(($s['kind'] ?? '') . '|' . ($s['date'] ?? '') . '|' . ($s['home'] ?? '') . '|' . ($s['away'] ?? '') . '|' . ($s['score_home'] ?? '') . '-' . ($s['score_away'] ?? '')), 0, 16);
            $cache[] = $s;
        }
        return $cache;
    }

    public static function state(): array
    {
        return (JsonStore::read(self::$dir . '/state.json', []) ?? []) + ['at' => null, 'items' => [], 'done_at' => null, 'running' => false];
    }

    private static function save(callable $fn): array
    {
        return JsonStore::update(self::$dir . '/state.json', fn ($s) => $fn((is_array($s) ? $s : []) + ['at' => null, 'items' => [], 'done_at' => null, 'running' => false]), []);
    }

    // ------------------------------------------------------------------ plan

    /**
     * Rapproche les feuilles des fiches actuelles. Les feuilles déjà traitées gardent leur état.
     * Statuts : a-creer, a-comparer (fiche existante, écarts à proposer), sans-date, fait, compare, erreur.
     */
    public static function plan(): array
    {
        $sheets = self::sheets();
        if (!$sheets) {
            throw new \RuntimeException('Fichier des feuilles introuvable ou illisible (' . basename(self::$data) . ') : refaites la mise à jour en un clic.');
        }
        $byDate = [];
        foreach (Index::all() as $e) {
            $m = $e['m'] ?? null;
            if (($e['type'] ?? '') !== 'match' || !$m || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($m['date'] ?? ''))) {
                continue;
            }
            if (!self::isSochaux((string) $m['home']) && !self::isSochaux((string) $m['away'])) {
                continue;
            }
            $byDate[$m['date']][] = $e;
        }
        $old = self::state()['items'];
        $used = [];
        $items = [];
        // Les feuilles déjà rapprochées gardent leur fiche (pas de double rapprochement).
        foreach ($old as $k => $it) {
            if (!empty($it['fiche'])) {
                $used[(int) $it['fiche']] = true;
            }
        }
        foreach ($sheets as $s) {
            $k = $s['key'];
            if (isset($old[$k]) && in_array($old[$k]['status'], ['fait', 'compare'], true)) {
                $items[$k] = $old[$k];
                continue;
            }
            if (!preg_match('/^\d{4}-(\d{2})-(\d{2})$/', (string) ($s['date'] ?? ''), $d) || !checkdate((int) $d[1], (int) $d[2], (int) substr($s['date'], 0, 4))) {
                $items[$k] = ['status' => 'sans-date', 'fiche' => null, 'why' => 'feuille sans date valable'];
                continue;
            }
            [$opp, $us, $them] = self::side($s);
            $best = null;
            $t0 = strtotime($s['date']);
            for ($off = -3; $off <= 3; $off++) {
                foreach ($byDate[date('Y-m-d', $t0 + $off * 86400)] ?? [] as $e) {
                    if (isset($used[(int) $e['id']])) {
                        continue;
                    }
                    $m = $e['m'];
                    $eOpp = $m['sh'] ? $m['away'] : $m['home'];
                    $sc = $m['sh_score'] ? ($m['sh'] ? $m['sh_score'] : array_reverse($m['sh_score'])) : null;
                    $score = self::similar($opp, $eOpp) * 2 + ($sc && $sc[0] === $us && $sc[1] === $them ? 1 : 0) - abs($off) * 0.3;
                    if ($best === null || $score > $best[0]) {
                        $best = [$score, (int) $e['id']];
                    }
                }
            }
            if ($best && $best[0] > 0.4) {
                $used[$best[1]] = true;
                $items[$k] = ['status' => 'a-comparer', 'fiche' => $best[1], 'why' => null];
            } else {
                $items[$k] = ['status' => 'a-creer', 'fiche' => null, 'why' => null];
            }
        }
        return self::save(function ($st) use ($items) {
            $st['items'] = $items;
            $st['at'] = date('c');
            return $st;
        });
    }

    public static function summary(?array $state = null): array
    {
        $state ??= self::state();
        $out = ['a-creer' => 0, 'a-comparer' => 0, 'fait' => 0, 'compare' => 0, 'sans-date' => 0, 'erreur' => 0, 'props' => 0, 'total' => count($state['items'])];
        foreach ($state['items'] as $it) {
            $out[$it['status']] = ($out[$it['status']] ?? 0) + 1;
            $out['props'] += (int) ($it['props'] ?? 0);
        }
        $out['left'] = $out['a-creer'] + $out['a-comparer'];
        return $out;
    }

    // ------------------------------------------------------------------ traitement

    /** Traite au plus $max feuilles. @return array{created:int,compared:int,props:int,errors:int,left:int,messages:list<string>} */
    public static function run(int $max = 150): array
    {
        $res = ['created' => 0, 'compared' => 0, 'props' => 0, 'errors' => 0, 'left' => 0, 'messages' => []];
        $fp = fopen(self::lockFile(), 'c');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            $res['messages'][] = 'un autre lot est déjà en cours';
            $res['left'] = self::summary()['left'];
            return $res;
        }
        try {
            $state = self::state();
            if (!$state['items']) {
                $state = self::plan();
            }
            $sheets = [];
            foreach (self::sheets() as $s) {
                $sheets[$s['key']] = $s;
            }
            $patch = [];
            Fiches::batch(function () use ($state, $sheets, $max, &$patch, &$res) {
                $n = 0;
                foreach ($state['items'] as $k => $it) {
                    if ($n >= $max) {
                        break;
                    }
                    if (!in_array($it['status'], ['a-creer', 'a-comparer'], true) || !isset($sheets[$k])) {
                        continue;
                    }
                    $n++;
                    try {
                        if ($it['status'] === 'a-creer') {
                            $id = self::create($sheets[$k]);
                            $patch[$k] = ['status' => 'fait', 'fiche' => $id, 'why' => null, 'at' => date('c')];
                            $res['created']++;
                        } else {
                            $p = self::compare($sheets[$k], (int) $it['fiche']);
                            $patch[$k] = ['status' => 'compare', 'fiche' => (int) $it['fiche'], 'props' => $p, 'why' => null, 'at' => date('c')];
                            $res['compared']++;
                            $res['props'] += $p;
                        }
                    } catch (\Throwable $e) {
                        $patch[$k] = ['status' => 'erreur', 'why' => $e->getMessage(), 'at' => date('c')] + $it;
                        $res['errors']++;
                        $res['messages'][] = ($sheets[$k]['date'] ?? '?') . ' ' . ($sheets[$k]['home'] ?? '') . ' – ' . ($sheets[$k]['away'] ?? '') . ' : ' . $e->getMessage();
                    }
                }
            });
            $st = self::save(function ($st) use ($patch) {
                foreach ($patch as $k => $p) {
                    $st['items'][$k] = $p + ($st['items'][$k] ?? []);
                }
                return $st;
            });
            $res['left'] = self::summary($st)['left'];
            if ($res['left'] === 0) {
                self::save(fn ($s) => ['done_at' => date('c')] + $s);
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $res;
    }

    /** Lance ou met en pause l'import par la tâche planifiée (la page l'avance aussi tant qu'elle est ouverte). */
    public static function start(bool $on): void
    {
        if ($on && !self::state()['items']) {
            self::plan();
        }
        self::save(fn ($s) => ['running' => $on] + $s);
    }

    /** Tâche planifiée : un lot si l'import est lancé. */
    public static function tick(): ?string
    {
        $st = self::state();
        if (empty($st['running']) || !self::summary($st)['left']) {
            return null;
        }
        $r = self::run(150);
        if (!$r['left']) {
            self::save(fn ($s) => ['running' => false] + $s);
        }
        return $r['created'] + $r['compared'] ? $r['created'] . ' fiche(s) créée(s), ' . $r['compared'] . ' comparée(s), ' . $r['props'] . ' proposition(s)' : null;
    }

    /** Remet les erreurs à faire. */
    public static function retry(): void
    {
        self::save(function ($st) {
            foreach ($st['items'] as $k => $it) {
                if ($it['status'] === 'erreur') {
                    $st['items'][$k]['status'] = !empty($it['fiche']) ? 'a-comparer' : 'a-creer';
                }
            }
            return $st;
        });
    }

    private static function lockFile(): string
    {
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
        return self::$dir . '/run.lock';
    }

    // ------------------------------------------------------------------ création

    public static function create(array $s): int
    {
        [$opp, $us, $them, $home] = self::side($s);
        $doc = Fiches::blank('match');
        [$comp, $label, $code] = self::competition($s);
        $x = &$doc['match'];
        $x['date'] = $s['date'];
        $x['date_text'] = ucfirst(date_fr($s['date'], true));
        $x['season'] = FcsmStory::seasonFor($s['date']);
        [$x['competition'], $x['competition_label'], $x['competition_code']] = [$comp, $label, $code];
        $x['round'] = self::round((string) ($s['round'] ?? ''));
        $x['round_text'] = (string) ($s['round'] ?? '');
        $x['home'] = ['name' => self::club((string) $s['home']), 'level' => null];
        $x['away'] = ['name' => self::club((string) $s['away']), 'level' => null];
        $x['sochaux_home'] = $home;
        $h = (int) $s['score_home'];
        $a = (int) $s['score_away'];
        $note = trim((string) ($s['score_note'] ?? ''));
        $x['score'] = ['home' => $h, 'away' => $a, 'extra' => null, 'aet' => (bool) preg_match('/\bap\b|a\.p|prolong/iu', $note), 'pens' => null];
        $x['score_raw'] = "$h-$a";
        $x['score_line'] = $x['home']['name'] . ' / ' . $x['away']['name'] . " : $h-$a";
        $x['result'] = $us > $them ? 'V' : ($us < $them ? 'D' : 'N');
        $x['stadium'] = trim((string) ($s['stadium'] ?? ''));
        $place = trim((string) ($s['place'] ?? ''));
        if (!empty($s['spectators'])) {
            $x['spectators'] = (int) $s['spectators'];
            $x['spectators_text'] = number_format((int) $s['spectators'], 0, ',', "\u{202F}") . ' spectateurs';
        }
        $x['referee'] = trim((string) ($s['referee'] ?? ''));
        $x['header_extra'] = array_values(array_filter([
            $x['stadium'] === '' && $place !== '' ? 'Lieu : ' . $place : null,
            !empty($s['half_time']) ? 'Mi-temps : ' . $s['half_time'] : null,
            $note !== '' ? 'À noter : ' . $note : null,
            !empty($s['coach']) ? 'Entraîneur : ' . $s['coach'] : null,
        ]));
        $goals = trim(preg_replace('/^Buts?\s*:\s*/u', '', (string) ($s['goals_text'] ?? '')) ?? '');
        $x['goals_text'] = $goals;
        $x['goals'] = [];
        foreach ((array) ($s['goals_by_team'] ?? []) as $g) {
            $x['goals'][] = ['team' => self::isSochaux((string) $g['team']) ? 'Sochaux' : self::club($opp), 'scorers' => trim(preg_replace('/\s*,\s*/u', ', ', preg_replace('/\s*\(/u', ' (', (string) $g['text']) ?? '') ?? '')];
        }
        if (!$x['goals'] && $goals !== '') {
            $x['goals'][] = ['team' => 'Buts', 'scorers' => $goals];
        }
        $byName = [];
        foreach ((array) ($s['scorers'] ?? []) as $g) {
            $byName[Names::lineupLastName(FcsmImport::lineupName((string) $g['name']))] = $g;
        }
        foreach ((array) ($s['lineup'] ?? []) as $p) {
            $x['lineup']['rows'][] = self::row((string) $p['name'], (string) ($p['position'] ?? ''), (bool) ($p['captain'] ?? false), $byName, $p['sub'] ?? null, false);
            if (!empty($p['sub'][0])) {
                $x['lineup']['rows'][] = self::row((string) $p['sub'][0], 'R', false, $byName, ['', $p['sub'][1] ?? null], true);
            }
        }
        if (!empty($s['coach'])) {
            $x['lineup']['rows'][] = self::row((string) $s['coach'], 'E', false, [], null, false);
        }
        unset($x);
        $doc['title'] = self::title($doc['match']);
        $doc['intro'] = self::intro($doc['match'], $opp, $us, $them, $place);
        $doc['sections'][] = ['title' => 'Sources', 'html' => '<p>Feuille de match des archives de l’association (' . e((string) $s['file']) . ').</p>'];
        $doc['status'] = 'publie';
        $season = $doc['match']['season'];
        $cats = [];
        if ($season && FcsmImport::ensureSeason($season) && ($c = \App\Data\Categories::get($season))) {
            $cats[] = $season;
            if (!empty($c['parent'])) {
                $cats[] = $c['parent'];
            }
        }
        $doc['categories'] = $cats;
        $doc['legacy'] = ['feuilles' => $s['key'], 'file' => $s['file']];
        $doc['path'] = Paths::unique(Paths::suggest($doc), -1);
        $doc['slug'] = basename(rtrim($doc['path'], '/'));
        return (int) Fiches::save($doc, self::AUTHOR, 'Import des feuilles de match (' . $s['file'] . ')')['id'];
    }

    private static function row(string $name, string $pos, bool $cap, array $goals, ?array $sub, bool $in): array
    {
        $name = FcsmImport::lineupName($name);
        $g = $goals[Names::lineupLastName($name)] ?? null;
        $min = $g ? array_values(array_filter(array_map(fn ($v) => preg_replace('/\D.*$/', '', (string) $v), (array) $g['minutes']))) : [];
        $minute = $sub[1] ?? null;
        return ['position' => $pos, 'name' => trim($name), 'number' => null, 'extra' => null, 'captain' => $cap,
            'goals' => $min, 'own_goals' => [], 'goals_text' => $min ? implode("', ", $min) . "'" : ($g ? '1 but' : ''),
            'sub_in' => $in ? $minute : null, 'sub_out' => !$in && $sub ? $minute : null,
            'sub_text' => $in ? ($minute ? "Entrée {$minute}'" : 'Entré en jeu') : ($sub ? ($minute ? "Sortie {$minute}'" : 'Remplacé') : ''),
            'yellow' => [], 'red' => [], 'cards_text' => '', 'person_id' => null];
    }

    // ------------------------------------------------------------------ comparaison

    /** Écarts entre la feuille et une fiche existante → propositions Trouvailles. @return int ajoutées */
    public static function compare(array $s, int $id): int
    {
        $doc = Fiches::get($id);
        if (!$doc || ($doc['type'] ?? '') !== 'match') {
            throw new \RuntimeException("fiche $id introuvable");
        }
        $m = $doc['match'];
        [$opp, $us, $them, $home] = self::side($s);
        $src = [['label' => 'Feuille de match ' . self::fileLabel((string) $s['file']), 'url' => '', 'snippet' => trim((string) ($s['header'] ?? '')), 'kind' => self::ORIGIN]];
        $items = [];
        $add = function (string $field, string $value, string $current) use (&$items, $src) {
            $value = trim($value);
            if ($value === '' || ($current !== '' && Trouvailles::same($current, $value))) {
                return;
            }
            $type = in_array($field, ['info', 'piste'], true) ? $field : ($current === '' ? 'complement' : 'divergence');
            $items[] = ['field' => $field, 'value' => $value, 'current' => $current, 'type' => $type, 'origin' => self::ORIGIN, 'sources' => $src];
        };
        $sh = (bool) ($m['sochaux_home'] ?? true);
        $cur = isset($m['score']['home']) ? ($sh ? $m['score']['home'] . '-' . $m['score']['away'] : $m['score']['away'] . '-' . $m['score']['home']) : '';
        $add('score', "$us-$them", $cur);
        if (($m['date'] ?? '') !== $s['date']) {
            $add('date', date('d/m/Y', strtotime($s['date'])), $m['date'] ? date('d/m/Y', strtotime((string) $m['date'])) : '');
        }
        if ($sh !== $home) {
            $add('info', 'Selon la feuille de match, Sochaux jouait ' . ($home ? 'à domicile' : 'à l’extérieur') . ' (' . $s['home'] . ' – ' . $s['away'] . ($s['place'] ? ', à ' . $s['place'] : '') . ').', '');
        }
        if (!empty($s['spectators'])) {
            $now = (int) ($m['spectators'] ?? 0);
            if (!$now || abs($now - (int) $s['spectators']) > max(500, 0.1 * $now)) {
                $add('affluence', (string) (int) $s['spectators'], $now ? (string) $now : '');
            }
        }
        if (!empty($s['referee']) && trim((string) ($m['referee'] ?? '')) === '') {
            $add('arbitre', (string) $s['referee'], '');
        }
        if (!empty($s['stadium']) && trim((string) ($m['stadium'] ?? '')) === '') {
            $add('stade', (string) $s['stadium'], '');
        }
        $goals = trim(preg_replace('/^Buts?\s*:\s*/u', '', (string) ($s['goals_text'] ?? '')) ?? '');
        if ($goals !== '' && $us > 0 && trim((string) ($m['goals_text'] ?? '')) === '' && !array_filter((array) ($m['goals'] ?? []))) {
            $soc = implode(', ', array_map(fn ($g) => $g['name'] . ($g['minutes'] ? ' (' . implode(', ', array_map(fn ($v) => preg_replace('/\D.*$/', '', (string) $v) . "'", $g['minutes'])) . ')' : ''), (array) ($s['scorers'] ?? [])));
            $add('buteurs', $soc !== '' ? $soc : $goals, '');
        }
        $names = array_map(fn ($p) => (string) $p['name'], array_filter((array) ($s['lineup'] ?? []), fn ($p) => ($p['position'] ?? '') !== 'E'));
        if (count($names) >= 7 && !array_filter((array) ($m['lineup']['rows'] ?? []))) {
            $add('composition', implode(', ', $names), '');
        }
        return Trouvailles::propose($id, $items);
    }

    // ------------------------------------------------------------------ outils

    /** [adversaire, buts Sochaux, buts adversaire, Sochaux à domicile]. */
    private static function side(array $s): array
    {
        $home = self::isSochaux((string) $s['home']) || !self::isSochaux((string) $s['away']);
        return $home ? [(string) $s['away'], (int) $s['score_home'], (int) $s['score_away'], true] : [(string) $s['home'], (int) $s['score_away'], (int) $s['score_home'], false];
    }

    private static function isSochaux(string $n): bool
    {
        return (bool) preg_match('/sochaux/iu', Names::ascii($n));
    }

    /** Mots communs aux deux noms de club (sans les mots génériques). */
    private static function similar(string $a, string $b): int
    {
        $stop = ['club', 'sochaux', 'stade', 'union', 'sport', 'sports', 'football', 'olympique', 'racing', 'sportive', 'association', 'athletic'];
        $w = fn ($s) => array_filter(preg_split('/[^a-z0-9]+/', strtolower(Names::ascii($s))) ?: [], fn ($x) => strlen($x) > 2 && !in_array($x, $stop, true));
        return count(array_intersect(array_unique($w($a)), array_unique($w($b))));
    }

    /** « FC Sochaux » → « Sochaux » ; « Lausanne Sports(Sui.) » → « Lausanne Sports (Sui.) ». */
    private static function club(string $n): string
    {
        $n = trim(preg_replace('/\s*\(/u', ' (', $n) ?? $n);
        return self::isSochaux($n) ? 'Sochaux' : $n;
    }

    /** [compétition, libellé, code] au format du musée. */
    public static function competition(array $s): array
    {
        $c = trim((string) ($s['competition'] ?? ''));
        $l = strtolower(Names::ascii($c));
        if (($s['kind'] ?? '') === 'amical' || str_contains($l, 'amical')) {
            return ['Amical', 'Amical', 'Amical'];
        }
        $champ = str_contains($l, 'championnat') || str_contains($l, 'division') || str_contains($l, 'champ.');
        return match (true) {
            str_contains($l, 'coupe de france') || str_contains($l, 'coupe defrance') => ['Coupe de France', 'Coupe de France', 'CDF'],
            str_contains($l, 'coupe de la ligue') => ['Coupe de la Ligue', 'Coupe de la Ligue', 'CDL'],
            str_contains($l, 'uefa') || str_contains($l, 'liga europa') => ['Coupe d\'Europe', 'Coupe UEFA', 'UEFA'],
            str_contains($l, 'intertoto') => ['Coupe d\'Europe', 'Coupe Intertoto', 'Coupe intertoto'],
            str_contains($l, 'barrage') => ['Barrages', 'Barrages', 'Barrages'],
            $champ && preg_match('/\b(ligue 1|l1)\b/', $l) === 1 => ['Championnat', 'Ligue 1', 'L1'],
            $champ && preg_match('/\b(ligue 2|l2)\b/', $l) === 1 => ['Championnat', 'Ligue 2', 'L2'],
            $champ && preg_match('/\bd ?1\b|division 1/', $l) === 1 => ['Championnat', 'Division 1', 'D1'],
            $champ && preg_match('/\bd ?2\b|division 2/', $l) === 1 => ['Championnat', 'Division 2', 'D2'],
            $champ && str_contains($l, 'national') => ['Championnat', 'National', 'N1'],
            $champ && str_contains($l, 'amateur') => ['Championnat', 'CFA', 'CFA'],
            $champ => ['Championnat', $c !== '' ? $c : 'Championnat', ''],
            $c === '' => ['Match', 'Match', ''],
            default => ['Coupe', $c, $c],
        };
    }

    /** « 18ème journée » → « J18 » ; le reste tel quel. */
    private static function round(string $r): string
    {
        return preg_match('/(\d+)\s*(?:ère|ème|e)\s+journée/iu', $r, $m) ? 'J' . $m[1] : $r;
    }

    /** Titre au format du musée : « J17 – Gazélec Ajaccio / Sochaux – D2 – 07/11/1987 – 0-0 ». */
    private static function title(array $x): string
    {
        $teams = $x['home']['name'] . ' / ' . $x['away']['name'];
        $date = date('d/m/Y', strtotime((string) $x['date']));
        return match ($x['competition']) {
            'Amical' => "Amical – $teams – $date – " . $x['score_raw'],
            'Championnat' => ($x['round'] !== '' && preg_match('/^J\d+$/', $x['round']) ? $x['round'] . ' – ' : '') . "$teams – " . ($x['competition_code'] ?: $x['competition_label']) . " – $date – " . $x['score_raw'],
            default => ($x['round'] !== '' ? $x['round'] . ' – ' : '') . "$teams – " . ($x['competition_code'] ?: $x['competition_label'] ?: 'Match') . " – $date – " . $x['score_raw'],
        };
    }

    private static function intro(array $x, string $opp, int $us, int $them, string $place): string
    {
        $res = $us > $them ? "Victoire sochalienne $us à $them" : ($us < $them ? "Défaite $us à $them" : "Match nul $us partout");
        $comp = $x['competition'] === 'Amical' ? 'match amical' : mb_strtolower($x['competition_label'] ?: $x['competition']);
        return $res . ' face à ' . self::club($opp) . ($place !== '' ? ' à ' . $place : '') . ', le ' . date_fr($x['date']) . ' (' . $comp . ').';
    }

    /** « FM_197879.docx » → « 1978-1979 (officiels) ». */
    public static function fileLabel(string $f): string
    {
        if (preg_match('/^(FM|AM)_(\d{4})(\d{2})/', $f, $m)) {
            $a = (int) $m[2];
            return $a . '-' . ($a + 1) . ($m[1] === 'AM' ? ' (amicaux)' : '');
        }
        return $f;
    }
}
