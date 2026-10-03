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
        return is_array($doc) ? $doc : null;
    }

    /** @return \Generator<int,array> */
    public static function all(): \Generator
    {
        foreach (glob(self::DIR . '/*.json') ?: [] as $file) {
            $doc = json_decode((string) file_get_contents($file), true);
            if (is_array($doc)) {
                yield (int) $doc['id'] => $doc;
            }
        }
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
            $before = self::get((int) $doc['id']);
        }
        $id = (int) $doc['id'];
        $doc['modified'] = date('c');
        $doc['modified_by'] = $user['name'] ?? ($doc['modified_by'] ?? null);
        if ($before === null && empty($doc['author'])) {
            $doc['author'] = $user['name'] ?? null;
        }

        $diff = $before ? self::diff($before, $doc) : [['Fiche', '—', 'créée']];
        if ($before && !$diff) {
            return $doc; // rien n'a changé
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

    private static function addVersion(int $id, array $doc, ?array $user, string $message, array $diff): void
    {
        $dir = self::VERSIONS . "/$id";
        $index = JsonStore::update("$dir/index.json", function ($idx) use ($dir, $doc, $user, $message, $diff) {
            $idx = $idx ?: [];
            $n = ($idx ? max(array_column($idx, 'n')) : 0) + 1;
            JsonStore::write("$dir/$n.json", $doc);
            $idx[] = [
                'n' => $n,
                'at' => date('c'),
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
