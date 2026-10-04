<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Data\Collections;
use App\Data\Derived;
use App\Data\Fiches;
use App\Data\Index;
use App\Data\Names;
use App\Front\Explore;
use App\Front\Site;

/**
 * « Les chiffres du FCSM » : 100 statistiques calculées automatiquement, sans aucune saisie,
 * à partir de trois sources du musée :
 * - les tableaux de carrière des fiches joueurs (toutes les saisons depuis 1929) ;
 * - les matchs officiels racontés (compositions, buteurs minute par minute, affluences,
 *   récits des temps forts) ;
 * - les fiches des personnes (naissance, taille, pied, formation au club).
 *
 * Les séries ne sont comptées que dans les saisons racontées en entier : un match absent du
 * musée ne doit pas prolonger (ni couper) une série. Chaque chapitre propose plus de chiffres
 * que son quota ; si l'un manque (données insuffisantes), un chiffre de réserve prend sa place
 * pour qu'il y en ait toujours 100. Résultat gardé en cache (une version par langue), refait
 * quand les données calculées changent.
 */
final class Chiffres
{
    private const CACHE = STORAGE_PATH . '/cache/chiffres-%s.json';
    private const V = 1;
    public const COUNT = 100;
    /** Saison « racontée en entier » : au moins autant de matchs de championnat dans le musée. */
    public const FULL_SEASON = 28;
    public const BONAL = 'auguste-bonal';
    /** Voisins de l'Est pour les derbys. */
    public const EAST = ['strasbourg', 'metz', 'nancy', 'mulhouse', 'besancon', 'dijon'];
    /** Départements de Franche-Comté. */
    public const FRANCHE_COMTE = ['25' => 'Doubs', '39' => 'Jura', '70' => 'Haute-Saône', '90' => 'Territoire de Belfort'];

    /** Chapitres dans l'ordre de la page : clé => [titre, chapeau, quota]. */
    public const CHAPTERS = [
        'monuments' => ['Les monuments', 'Les records de toute une carrière sous le maillot sochalien, saison après saison depuis 1929.', 11],
        'buteurs' => ['Les buteurs', 'Triplés, penaltys, jokers et coups de tête : ceux qui ont fait trembler les filets.', 8],
        'chrono' => ['Le chrono', 'Minute par minute : les buts les plus rapides, les plus tardifs et les retournements de situation.', 11],
        'defense' => ['Les gardiens et la défense', 'Blanchissages, minutes d’invincibilité et murs jaune et bleu.', 8],
        'series' => ['Les séries', 'Invincibilité, victoires en rafale et longues disettes.', 9],
        'scores' => ['Les scores', 'Cartons, raclées, scores fétiches et séances de tirs au but.', 10],
        'acteurs' => ['Les acteurs', 'Passeurs, capitaines, inusables et entraîneurs : ceux qui ont tenu la maison.', 9],
        'adversaires' => ['Les adversaires', 'Victimes préférées, bêtes noires et voisins de l’Est.', 9],
        'bonal' => ['Bonal et les tribunes', 'Affluences, forteresse et déplacements : le peuple jaune et bleu en chiffres.', 9],
        'calendrier' => ['Saisons et calendrier', 'Les saisons en or, les mois fastes et les jours sans.', 7],
        'portraits' => ['Les Lions en portrait', 'Âges, tailles, pieds et origines des Lionceaux.', 9],
    ];

    /**
     * Ordre de priorité des chiffres de chaque chapitre : les premiers (jusqu'au quota) sont
     * affichés, les suivants servent de réserve si un chiffre manque.
     */
    public const ORDER = [
        'monuments' => ['buteur', 'matchs', 'saisons', 'buteur-d1', 'saison-record', 'ratio', 'buteur-cdf', 'buteur-europe', 'buteur-d2', 'cent-buts', 'trois-cents',
            'milieu-buteur', 'matchs-d1', 'matchs-europe', 'defenseur-buteur', 'gardien-matchs', 'buteur-cdl'],
        'buteurs' => ['quadruple', 'triples', 'penaltys', 'joker', 'serie-buteur', 'ouvreur', 'tete', 'csc', 'doubles', 'bourreau', 'saison-buteurs', 'buteurs-differents'],
        'chrono' => ['but-rapide', 'but-tardif', 'minute-fetiche', 'dernier-quart', 'remontada', 'remontadas', 'victoire-tardive', 'egalisation-tardive', 'ouverture', 'mene',
            'avance-perdue', 'seconde-periode', 'premier-quart'],
        'defense' => ['blanchissages', 'invincibilite-gardien', 'serie-clean', 'defenseur', 'defense-saison', 'blanchissages-saison', 'penalty-arrete', 'zero-zero', 'clean-pct'],
        'series' => ['invincibilite', 'victoires', 'bonal-victoires', 'bonal-invaincu', 'exterieur-invaincu', 'buts-consecutifs', 'defaites', 'sans-victoire',
            'exterieur-disette', 'muet', 'nuls'],
        'scores' => ['large-victoire', 'lourde-defaite', 'bilan', 'prolifique', 'festival', 'score-roi', 'un-zero', 'moyenne', 'tab', 'tab-record', 'nul-fou', 'prolongations'],
        'acteurs' => ['passeur', 'capitaine', 'minutes', 'inusable', 'entraineur', 'entraineur-victoires', 'jaunes', 'rouges', 'coequipiers', 'remplacant', 'duo', 'banc',
            'tous-les-matchs'],
        'adversaires' => ['adversaire', 'victime', 'bete-noire', 'meilleur-bilan', 'pire-bilan', 'derbys', 'petits-poucets', 'europe', 'arbitre', 'buts-contre',
            'adversaires-differents'],
        'bonal' => ['affluence-record', 'affluence-bonal', 'moyenne-bonal', 'bilan-bonal', 'saison-public', 'spectateurs', 'affluence-faible', 'stades', 'stade-visite',
            'decennie-public'],
        'calendrier' => ['saison-or', 'saison-noire', 'attaque-saison', 'saison-marathon', 'mois', 'mois-maudit', 'jour', 'date-fetiche', 'decennie'],
        'portraits' => ['plus-jeune', 'doyen', 'jeune-buteur', 'onze-jeune', 'geant', 'gauchers', 'enfants-du-pays', 'pays', 'formes', 'vieux-buteur', 'internationaux',
            'prenom', 'petit', 'ville'],
    ];

    /** Sources (badge de chaque chiffre). */
    public const SOURCES = [
        'carrieres' => 'Carrières',
        'matchs' => 'Matchs racontés',
        'recits' => 'Récits des matchs',
        'fiches' => 'Fiches des Lions',
    ];

    /** Données préparées (tests : on peut fournir son propre contexte). */
    public static ?array $context = null;
    private static array $people = [];
    private static ?array $clubNames = null;

    // ------------------------------------------------------------------ lecture

    /** Les chiffres de la page (en cache, refaits quand les données calculées changent). */
    public static function all(): array
    {
        $file = sprintf(self::CACHE, I18n::lang());
        $sig = self::signature();
        $c = JsonStore::read($file);
        if (is_array($c) && ($c['sig'] ?? '') === $sig && ($c['v'] ?? 0) === self::V) {
            return $c;
        }
        $c = self::build() + ['sig' => $sig, 'v' => self::V];
        JsonStore::write($file, $c);
        return $c;
    }

    /**
     * Les chiffres sans faire attendre (page d'accueil) : la version en cache, même périmée ;
     * un cache absent ou périmé est refait après l'envoi de la page.
     */
    public static function cached(): ?array
    {
        $c = JsonStore::read(sprintf(self::CACHE, I18n::lang()));
        $ok = is_array($c) && ($c['v'] ?? 0) === self::V && !empty($c['chapters']);
        if (!$ok || ($c['sig'] ?? '') !== self::signature()) {
            self::refreshLater();
        }
        return $ok ? $c : null;
    }

    private static bool $later = false;

    private static function refreshLater(): void
    {
        if (self::$later || PHP_SAPI === 'cli') {
            return;
        }
        self::$later = true;
        $lang = I18n::lang();
        register_shutdown_function(function () use ($lang) {
            \App\Core\Response::detach();
            @set_time_limit(120);
            I18n::set($lang);
            self::all();
        });
    }

    /**
     * Le chiffre du jour : un chiffre par jour, dans un ordre mélangé à chaque cycle, sans
     * répétition avant d'avoir montré tous les chiffres.
     *
     * @return array{stat:array, chapter:string, count:int}|null
     */
    public static function daily(?string $date = null, ?array $all = null): ?array
    {
        $all ??= self::cached();
        $list = [];
        foreach ($all['chapters'] ?? [] as $ch) {
            foreach ($ch['stats'] as $s) {
                $list[] = ['stat' => $s, 'chapter' => $ch['title']];
            }
        }
        $n = count($list);
        if (!$n) {
            return null;
        }
        $day = intdiv((int) strtotime(($date ?? date('Y-m-d')) . ' 12:00:00 UTC'), 86400);
        $order = (new \Random\Randomizer(new \Random\Engine\Mt19937(1928 + intdiv($day, $n))))->shuffleArray(range(0, $n - 1));
        return $list[$order[$day % $n]] + ['count' => $n];
    }

    /**
     * Calcule d'avance les chiffres périmés, dans chaque langue du site (tâche planifiée).
     * @return int nombre de langues recalculées
     */
    public static function warm(): int
    {
        $prev = I18n::lang();
        $n = 0;
        try {
            foreach (I18n::enabled() as $lang) {
                I18n::set($lang);
                self::$people = [];
                $c = JsonStore::read(sprintf(self::CACHE, $lang));
                if (!is_array($c) || ($c['sig'] ?? '') !== self::signature() || ($c['v'] ?? 0) !== self::V) {
                    self::all();
                    $n++;
                }
            }
        } finally {
            I18n::set($prev);
            self::$people = [];
        }
        return $n;
    }

    private static function signature(): string
    {
        $files = [STORAGE_PATH . '/cache/derived.php', \App\Data\Index::CACHE];
        if (I18n::lang() !== I18n::DEFAULT) {
            $files[] = DATA_PATH . '/i18n/' . I18n::lang() . '.json';
        }
        return implode('-', array_map(fn ($f) => is_file($f) ? (string) filemtime($f) : '0', $files));
    }

    /** Calcul complet : chapitres, chiffres numérotés de 1 à 100, périmètre des sources. */
    public static function build(): array
    {
        self::$people = []; // les adresses des fiches dépendent de la langue
        $c = self::$context ?? self::context();
        $lists = [];
        foreach (self::CHAPTERS as $key => $ch) {
            $lists[$key] = self::ordered($key, array_filter(self::$key($c)));
        }
        $take = self::quotas(array_map('count', $lists), array_map(fn ($ch) => $ch[2], self::CHAPTERS), self::COUNT);
        $chapters = [];
        $n = 0;
        foreach (self::CHAPTERS as $key => [$title, $intro]) {
            $stats = [];
            foreach (array_slice($lists[$key], 0, $take[$key]) as $s) {
                $s['n'] = ++$n;
                $s['src_label'] = t(self::SOURCES[$s['src']] ?? $s['src']);
                $stats[] = $s;
            }
            if ($stats) {
                $chapters[] = ['key' => $key, 'title' => t($title), 'intro' => t($intro), 'stats' => $stats];
            }
        }
        return ['built' => date('c'), 'count' => $n, 'chapters' => $chapters, 'scope' => $c['scope']];
    }

    /** Chiffres d'un chapitre dans l'ordre de priorité (les clés inconnues à la fin). */
    public static function ordered(string $chapter, array $stats): array
    {
        $rank = array_flip(self::ORDER[$chapter] ?? []);
        uksort($stats, fn ($a, $b) => ($rank[$a] ?? 999) <=> ($rank[$b] ?? 999));
        return array_values($stats);
    }

    /**
     * Nombre de chiffres retenus par chapitre : le quota, et si un chapitre n'a pas assez de
     * chiffres, les chiffres de réserve des autres chapitres (dans l'ordre) comblent le manque.
     *
     * @param array<string,int> $available chiffres calculés par chapitre
     * @param array<string,int> $quota
     * @return array<string,int>
     */
    public static function quotas(array $available, array $quota, int $total): array
    {
        $take = [];
        foreach ($quota as $k => $q) {
            $take[$k] = min($q, $available[$k] ?? 0);
        }
        $missing = $total - array_sum($take);
        while ($missing > 0) {
            $added = false;
            foreach ($quota as $k => $q) {
                if ($missing > 0 && ($available[$k] ?? 0) > $take[$k]) {
                    $take[$k]++;
                    $missing--;
                    $added = true;
                }
            }
            if (!$added) {
                break;
            }
        }
        return $take;
    }

    /** Tous les chiffres à plat (tests, recherche). */
    public static function flat(?array $all = null): array
    {
        $out = [];
        foreach (($all ?? self::all())['chapters'] as $ch) {
            foreach ($ch['stats'] as $s) {
                $out[$s['key']] = $s + ['chapter' => $ch['key']];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ préparation

    /** Données préparées une fois pour tous les chapitres. */
    public static function context(): array
    {
        $d = Derived::get();
        $M = $d['matches'];
        // Matchs officiels datés, dans l'ordre chronologique.
        $O = [];
        foreach ($M as $mid => $x) {
            if ($x['v'] && $x['date'] && !in_array($x['comp'], Derived::OFFICIAL_EXCLUDED, true)) {
                $O[$mid] = $x;
            }
        }
        uasort($O, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: $a['id'] <=> $b['id']);

        // Saisons racontées en entier, regroupées en blocs de saisons qui se suivent.
        $league = [];
        foreach ($O as $x) {
            if ($x['comp'] === 'Championnat' && $x['season']) {
                $league[$x['season']] = ($league[$x['season']] ?? 0) + 1;
            }
        }
        $full = array_keys(array_filter($league, fn ($n) => $n >= self::FULL_SEASON));
        sort($full);
        // La saison en cours compte dès qu'elle suit une saison racontée en entier.
        $cur = Explore::currentSeason();
        if ($full && !in_array($cur, $full, true) && (int) substr((string) end($full), 0, 4) === (int) substr($cur, 0, 4) - 1) {
            $full[] = $cur;
        }
        $blockOf = [];
        $b = -1;
        $prev = null;
        foreach ($full as $s) {
            $y = (int) substr($s, 0, 4);
            if ($prev === null || $y !== $prev + 1) {
                $b++;
            }
            $blockOf[$s] = $b;
            $prev = $y;
        }
        $blocks = [];
        foreach ($O as $mid => $x) {
            if (isset($blockOf[$x['season']])) {
                $blocks[$blockOf[$x['season']]][] = $mid;
            }
        }

        // Compositions douteuses (tableau recopié d'un autre match, buteurs qui ne collent pas
        // au score) : écartées des chiffres tirés des compositions. Dates douteuses : écartées
        // des âges.
        $unsure = $badDate = [];
        foreach ($d['quality'] as $q) {
            if (in_array($q['code'], ['tableau', 'buts'], true)) {
                $unsure[$q['id']] = true;
            } elseif ($q['code'] === 'date' && $q['sev'] !== 'basse') {
                $badDate[$q['id']] = true; // (jour de la semaine seul faux : la date reste sûre)
            }
        }
        $apps = [];
        foreach ($d['apps'] as $a) {
            if (isset($O[$a[1]]) && !isset($unsure[$a[1]])) {
                $apps[] = $a;
            }
        }

        // Fiches : personnes (naissance, taille, carrière) et détails des matchs officiels.
        $P = [];
        $X = [];
        $periods = [];
        $tables = [];
        foreach (Fiches::all() as $id => $doc) {
            if (($doc['status'] ?? '') === 'corbeille') {
                continue;
            }
            if ($doc['type'] === 'personne') {
                $pp = $doc['personne'];
                if (!empty($pp['stats']['rows'])) {
                    $tables[$id] = md5(json_encode($pp['stats']['rows']));
                }
                $periods[$id] = [isset($pp['arrival']['iso']) ? (int) substr((string) $pp['arrival']['iso'], 0, 4) : null,
                    isset($pp['departure']['iso']) ? (int) substr((string) $pp['departure']['iso'], 0, 4) : null];
                $P[$id] = [
                    'birth' => ($pp['birth']['date']['precision'] ?? '') === 'day' && !empty($pp['birth']['date']['iso']) ? substr((string) $pp['birth']['date']['iso'], 0, 10) : null,
                    'height' => (int) ($pp['height_cm'] ?? 0) ?: null,
                    'foot' => self::foot((string) ($pp['foot'] ?? '')),
                    'first' => trim((string) ($pp['first_name'] ?? '')),
                    'city' => trim((string) ($pp['birth']['place']['city'] ?? '')),
                    'dept' => trim((string) ($pp['birth']['place']['department'] ?? '')),
                    'country' => trim((string) ($pp['birth']['place']['country'] ?? '')),
                    'formed' => (bool) ($pp['formed_at_club'] ?? false),
                    'intl' => (bool) ($pp['international_flag'] ?? false),
                    'line' => $pp['line'] ?? null,
                    'player' => in_array('joueur', (array) ($pp['roles'] ?? []), true),
                    'career' => !empty($pp['stats']['rows']) ? self::career($pp['stats']) : null,
                ];
            } elseif ($doc['type'] === 'match' && isset($O[$id])) {
                $m = $doc['match'];
                $x = $O[$id];
                $seq = self::goalSequence($m);
                $X[$id] = [
                    'seq' => $seq,
                    'opp' => self::oppMinutes($m, $x, $seq),
                    'ref' => self::referee((string) ($m['referee'] ?? '')),
                    'pens' => isset($m['score']['pens']['home'], $m['score']['pens']['away'])
                        ? ($x['sh'] ? [(int) $m['score']['pens']['home'], (int) $m['score']['pens']['away']] : [(int) $m['score']['pens']['away'], (int) $m['score']['pens']['home']]) : null,
                    'aet' => (bool) ($m['score']['aet'] ?? false),
                    'lv' => $x['sh'] ? [$m['home']['level'] ?? null, $m['away']['level'] ?? null] : [$m['away']['level'] ?? null, $m['home']['level'] ?? null],
                    'csc' => self::ownGoalsFor($m),
                    'saves' => self::penaltySaves($m),
                ];
            }
        }

        $P = self::dropCopiedCareers($P, $periods, $tables);

        $first = $O ? reset($O)['date'] : null;
        $last = $O ? end($O)['date'] : null;
        $careers = array_filter($P, fn ($p) => !empty($p['career']['m']));
        $cFrom = $careers ? min(array_map(fn ($p) => $p['career']['from'], $careers)) : null;
        return [
            'M' => $M, 'O' => $O, 'blocks' => array_values($blocks), 'full' => $full, 'apps' => $apps,
            'scorers' => array_diff_key(array_intersect_key($d['scorers'] ?? [], $O), $unsure), 'P' => $P, 'X' => $X,
            'unsure' => $unsure, 'bad_date' => $badDate,
            'scope' => [
                'matches' => count($O), 'from' => $first, 'to' => $last, 'full' => count($full),
                'careers' => count($careers), 'careers_from' => $cFrom,
                'people' => count(array_filter($P, fn ($p) => $p['player'])),
            ],
        ];
    }

    /**
     * Tableaux de carrière recopiés d'une fiche à l'autre (modèle de l'ancien site) : le même
     * tableau sur plusieurs fiches n'est gardé que pour la seule fiche dont les dates d'arrivée
     * et de départ correspondent ; sinon il est écarté partout.
     *
     * @param array<int,array> $P personnes
     * @param array<int,array{0:?int,1:?int}> $periods arrivée et départ (années)
     * @param array<int,string> $tables empreinte du tableau de chaque fiche
     */
    public static function dropCopiedCareers(array $P, array $periods, array $tables): array
    {
        $by = [];
        foreach ($tables as $id => $sig) {
            $by[$sig][] = $id;
        }
        foreach ($by as $ids) {
            if (count($ids) < 2) {
                continue;
            }
            $match = array_values(array_filter($ids, fn ($id) => !empty($P[$id]['career']) && self::periodMatches($P[$id]['career'], $periods[$id] ?? [null, null])));
            foreach ($ids as $id) {
                if (isset($P[$id]) && !(count($match) === 1 && $match[0] === $id)) {
                    $P[$id]['career'] = null;
                }
            }
        }
        return $P;
    }

    /** Les saisons du tableau collent-elles aux dates d'arrivée et de départ de la fiche ? */
    public static function periodMatches(array $career, array $period): bool
    {
        [$arr, $dep] = $period;
        if ($arr === null && $dep === null) {
            return false;
        }
        return ($arr === null || ($career['from'] >= $arr - 2 && $career['from'] <= $arr + 3)) && ($dep === null || $career['to'] <= $dep + 2);
    }

    /**
     * Tableau de carrière d'une fiche joueur → totaux par compétition.
     * Colonnes : « Saisons », puis (compétition, Buts)…, « Total matches », « Total Buts ».
     */
    public static function career(array $stats): ?array
    {
        $cols = [];
        $comp = null;
        foreach ($stats['headers'] ?? [] as $i => $h) {
            if ($i === 0) {
                continue;
            }
            $h = mb_strtolower(trim((string) $h));
            if (preg_match('/^total\s+match/u', $h)) {
                $cols[$i] = ['tm', null];
            } elseif (preg_match('/^total\s+but/u', $h)) {
                $cols[$i] = ['tg', null];
            } elseif ($h === 'buts') {
                $cols[$i] = $comp ? ['g', $comp] : ['skip', null];
            } elseif (preg_match('/clean|encaiss/u', $h)) {
                $cols[$i] = ['skip', null];
                $comp = null;
            } else {
                $comp = self::compKey($h);
                $cols[$i] = ['m', $comp];
            }
        }
        $k = ['m' => 0, 'g' => 0, 'seasons' => 0, 'from' => null, 'to' => null, 'by' => [], 'best' => null];
        foreach ($stats['rows'] ?? [] as $r) {
            if (!preg_match('/^\s*(\d{3,4})\s*-\s*(\d{4})/', (string) ($r[0] ?? ''), $mm)) {
                continue;
            }
            $y = (int) $mm[2] - 1;
            $row = ['y' => $y, 'm' => 0, 'g' => 0];
            $tm = $tg = null;
            foreach ($cols as $i => [$type, $c]) {
                $v = trim((string) ($r[$i] ?? ''));
                if (!preg_match('/^\d+$/', $v)) {
                    continue;
                }
                $v = (int) $v;
                if ($type === 'm') {
                    $k['by'][$c]['m'] = ($k['by'][$c]['m'] ?? 0) + $v;
                    $row['m'] += $v;
                } elseif ($type === 'g') {
                    $k['by'][$c]['g'] = ($k['by'][$c]['g'] ?? 0) + $v;
                    $row['g'] += $v;
                } elseif ($type === 'tm') {
                    $tm = $v;
                } elseif ($type === 'tg') {
                    $tg = $v;
                }
            }
            // Le total de la ligne fait foi quand il est renseigné.
            $row['m'] = $tm ?? $row['m'];
            $row['g'] = $tg ?? $row['g'];
            // Saison au club : un chiffre, ou « ? » (statistiques perdues, comme en 1945-1946).
            $unknown = (bool) array_filter(array_slice($r, 1), fn ($v) => trim((string) $v) === '?');
            if ($row['m'] <= 0 && $row['g'] <= 0 && !$unknown) {
                continue;
            }
            $k['m'] += $row['m'];
            $k['g'] += $row['g'];
            $k['seasons']++;
            $k['from'] = $k['from'] === null ? $y : min($k['from'], $y);
            $k['to'] = $k['to'] === null ? $y + 1 : max($k['to'], $y + 1);
            if (!$k['best'] || $row['g'] > $k['best']['g']) {
                $k['best'] = $row;
            }
        }
        return $k['m'] > 0 || $k['g'] > 0 ? $k : null;
    }

    /** Compétition d'une colonne de tableau de carrière. */
    public static function compKey(string $h): string
    {
        $h = Names::ascii($h);
        return match (true) {
            (bool) preg_match('/barrage/', $h) => 'barrages',
            (bool) preg_match('/coupe de france/', $h) => 'cdf',
            (bool) preg_match('/coupe de la ligue/', $h) => 'cdl',
            (bool) preg_match('/uefa|intertoto|europa|ligue des champions|champions league|coupe des (coupes|clubs|villes)/', $h) => 'europe',
            (bool) preg_match('/drago/', $h) => 'drago',
            (bool) preg_match('/^(division ?2|ligue 2|d2)\b/', $h) => 'd2',
            (bool) preg_match('/^(division ?1|ligue 1|d1|championnat de france)/', $h) => 'd1',
            (bool) preg_match('/national/', $h) => 'national',
            default => 'autre',
        };
    }

    /**
     * Buts d'un match minute par minute, d'après les temps forts (« (1-0) ») : seulement si la
     * suite est cohérente (un but à la fois, minutes croissantes, score final retrouvé).
     *
     * @return list<array{min:int,add:int,us:bool,text:string}>|null
     */
    public static function goalSequence(array $m): ?array
    {
        if (!isset($m['score']['home'], $m['score']['away'])) {
            return null;
        }
        $sh = (bool) ($m['sochaux_home'] ?? true);
        $h = $a = 0;
        $prev = -1;
        $out = [];
        foreach ($m['highlights'] ?? [] as $hl) {
            if (empty($hl['goal'])) {
                continue;
            }
            if (!preg_match('/^(\d+)-(\d+)$/', (string) ($hl['score'] ?? ''), $s) || !($t = self::minute((string) ($hl['minute'] ?? '')))) {
                return null;
            }
            [$nh, $na] = [(int) $s[1], (int) $s[2]];
            $home = $nh === $h + 1 && $na === $a;
            if (!$home && !($na === $a + 1 && $nh === $h)) {
                return null;
            }
            if ($t[0] * 100 + $t[1] < $prev) {
                return null;
            }
            $prev = $t[0] * 100 + $t[1];
            [$h, $a] = [$nh, $na];
            $out[] = ['min' => $t[0], 'add' => $t[1], 'us' => $home === $sh, 'text' => (string) ($hl['text'] ?? '')];
        }
        if ($h !== (int) $m['score']['home'] || $a !== (int) $m['score']['away']) {
            return null;
        }
        return $out;
    }

    /** « 45+2 » → [45, 2] ; « 90 » → [90, 0] ; minute impossible → null. */
    public static function minute(string $s): ?array
    {
        if (!preg_match('/^\s*(\d{1,3})\s*(?:\'|’)?\s*(?:\+\s*(\d{1,2}))?\s*(?:\'|’)?\s*$/u', $s, $m)) {
            return null;
        }
        $base = (int) $m[1];
        return $base >= 1 && $base <= 130 ? [$base, (int) ($m[2] ?? 0)] : null;
    }

    /** Minutes des buts encaissés (temps forts, sinon ligne des buteurs adverses), null si inconnues. */
    private static function oppMinutes(array $m, array $x, ?array $seq): ?array
    {
        if ($x['them'] === null) {
            return null;
        }
        if ((int) $x['them'] === 0) {
            return [];
        }
        if ($seq !== null) {
            return array_values(array_map(fn ($g) => $g['min'], array_filter($seq, fn ($g) => !$g['us'])));
        }
        foreach ($m['goals'] ?? [] as $g) {
            if (preg_match('/sochaux/iu', (string) ($g['team'] ?? ''))) {
                continue;
            }
            // « 45+1' » compte pour la 45e minute (et non la 1re).
            preg_match_all('/(\d{1,3})(?:\s*(?:\'|’)?\s*\+\s*\d{1,2})?\s*(?:\'|’)/u', (string) ($g['scorers'] ?? ''), $mm);
            $mins = array_map('intval', $mm[1]);
            if (count($mins) === (int) $x['them']) {
                sort($mins);
                return $mins;
            }
        }
        return null;
    }

    /** Buts contre leur camp des adversaires, comptés pour Sochaux. */
    private static function ownGoalsFor(array $m): int
    {
        $n = 0;
        foreach ($m['goals'] ?? [] as $g) {
            if (preg_match('/sochaux/iu', (string) ($g['team'] ?? ''))) {
                $n += preg_match_all('/c\s*\.?\s*s\s*\.?\s*c|contre son camp/iu', (string) ($g['scorers'] ?? ''));
            }
        }
        return $n;
    }

    /** Temps forts racontant un penalty arrêté (attribué ensuite au gardien sochalien nommé). */
    private static function penaltySaves(array $m): array
    {
        $out = [];
        foreach ($m['highlights'] ?? [] as $h) {
            $t = Names::ascii((string) ($h['text'] ?? ''));
            if (empty($h['goal']) && preg_match('/penalty|peno/', $t) && preg_match('/\b(arrete|arret|detourne|repousse|stoppe|sort le|capte|claque|devine)/', $t)
                && !preg_match('/tirs? au but|seance/', $t)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    private static function referee(string $r): ?string
    {
        $r = trim(preg_replace('/\s+/u', ' ', $r));
        if (mb_strlen($r) < 4 || preg_match('/^(x+|\?+|-+|inconnu|nc|n\.c\.?)$/iu', $r) || !preg_match('/\p{L}{3}/u', $r)) {
            return null;
        }
        return $r;
    }

    private static function foot(string $f): ?string
    {
        $f = Names::ascii($f);
        return match (true) {
            str_starts_with($f, 'gauch') || $f === 'gauche' => 'g',
            str_starts_with($f, 'droit') => 'd',
            str_starts_with($f, 'ambi') => 'a',
            default => null,
        };
    }

    // ------------------------------------------------------------------ outils

    /** Nombre dans la langue de la page : 1 234 / 1,234 ; 0,74 / 0.74. */
    public static function num(int|float $n, int $dec = 0): string
    {
        return I18n::isEn() ? number_format($n, $dec, '.', ',') : number_format($n, $dec, ',', "\u{00A0}");
    }

    /** Pourcentage : 54 % / 54%. */
    public static function pct(float $ratio, int $dec = 0): string
    {
        return self::num($ratio * 100, $dec) . (I18n::isEn() ? '%' : "\u{00A0}%");
    }

    private static function unit(int|float $n, string $one, string $many, array $vars = []): string
    {
        return t(abs($n) >= 2 ? $many : $one, $vars);
    }

    /** Âge en années et jours entre deux dates ISO. */
    public static function age(string $birth, string $date): array
    {
        $b = new \DateTimeImmutable($birth);
        $diff = $b->diff(new \DateTimeImmutable($date));
        $last = $b->modify('+' . $diff->y . ' years');
        return [$diff->y, (int) $last->diff(new \DateTimeImmutable($date))->days];
    }

    private static function ageUnit(array $a): string
    {
        return $a[1] >= 2 ? t('ans et {d} jours', ['d' => $a[1]]) : ($a[1] === 1 ? t('ans et 1 jour') : t('ans pile'));
    }

    /** Rang d'une minute : « 1re », « 88e » / « 1st », « 88th ». */
    public static function nth(int $n): string
    {
        return I18n::isEn() ? ordinal($n) : ($n === 1 ? '1re' : $n . 'e');
    }

    private static function months(): array
    {
        return I18n::isEn()
            ? ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
            : ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    }

    private static function weekdays(): array
    {
        return I18n::isEn()
            ? ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
            : ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    }

    /** « 12 août » / « August 12 » à partir de « 08-12 ». */
    private static function dayMonth(string $md): string
    {
        [$mo, $da] = array_map('intval', explode('-', $md));
        $name = self::months()[$mo - 1];
        return I18n::isEn() ? "$name $da" : ($da === 1 ? '1er' : (string) $da) . " $name";
    }

    /** Pastille d'une personne (fiche publiée), null sinon. */
    private static function person(int $pid): ?array
    {
        if (!array_key_exists($pid, self::$people)) {
            $s = Index::get($pid);
            self::$people[$pid] = $s && $s['type'] === 'personne' && Index::visible($s)
                ? ['id' => $pid, 'name' => $s['p']['name'], 'href' => url($s['path']), 'image' => $s['image'], 'last' => $s['p']['last'] ?: $s['p']['name']]
                : null;
        }
        return self::$people[$pid];
    }

    /** Pastille d'un match. */
    private static function match(array $x): array
    {
        return ['name' => Site::matchLabel($x), 'href' => url($x['path']), 'image' => $x['image'], 'meta' => date_fr($x['date'])];
    }

    private static function clubName(string $id): string
    {
        if (self::$clubNames === null) {
            self::$clubNames = [];
            foreach (Collections::get('clubs', []) as $cl) {
                self::$clubNames[$cl['id']] = $cl['name'];
            }
        }
        return self::$clubNames[$id] ?? ucwords(str_replace('-', ' ', $id));
    }

    private static function club(string $id): array
    {
        return ['name' => self::clubName($id), 'href' => url('/face-a-face/' . $id . '/'), 'image' => null];
    }

    /** Score vu de Sochaux : « 3-1 ». */
    private static function score(array $x): string
    {
        return $x['us'] . '-' . $x['them'];
    }

    /** Adversaire d'un match (nom du club). */
    private static function opp(array $x): string
    {
        return $x['club'] ? self::clubName((string) $x['club']) : (string) $x['opp'];
    }

    private static function stat(string $key, string $value, string $unit, string $label, string $text, array $who = [], string $src = 'matchs', array $more = []): array
    {
        return ['key' => $key, 'value' => $value, 'unit' => $unit, 'label' => $label, 'text' => $text,
            'who' => array_values(array_filter($who)), 'src' => $src, 'more' => $more];
    }

    /**
     * Classement de personnes publiées : [pid => valeur] → [[pastille, valeur], …].
     * Ex aequo : la plus petite valeur de départage ($tie, facultatif) passe devant.
     */
    private static function rank(array $vals, int $n = 5, bool $asc = false, array $tie = []): array
    {
        $keys = array_keys($vals);
        usort($keys, fn ($a, $b) => ($asc ? $vals[$a] <=> $vals[$b] : $vals[$b] <=> $vals[$a]) ?: (($tie[$a] ?? 0) <=> ($tie[$b] ?? 0)) ?: $a <=> $b);
        $out = [];
        foreach ($keys as $pid) {
            if ($p = self::person((int) $pid)) {
                $out[] = [$p, $vals[$pid]];
                if (count($out) >= $n) {
                    break;
                }
            }
        }
        return $out;
    }

    /** Suivants d'un classement (« Suivent : … »). */
    private static function more(array $rank, callable $fmt): array
    {
        return array_map(fn ($r) => ['name' => $r[0]['name'], 'href' => $r[0]['href'], 'v' => $fmt($r[1])], array_slice($rank, 1));
    }

    /** « A, B et C » / « A, B and C ». */
    private static function andList(array $items): string
    {
        $last = array_pop($items);
        return $items ? implode(', ', $items) . ' ' . t('et') . ' ' . $last : (string) $last;
    }

    /** Bilan V/N/D d'une liste de matchs. */
    private static function record(array $list): array
    {
        $r = ['v' => 0, 'n' => 0, 'd' => 0, 'm' => 0, 'gf' => 0, 'ga' => 0];
        foreach ($list as $x) {
            if (!$x['result']) {
                continue;
            }
            $r['m']++;
            $r[strtolower($x['result'])]++;
            $r['gf'] += (int) $x['us'];
            $r['ga'] += (int) $x['them'];
        }
        return $r;
    }

    /** « 12 victoires, 5 nuls et 3 défaites » ; $skipZero : sans les catégories vides. */
    private static function vnd(array $r, bool $skipZero = false): string
    {
        $parts = [];
        foreach (['v' => ['{n} victoire', '{n} victoires'], 'n' => ['{n} nul', '{n} nuls'], 'd' => ['{n} défaite', '{n} défaites']] as $k => [$one, $many]) {
            if (!$skipZero || $r[$k] > 0) {
                $parts[] = self::unit($r[$k], $one, $many, ['n' => self::num($r[$k])]);
            }
        }
        $last = array_pop($parts);
        return $parts ? implode(', ', $parts) . ' ' . t('et') . ' ' . $last : (string) $last;
    }

    /**
     * Plus longue série de matchs consécutifs vérifiant $ok, dans chaque bloc de saisons
     * racontées en entier. À longueur égale, la plus récente.
     *
     * @param list<list<int>> $blocks
     * @return list<int> identifiants des matchs de la série
     */
    public static function longestRun(array $blocks, callable $ok): array
    {
        $best = [];
        foreach ($blocks as $ids) {
            $cur = [];
            foreach ($ids as $id) {
                if ($ok($id)) {
                    $cur[] = $id;
                    if (count($cur) >= count($best)) {
                        $best = $cur;
                    }
                } else {
                    $cur = [];
                }
            }
        }
        return $best;
    }

    private static function runStat(array $c, string $key, string $label, string $one, string $many, callable $ok, ?callable $filter = null): ?array
    {
        $blocks = $filter ? array_map(fn ($ids) => array_values(array_filter($ids, fn ($id) => $filter($c['O'][$id]))), $c['blocks']) : $c['blocks'];
        $run = self::longestRun($blocks, fn ($id) => $ok($c['O'][$id]));
        if (count($run) < 2) {
            return null;
        }
        $a = $c['O'][$run[0]];
        $b = $c['O'][end($run)];
        $r = self::record(array_map(fn ($id) => $c['O'][$id], $run));
        $kinds = count(array_filter([$r['v'], $r['n'], $r['d']]));
        return self::stat($key, self::num(count($run)), self::unit(count($run), $one, $many), t($label),
            $kinds >= 2 ? t('Du {a} au {b} : {vnd}.', ['a' => date_fr($a['date']), 'b' => date_fr($b['date']), 'vnd' => self::vnd($r, true)])
                : t('Du {a} au {b}.', ['a' => date_fr($a['date']), 'b' => date_fr($b['date'])]), [self::match($a)]);
    }

    /** Apparitions officielles groupées par joueur : pid => [mid => app]. */
    private static function byPlayer(array $c, bool $starters = false): array
    {
        $out = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && (!$starters || $a[8] !== 'R')) {
                $out[$a[0]][$a[1]] = $a;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ 1. Les monuments

    private static function monuments(array $c): array
    {
        $car = [];
        foreach ($c['P'] as $pid => $p) {
            if (!empty($p['career']['m']) && self::person((int) $pid)) {
                $car[$pid] = $p['career'];
            }
        }
        $top = function (callable $f, ?callable $only = null) use ($car, $c): array {
            $v = [];
            $tie = [];
            foreach ($car as $pid => $k) {
                if ($only && !$only($c['P'][$pid])) {
                    continue;
                }
                $x = $f($k);
                if ($x > 0) {
                    $v[$pid] = $x;
                    $tie[$pid] = -$k['m'];
                }
            }
            return self::rank($v, 5, false, $tie);
        };
        $span = fn (array $k) => $k['to'] - $k['from'] <= 1 ? t('en {s}', ['s' => $k['from'] . '-' . $k['to']]) : t('de {a} à {b}', ['a' => $k['from'], 'b' => $k['to']]);
        $goals = fn ($v) => self::num($v);
        $out = [];

        $compTop = function (string $key, string $comp, string $label, string $text) use ($top, $car, $goals): ?array {
            $r = $top(fn ($k) => $k['by'][$comp]['g'] ?? 0);
            if (!$r) {
                return null;
            }
            [$p, $g] = $r[0];
            return self::stat($key, self::num($g), self::unit($g, 'but', 'buts'), t($label),
                t($text, ['m' => self::num($car[$p['id']]['by'][$comp]['m'] ?? 0)]), [$p], 'carrieres', self::more($r, $goals));
        };

        if ($r = $top(fn ($k) => $k['g'])) {
            [$p, $g] = $r[0];
            $k = $car[$p['id']];
            $out['buteur'] = self::stat('buteur', self::num($g), self::unit($g, 'but', 'buts'), t('Le meilleur buteur de l’histoire'),
                t('En {m} matchs officiels, {span}.', ['m' => self::num($k['m']), 'span' => $span($k)]), [$p], 'carrieres', self::more($r, $goals));
        }
        if ($r = $top(fn ($k) => $k['m'])) {
            [$p, $m] = $r[0];
            $k = $car[$p['id']];
            $out['matchs'] = self::stat('matchs', self::num($m), self::unit($m, 'match', 'matchs'), t('Le recordman des matchs'),
                t('{s} saisons, {span}.', ['s' => $k['seasons'], 'span' => $span($k)]), [$p], 'carrieres', self::more($r, $goals));
        }
        // La plus longue fidélité, si elle n'appartient pas déjà au recordman des matchs.
        if (($r = $top(fn ($k) => $k['seasons'])) && $r[0][0]['id'] !== ($out['matchs']['who'][0]['id'] ?? null)) {
            [$p, $s] = $r[0];
            $k = $car[$p['id']];
            $out['saisons'] = self::stat('saisons', self::num($s), self::unit($s, 'saison', 'saisons'), t('La plus longue fidélité'),
                t('{m} matchs officiels, {span}.', ['m' => self::num($k['m']), 'span' => $span($k)]), [$p], 'carrieres', self::more($r, $goals));
        }
        $out['buteur-d1'] = $compTop('buteur-d1', 'd1', 'Le meilleur buteur en première division', 'En {m} matchs de Division 1 et de Ligue 1.');
        if ($r = $top(fn ($k) => $k['best']['g'] ?? 0)) {
            [$p, $g] = $r[0];
            $b = $car[$p['id']]['best'];
            $out['saison-record'] = self::stat('saison-record', self::num($g), self::unit($g, 'but', 'buts'), t('Le plus de buts en une saison'),
                t('Saison {s}, en {m} matchs officiels.', ['s' => $b['y'] . '-' . ($b['y'] + 1), 'm' => self::num($b['m'])]), [$p], 'carrieres',
                self::more($r, fn ($v) => self::num($v)));
        }
        $ratio = [];
        foreach ($car as $pid => $k) {
            if ($k['m'] >= 50 && $k['g'] > 0) {
                $ratio[$pid] = round($k['g'] / $k['m'], 3);
            }
        }
        if ($r = self::rank($ratio, 4)) {
            [$p, $v] = $r[0];
            $k = $car[$p['id']];
            $out['ratio'] = self::stat('ratio', self::num($v, 2), t('but par match'), t('Le meilleur ratio buts par match'),
                t('{g} buts en {m} matchs officiels (50 au moins).', ['g' => self::num($k['g']), 'm' => self::num($k['m'])]), [$p], 'carrieres',
                self::more($r, fn ($v) => self::num($v, 2)));
        }
        $out['buteur-cdf'] = $compTop('buteur-cdf', 'cdf', 'Le roi de la Coupe de France', 'En {m} matchs de Coupe de France.');
        $out['buteur-europe'] = $compTop('buteur-europe', 'europe', 'Le buteur des soirées européennes', 'En {m} matchs de coupe d’Europe.');
        $out['buteur-d2'] = $compTop('buteur-d2', 'd2', 'Le meilleur buteur en deuxième division', 'En {m} matchs de Division 2 et de Ligue 2.');
        if ($r = $top(fn ($k) => $k['by']['d1']['m'] ?? 0)) {
            [$p, $m] = $r[0];
            $out['matchs-d1'] = self::stat('matchs-d1', self::num($m), self::unit($m, 'match', 'matchs'), t('Le plus de matchs en première division'),
                t('{span}, en Division 1 et en Ligue 1.', ['span' => ucfirst($span($car[$p['id']]))]), [$p], 'carrieres', self::more($r, $goals));
        }
        foreach ([['cent-buts', 'g', 100, 'Le club des 100 buts', 'Le seul Lionceau à avoir marqué 100 buts officiels ou plus.', 'Les seuls Lionceaux à avoir marqué 100 buts officiels ou plus.'],
            ['trois-cents', 'm', 300, 'Le cercle des 300 matchs', 'Le seul Lionceau à avoir disputé 300 matchs officiels ou plus.', 'Les Lionceaux qui ont disputé 300 matchs officiels ou plus.']] as [$key, $f, $min, $label, $one, $many]) {
            $v = array_map(fn ($k) => $k[$f], array_filter($car, fn ($k) => $k[$f] >= $min));
            if ($r = self::rank($v, 30)) {
                $out[$key] = self::stat($key, self::num(count($r)), self::unit(count($r), 'joueur', 'joueurs'), t($label),
                    self::unit(count($r), $one, $many), array_map(fn ($x) => $x[0], array_slice($r, 0, 3)), 'carrieres',
                    self::more(array_merge([[null]], array_slice($r, 3)), $goals));
            }
        }
        foreach ([['defenseur-buteur', 'D', 'Le défenseur le plus buteur'], ['milieu-buteur', 'M', 'Le milieu le plus buteur']] as [$key, $line, $label]) {
            if ($r = $top(fn ($k) => $k['g'], fn ($p) => $p['line'] === $line)) {
                [$p, $g] = $r[0];
                $k = $car[$p['id']];
                $out[$key] = self::stat($key, self::num($g), self::unit($g, 'but', 'buts'), t($label),
                    t('En {m} matchs officiels, {span}.', ['m' => self::num($k['m']), 'span' => $span($k)]), [$p], 'carrieres', self::more($r, $goals));
            }
        }
        if ($r = $top(fn ($k) => $k['m'], fn ($p) => $p['line'] === 'G')) {
            [$p, $m] = $r[0];
            $out['gardien-matchs'] = self::stat('gardien-matchs', self::num($m), self::unit($m, 'match', 'matchs'), t('Le gardien le plus capé'),
                t('{s} saisons dans les buts, {span}.', ['s' => $car[$p['id']]['seasons'], 'span' => $span($car[$p['id']])]), [$p], 'carrieres', self::more($r, $goals));
        }
        if ($r = $top(fn ($k) => $k['by']['europe']['m'] ?? 0)) {
            [$p, $m] = $r[0];
            $out['matchs-europe'] = self::stat('matchs-europe', self::num($m), self::unit($m, 'match européen', 'matchs européens'), t('L’Européen'),
                t('Le plus de matchs de coupe d’Europe sous le maillot sochalien.'), [$p], 'carrieres', self::more($r, $goals));
        }
        $out['buteur-cdl'] = $compTop('buteur-cdl', 'cdl', 'Le roi de la Coupe de la Ligue', 'En {m} matchs de Coupe de la Ligue.');
        return $out;
    }

    // ------------------------------------------------------------------ 2. Les buteurs

    private static function buteurs(array $c): array
    {
        $O = $c['O'];
        $out = [];
        // Buts par joueur et par match (compositions).
        $pm = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && $a[2] > 0) {
                $pm[] = $a;
            }
        }
        if ($pm) {
            usort($pm, fn ($a, $b) => $b[2] <=> $a[2] ?: strcmp($O[$b[1]]['date'], $O[$a[1]]['date']));
            $max = $pm[0][2];
            $same = array_values(array_filter($pm, fn ($a) => $a[2] === $max && self::person($a[0])));
            if ($same) {
                $a = $same[0];
                $x = $O[$a[1]];
                $out['quadruple'] = self::stat('quadruple', self::num($max), self::unit($max, 'but', 'buts'), t('Le plus de buts dans un match'),
                    t('Contre {opp}, le {date}.', ['opp' => self::opp($x), 'date' => date_fr($x['date'])]) . (count($same) > 1 ? ' ' . t('Exploit réalisé {k} fois dans les matchs racontés.', ['k' => count($same)]) : ''),
                    [self::person($a[0]), self::match($x)]);
            }
            $hat = array_filter($pm, fn ($a) => $a[2] >= 3);
            $per = [];
            foreach ($hat as $a) {
                $per[$a[0]] = ($per[$a[0]] ?? 0) + 1;
            }
            if ($hat && ($r = self::rank($per, 4))) {
                $out['triples'] = self::stat('triples', self::num(count($hat)), self::unit(count($hat), 'coup du chapeau', 'coups du chapeau'), t('Les coups du chapeau'),
                    t('Trois buts ou plus dans un match officiel ; le spécialiste en compte {k}.', ['k' => $r[0][1]]), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
            }
            $two = [];
            foreach ($pm as $a) {
                if ($a[2] === 2) {
                    $two[$a[0]] = ($two[$a[0]] ?? 0) + 1;
                }
            }
            if ($r = self::rank($two, 4)) {
                $out['doubles'] = self::stat('doubles', self::num(array_sum($two)), self::unit(array_sum($two), 'doublé', 'doublés'), t('Les doublés'),
                    t('Deux buts dans le même match ; le recordman en a signé {k}.', ['k' => $r[0][1]]), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
            }
        }
        // Penaltys, ouvertures du score (minutes des buteurs).
        $pens = [];
        $opener = [];
        foreach ($c['scorers'] as $mid => $list) {
            $first = null;
            foreach ($list as [$pid, $name, $mins, $p]) {
                if ($pid && $p > 0) {
                    $pens[$pid] = ($pens[$pid] ?? 0) + $p;
                }
                foreach ($mins as $mn) {
                    $t = self::minute($mn);
                    if ($t && (!$first || $t[0] * 100 + $t[1] < $first[1])) {
                        $first = [$pid, $t[0] * 100 + $t[1]];
                    }
                }
            }
            if ($first && $first[0]) {
                $opener[$first[0]] = ($opener[$first[0]] ?? 0) + 1;
            }
        }
        if ($r = self::rank($pens, 4)) {
            $out['penaltys'] = self::stat('penaltys', self::num($r[0][1]), self::unit($r[0][1], 'penalty marqué', 'penaltys marqués'), t('Le roi des penaltys'),
                t('{t} penaltys transformés en tout dans les matchs officiels racontés.', ['t' => self::num(array_sum($pens))]), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        // Joker : buts marqués en entrant en jeu.
        $sub = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && $a[8] === 'R' && $a[2] > 0) {
                $sub[$a[0]] = ($sub[$a[0]] ?? 0) + $a[2];
            }
        }
        if ($r = self::rank($sub, 4)) {
            $out['joker'] = self::stat('joker', self::num($r[0][1]), self::unit($r[0][1], 'but en sortant du banc', 'buts en sortant du banc'), t('Le meilleur joker'),
                t('{t} buts sochaliens ont été marqués par des remplaçants entrés en jeu.', ['t' => self::num(array_sum($sub))]), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        // Série de matchs consécutifs (joués par le joueur) avec au moins un but.
        $best = null;
        foreach (self::byPlayer($c) as $pid => $list) {
            if (!self::person((int) $pid)) {
                continue;
            }
            foreach ($c['blocks'] as $ids) {
                $cur = [];
                foreach ($ids as $mid) {
                    // Composition douteuse : la série ne peut pas être prouvée, elle s'arrête.
                    if (isset($c['unsure'][$mid])) {
                        $cur = [];
                        continue;
                    }
                    if (!isset($list[$mid])) {
                        continue;
                    }
                    if ($list[$mid][2] > 0) {
                        $cur[] = $mid;
                        if (!$best || count($cur) > count($best[1])) {
                            $best = [$pid, $cur];
                        }
                    } else {
                        $cur = [];
                    }
                }
            }
        }
        if ($best && count($best[1]) >= 3) {
            [$pid, $run] = $best;
            $g = 0;
            foreach ($run as $mid) {
                foreach ($c['apps'] as $a) {
                    if ($a[0] === $pid && $a[1] === $mid) {
                        $g += $a[2];
                    }
                }
            }
            $out['serie-buteur'] = self::stat('serie-buteur', self::num(count($run)), t('matchs de suite avec un but'), t('La plus longue série de buts'),
                t('{g} buts du {a} au {b}, sans un match sans marquer.', ['g' => $g, 'a' => date_fr($c['O'][$run[0]]['date']), 'b' => date_fr($c['O'][end($run)]['date'])]),
                [self::person((int) $pid)]);
        }
        // Bourreau : le plus de buts d'un joueur contre un même adversaire.
        $vs = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && $a[2] > 0 && ($club = $O[$a[1]]['club'])) {
                $vs[$a[0] . '|' . $club] = ($vs[$a[0] . '|' . $club] ?? 0) + $a[2];
            }
        }
        arsort($vs);
        foreach ($vs as $k => $g) {
            [$pid, $club] = explode('|', $k, 2);
            if ($p = self::person((int) $pid)) {
                $out['bourreau'] = self::stat('bourreau', self::num($g), self::unit($g, 'but', 'buts'), t('Le bourreau'),
                    t('Le plus de buts d’un Sochalien contre un même adversaire : {club}.', ['club' => self::clubName($club)]), [$p, self::club($club)]);
                break;
            }
        }
        if ($r = self::rank($opener, 4)) {
            $out['ouvreur'] = self::stat('ouvreur', self::num($r[0][1]), self::unit($r[0][1], 'fois', 'fois'), t('L’ouvreur'),
                t('Le plus souvent auteur du premier but sochalien d’un match.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        $diff = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && $a[2] > 0) {
                $diff[$a[0]] = true;
            }
        }
        if ($diff) {
            $out['buteurs-differents'] = self::stat('buteurs-differents', self::num(count($diff)), t('buteurs différents'), t('Tous buteurs'),
                t('Joueurs sochaliens ayant marqué au moins un but dans les matchs officiels racontés.'));
        }
        // Buts de la tête (récits des buts sochaliens).
        $head = $tot = 0;
        foreach ($c['X'] as $x) {
            foreach ($x['seq'] ?? [] as $g) {
                if ($g['us']) {
                    $tot++;
                    $head += preg_match('/de la t[eê]te|d[\'’]une t[eê]te|t[eê]te (pi[qu]+[ée]e|plongeante|victorieuse|rageuse|imparable|croisée|décroisée)/iu', $g['text']) ? 1 : 0;
                }
            }
        }
        if ($tot >= 50) {
            $out['tete'] = self::stat('tete', self::pct($head / $tot), t('des buts'), t('De la tête'),
                t('{h} buts sochaliens sur {t} racontés ont été marqués de la tête.', ['h' => self::num($head), 't' => self::num($tot)]), [], 'recits');
        }
        $csc = array_sum(array_column($c['X'], 'csc'));
        if ($csc > 0) {
            $out['csc'] = self::stat('csc', self::num($csc), self::unit($csc, 'but contre son camp', 'buts contre leur camp'), t('Le coup de pouce'),
                t('Marqués par des adversaires… pour Sochaux.'));
        }
        // Saison au plus grand nombre de buteurs différents.
        $bs = [];
        foreach ($c['apps'] as $a) {
            $s = $O[$a[1]]['season'];
            if ($a[6] === 'player' && $a[2] > 0 && in_array($s, $c['full'], true)) {
                $bs[$s][$a[0]] = true;
            }
        }
        if ($bs) {
            $bs = array_map('count', $bs);
            arsort($bs);
            $s = array_key_first($bs);
            $out['saison-buteurs'] = self::stat('saison-buteurs', self::num($bs[$s]), t('buteurs différents'), t('La saison aux mille buteurs'),
                t('En {s}, le record de buteurs sochaliens différents sur une saison.', ['s' => $s]));
        }
        return $out;
    }

    // ------------------------------------------------------------------ 3. Le chrono

    private static function chrono(array $c): array
    {
        $O = $c['O'];
        $out = [];
        $goals = []; // [mid, pid, base, add]
        foreach ($c['scorers'] as $mid => $list) {
            foreach ($list as [$pid, $name, $mins]) {
                foreach ($mins as $mn) {
                    if ($t = self::minute($mn)) {
                        $goals[] = [$mid, $pid, $t[0], $t[1], $name];
                    }
                }
            }
        }
        if ($goals) {
            // But le plus rapide (le plus récent à minute égale).
            $fast = $goals;
            usort($fast, fn ($a, $b) => $a[2] <=> $b[2] ?: $a[3] <=> $b[3] ?: strcmp($O[$b[0]]['date'], $O[$a[0]]['date']));
            $g = $fast[0];
            $k = count(array_filter($goals, fn ($x) => $x[2] === $g[2]));
            $x = $O[$g[0]];
            $out['but-rapide'] = self::stat('but-rapide', self::nth($g[2]), t('minute'), t('Le but le plus rapide'),
                t('Contre {opp}, le {date}.', ['opp' => self::opp($x), 'date' => date_fr($x['date'])])
                . ($k > 1 ? ' ' . t('{k} buts sochaliens ont été marqués dès la {m} minute.', ['k' => $k, 'm' => self::nth($g[2])]) : ''),
                [$g[1] ? self::person((int) $g[1]) : null, self::match($x)]);
            // But le plus tardif.
            $late = $goals;
            usort($late, fn ($a, $b) => $b[2] <=> $a[2] ?: $b[3] <=> $a[3] ?: strcmp($O[$b[0]]['date'], $O[$a[0]]['date']));
            $g = $late[0];
            $x = $O[$g[0]];
            $out['but-tardif'] = self::stat('but-tardif', self::nth($g[2]) . ($g[3] ? '+' . $g[3] : ''), t('minute'), t('Le but le plus tardif'),
                t('Contre {opp}, le {date}.', ['opp' => self::opp($x), 'date' => date_fr($x['date'])]), [$g[1] ? self::person((int) $g[1]) : null, self::match($x)]);
            // Minute fétiche (temps réglementaire, hors temps additionnel).
            $per = [];
            foreach ($goals as $g) {
                if (!$g[3] && $g[2] <= 90) {
                    $per[$g[2]] = ($per[$g[2]] ?? 0) + 1;
                }
            }
            arsort($per);
            $mf = array_key_first($per);
            $out['minute-fetiche'] = self::stat('minute-fetiche', self::nth((int) $mf), t('minute'), t('La minute fétiche'),
                t('{n} buts sochaliens marqués à cette minute précise, temps additionnel non compris.', ['n' => $per[$mf]]));
            // Répartition dans le temps réglementaire.
            $reg = array_filter($goals, fn ($g) => $g[2] <= 90);
            $tot = max(1, count($reg));
            $endQ = count(array_filter($reg, fn ($g) => $g[2] >= 76));
            $startQ = count(array_filter($reg, fn ($g) => $g[2] <= 15));
            $second = count(array_filter($reg, fn ($g) => $g[2] >= 46));
            $out['dernier-quart'] = self::stat('dernier-quart', self::pct($endQ / $tot), t('des buts'), t('Le dernier quart d’heure'),
                t('{n} buts sochaliens sur {t} marqués après la 75e minute, temps additionnel compris.', ['n' => self::num($endQ), 't' => self::num($tot)]));
            $out['premier-quart'] = self::stat('premier-quart', self::pct($startQ / $tot), t('des buts'), t('Les départs canon'),
                t('{n} buts sochaliens marqués dans le premier quart d’heure.', ['n' => self::num($startQ)]));
            $out['seconde-periode'] = self::stat('seconde-periode', self::pct($second / $tot), t('des buts'), t('Les Lions de la seconde période'),
                t('{n} buts sochaliens marqués après la pause, contre {f} avant.', ['n' => self::num($second), 'f' => self::num($tot - $second)]));
        }

        // Déroulé des matchs racontés minute par minute.
        $flow = [];
        foreach ($c['X'] as $mid => $x) {
            if ($x['seq'] === null || !$O[$mid]['result']) {
                continue;
            }
            $us = $them = 0;
            $worst = 0;
            $bestLead = 0;
            $firstUs = null;
            $lastNotLeading = -1; // index du dernier but après lequel Sochaux ne menait pas
            foreach ($x['seq'] as $i => $g) {
                $g['us'] ? $us++ : $them++;
                $firstUs ??= $g['us'];
                $worst = max($worst, $them - $us);
                $bestLead = max($bestLead, $us - $them);
                if ($us <= $them) {
                    $lastNotLeading = $i;
                }
            }
            $flow[$mid] = ['worst' => $worst, 'lead' => $bestLead, 'first' => $firstUs, 'seq' => $x['seq'], 'decisive' => $x['seq'][$lastNotLeading + 1] ?? null];
        }
        $wins = array_filter($flow, fn ($f, $mid) => $O[$mid]['result'] === 'V', ARRAY_FILTER_USE_BOTH);
        // Plus belle remontada : le plus gros retard effacé pour gagner.
        $rem = array_filter($wins, fn ($f) => $f['worst'] > 0);
        if ($rem) {
            uksort($rem, fn ($a, $b) => $rem[$b]['worst'] <=> $rem[$a]['worst'] ?: strcmp($O[$b]['date'], $O[$a]['date']));
            $mid = array_key_first($rem);
            $x = $O[$mid];
            $w = $rem[$mid]['worst'];
            $out['remontada'] = self::stat('remontada', self::num($w), self::unit($w, 'but de retard effacé', 'buts de retard effacés'), t('La plus belle remontada'),
                t('Mené de {w} buts, Sochaux s’impose {s} contre {opp}, le {date}.', ['w' => $w, 's' => self::score($x), 'opp' => self::opp($x), 'date' => date_fr($x['date'])]),
                [self::match($x)], 'recits');
            $out['remontadas'] = self::stat('remontadas', self::num(count($rem)), self::unit(count($rem), 'victoire après avoir été mené', 'victoires après avoir été mené'), t('Les remontadas'),
                t('Sur {t} matchs officiels racontés minute par minute.', ['t' => self::num(count($flow))]), [], 'recits');
        }
        // Victoires arrachées : but décisif après la 85e minute.
        $lateWins = array_filter($wins, fn ($f) => $f['decisive'] && $f['decisive']['min'] >= 85 && $f['decisive']['min'] <= 90);
        if ($lateWins) {
            uksort($lateWins, fn ($a, $b) => strcmp($O[$b]['date'], $O[$a]['date']));
            $x = $O[array_key_first($lateWins)];
            $out['victoire-tardive'] = self::stat('victoire-tardive', self::num(count($lateWins)), self::unit(count($lateWins), 'victoire arrachée', 'victoires arrachées'), t('Au bout du suspense'),
                t('Victoires obtenues grâce à un but décisif après la 85e minute. La dernière : contre {opp}, le {date}.', ['opp' => self::opp($x), 'date' => date_fr($x['date'])]),
                [self::match($x)], 'recits');
        }
        $lateDraws = array_filter($flow, fn ($f, $mid) => $O[$mid]['result'] === 'N' && $f['seq'] && end($f['seq'])['us'] && end($f['seq'])['min'] >= 85, ARRAY_FILTER_USE_BOTH);
        if ($lateDraws) {
            uksort($lateDraws, fn ($a, $b) => strcmp($O[$b]['date'], $O[$a]['date']));
            $x = $O[array_key_first($lateDraws)];
            $out['egalisation-tardive'] = self::stat('egalisation-tardive', self::num(count($lateDraws)), self::unit(count($lateDraws), 'nul arraché', 'nuls arrachés'), t('L’égalisation sur le fil'),
                t('Matchs nuls sauvés par une égalisation sochalienne après la 85e minute. Le dernier : contre {opp}, le {date}.', ['opp' => self::opp($x), 'date' => date_fr($x['date'])]),
                [self::match($x)], 'recits');
        }
        // Quand Sochaux marque le premier / encaisse le premier.
        foreach ([[true, 'ouverture', 'Quand Sochaux marque le premier'], [false, 'mene', 'Quand Sochaux encaisse le premier']] as [$first, $key, $label]) {
            $l = array_filter($flow, fn ($f) => $f['first'] === $first);
            if (count($l) >= 20) {
                $r = self::record(array_map(fn ($mid) => $O[$mid], array_keys($l)));
                $out[$key] = self::stat($key, self::pct($r['v'] / $r['m']), t('de victoires'), t($label),
                    ucfirst(self::vnd($r)) . ' ' . t('en {t} matchs racontés minute par minute.', ['t' => self::num($r['m'])]), [], 'recits');
            }
        }
        // Plus gros avantage perdu.
        $lost = array_filter($flow, fn ($f, $mid) => $O[$mid]['result'] === 'D' && $f['lead'] > 0, ARRAY_FILTER_USE_BOTH);
        if ($lost) {
            uksort($lost, fn ($a, $b) => $lost[$b]['lead'] <=> $lost[$a]['lead'] ?: strcmp($O[$b]['date'], $O[$a]['date']));
            $mid = array_key_first($lost);
            $x = $O[$mid];
            $l = $lost[$mid]['lead'];
            $out['avance-perdue'] = self::stat('avance-perdue', self::num($l), self::unit($l, 'but d’avance', 'buts d’avance'), t('L’avance envolée'),
                t('Sochaux menait de {l} buts et s’incline {s} contre {opp}, le {date}.', ['l' => $l, 's' => self::score($x), 'opp' => self::opp($x), 'date' => date_fr($x['date'])]),
                [self::match($x)], 'recits');
        }
        return $out;
    }

    // ------------------------------------------------------------------ 4. Les gardiens et la défense

    private static function defense(array $c): array
    {
        $O = $c['O'];
        $out = [];
        // Gardiens titulaires ayant joué tout le match.
        $keepers = []; // mid => pid
        $cs = $played = $csSeason = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] !== 'player' || $a[8] !== 'G') {
                continue;
            }
            $x = $O[$a[1]];
            $keepers[$a[1]][] = $a[0];
            $full = !empty($c['X'][$a[1]]['aet']) ? 120 : 90;
            if ($a[3] >= $full && $x['them'] !== null) {
                $played[$a[0]] = ($played[$a[0]] ?? 0) + 1;
                if ((int) $x['them'] === 0) {
                    $cs[$a[0]] = ($cs[$a[0]] ?? 0) + 1;
                    $csSeason[$a[0] . '|' . $x['season']] = ($csSeason[$a[0] . '|' . $x['season']] ?? 0) + 1;
                }
            }
        }
        if ($r = self::rank($cs, 4)) {
            [$p, $n] = $r[0];
            $out['blanchissages'] = self::stat('blanchissages', self::num($n), self::unit($n, 'match sans encaisser', 'matchs sans encaisser'), t('Le roi des blanchissages'),
                t('En {m} matchs officiels joués en entier dans les buts.', ['m' => self::num($played[$p['id']])]), [$p], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        // Invincibilité : minutes consécutives sans encaisser.
        $best = null;
        foreach ($c['blocks'] as $ids) {
            $run = 0;
            $from = null;
            $gks = [];
            foreach ($ids as $mid) {
                $x = $O[$mid];
                $mins = $c['X'][$mid]['opp'] ?? null;
                $len = !empty($c['X'][$mid]['aet']) ? 120 : 90;
                if ($x['them'] === null) {
                    $run = 0;
                    $from = null;
                    $gks = [];
                    continue;
                }
                if ((int) $x['them'] === 0) {
                    $run += $len;
                    $from ??= $mid;
                    foreach ($keepers[$mid] ?? [] as $pid) {
                        $gks[$pid] = true;
                    }
                    continue;
                }
                // Premier but encaissé : la série s'arrête à cette minute (inconnue : au coup d'envoi).
                $first = $mins ? min(max($mins[0], 0), $len) : 0;
                $total = $run + $first;
                if ($from !== null && (!$best || $total > $best['min'])) {
                    foreach ($keepers[$mid] ?? [] as $pid) {
                        $gks[$pid] = true;
                    }
                    $best = ['min' => $total, 'from' => $from, 'to' => $mid, 'gks' => array_keys($gks)];
                }
                $last = $mins ? min(max(end($mins), 0), $len) : $len;
                $run = $len - $last;
                $from = $run > 0 ? $mid : null;
                $gks = [];
                if ($run > 0) {
                    foreach ($keepers[$mid] ?? [] as $pid) {
                        $gks[$pid] = true;
                    }
                }
            }
        }
        if ($best && $best['min'] >= 360) {
            $a = $O[$best['from']];
            $b = $O[$best['to']];
            $who = array_map(fn ($pid) => self::person((int) $pid), $best['gks']);
            $out['invincibilite-gardien'] = self::stat('invincibilite-gardien', self::num($best['min']), t('minutes sans encaisser'), t('L’invincibilité record'),
                t('Du {a} au {b}, but encaissé contre {opp}.', ['a' => date_fr($a['date']), 'b' => date_fr($b['date']), 'opp' => self::opp($b)]), array_slice($who, 0, 2), 'recits');
        }
        $run = self::longestRun($c['blocks'], fn ($id) => $O[$id]['them'] !== null && (int) $O[$id]['them'] === 0);
        if (count($run) >= 2) {
            $out['serie-clean'] = self::stat('serie-clean', self::num(count($run)), t('matchs de suite sans encaisser'), t('La muraille'),
                t('Du {a} au {b}.', ['a' => date_fr($O[$run[0]]['date']), 'b' => date_fr($O[end($run)]['date'])]), [self::match($O[$run[0]])]);
        }
        // Meilleur défenseur : buts encaissés par match quand il était titulaire en défense.
        $def = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && $a[8] === 'D' && $O[$a[1]]['them'] !== null) {
                $def[$a[0]]['m'] = ($def[$a[0]]['m'] ?? 0) + 1;
                $def[$a[0]]['ga'] = ($def[$a[0]]['ga'] ?? 0) + (int) $O[$a[1]]['them'];
            }
        }
        $avg = [];
        foreach ($def as $pid => $v) {
            if ($v['m'] >= 100) {
                $avg[$pid] = round($v['ga'] / $v['m'], 3);
            }
        }
        if ($r = self::rank($avg, 4, true)) {
            [$p, $v] = $r[0];
            $out['defenseur'] = self::stat('defenseur', self::num($v, 2), t('but encaissé par match'), t('Le meilleur défenseur'),
                t('Avec lui titulaire en défense, sur {m} matchs officiels (100 au moins).', ['m' => self::num($def[$p['id']]['m'])]), [$p], 'matchs',
                self::more($r, fn ($v) => self::num($v, 2)));
        }
        // Meilleure défense d'une saison de championnat.
        $seasons = [];
        foreach ($O as $x) {
            if ($x['comp'] === 'Championnat' && $x['them'] !== null && in_array($x['season'], $c['full'], true)) {
                $seasons[$x['season']]['m'] = ($seasons[$x['season']]['m'] ?? 0) + 1;
                $seasons[$x['season']]['ga'] = ($seasons[$x['season']]['ga'] ?? 0) + (int) $x['them'];
            }
        }
        $seasons = array_filter($seasons, fn ($s) => $s['m'] >= self::FULL_SEASON);
        if ($seasons) {
            uasort($seasons, fn ($a, $b) => $a['ga'] / $a['m'] <=> $b['ga'] / $b['m']);
            $s = array_key_first($seasons);
            $v = $seasons[$s];
            $out['defense-saison'] = self::stat('defense-saison', self::num($v['ga'] / $v['m'], 2), t('but encaissé par match'), t('La meilleure défense'),
                t('En championnat, saison {s} : {ga} buts encaissés en {m} matchs.', ['s' => $s, 'ga' => $v['ga'], 'm' => $v['m']]));
        }
        if ($csSeason) {
            arsort($csSeason);
            foreach ($csSeason as $k => $n) {
                [$pid, $s] = explode('|', $k, 2);
                if ($p = self::person((int) $pid)) {
                    $out['blanchissages-saison'] = self::stat('blanchissages-saison', self::num($n), self::unit($n, 'match sans encaisser', 'matchs sans encaisser'), t('Le record sur une saison'),
                        t('Le plus de blanchissages d’un gardien en une saison : {s}.', ['s' => $s]), [$p]);
                    break;
                }
            }
        }
        // Penaltys arrêtés (récits) : attribués au gardien sochalien nommé dans le récit.
        $saves = [];
        foreach ($c['X'] as $mid => $x) {
            foreach ($x['saves'] as $t) {
                foreach ($keepers[$mid] ?? [] as $pid) {
                    $p = self::person((int) $pid);
                    $last = $p ? implode(' ', Names::tokens($p['last'])) : '';
                    if ($last !== '' && preg_match('/\b' . preg_quote($last, '/') . '\b/', $t)) {
                        $saves[$pid] = ($saves[$pid] ?? 0) + 1;
                    }
                }
            }
        }
        if ($r = self::rank($saves, 4)) {
            $out['penalty-arrete'] = self::stat('penalty-arrete', self::num($r[0][1]), self::unit($r[0][1], 'penalty arrêté', 'penaltys arrêtés'), t('Le gardien qui dit non'),
                t('Penaltys repoussés pendant le match, d’après les récits des temps forts.'), [$r[0][0]], 'recits', self::more($r, fn ($v) => self::num($v)));
        }
        $scored = array_filter($O, fn ($x) => $x['them'] !== null);
        $nil = array_filter($scored, fn ($x) => (int) $x['them'] === 0);
        if ($scored) {
            $out['clean-pct'] = self::stat('clean-pct', self::pct(count($nil) / count($scored)), t('des matchs sans encaisser'), t('Le cadenas'),
                t('{n} matchs officiels sur {t} terminés sans le moindre but encaissé.', ['n' => self::num(count($nil)), 't' => self::num(count($scored))]));
            $zz = count(array_filter($nil, fn ($x) => (int) $x['us'] === 0));
            $out['zero-zero'] = self::stat('zero-zero', self::num($zz), self::unit($zz, 'match nul et vierge', 'matchs nuls et vierges'), t('Les 0-0'),
                t('Soit {p} des matchs officiels racontés.', ['p' => self::pct($zz / count($scored))]));
        }
        return $out;
    }

    // ------------------------------------------------------------------ 5. Les séries

    private static function series(array $c): array
    {
        $bonal = fn ($x) => $x['sh'] && $x['stade'] === self::BONAL;
        $res = fn ($x) => $x['result'] !== null;
        $out = array_filter([
            'invincibilite' => self::runStat($c, 'invincibilite', 'La plus longue invincibilité', 'match sans défaite', 'matchs sans défaite', fn ($x) => $res($x) && $x['result'] !== 'D'),
            'victoires' => self::runStat($c, 'victoires', 'La plus longue série de victoires', 'victoire de suite', 'victoires de suite', fn ($x) => $x['result'] === 'V'),
            'bonal-invaincu' => self::runStat($c, 'bonal-invaincu', 'Bonal imprenable', 'match sans défaite à Bonal', 'matchs sans défaite à Bonal', fn ($x) => $res($x) && $x['result'] !== 'D', $bonal),
            'exterieur-invaincu' => self::runStat($c, 'exterieur-invaincu', 'Les globe-trotters', 'match sans défaite à l’extérieur', 'matchs sans défaite à l’extérieur', fn ($x) => $res($x) && $x['result'] !== 'D', fn ($x) => !$x['sh']),
            'buts-consecutifs' => self::runStat($c, 'buts-consecutifs', 'Toujours au moins un but', 'match de suite en marquant', 'matchs de suite en marquant', fn ($x) => (int) $x['us'] > 0),
            'defaites' => self::runStat($c, 'defaites', 'La plus longue série de défaites', 'défaite de suite', 'défaites de suite', fn ($x) => $x['result'] === 'D'),
            'sans-victoire' => self::runStat($c, 'sans-victoire', 'La plus longue disette', 'match sans victoire', 'matchs sans victoire', fn ($x) => $res($x) && $x['result'] !== 'V'),
            'bonal-victoires' => self::runStat($c, 'bonal-victoires', 'Bonal en feu', 'victoire de suite à Bonal', 'victoires de suite à Bonal', fn ($x) => $x['result'] === 'V', $bonal),
            'nuls' => self::runStat($c, 'nuls', 'Le temps des nuls', 'match nul de suite', 'matchs nuls de suite', fn ($x) => $x['result'] === 'N'),
            'muet' => self::runStat($c, 'muet', 'La panne sèche', 'match de suite sans marquer', 'matchs de suite sans marquer', fn ($x) => $x['us'] !== null && (int) $x['us'] === 0),
            'exterieur-disette' => self::runStat($c, 'exterieur-disette', 'Les voyages sans victoire', 'déplacement sans victoire', 'déplacements sans victoire', fn ($x) => $res($x) && $x['result'] !== 'V', fn ($x) => !$x['sh']),
        ]);
        // Invincibilité à Bonal faite uniquement de victoires : déjà dite par « Bonal en feu ».
        if (isset($out['bonal-invaincu'], $out['bonal-victoires']) && $out['bonal-invaincu']['value'] === $out['bonal-victoires']['value']) {
            unset($out['bonal-invaincu']);
        }
        return $out;
    }

    // ------------------------------------------------------------------ 6. Les scores

    private static function scores(array $c): array
    {
        $L = array_filter($c['O'], fn ($x) => $x['us'] !== null && $x['result'] !== null);
        if (!$L) {
            return [];
        }
        $out = [];
        $pick = function (callable $cmp) use ($L): array {
            $l = array_values($L);
            usort($l, fn ($a, $b) => $cmp($a, $b) ?: strcmp($b['date'], $a['date']));
            return $l[0];
        };
        $line = fn (array $x) => t('Contre {opp}, le {date} ({comp}).', ['opp' => self::opp($x), 'date' => date_fr($x['date']), 'comp' => t($x['label'] ?: $x['comp'])]);
        $x = $pick(fn ($a, $b) => ($b['us'] - $b['them']) <=> ($a['us'] - $a['them']) ?: $b['us'] <=> $a['us']);
        $big = $x;
        $out['large-victoire'] = self::stat('large-victoire', self::score($x), '', t('La plus large victoire'), $line($x), [self::match($x)]);
        $x = $pick(fn ($a, $b) => ($b['them'] - $b['us']) <=> ($a['them'] - $a['us']) ?: $b['them'] <=> $a['them']);
        $out['lourde-defaite'] = self::stat('lourde-defaite', self::score($x), '', t('La plus lourde défaite'), $line($x), [self::match($x)]);
        $r = self::record($L);
        $out['bilan'] = self::stat('bilan', self::pct($r['v'] / $r['m']), t('de victoires'), t('Le bilan officiel'),
            ucfirst(self::vnd($r)) . ' ' . t('en {m} matchs officiels racontés.', ['m' => self::num($r['m'])]));
        $x = $pick(fn ($a, $b) => $b['us'] <=> $a['us'] ?: $a['them'] <=> $b['them']);
        // Le festival n'a d'intérêt que s'il ne s'agit pas déjà de la plus large victoire.
        if ($x['id'] !== $big['id']) {
            $out['festival'] = self::stat('festival', self::num((int) $x['us']), t('buts marqués dans un match'), t('Le festival offensif'),
                t('{s} contre {opp}, le {date}.', ['s' => self::score($x), 'opp' => self::opp($x), 'date' => date_fr($x['date'])]), [self::match($x)]);
        }
        $x = $pick(fn ($a, $b) => ($b['us'] + $b['them']) <=> ($a['us'] + $a['them']));
        $out['prolifique'] = self::stat('prolifique', self::num($x['us'] + $x['them']), t('buts dans un même match'), t('Le match le plus fou'),
            t('{s} contre {opp}, le {date}.', ['s' => self::score($x), 'opp' => self::opp($x), 'date' => date_fr($x['date'])]), [self::match($x)]);
        $freq = [];
        foreach ($L as $x) {
            $freq[self::score($x)] = ($freq[self::score($x)] ?? 0) + 1;
        }
        arsort($freq);
        $top = array_key_first($freq);
        $out['score-roi'] = self::stat('score-roi', $top, self::unit($freq[$top], '{n} fois', '{n} fois', ['n' => self::num($freq[$top])]), t('Le score roi'),
            t('Le score final le plus fréquent des matchs officiels racontés.'));
        $w10 = $freq['1-0'] ?? 0;
        if ($w10) {
            $out['un-zero'] = self::stat('un-zero', self::num($w10), self::unit($w10, 'victoire 1-0', 'victoires 1-0'), t('Le 1-0 à la sochalienne'),
                t('Soit {p} des victoires officielles racontées.', ['p' => self::pct($w10 / max(1, $r['v']))]));
        }
        $out['moyenne'] = self::stat('moyenne', self::num($r['gf'] / $r['m'], 2), self::unit($r['gf'] / $r['m'], 'but marqué par match', 'buts marqués par match'), t('Les buts par match'),
            t('{f} buts marqués et {a} encaissés en {m} matchs officiels.', ['f' => self::num($r['gf']), 'a' => self::num($r['ga']), 'm' => self::num($r['m'])]));
        // Tirs au but.
        $tab = array_filter($c['X'], fn ($x) => $x['pens'] !== null);
        if ($tab) {
            $won = count(array_filter($tab, fn ($x) => $x['pens'][0] > $x['pens'][1]));
            $out['tab'] = self::stat('tab', $won . '/' . count($tab), t('séances gagnées'), t('Les tirs au but'),
                t('{w} séances gagnées, {l} perdues dans les matchs racontés.', ['w' => $won, 'l' => count($tab) - $won]));
            uasort($tab, fn ($a, $b) => array_sum($b['pens']) <=> array_sum($a['pens']));
            $mid = array_key_first($tab);
            $x = $c['O'][$mid];
            $p = $tab[$mid]['pens'];
            $out['tab-record'] = self::stat('tab-record', $p[0] . '-' . $p[1], t('aux tirs au but'), t('La séance interminable'),
                t('Après {s} contre {opp}, le {date}.', ['s' => self::score($x), 'opp' => self::opp($x), 'date' => date_fr($x['date'])]), [self::match($x)]);
        }
        $aet = count(array_filter($c['X'], fn ($x) => $x['aet']));
        if ($aet) {
            $out['prolongations'] = self::stat('prolongations', self::num($aet), self::unit($aet, 'match prolongé', 'matchs prolongés'), t('Les prolongations'),
                t('Matchs officiels allés jusqu’à 120 minutes.'));
        }
        $draws = array_filter($L, fn ($x) => $x['result'] === 'N' && (int) $x['us'] === (int) $x['them']);
        if ($draws) {
            $l = array_values($draws);
            usort($l, fn ($a, $b) => $b['us'] <=> $a['us'] ?: strcmp($b['date'], $a['date']));
            $x = $l[0];
            if ((int) $x['us'] >= 3) {
                $out['nul-fou'] = self::stat('nul-fou', self::score($x), '', t('Le nul le plus fou'), $line($x), [self::match($x)]);
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ 7. Les acteurs

    private static function acteurs(array $c): array
    {
        $O = $c['O'];
        $out = [];
        // Passes décisives citées dans les récits des buts sochaliens.
        $assists = $duos = [];
        $squad = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player') {
                $squad[$a[1]][] = $a[0];
            }
        }
        foreach ($c['X'] as $mid => $x) {
            if (!$x['seq'] || empty($squad[$mid])) {
                continue;
            }
            $byMin = [];
            foreach ($c['scorers'][$mid] ?? [] as [$pid, $name, $mins]) {
                foreach ($mins as $mn) {
                    if ($pid && ($t = self::minute($mn))) {
                        $byMin[$t[0]] = $pid;
                    }
                }
            }
            foreach ($x['seq'] as $g) {
                if (!$g['us'] || !($passer = self::passer($g['text'], $squad[$mid]))) {
                    continue;
                }
                $scorer = $byMin[$g['min']] ?? null;
                if ($scorer === $passer) {
                    continue;
                }
                $assists[$passer] = ($assists[$passer] ?? 0) + 1;
                if ($scorer) {
                    $duos[$passer . '|' . $scorer] = ($duos[$passer . '|' . $scorer] ?? 0) + 1;
                }
            }
        }
        if ($r = self::rank($assists, 4)) {
            $out['passeur'] = self::stat('passeur', self::num($r[0][1]), self::unit($r[0][1], 'passe décisive', 'passes décisives'), t('Le meilleur passeur'),
                t('Passes, centres et corners décisifs cités dans les récits des buts sochaliens.'), [$r[0][0]], 'recits', self::more($r, fn ($v) => self::num($v)));
        }
        arsort($duos);
        foreach ($duos as $k => $n) {
            [$a, $b] = explode('|', $k);
            if ($n >= 4 && ($pa = self::person((int) $a)) && ($pb = self::person((int) $b))) {
                $out['duo'] = self::stat('duo', self::num($n), self::unit($n, 'but', 'buts'), t('Le duo infernal'),
                    t('{a} à la passe, {b} à la conclusion, d’après les récits des buts.', ['a' => $pa['name'], 'b' => $pb['name']]), [$pa, $pb], 'recits');
                break;
            }
        }
        $sum = function (int $i, ?callable $only = null) use ($c): array {
            $v = [];
            foreach ($c['apps'] as $a) {
                if ($a[6] === 'player' && (!$only || $only($a))) {
                    $v[$a[0]] = ($v[$a[0]] ?? 0) + ($i < 0 ? 1 : (int) $a[$i]);
                }
            }
            return array_filter($v);
        };
        if ($r = self::rank($sum(-1, fn ($a) => $a[7]), 4)) {
            $out['capitaine'] = self::stat('capitaine', self::num($r[0][1]), self::unit($r[0][1], 'match avec le brassard', 'matchs avec le brassard'), t('Le capitaine'),
                t('Le plus de matchs officiels disputés avec le brassard, dans les compositions racontées.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        $minutes = $sum(3);
        if ($r = self::rank($minutes, 4)) {
            [$p, $mn] = $r[0];
            $games = count(self::byPlayer($c)[$p['id']] ?? []);
            $out['minutes'] = self::stat('minutes', self::num($mn), t('minutes jouées'), t('Le plus de minutes sur le terrain'),
                t('Soit {h} heures de jeu en {m} matchs officiels racontés.', ['h' => self::num(intdiv($mn, 60)), 'm' => self::num($games)]), [$p], 'matchs',
                self::more($r, fn ($v) => self::num($v)));
        }
        // L'inusable : matchs officiels consécutifs joués (dans les saisons racontées en entier).
        $best = null;
        $by = self::byPlayer($c);
        foreach ($c['blocks'] as $ids) {
            $runs = [];
            foreach ($ids as $mid) {
                // Un joueur absent de ce match (ou une composition douteuse) arrête la série.
                $next = [];
                foreach (array_unique($squad[$mid] ?? []) as $pid) {
                    $run = $runs[$pid] ?? ['n' => 0, 'from' => $mid];
                    $run['n']++;
                    $run['last'] = $mid;
                    $next[$pid] = $run;
                    if ((!$best || $run['n'] > $best['n']) && self::person((int) $pid)) {
                        $best = $run + ['pid' => $pid];
                    }
                }
                $runs = $next;
            }
        }
        if ($best && $best['n'] >= 10) {
            $out['inusable'] = self::stat('inusable', self::num($best['n']), t('matchs officiels de suite'), t('L’inusable'),
                t('Sans en manquer un seul, du {a} au {b}.', ['a' => date_fr($O[$best['from']]['date']), 'b' => date_fr($O[$best['last']]['date'])]), [self::person((int) $best['pid'])]);
        }
        // Entraîneurs.
        $coach = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'coach' && $O[$a[1]]['result']) {
                $coach[$a[0]][] = $O[$a[1]];
            }
        }
        $cm = array_map('count', $coach);
        if ($r = self::rank($cm, 4)) {
            [$p, $m] = $r[0];
            $out['entraineur'] = self::stat('entraineur', self::num($m), self::unit($m, 'match sur le banc', 'matchs sur le banc'), t('L’entraîneur le plus fidèle'),
                ucfirst(self::vnd(self::record($coach[$p['id']]))) . '.', [$p], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        $pct = [];
        foreach ($coach as $pid => $l) {
            if (count($l) >= 50) {
                $rr = self::record($l);
                $pct[$pid] = round($rr['v'] / $rr['m'], 4);
            }
        }
        if ($r = self::rank($pct, 4)) {
            [$p, $v] = $r[0];
            $out['entraineur-victoires'] = self::stat('entraineur-victoires', self::pct($v), t('de victoires'), t('L’entraîneur qui gagne'),
                t('En {m} matchs officiels sur le banc (50 au moins).', ['m' => self::num(count($coach[$p['id']]))]), [$p], 'matchs', self::more($r, fn ($v) => self::pct($v)));
        }
        if ($r = self::rank($sum(-1, fn ($a) => $a[8] === 'R'), 4)) {
            $out['remplacant'] = self::stat('remplacant', self::num($r[0][1]), self::unit($r[0][1], 'entrée en jeu', 'entrées en jeu'), t('Le remplaçant de luxe'),
                t('Le plus d’apparitions en sortant du banc.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        if ($r = self::rank($sum(4), 4)) {
            $out['jaunes'] = self::stat('jaunes', self::num($r[0][1]), self::unit($r[0][1], 'carton jaune', 'cartons jaunes'), t('Le plus averti'),
                t('Dans les matchs officiels racontés.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        if ($r = self::rank($sum(5), 4)) {
            $out['rouges'] = self::stat('rouges', self::num($r[0][1]), self::unit($r[0][1], 'carton rouge', 'cartons rouges'), t('Le plus expulsé'),
                t('Dans les matchs officiels racontés.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        // Le plus entouré : coéquipiers différents (Fil jaune).
        $adj = FilJaune::graph()['adj'] ?? [];
        $deg = array_map('count', $adj);
        if ($r = self::rank($deg, 4)) {
            $out['coequipiers'] = self::stat('coequipiers', self::num($r[0][1]), t('coéquipiers différents'), t('Le plus entouré'),
                t('Le Lionceau qui a partagé le terrain avec le plus de joueurs différents, d’après le Fil jaune.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        // Saisons jouées sans manquer un match officiel.
        $seasonGames = [];
        foreach ($O as $mid => $x) {
            if (in_array($x['season'], $c['full'], true) && $x['season'] !== Explore::currentSeason()) {
                $seasonGames[$x['season']][] = $mid;
            }
        }
        $ever = [];
        foreach ($by as $pid => $list) {
            foreach ($seasonGames as $s => $ids) {
                if (!array_diff($ids, array_keys($list))) {
                    $ever[$pid] = ($ever[$pid] ?? 0) + 1;
                }
            }
        }
        if (($r = self::rank($ever, 4)) && $r[0][1] >= 2) {
            $out['tous-les-matchs'] = self::stat('tous-les-matchs', self::num($r[0][1]), self::unit($r[0][1], 'saison complète', 'saisons complètes'), t('Toujours là'),
                t('Saisons jouées sans manquer un seul match officiel.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        $bench = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'bench') {
                $bench[$a[0]] = ($bench[$a[0]] ?? 0) + 1;
            }
        }
        if ($r = self::rank($bench, 4)) {
            $out['banc'] = self::stat('banc', self::num($r[0][1]), t('matchs sur le banc sans entrer'), t('La patience faite homme'),
                t('Le plus de feuilles de match sans entrer en jeu.'), [$r[0][0]], 'matchs', self::more($r, fn ($v) => self::num($v)));
        }
        return $out;
    }

    /** Joueur sochalien cité comme passeur dans le récit d'un but (« sur un centre de Larbi »). */
    public static function passer(string $text, array $squad): ?int
    {
        if (!preg_match_all('/\b(?:passe|centre|service|offrande|remise|déviation|corner|coup[- ]franc|caviar|ouverture|débordement|décalage|talonnade|une-deux)\b[^.()]{0,30}?\bde\s+(?:l[ae]\s+|l[\'’])?([\p{Lu}][\p{L}\'’-]+(?:\s+[\p{Lu}][\p{L}\'’-]+)?)/u', $text, $mm)) {
            return null;
        }
        foreach ($mm[1] as $name) {
            $key = implode(' ', Names::tokens($name));
            $last = Names::tokens($name);
            $last = end($last) ?: '';
            foreach (array_unique($squad) as $pid) {
                $p = self::person((int) $pid);
                if (!$p) {
                    continue;
                }
                $pl = implode(' ', Names::tokens($p['last']));
                if ($pl !== '' && ($pl === $key || $pl === $last || implode(' ', Names::tokens($p['name'])) === $key)) {
                    return (int) $pid;
                }
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ 8. Les adversaires

    private static function adversaires(array $c): array
    {
        $O = array_filter($c['O'], fn ($x) => $x['result'] !== null);
        $out = [];
        $by = [];
        foreach ($O as $x) {
            if ($x['club']) {
                $by[$x['club']][] = $x;
            }
        }
        if (!$by) {
            return [];
        }
        $rec = array_map(fn ($l) => self::record($l), $by);
        $pick = function (callable $v, int $min = 1) use ($rec): ?string {
            $l = array_filter($rec, fn ($r) => $r['m'] >= $min);
            if (!$l) {
                return null;
            }
            uksort($l, fn ($a, $b) => $v($l[$b]) <=> $v($l[$a]) ?: $l[$b]['m'] <=> $l[$a]['m'] ?: strcmp($a, $b));
            return array_key_first($l);
        };
        $k = $pick(fn ($r) => $r['m']);
        $out['adversaire'] = self::stat('adversaire', self::num($rec[$k]['m']), t('matchs officiels'), t('L’adversaire le plus affronté'),
            t('Contre {club} : {vnd}.', ['club' => self::clubName($k), 'vnd' => self::vnd($rec[$k])]), [self::club($k)]);
        $k = $pick(fn ($r) => $r['v']);
        $out['victime'] = self::stat('victime', self::num($rec[$k]['v']), self::unit($rec[$k]['v'], 'victoire', 'victoires'), t('La victime préférée'),
            t('Contre {club}, en {m} matchs officiels.', ['club' => self::clubName($k), 'm' => $rec[$k]['m']]), [self::club($k)]);
        $k = $pick(fn ($r) => $r['d']);
        $out['bete-noire'] = self::stat('bete-noire', self::num($rec[$k]['d']), self::unit($rec[$k]['d'], 'défaite', 'défaites'), t('La bête noire'),
            t('Contre {club}, en {m} matchs officiels.', ['club' => self::clubName($k), 'm' => $rec[$k]['m']]), [self::club($k)]);
        if ($k = $pick(fn ($r) => $r['v'] / $r['m'], 10)) {
            $out['meilleur-bilan'] = self::stat('meilleur-bilan', self::pct($rec[$k]['v'] / $rec[$k]['m']), t('de victoires'), t('Le client idéal'),
                t('Contre {club} : {vnd} (10 matchs au moins).', ['club' => self::clubName($k), 'vnd' => self::vnd($rec[$k])]), [self::club($k)]);
        }
        if ($k = $pick(fn ($r) => -$r['v'] / $r['m'], 10)) {
            $out['pire-bilan'] = self::stat('pire-bilan', self::pct($rec[$k]['v'] / $rec[$k]['m']), t('de victoires seulement'), t('L’os'),
                t('Contre {club} : {vnd} (10 matchs au moins).', ['club' => self::clubName($k), 'vnd' => self::vnd($rec[$k])]), [self::club($k)]);
        }
        $out['adversaires-differents'] = self::stat('adversaires-differents', self::num(count($by)), t('clubs différents'), t('Les adversaires'),
            t('Affrontés en match officiel dans les matchs racontés, de la Ligue 1 aux clubs amateurs de la Coupe de France.'));
        $k = $pick(fn ($r) => $r['gf']);
        $out['buts-contre'] = self::stat('buts-contre', self::num($rec[$k]['gf']), self::unit($rec[$k]['gf'], 'but marqué', 'buts marqués'), t('Le punching-ball'),
            t('Contre {club}, en {m} matchs officiels.', ['club' => self::clubName($k), 'm' => $rec[$k]['m']]), [self::club($k)]);
        $east = array_values(array_filter(self::EAST, fn ($id) => isset($by[$id])));
        if ($east) {
            $r = self::record(array_merge(...array_map(fn ($id) => $by[$id], $east)));
            $out['derbys'] = self::stat('derbys', self::num($r['m']), t('matchs officiels'), t('Les derbys de l’Est'),
                ucfirst(self::vnd($r)) . ' ' . t('contre {clubs}.', ['clubs' => self::andList(array_map(fn ($id) => self::clubName($id), $east))]));
        }
        // Arbitres.
        $refs = [];
        foreach ($c['X'] as $mid => $x) {
            if ($x['ref'] && isset($O[$mid])) {
                $refs[mb_strtolower($x['ref'])][] = $mid;
            }
        }
        if ($refs) {
            uasort($refs, fn ($a, $b) => count($b) <=> count($a));
            $k = array_key_first($refs);
            $ids = $refs[$k];
            $out['arbitre'] = self::stat('arbitre', self::num(count($ids)), t('matchs arbitrés'), t('L’arbitre le plus croisé'),
                t('{ref} : {vnd}.', ['ref' => $c['X'][$ids[0]]['ref'], 'vnd' => self::vnd(self::record(array_map(fn ($id) => $O[$id], $ids)))]));
        }
        // Petits poucets : défaites en coupe face à un club de division inférieure.
        $rankLv = fn (?string $l) => match (true) {
            $l === null || $l === '' => null,
            (bool) preg_match('/^(D1|L1)$/', $l) => 1,
            (bool) preg_match('/^(D2|L2)$/', $l) => 2,
            (bool) preg_match('/^(N|N1|D3)$/', $l) => 3,
            (bool) preg_match('/^(N2|D4|CFA)$/', $l) => 4,
            (bool) preg_match('/^(N3|D5|CFA2)$/', $l) => 5,
            (bool) preg_match('/^(R1|DH|R2|DHR|PH|R3)$/', $l) => 6,
            default => null,
        };
        $upsets = [];
        foreach ($c['X'] as $mid => $x) {
            $m = $O[$mid] ?? null;
            if (!$m || !str_starts_with($m['comp'], 'Coupe') || $m['result'] !== 'D') {
                continue;
            }
            [$us, $them] = [$rankLv($x['lv'][0]), $rankLv($x['lv'][1])];
            if ($us && $them && $them > $us) {
                $upsets[$mid] = $them - $us;
            }
        }
        if ($upsets) {
            uksort($upsets, fn ($a, $b) => $upsets[$b] <=> $upsets[$a] ?: strcmp($O[$b]['date'], $O[$a]['date']));
            $x = $O[array_key_first($upsets)];
            $out['petits-poucets'] = self::stat('petits-poucets', self::num(count($upsets)), self::unit(count($upsets), 'élimination', 'éliminations'), t('Les petits poucets'),
                t('Défaites en coupe face à un club de division inférieure. Le plus grand écart : {opp}, le {date}.', ['opp' => self::opp($x), 'date' => date_fr($x['date'])]),
                [self::match($x)]);
        }
        $eu = array_filter($O, fn ($x) => $x['comp'] === "Coupe d'Europe");
        if ($eu) {
            $r = self::record($eu);
            $out['europe'] = self::stat('europe', self::num($r['m']), self::unit($r['m'], 'match européen', 'matchs européens'), t('Les soirées européennes'),
                ucfirst(self::vnd($r)) . ' ' . t('dans les matchs racontés.'));
        }
        return $out;
    }

    // ------------------------------------------------------------------ 9. Bonal et les tribunes

    private static function bonal(array $c): array
    {
        $O = $c['O'];
        $out = [];
        $att = array_filter($O, fn ($x) => (int) $x['spectators'] > 0);
        $home = array_filter($att, fn ($x) => $x['sh'] && $x['stade'] === self::BONAL);
        $line = fn (array $x) => t('Contre {opp}, le {date}.', ['opp' => self::opp($x), 'date' => date_fr($x['date'])]);
        if ($att) {
            $l = array_values($att);
            usort($l, fn ($a, $b) => $b['spectators'] <=> $a['spectators']);
            $x = $l[0];
            $out['affluence-record'] = self::stat('affluence-record', self::num($x['spectators']), t('spectateurs'), t('L’affluence record'),
                $line($x) . ' ' . ($x['sh'] ? t('À domicile.') : t('À l’extérieur, au {stade}.', ['stade' => Explore::stadiumName((string) $x['stade'])])), [self::match($x)]);
        }
        if ($home) {
            $l = array_values($home);
            usort($l, fn ($a, $b) => $b['spectators'] <=> $a['spectators']);
            $out['affluence-bonal'] = self::stat('affluence-bonal', self::num($l[0]['spectators']), t('spectateurs'), t('Bonal plein à craquer'),
                $line($l[0]) . ' ' . t('Le record des matchs officiels racontés à Bonal.'), [self::match($l[0])]);
            $tot = array_sum(array_column($home, 'spectators'));
            $out['moyenne-bonal'] = self::stat('moyenne-bonal', self::num($tot / count($home)), t('spectateurs par match'), t('La moyenne à Bonal'),
                t('Sur {m} matchs officiels à domicile dont l’affluence est connue.', ['m' => self::num(count($home))]));
            $out['spectateurs'] = self::stat('spectateurs', self::num($tot), t('spectateurs à Bonal'), t('Le peuple jaune et bleu'),
                t('Cumul des affluences des matchs officiels racontés à Bonal.'));
            // Meilleure saison et meilleure décennie de public.
            foreach ([['saison-public', 'season', 'La saison des foules', 'Moyenne à Bonal en {k}, la meilleure des saisons racontées.'],
                ['decennie-public', 'decade', 'La décennie des foules', 'Moyenne à Bonal dans les {k}.']] as [$key, $f, $label, $text]) {
                $g = [];
                foreach ($home as $x) {
                    if ($x[$f] !== null && ($f !== 'season' || in_array($x['season'], $c['full'], true))) {
                        $g[$x[$f]][] = $x['spectators'];
                    }
                }
                $g = array_filter($g, fn ($l) => count($l) >= ($f === 'decade' ? 60 : 10));
                if ($g) {
                    $avg = array_map(fn ($l) => array_sum($l) / count($l), $g);
                    arsort($avg);
                    $k = array_key_first($avg);
                    $out[$key] = self::stat($key, self::num($avg[$k]), t('spectateurs de moyenne'), t($label),
                        t($text, ['k' => $f === 'decade' ? decade_label((int) $k, true, true) : $k]));
                }
            }
            $l = array_values($home);
            usort($l, fn ($a, $b) => $a['spectators'] <=> $b['spectators']);
            $x = $l[0];
            $out['affluence-faible'] = self::stat('affluence-faible', self::num($x['spectators']), t('spectateurs'), t('L’intimité'),
                t('La plus faible affluence connue à Bonal en match officiel. {line}', ['line' => $line($x)]), [self::match($x)]);
        }
        $bon = array_filter($O, fn ($x) => $x['sh'] && $x['stade'] === self::BONAL && $x['result']);
        if ($bon) {
            $r = self::record($bon);
            $out['bilan-bonal'] = self::stat('bilan-bonal', self::pct($r['v'] / $r['m']), t('de victoires à Bonal'), t('La forteresse'),
                ucfirst(self::vnd($r)) . ' ' . t('en {m} matchs officiels à domicile.', ['m' => self::num($r['m'])]));
        }
        $stades = [];
        $away = [];
        foreach ($O as $x) {
            if ($x['stade']) {
                $stades[$x['stade']] = true;
                if (!$x['sh'] && $x['stade'] !== self::BONAL) {
                    $away[$x['stade']] = ($away[$x['stade']] ?? 0) + 1;
                }
            }
        }
        if ($stades) {
            $out['stades'] = self::stat('stades', self::num(count($stades)), t('stades différents'), t('Le tour de France des stades'),
                t('Où Sochaux a disputé au moins un match officiel raconté, Bonal compris.'));
        }
        if ($away) {
            arsort($away);
            $k = array_key_first($away);
            $out['stade-visite'] = self::stat('stade-visite', self::num($away[$k]), self::unit($away[$k], 'déplacement', 'déplacements'), t('La résidence secondaire'),
                t('Le stade le plus visité à l’extérieur : {stade}.', ['stade' => Explore::stadiumName((string) $k)]));
        }
        return $out;
    }

    // ------------------------------------------------------------------ 10. Saisons et calendrier

    private static function calendrier(array $c): array
    {
        $O = array_filter($c['O'], fn ($x) => $x['result'] !== null);
        $out = [];
        $seasons = [];
        foreach ($O as $x) {
            if (in_array($x['season'], $c['full'], true)) {
                $seasons[$x['season']][] = $x;
            }
        }
        $seasons = array_filter($seasons, fn ($l) => count($l) >= self::FULL_SEASON);
        if ($seasons) {
            $rec = array_map(fn ($l) => self::record($l), $seasons);
            uksort($rec, fn ($a, $b) => $rec[$b]['v'] / $rec[$b]['m'] <=> $rec[$a]['v'] / $rec[$a]['m']);
            $s = array_key_first($rec);
            $out['saison-or'] = self::stat('saison-or', self::pct($rec[$s]['v'] / $rec[$s]['m']), t('de victoires'), t('La saison en or'),
                t('{s} : {vnd} en matchs officiels.', ['s' => $s, 'vnd' => self::vnd($rec[$s])]));
            $league = [];
            foreach ($seasons as $s => $l) {
                $ch = array_filter($l, fn ($x) => $x['comp'] === 'Championnat');
                if (count($ch) >= self::FULL_SEASON) {
                    $league[$s] = self::record($ch);
                }
            }
            if ($league) {
                uksort($league, fn ($a, $b) => $league[$b]['gf'] / $league[$b]['m'] <=> $league[$a]['gf'] / $league[$a]['m']);
                $s = array_key_first($league);
                $out['attaque-saison'] = self::stat('attaque-saison', self::num($league[$s]['gf'] / $league[$s]['m'], 2), t('buts par match'), t('L’attaque de feu'),
                    t('En championnat, saison {s} : {g} buts en {m} matchs.', ['s' => $s, 'g' => $league[$s]['gf'], 'm' => $league[$s]['m']]));
            }
            $len = array_map('count', $seasons);
            arsort($len);
            $s = array_key_first($len);
            $out['saison-marathon'] = self::stat('saison-marathon', self::num($len[$s]), t('matchs officiels'), t('La saison marathon'),
                t('En {s}, championnat et coupes réunis.', ['s' => $s]));
            uksort($rec, fn ($a, $b) => $rec[$a]['v'] / $rec[$a]['m'] <=> $rec[$b]['v'] / $rec[$b]['m']);
            $s = array_key_first($rec);
            $out['saison-noire'] = self::stat('saison-noire', self::pct($rec[$s]['v'] / $rec[$s]['m']), t('de victoires seulement'), t('La saison noire'),
                t('{s} : {vnd} en matchs officiels.', ['s' => $s, 'vnd' => self::vnd($rec[$s])]));
        }
        // Mois et jours.
        $months = $days = $dates = [];
        foreach ($O as $x) {
            $ts = strtotime($x['date']);
            $months[(int) date('n', $ts)][] = $x;
            $days[(int) date('w', $ts)][] = $x;
            $dates[substr($x['date'], 5, 5)] = ($dates[substr($x['date'], 5, 5)] ?? 0) + 1;
        }
        $mr = array_filter(array_map(fn ($l) => self::record($l), $months), fn ($r) => $r['m'] >= 40);
        if ($mr) {
            uksort($mr, fn ($a, $b) => $mr[$b]['v'] / $mr[$b]['m'] <=> $mr[$a]['v'] / $mr[$a]['m']);
            $k = array_key_first($mr);
            $out['mois'] = self::stat('mois', self::months()[$k - 1], self::pct($mr[$k]['v'] / $mr[$k]['m']) . ' ' . t('de victoires'), t('Le mois porte-bonheur'),
                ucfirst(self::vnd($mr[$k])) . ' ' . t('en {m} matchs officiels.', ['m' => self::num($mr[$k]['m'])]));
            $k = array_key_last($mr);
            $out['mois-maudit'] = self::stat('mois-maudit', self::months()[$k - 1], self::pct($mr[$k]['v'] / $mr[$k]['m']) . ' ' . t('de victoires'), t('Le mois maudit'),
                ucfirst(self::vnd($mr[$k])) . ' ' . t('en {m} matchs officiels.', ['m' => self::num($mr[$k]['m'])]));
        }
        if ($days) {
            $cnt = array_map('count', $days);
            arsort($cnt);
            $k = array_key_first($cnt);
            $dr = array_filter(array_map(fn ($l) => self::record($l), $days), fn ($r) => $r['m'] >= 30);
            uksort($dr, fn ($a, $b) => $dr[$b]['v'] / $dr[$b]['m'] <=> $dr[$a]['v'] / $dr[$a]['m']);
            $best = array_key_first($dr);
            // Aucun jour avec 30 matchs (base encore mince) : pas de « jour le plus faste ».
            $out['jour'] = self::stat('jour', self::weekdays()[$k], self::pct($cnt[$k] / count($O)) . ' ' . t('des matchs'), t('Le jour du match'),
                $best === null ? t('Le jour le plus joué.') : t('Le jour le plus joué. Le plus faste : le {d}, avec {p} de victoires.', ['d' => self::weekdays()[$best], 'p' => self::pct($dr[$best]['v'] / $dr[$best]['m'])]));
        }
        if ($dates) {
            arsort($dates);
            $k = array_key_first($dates);
            $out['date-fetiche'] = self::stat('date-fetiche', self::dayMonth($k), self::unit($dates[$k], '{n} match', '{n} matchs', ['n' => $dates[$k]]), t('La date fétiche'),
                t('Le jour de l’année où Sochaux a disputé le plus de matchs officiels racontés.'));
        }
        $dec = [];
        foreach ($O as $x) {
            if ($x['decade']) {
                $dec[$x['decade']][] = $x;
            }
        }
        $dec = array_filter(array_map(fn ($l) => self::record($l), $dec), fn ($r) => $r['m'] >= 100);
        if ($dec) {
            uksort($dec, fn ($a, $b) => $dec[$b]['v'] / $dec[$b]['m'] <=> $dec[$a]['v'] / $dec[$a]['m']);
            $k = array_key_first($dec);
            $out['decennie'] = self::stat('decennie', decade_label((int) $k), self::pct($dec[$k]['v'] / $dec[$k]['m']) . ' ' . t('de victoires'), t('La décennie dorée'),
                ucfirst(self::vnd($dec[$k])) . ' ' . t('en {m} matchs officiels racontés.', ['m' => self::num($dec[$k]['m'])]));
        }
        return $out;
    }

    // ------------------------------------------------------------------ 11. Les Lions en portrait

    private static function portraits(array $c): array
    {
        $O = $c['O'];
        $P = $c['P'];
        $out = [];
        // Âges : premier match, dernier match, buts. Date de naissance invraisemblable écartée :
        // moins de 15 ans ou plus de 45 ans le jour du match, plus de 36 ans à l'arrivée au club
        // (première saison du tableau de carrière ou premier match raconté).
        $okBirth = [];
        foreach ($P as $pid => $p) {
            if (empty($p['birth'])) {
                continue;
            }
            $by = (int) substr($p['birth'], 0, 4);
            $from = $p['career']['from'] ?? null;
            $okBirth[$pid] = $from === null || ($from - $by >= 14 && $from - $by <= 36);
        }
        $firstAge = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && !empty($okBirth[$a[0]])) {
                $y = self::age($P[$a[0]]['birth'], $O[$a[1]]['date'])[0];
                $firstAge[$a[0]] = min($firstAge[$a[0]] ?? 99, $y);
            }
        }
        foreach ($firstAge as $pid => $y) {
            if ($y > 36) {
                $okBirth[$pid] = false;
            }
        }
        $ages = ['first' => [], 'last' => [], 'firstGoal' => [], 'lastGoal' => []];
        foreach ($c['apps'] as $a) {
            if ($a[6] !== 'player' || empty($okBirth[$a[0]]) || isset($c['bad_date'][$a[1]])) {
                continue;
            }
            $age = self::age($P[$a[0]]['birth'], $O[$a[1]]['date']);
            if ($age[0] < 15 || $age[0] > 45) {
                continue;
            }
            $days = $age[0] * 366 + $age[1];
            foreach (['first' => [true, false], 'last' => [false, false], 'firstGoal' => [true, true], 'lastGoal' => [false, true]] as $k => [$min, $goal]) {
                $cur = $ages[$k][$a[0]] ?? null;
                if ((!$goal || $a[2] > 0) && (!$cur || ($min ? $days < $cur[0] : $days > $cur[0]))) {
                    $ages[$k][$a[0]] = [$days, $age, $a[1]];
                }
            }
        }
        ['first' => $first, 'last' => $last, 'firstGoal' => $firstGoal, 'lastGoal' => $lastGoal] = $ages;
        foreach ([['plus-jeune', $first, true, 'Le plus jeune Lionceau', 'À son premier match officiel, contre {opp}, le {date}.'],
            ['doyen', $last, false, 'Le doyen sur le terrain', 'À son dernier match officiel, contre {opp}, le {date}.'],
            ['jeune-buteur', $firstGoal, true, 'Le plus jeune buteur', 'Contre {opp}, le {date}.'],
            ['vieux-buteur', $lastGoal, false, 'Le buteur le plus âgé', 'Contre {opp}, le {date}.']] as [$key, $arr, $asc, $label, $text]) {
            if (!$arr) {
                continue;
            }
            $r = self::rank(array_map(fn ($v) => $v[0], $arr), 1, $asc);
            if ($r) {
                [$p] = $r[0];
                [, $age, $mid] = $arr[$p['id']];
                $x = $O[$mid];
                $out[$key] = self::stat($key, (string) $age[0], self::ageUnit($age), t($label), t($text, ['opp' => self::opp($x), 'date' => date_fr($x['date'])]),
                    [$p, self::match($x)], 'fiches');
            }
        }
        // Le onze le plus jeune (âge moyen des titulaires).
        $starters = [];
        foreach ($c['apps'] as $a) {
            if ($a[6] === 'player' && !in_array($a[8], ['R', 'E'], true)) {
                $starters[$a[1]][] = $a[0];
            }
        }
        $avg = [];
        foreach ($starters as $mid => $pids) {
            if (isset($c['bad_date'][$mid])) {
                continue;
            }
            $ages = [];
            foreach ($pids as $pid) {
                if (!empty($okBirth[$pid])) {
                    $a = self::age($P[$pid]['birth'], $O[$mid]['date']);
                    $ages[] = $a[0] + $a[1] / 365.25;
                }
            }
            if (count($pids) >= 10 && count($ages) >= 10) {
                $avg[$mid] = array_sum($ages) / count($ages);
            }
        }
        if ($avg) {
            asort($avg);
            $mid = array_key_first($avg);
            $y = (int) floor($avg[$mid]);
            $m = (int) floor(($avg[$mid] - $y) * 12);
            $out['onze-jeune'] = self::stat('onze-jeune', (string) $y, $m >= 2 ? t('ans et {m} mois de moyenne', ['m' => $m]) : t('ans de moyenne'), t('Le onze le plus jeune'),
                t('Âge moyen des titulaires contre {opp}, le {date}.', ['opp' => self::opp($O[$mid]), 'date' => date_fr($O[$mid]['date'])]), [self::match($O[$mid])], 'fiches');
        }
        // Tailles, pieds, origines (joueurs publiés).
        $players = [];
        foreach ($P as $pid => $p) {
            if ($p['player'] && self::person((int) $pid)) {
                $players[$pid] = $p;
            }
        }
        $h = array_filter(array_map(fn ($p) => $p['height'], $players), fn ($v) => $v && $v >= 150 && $v <= 210);
        if ($h) {
            $r = self::rank($h, 3);
            $out['geant'] = self::stat('geant', self::num($r[0][1] / 100, 2), self::unit($r[0][1] / 100, 'mètre', 'mètres'), t('Le géant'),
                t('Le plus grand des {n} Lionceaux dont la taille est connue.', ['n' => self::num(count($h))]), [$r[0][0]], 'fiches', self::more($r, fn ($v) => self::num($v / 100, 2)));
            $r = self::rank($h, 3, true);
            $out['petit'] = self::stat('petit', self::num($r[0][1] / 100, 2), self::unit($r[0][1] / 100, 'mètre', 'mètres'), t('Le plus petit gabarit'),
                t('La preuve que le football n’est pas qu’une affaire de taille.'), [$r[0][0]], 'fiches', self::more($r, fn ($v) => self::num($v / 100, 2)));
        }
        $feet = array_count_values(array_filter(array_map(fn ($p) => $p['foot'], $players)));
        $known = array_sum($feet);
        if ($known >= 50) {
            $out['gauchers'] = self::stat('gauchers', self::pct(($feet['g'] ?? 0) / $known), t('des joueurs'), t('Les gauchers'),
                t('{g} gauchers, {d} droitiers et {a} ambidextres parmi les {n} fiches qui le précisent.', ['g' => $feet['g'] ?? 0, 'd' => $feet['d'] ?? 0, 'a' => $feet['a'] ?? 0, 'n' => $known]), [], 'fiches');
        }
        $fc = array_filter($players, fn ($p) => isset(self::FRANCHE_COMTE[$p['dept']]));
        if ($fc) {
            $doubs = count(array_filter($fc, fn ($p) => $p['dept'] === '25'));
            $out['enfants-du-pays'] = self::stat('enfants-du-pays', self::num(count($fc)), t('Lionceaux nés en Franche-Comté'), t('Les enfants du pays'),
                t('Dont {d} dans le Doubs, sur les fiches dont le lieu de naissance est connu.', ['d' => $doubs]), [], 'fiches');
        }
        $countries = [];
        foreach ($players as $p) {
            $k = trim($p['country']);
            if ($k !== '' && mb_strlen($k) > 2) {
                $countries[$k] = ($countries[$k] ?? 0) + 1;
            }
        }
        if (count($countries) >= 3) {
            arsort($countries);
            $abroad = array_slice(array_filter($countries, fn ($k) => $k !== 'France', ARRAY_FILTER_USE_KEY), 0, 3, true);
            $out['pays'] = self::stat('pays', self::num(count($countries)), t('pays de naissance'), t('Le monde en jaune et bleu'),
                t('Après la France, en tête : {list}.', ['list' => implode(', ', array_map(fn ($k, $n) => t($k) . " ($n)", array_keys($abroad), $abroad))]), [], 'fiches');
        }
        $formed = count(array_filter($players, fn ($p) => $p['formed']));
        if ($formed) {
            $out['formes'] = self::stat('formes', self::num($formed), t('joueurs formés au club'), t('Made in Sochaux'),
                t('Sur {n} fiches de joueurs : le centre de formation en chiffres.', ['n' => self::num(count($players))]), [], 'fiches');
        }
        $names = [];
        foreach ($players as $p) {
            $f = trim(explode(' ', $p['first'])[0] ?? '');
            if (mb_strlen($f) >= 2) {
                $names[$f] = ($names[$f] ?? 0) + 1;
            }
        }
        if ($names) {
            arsort($names);
            $k = array_key_first($names);
            $out['prenom'] = self::stat('prenom', $k, self::unit($names[$k], '{n} joueur', '{n} joueurs', ['n' => $names[$k]]), t('Le prénom le plus porté'),
                t('Chez les Lionceaux, devant {list}.', ['list' => implode(', ', array_map(fn ($k, $n) => "$k ($n)", array_slice(array_keys($names), 1, 3), array_slice($names, 1, 3)))]), [], 'fiches');
        }
        $intl = count(array_filter($players, fn ($p) => $p['intl']));
        if ($intl) {
            $out['internationaux'] = self::stat('internationaux', self::num($intl), t('internationaux'), t('Les internationaux'),
                t('Joueurs passés par Sochaux et sélectionnés en équipe nationale, toutes catégories et tous pays confondus.'), [], 'fiches');
        }
        $cities = [];
        foreach ($players as $p) {
            if (preg_match('/\p{L}{3}/u', $p['city']) && !preg_match('/^x+$/iu', $p['city'])) {
                $cities[$p['city']] = ($cities[$p['city']] ?? 0) + 1;
            }
        }
        if ($cities) {
            arsort($cities);
            $k = array_key_first($cities);
            $out['ville'] = self::stat('ville', $k, self::unit($cities[$k], '{n} joueur', '{n} joueurs', ['n' => $cities[$k]]), t('La ville natale la plus représentée'),
                t('Le berceau le plus fréquent des Lionceaux.'), [], 'fiches');
        }
        return $out;
    }
}
