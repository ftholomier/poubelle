<?php
declare(strict_types=1);

namespace App\Data;

use App\Core\JsonStore;
use RuntimeException;

/**
 * Fiches (matchs, personnes, articles, pages, objets, moments) : un fichier JSON
 * par fiche dans data/fiches/{id}.json.
 *
 * Chaque enregistrement crée une version (storage/versions/{id}/) : qui, quand,
 * quoi (différences), restauration possible.
 */
final class Fiches
{
    public const DIR = DATA_PATH . '/fiches';
    public const VERSIONS = STORAGE_PATH . '/versions';
    /** Les fiches créées dans le back-office ont un identifiant ≥ 1 000 000 (les importées gardent l'id WordPress). */
    public const FIRST_NEW_ID = 1000000;

    public const TYPES = [
        'match' => 'Match',
        'personne' => 'Personne',
        'article' => 'Article',
        'page' => 'Page',
        'objet' => 'Objet',
        'moment' => 'Moment du centenaire',
    ];

    public const STATUSES = [
        'brouillon' => 'Brouillon',
        'relire' => 'À relire',
        'planifie' => 'Planifié',
        'publie' => 'Publié',
        'corbeille' => 'Corbeille',
    ];

    public static function path(int $id): string
    {
        return self::DIR . "/$id.json";
    }

    public static function get(int $id): ?array
    {
        $doc = JsonStore::read(self::path($id));
        return self::usable($doc, $id) ? self::normalize($doc) : null;
    }

    /** @var array<int,list<string>> champs réparés à la lecture, par fiche (signalés dans Qualité) */
    private static array $repaired = [];

    /** Fiches lues avec des champs d'un type inattendu (fichier retouché à la main) : fiche => champs. */
    public static function repaired(): array
    {
        return self::$repaired;
    }

    /**
     * Types attendus des champs lus par les pages, les calculs et le back-office : un fichier
     * retouché à la main (« 2 » ou 2.0 au lieu de 2, une liste écrite comme un texte, une date en
     * nombre) est lu comme s'il était bien formé, au lieu de faire échouer la page. Rien n'est
     * écrit : la fiche est réparée à son prochain enregistrement, et signalée dans Qualité d'ici là.
     * Une fiche bien formée n'est pas modifiée.
     */
    private static function normalize(array $doc): array
    {
        $fixed = [];
        // Texte : un nombre devient du texte, une liste un texte vide (null permis).
        $str = function (array &$a, array $keys, string $at) use (&$fixed): void {
            foreach ($keys as $k) {
                if (array_key_exists($k, $a) && $a[$k] !== null && !is_string($a[$k])) {
                    $a[$k] = is_scalar($a[$k]) ? (string) $a[$k] : '';
                    $fixed[] = $at . $k;
                }
            }
        };
        // Entier (buts, spectateurs) : « 2 » ou 2.0 → 2 ; illisible → null.
        $int = function (array &$a, array $keys, string $at) use (&$fixed): void {
            foreach ($keys as $k) {
                if (array_key_exists($k, $a) && $a[$k] !== null && !is_int($a[$k])) {
                    $a[$k] = is_numeric($a[$k]) && (float) $a[$k] === floor((float) $a[$k]) ? (int) $a[$k] : null;
                    $fixed[] = $at . $k;
                }
            }
        };
        // Liste : un texte devient une liste d'un élément (minutes de buts, cartons) ou une liste vide.
        $list = function (array &$a, array $keys, string $at, bool $strings = false) use (&$fixed): void {
            foreach ($keys as $k) {
                if (array_key_exists($k, $a) && $a[$k] !== null && !is_array($a[$k])) {
                    $a[$k] = $strings && is_scalar($a[$k]) && (string) $a[$k] !== '' ? [(string) $a[$k]] : [];
                    $fixed[] = $at . $k;
                } elseif ($strings && is_array($a[$k] ?? null)) {
                    foreach ($a[$k] as $i => $v) {
                        if (!is_string($v)) {
                            $a[$k][$i] = is_scalar($v) ? (string) $v : '';
                            $fixed[] = $at . $k;
                        }
                    }
                }
            }
        };
        // Liste d'éléments structurés : ceux qui n'en sont pas sont écartés.
        $items = function (array &$a, array $keys, string $at) use (&$fixed, $list): void {
            $list($a, $keys, $at);
            foreach ($keys as $k) {
                if (is_array($a[$k] ?? null) && count(array_filter($a[$k], 'is_array')) !== count($a[$k])) {
                    $a[$k] = array_values(array_filter($a[$k], 'is_array'));
                    $fixed[] = $at . $k;
                }
            }
        };
        // Bloc (score, naissance…) : autre chose qu'un tableau devient null.
        $map = function (array &$a, array $keys, string $at) use (&$fixed): void {
            foreach ($keys as $k) {
                if (array_key_exists($k, $a) && $a[$k] !== null && !is_array($a[$k])) {
                    $a[$k] = null;
                    $fixed[] = $at . $k;
                }
            }
        };

        $str($doc, ['title', 'path', 'status', 'slug', 'intro', 'publish_at', 'featured_image', 'date', 'modified'], '');
        $list($doc, ['categories'], '', true);
        $map($doc, ['seo', 'key_figure'], '');
        if (is_array($doc['seo'] ?? null)) {
            $str($doc['seo'], ['title', 'description'], 'seo.');
        }
        if (is_array($doc['key_figure'] ?? null)) {
            $str($doc['key_figure'], ['number', 'text'], 'key_figure.');
        }
        $items($doc, ['sections', 'gallery', 'images', 'videos', 'embeds', 'tables'], '');
        foreach (array_keys($doc['sections'] ?? []) as $i) {
            $str($doc['sections'][$i], ['title', 'html'], 'sections.');
        }
        $list($doc, ['i18n', 'legacy'], '');
        if ($doc['type'] === 'match') {
            $m = &$doc['match'];
            $str($m, ['date', 'date_text', 'season', 'competition', 'competition_label', 'round', 'round_text', 'result', 'stadium', 'spectators_text', 'referee', 'goals_text', 'event', 'score_raw', 'score_line'], 'match.');
            $int($m, ['spectators'], 'match.');
            foreach (['home', 'away'] as $side) {
                if (array_key_exists($side, $m) && !is_array($m[$side])) {
                    $m[$side] = ['name' => is_scalar($m[$side]) ? (string) $m[$side] : '', 'level' => null];
                    $fixed[] = "match.$side";
                }
                if (is_array($m[$side] ?? null)) {
                    $str($m[$side], ['name', 'level'], "match.$side.");
                }
            }
            $map($m, ['score', 'lineup'], 'match.');
            if (is_array($m['score'] ?? null)) {
                $int($m['score'], ['home', 'away'], 'match.score.');
                $str($m['score'], ['extra'], 'match.score.');
                $map($m['score'], ['pens'], 'match.score.');
                if (is_array($m['score']['pens'] ?? null)) {
                    $int($m['score']['pens'], ['home', 'away'], 'match.score.pens.');
                }
            }
            $items($m, ['goals', 'highlights', 'other_lineups'], 'match.');
            $list($m, ['header_extra'], 'match.', true);
            foreach (array_keys($m['goals'] ?? []) as $i) {
                $str($m['goals'][$i], ['team', 'scorers'], 'match.goals.');
            }
            foreach (array_keys($m['highlights'] ?? []) as $i) {
                $str($m['highlights'][$i], ['minute', 'text', 'score'], 'match.highlights.');
            }
            foreach (['reactions', 'breves'] as $k) {
                $list($m, [$k], 'match.');
                foreach ($m[$k] ?? [] as $i => $r) {
                    if (is_array($r)) {
                        $str($m[$k][$i], ['text', 'who'], "match.$k.");
                    } elseif (!is_string($r)) {
                        $m[$k][$i] = is_scalar($r) ? (string) $r : '';
                        $fixed[] = "match.$k";
                    }
                }
            }
            if (is_array($m['lineup'] ?? null)) {
                $items($m['lineup'], ['rows'], 'match.lineup.');
                foreach (array_keys($m['lineup']['rows'] ?? []) as $i) {
                    $r = &$m['lineup']['rows'][$i];
                    $str($r, ['name', 'position', 'number', 'sub_in', 'sub_out', 'goals_text', 'sub_text', 'cards_text'], 'match.lineup.rows.');
                    $list($r, ['goals', 'own_goals', 'yellow', 'red'], 'match.lineup.rows.', true);
                    unset($r);
                }
            }
            unset($m);
        } elseif ($doc['type'] === 'personne') {
            $p = &$doc['personne'];
            $str($p, ['first_name', 'last_name', 'display_name', 'nickname', 'subtitle', 'position', 'line', 'nationality', 'height', 'weight', 'foot',
                'first_match', 'last_match', 'first_goal', 'first_match_coached', 'last_match_coached', 'shirt_numbers'], 'personne.');
            $int($p, ['height_cm', 'weight_kg'], 'personne.');
            $list($p, ['roles', 'honours', 'then', 'aliases'], 'personne.', true);
            $list($p, ['international', 'highlight_matches'], 'personne.');
            $items($p, ['fiche'], 'personne.');
            foreach (array_keys($p['fiche'] ?? []) as $i) {
                $str($p['fiche'][$i], ['label', 'value'], 'personne.fiche.');
            }
            $map($p, ['birth', 'death', 'arrival', 'departure', 'arrival_coach', 'departure_coach', 'stats', 'album', 'trial'], 'personne.');
            foreach (['birth', 'death'] as $k) {
                if (is_array($p[$k] ?? null)) {
                    $str($p[$k], ['text'], "personne.$k.");
                    $map($p[$k], ['date', 'place'], "personne.$k.");
                    if (is_array($p[$k]['date'] ?? null)) {
                        $str($p[$k]['date'], ['iso', 'precision', 'text'], "personne.$k.date.");
                    }
                    if (is_array($p[$k]['place'] ?? null)) {
                        $str($p[$k]['place'], ['text', 'city', 'country', 'department'], "personne.$k.place.");
                    }
                }
            }
            foreach (['arrival', 'departure', 'arrival_coach', 'departure_coach'] as $k) {
                if (is_array($p[$k] ?? null)) {
                    $str($p[$k], ['iso', 'precision', 'text'], "personne.$k.");
                }
            }
            unset($p);
        }
        $id = (int) $doc['id'];
        if ($fixed) {
            self::$repaired[$id] = array_values(array_unique($fixed));
        } else {
            unset(self::$repaired[$id]);
        }
        return $doc;
    }

    /**
     * Fichier de fiche utilisable : un objet JSON qui porte le numéro de son fichier, un type
     * connu et, pour un match ou une personne, ses données. Sinon la fiche est ignorée partout
     * (et signalée dans Qualité) au lieu de casser les pages qui la lisent.
     */
    private static function usable(mixed $doc, int $id): bool
    {
        return is_array($doc) && $id > 0 && is_scalar($doc['id'] ?? null) && (int) $doc['id'] === $id
            && is_string($doc['type'] ?? null) && isset(self::TYPES[$doc['type']])
            && (!in_array($doc['type'], ['match', 'personne'], true) || is_array($doc[$doc['type']] ?? null));
    }

    /** Version enregistrée sur le disque (sans la copie en mémoire du processus). */
    public static function fresh(int $id): ?array
    {
        JsonStore::forget(self::path($id));
        return self::get($id);
    }

    /** @return \Generator<int,array> */
    public static function all(): \Generator
    {
        foreach (glob(self::DIR . '/*.json') ?: [] as $file) {
            $id = (int) basename($file, '.json');
            $doc = json_decode((string) file_get_contents($file), true);
            if (self::usable($doc, $id)) {
                yield $id => self::normalize($doc);
            }
        }
    }

    /**
     * Moment du centenaire : tant que sa semaine (case n° X du calendrier) n'est pas arrivée,
     * il reste « planifié » pour ce jour-là à 8 h, même marqué « publié » ou déplacé.
     */
    public static function scheduleMoment(array $doc): array
    {
        $n = (int) ($doc['moment']['number'] ?? 0);
        if (($doc['type'] ?? '') !== 'moment' || $n < 1 || $n > 100 || !in_array($doc['status'] ?? '', ['publie', 'planifie'], true)) {
            return $doc;
        }
        $start = strtotime((string) \App\Core\Settings::get('centenary.moments_start', '2026-06-11')) ?: strtotime('2026-06-11');
        $slot = strtotime(date('Y-m-d', strtotime('+' . (($n - 1) * 7) . ' days', $start)) . ' 08:00');
        if ($slot > time()) {
            $doc['status'] = 'planifie';
            $doc['publish_at'] = date('c', $slot);
        }
        return $doc;
    }

    public static function isVisible(array $doc): bool
    {
        $s = $doc['status'] ?? 'publie';
        if ($s === 'publie') {
            return true;
        }
        return $s === 'planifie' && !empty($doc['publish_at']) && strtotime($doc['publish_at']) <= time();
    }

    public static function nextId(): int
    {
        return JsonStore::update(STORAGE_PATH . '/counters.json', function ($c) {
            $c = $c ?: [];
            $max = $c['fiche'] ?? 0;
            if ($max < self::FIRST_NEW_ID) {
                foreach (glob(self::DIR . '/*.json') ?: [] as $f) {
                    $max = max($max, (int) basename($f, '.json'));
                }
                $max = max($max, self::FIRST_NEW_ID - 1);
            }
            $c['fiche'] = $max + 1;
            return $c;
        }, [])['fiche'];
    }

    /** Squelette d'une nouvelle fiche. */
    public static function blank(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException("Type de fiche inconnu : $type");
        }
        $doc = [
            'id' => 0,
            'type' => $type,
            'status' => 'brouillon',
            'publish_at' => null,
            'slug' => '',
            'path' => '',
            'title' => '',
            'categories' => [],
            'a_la_une' => false,
            'featured_image' => null,
            'date' => date('c'),
            'modified' => date('c'),
            'author' => null,
            'seo' => ['title' => '', 'description' => ''],
            'intro' => '',
            'sections' => [],
            'key_figure' => null,
            'gallery' => [],
            'images' => [],
            'videos' => [],
            'embeds' => [],
            'tables' => [],
            'legacy' => [],
            'i18n' => [],
        ];
        $doc[$type === 'page' ? 'article' : $type] = match ($type) {
            'match' => [
                'date' => null, 'date_text' => null, 'season' => null, 'competition' => 'Championnat',
                'competition_label' => '', 'competition_code' => '', 'round' => '', 'round_text' => '',
                'home' => ['name' => 'Sochaux', 'level' => null], 'away' => ['name' => '', 'level' => null],
                'sochaux_home' => true, 'score' => null, 'score_raw' => '', 'score_line' => '', 'result' => null,
                'stadium' => '', 'spectators' => null, 'spectators_text' => '', 'referee' => '', 'goals_text' => '',
                'goals' => [], 'header_extra' => [], 'highlights' => [], 'reactions' => [], 'breves' => [],
                'lineup' => ['title' => 'Composition Sochaux', 'headers' => ['Postes', 'Nom et prénom', 'Buts', 'Remp.', 'Cartons'], 'rows' => [], 'source_table' => null],
                'other_lineups' => [], 'event' => null, 'opponent_club' => null, 'stadium_id' => null, 'formation' => '',
            ],
            'personne' => [
                'roles' => ['joueur'], 'first_name' => '', 'last_name' => '', 'display_name' => '', 'nickname' => '',
                'subtitle' => '', 'trial' => null, 'is_trial' => false, 'formed_at_club' => false, 'international_flag' => false,
                'birth' => null, 'death' => null, 'height' => null, 'height_cm' => null, 'weight' => null, 'weight_kg' => null,
                'foot' => null, 'position' => null, 'line' => null, 'nationality' => '', 'international' => [],
                'arrival' => null, 'departure' => null, 'arrival_coach' => null, 'departure_coach' => null,
                'first_match' => null, 'last_match' => null, 'first_goal' => null, 'first_match_coached' => null,
                'last_match_coached' => null, 'honours' => [], 'then' => [], 'fiche' => [], 'stats' => null,
                'shirt_numbers' => '', 'legend' => false, 'album' => ['in' => false, 'rarity' => null, 'number' => null],
                'on_map' => true, 'highlight_matches' => [],
            ],
            'objet' => ['collection' => 'photos', 'year' => null, 'date_text' => '', 'credit' => '', 'origin' => '', 'linked' => [], 'contribution' => null],
            'moment' => ['number' => null, 'year' => null, 'linked' => [], 'card' => null],
            default => ['kind' => 'article', 'heading' => '', 'subtitle' => '', 'season' => null],
        };
        return $doc;
    }

    /**
     * Enregistre une fiche (création ou mise à jour) et crée une version.
     *
     * @param array{name:string,email?:string}|null $user
     */
    public static function save(array $doc, ?array $user = null, string $message = ''): array
    {
        if (empty($doc['id'])) {
            $doc['id'] = self::nextId();
            $before = null;
        } else {
            $before = self::fresh((int) $doc['id']);
        }
        $id = (int) $doc['id'];
        $doc['modified'] = date('c');
        $doc['modified_by'] = $user['name'] ?? ($doc['modified_by'] ?? null);
        if ($before === null && empty($doc['author'])) {
            $doc['author'] = $user['name'] ?? null;
        }

        $diff = $before ? self::diff($before, $doc) : [['Fiche', '—', 'créée']];
        if ($before && !$diff) {
            return $before; // rien n'a changé : la fiche enregistrée reste telle quelle
        }

        // Première modification d'une fiche reprise de l'ancien site : son état d'origine
        // devient la version 1, pour pouvoir toujours y revenir.
        if ($before && !is_file(self::VERSIONS . "/$id/index.json")) {
            self::addVersion($id, $before, ['name' => 'Import'], 'État d’origine (reprise de l’ancien site)', [], (string) ($before['modified'] ?? '') ?: null);
        }
        JsonStore::write(self::path($id), $doc);
        self::addVersion($id, $doc, $user, $message ?: ($before ? self::summarize($diff) : 'Création de la fiche'), $diff);
        Index::put($doc);
        Derived::markDirty();
        \App\Services\Search::put($doc);
        Activity::log($user, $before ? 'a modifié' : 'a créé', $doc);
        return $doc;
    }

    /**
     * Exécute des enregistrements en lot : l'index et la recherche ne sont réécrits
     * qu'une fois à la fin (album, renommage d'une compétition, actions groupées…).
     */
    public static function batch(callable $fn): mixed
    {
        Index::defer(true);
        \App\Services\Search::defer(true);
        try {
            return $fn();
        } finally {
            Index::defer(false);
            \App\Services\Search::defer(false);
        }
    }

    public static function trash(int $id, ?array $user): void
    {
        $doc = self::get($id);
        if ($doc) {
            $doc['status_before_trash'] = $doc['status'];
            $doc['status'] = 'corbeille';
            self::save($doc, $user, 'Mise à la corbeille');
        }
    }

    public static function untrash(int $id, ?array $user): void
    {
        $doc = self::get($id);
        if ($doc && $doc['status'] === 'corbeille') {
            $doc['status'] = $doc['status_before_trash'] ?? 'brouillon';
            unset($doc['status_before_trash']);
            self::save($doc, $user, 'Sortie de la corbeille');
        }
    }

    /** Suppression définitive (administrateur) : la dernière version reste dans l'historique. */
    public static function destroy(int $id, ?array $user): void
    {
        $doc = self::get($id);
        if (!$doc) {
            return;
        }
        self::addVersion($id, $doc, $user, 'Suppression définitive', [['Fiche', 'existante', 'supprimée']]);
        JsonStore::delete(self::path($id));
        Index::remove($id);
        Derived::markDirty();
        \App\Services\Search::remove($id);
        Activity::log($user, 'a supprimé définitivement', $doc);
    }

    // ------------------------------------------------------------------ versions

    private static function addVersion(int $id, array $doc, ?array $user, string $message, array $diff, ?string $at = null): void
    {
        $dir = self::VERSIONS . "/$id";
        $index = JsonStore::update("$dir/index.json", function ($idx) use ($dir, $doc, $user, $message, $diff, $at) {
            $idx = $idx ?: [];
            $n = ($idx ? max(array_column($idx, 'n')) : 0) + 1;
            JsonStore::write("$dir/$n.json", $doc);
            $idx[] = [
                'n' => $n,
                'at' => $at ?? date('c'),
                'by' => $user['name'] ?? 'Système',
                'message' => $message,
                'diff' => array_slice($diff, 0, 30),
            ];
            return $idx;
        }, []);
        unset($index);
    }

    /** @return list<array> versions, la plus récente en premier */
    public static function versions(int $id): array
    {
        $idx = JsonStore::read(self::VERSIONS . "/$id/index.json", []) ?? [];
        return array_reverse($idx);
    }

    public static function version(int $id, int $n): ?array
    {
        return JsonStore::read(self::VERSIONS . "/$id/$n.json");
    }

    /** Restaure une version : crée une nouvelle version (réversible). */
    public static function restore(int $id, int $n, ?array $user): ?array
    {
        $old = self::version($id, $n);
        if (!$old) {
            return null;
        }
        $old['id'] = $id;
        return self::save($old, $user, "Restauration de la v$n");
    }

    // ------------------------------------------------------------------ différences

    private const DIFF_LABELS = [
        'title' => 'Titre', 'status' => 'Statut', 'slug' => 'Adresse', 'path' => 'Adresse', 'categories' => 'Rubriques',
        'featured_image' => 'Image à la une', 'gallery' => 'Galerie', 'sections' => 'Récit', 'key_figure' => 'Chiffre clé',
        'videos' => 'Vidéos', 'a_la_une' => 'À la une', 'publish_at' => 'Publication programmée', 'seo' => 'SEO',
        'match.score' => 'Score', 'match.date' => 'Date', 'match.stadium' => 'Stade', 'match.spectators' => 'Spectateurs',
        'match.referee' => 'Arbitre', 'match.lineup' => 'Composition', 'match.highlights' => 'Temps forts',
        'match.reactions' => 'Réactions', 'match.breves' => 'Brèves', 'match.competition' => 'Compétition',
        'match.home' => 'Domicile', 'match.away' => 'Extérieur', 'match.goals_text' => 'Buteurs',
        'personne.birth' => 'Naissance', 'personne.position' => 'Poste', 'personne.stats' => 'Statistiques',
        'personne.fiche' => "Fiche d'identité", 'personne.album' => "Carte de l'album", 'i18n' => 'Traduction EN',
    ];

    /** @return list<array{0:string,1:string,2:string}> */
    public static function diff(array $a, array $b): array
    {
        $out = [];
        $ignore = ['modified', 'modified_by'];
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
        foreach ($keys as $k) {
            if (in_array($k, $ignore, true)) {
                continue;
            }
            $va = $a[$k] ?? null;
            $vb = $b[$k] ?? null;
            if ($va === $vb) {
                continue;
            }
            if (in_array($k, ['match', 'personne', 'article', 'objet', 'moment'], true) && is_array($va) && is_array($vb)) {
                foreach (array_unique(array_merge(array_keys($va), array_keys($vb))) as $sk) {
                    if (($va[$sk] ?? null) !== ($vb[$sk] ?? null)) {
                        $out[] = [self::DIFF_LABELS["$k.$sk"] ?? ucfirst(str_replace('_', ' ', $sk)), self::short($va[$sk] ?? null), self::short($vb[$sk] ?? null)];
                    }
                }
                continue;
            }
            $out[] = [self::DIFF_LABELS[$k] ?? ucfirst(str_replace('_', ' ', $k)), self::short($va), self::short($vb)];
        }
        return $out;
    }

    private static function short(mixed $v): string
    {
        if ($v === null || $v === '' || $v === []) {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'oui' : 'non';
        }
        if (is_array($v)) {
            if (isset($v['home'], $v['away']) && is_int($v['home'])) {
                return $v['home'] . '-' . $v['away'];
            }
            if (array_is_list($v)) {
                return count($v) . ' élément' . (count($v) > 1 ? 's' : '');
            }
            $flat = json_encode($v, JSON_UNESCAPED_UNICODE);
            return mb_strlen($flat) > 60 ? mb_substr($flat, 0, 57) . '…' : $flat;
        }
        $s = trim(strip_tags((string) $v));
        return mb_strlen($s) > 60 ? mb_substr($s, 0, 57) . '…' : $s;
    }

    private static function summarize(array $diff): string
    {
        $labels = array_unique(array_column($diff, 0));
        return count($labels) === 1 ? $labels[0] . ' modifié' : implode(', ', array_slice($labels, 0, 3)) . (count($labels) > 3 ? '…' : '') . ' modifiés';
    }
}
