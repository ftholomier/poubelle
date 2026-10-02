<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Collection JSON sans base de données.
 *
 * storage/data/{nom}/
 *   meta.json                  prochain identifiant, compteur
 *   rec/{shard}/{id}.json      un fichier par enregistrement (shard = id / 1000)
 *   idx/{shard}.json           index léger des champs utiles aux listes et filtres
 *   sec/{champ}/{valeur}.json  index secondaires (ex. messages d'un pro)
 *
 * Les écritures se font sous verrou exclusif et par renommage atomique.
 */
final class Collection
{
    private const SHARD = 1000;

    private string $dir;
    /** @var callable(array):array */
    private $indexer;
    /** @var array<string,string> champ => 'one'|'many' */
    private array $secondary;
    /** @var array<int,array<int,array>> cache des shards d'index chargés */
    private array $idxCache = [];
    private ?array $metaCache = null;
    private bool $bulk = false;
    private array $bulkIdx = [];
    private array $bulkSec = [];

    public function __construct(public readonly string $name, ?callable $indexer = null, array $secondary = [])
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Nom de collection invalide');
        }
        $this->dir = STORAGE_PATH . '/data/' . $name;
        $this->indexer = $indexer ?? static fn (array $r): array => ['id' => $r['id']];
        $this->secondary = $secondary;
    }

    public function dir(): string
    {
        return $this->dir;
    }

    // ---------------------------------------------------------------- lecture

    public function get(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $data = Fs::readJson($this->recPath($id));
        return is_array($data) ? $data : null;
    }

    public function exists(int $id): bool
    {
        return $id > 0 && is_file($this->recPath($id));
    }

    /** Entrée d'index légère d'un enregistrement. */
    public function light(int $id): ?array
    {
        $shard = $this->loadShard(intdiv($id, self::SHARD));
        return $shard[$id] ?? null;
    }

    /** @return int[] shards existants, du plus récent au plus ancien */
    public function shards(bool $desc = true): array
    {
        $files = glob($this->dir . '/idx/*.json') ?: [];
        $out = [];
        foreach ($files as $f) {
            $out[] = (int) basename($f, '.json');
        }
        $desc ? rsort($out) : sort($out);
        return $out;
    }

    /** Toutes les entrées d'index (à réserver aux petites collections). @return array<int,array> */
    public function index(): array
    {
        $all = [];
        foreach ($this->shards(false) as $s) {
            foreach ($this->loadShard($s) as $id => $row) {
                $all[$id] = $row;
            }
        }
        return $all;
    }

    /** Parcourt l'index shard par shard (id décroissant par défaut). */
    public function iterate(bool $desc = true): \Generator
    {
        foreach ($this->shards($desc) as $s) {
            $rows = $this->loadShard($s);
            $desc ? krsort($rows) : ksort($rows);
            foreach ($rows as $id => $row) {
                yield $id => $row;
            }
            if (count($this->idxCache) > 8) {
                $this->idxCache = [];
            }
        }
    }

    /**
     * Recherche dans l'index : filtre, tri, pagination.
     * @return array{total:int, items:array<int,array>}
     */
    public function find(?callable $filter = null, ?callable $sort = null, int $limit = 50, int $offset = 0, bool $desc = true): array
    {
        if ($sort === null) {
            // Sans tri explicite : on s'arrête dès qu'on a la page, mais on compte tout.
            $total = 0;
            $items = [];
            foreach ($this->iterate($desc) as $id => $row) {
                if ($filter !== null && !$filter($row)) {
                    continue;
                }
                if ($total >= $offset && count($items) < $limit) {
                    $items[] = $row;
                }
                $total++;
            }
            return ['total' => $total, 'items' => $items];
        }
        $rows = [];
        foreach ($this->iterate($desc) as $row) {
            if ($filter === null || $filter($row)) {
                $rows[] = $row;
            }
        }
        usort($rows, $sort);
        return ['total' => count($rows), 'items' => array_slice($rows, $offset, $limit)];
    }

    public function count(?callable $filter = null): int
    {
        if ($filter === null) {
            return (int) ($this->meta()['count'] ?? 0);
        }
        $n = 0;
        foreach ($this->iterate() as $row) {
            if ($filter($row)) {
                $n++;
            }
        }
        return $n;
    }

    /** Identifiants d'un index secondaire. @return int[] */
    public function ids(string $field, string|int $value): array
    {
        if (!isset($this->secondary[$field])) {
            throw new \InvalidArgumentException('Index secondaire inconnu : ' . $field);
        }
        $ids = Fs::readJson($this->secPath($field, (string) $value), []);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /** @return array<int,array> enregistrements complets pour une liste d'ids */
    public function many(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $r = $this->get((int) $id);
            if ($r !== null) {
                $out[(int) $id] = $r;
            }
        }
        return $out;
    }

    /** Parcourt les enregistrements complets. */
    public function each(callable $fn, bool $desc = false): void
    {
        foreach ($this->iterate($desc) as $id => $_) {
            $r = $this->get((int) $id);
            if ($r !== null && $fn($r) === false) {
                return;
            }
        }
    }

    public function meta(): array
    {
        if ($this->metaCache === null) {
            $this->metaCache = Fs::readJson($this->dir . '/meta.json', ['next_id' => 1, 'count' => 0]);
        }
        return $this->metaCache;
    }

    // --------------------------------------------------------------- écriture

    /** Insère un enregistrement (id fourni ou auto-incrément). */
    public function insert(array $data): array
    {
        return $this->locked(function () use ($data): array {
            $meta = $this->freshMeta();
            $id = isset($data['id']) ? (int) $data['id'] : (int) $meta['next_id'];
            if ($id <= 0) {
                throw new \InvalidArgumentException('Identifiant invalide');
            }
            if (is_file($this->recPath($id))) {
                throw new \RuntimeException("L'enregistrement {$this->name}#{$id} existe déjà");
            }
            $data['id'] = $id;
            $now = date('c');
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
            $this->writeRecord($id, $data, null);
            $meta['next_id'] = max((int) $meta['next_id'], $id + 1);
            $meta['count'] = (int) $meta['count'] + 1;
            $meta['updated'] = time();
            $this->saveMeta($meta);
            return $data;
        });
    }

    /**
     * Met à jour un enregistrement. $patch : tableau fusionné (récursif sur les clés
     * associatives) ou callable(array $actuel): array.
     */
    public function update(int $id, array|callable $patch, bool $touch = true): ?array
    {
        return $this->locked(function () use ($id, $patch, $touch): ?array {
            $current = $this->get($id);
            if ($current === null) {
                return null;
            }
            $next = is_callable($patch) ? $patch($current) : self::merge($current, $patch);
            if (!is_array($next)) {
                return $current;
            }
            $next['id'] = $id;
            if ($touch) {
                $next['updated_at'] = date('c');
            }
            $this->writeRecord($id, $next, $current);
            $meta = $this->freshMeta();
            $meta['updated'] = time();
            $this->saveMeta($meta);
            return $next;
        });
    }

    /**
     * Met à jour plusieurs enregistrements en une passe (index réécrits une seule fois).
     * @param array<int,array|callable> $patches id => patch
     */
    public function updateMany(array $patches, bool $touch = false): int
    {
        return $this->locked(function () use ($patches, $touch): int {
            $n = 0;
            $shards = [];
            foreach ($patches as $id => $patch) {
                $id = (int) $id;
                $current = $this->get($id);
                if ($current === null) {
                    continue;
                }
                $next = is_callable($patch) ? $patch($current) : self::merge($current, $patch);
                if (!is_array($next)) {
                    continue;
                }
                $next['id'] = $id;
                if ($touch) {
                    $next['updated_at'] = date('c');
                }
                Fs::writeAtomic($this->recPath($id), (string) json_encode($next, Fs::JSON_FLAGS));
                $s = intdiv($id, self::SHARD);
                $shards[$s] ??= $this->loadShard($s, true);
                $shards[$s][$id] = ($this->indexer)($next);
                foreach ($this->secondary as $field => $mode) {
                    $old = self::values($current, $field, $mode);
                    $new = self::values($next, $field, $mode);
                    foreach (array_diff($old, $new) as $v) {
                        $this->secRemove($field, $v, $id);
                    }
                    foreach (array_diff($new, $old) as $v) {
                        $this->secAdd($field, $v, $id);
                    }
                }
                $n++;
            }
            foreach ($shards as $s => $rows) {
                ksort($rows);
                $this->saveShard((int) $s, $rows);
            }
            return $n;
        });
    }

    /** Remplace intégralement un enregistrement existant. */
    public function replace(int $id, array $data): ?array
    {
        return $this->update($id, static fn () => $data);
    }

    public function delete(int $id): bool
    {
        return $this->locked(function () use ($id): bool {
            $current = $this->get($id);
            if ($current === null) {
                return false;
            }
            @unlink($this->recPath($id));
            $s = intdiv($id, self::SHARD);
            $rows = $this->loadShard($s, true);
            unset($rows[$id]);
            $this->saveShard($s, $rows);
            foreach ($this->secondary as $field => $mode) {
                foreach (self::values($current, $field, $mode) as $v) {
                    $this->secRemove($field, $v, $id);
                }
            }
            $meta = $this->freshMeta();
            $meta['count'] = max(0, (int) $meta['count'] - 1);
            $meta['updated'] = time();
            $this->saveMeta($meta);
            return true;
        });
    }

    /** Vide entièrement la collection (import). */
    public function truncate(): void
    {
        Fs::rmrf($this->dir);
        $this->idxCache = [];
        $this->metaCache = null;
    }

    /** Mode d'écriture en masse : les index sont construits en une fois à la fin. */
    public function beginBulk(): void
    {
        $this->bulk = true;
        $this->bulkIdx = [];
        $this->bulkSec = [];
    }

    public function bulkPut(array $data): void
    {
        $id = (int) $data['id'];
        $now = date('c');
        $data['created_at'] ??= $now;
        $data['updated_at'] ??= $now;
        Fs::ensureDir(dirname($this->recPath($id)));
        file_put_contents($this->recPath($id), json_encode($data, Fs::JSON_FLAGS));
        $this->bulkIdx[intdiv($id, self::SHARD)][$id] = ($this->indexer)($data);
        foreach ($this->secondary as $field => $mode) {
            foreach (self::values($data, $field, $mode) as $v) {
                $this->bulkSec[$field][$v][] = $id;
            }
        }
    }

    public function endBulk(): void
    {
        $maxId = 0;
        $count = 0;
        foreach ($this->bulkIdx as $s => $rows) {
            ksort($rows);
            $this->saveShard((int) $s, $rows);
            $count += count($rows);
            $maxId = max($maxId, max(array_keys($rows)));
        }
        foreach ($this->bulkSec as $field => $map) {
            foreach ($map as $v => $ids) {
                Fs::writeJson($this->secPath($field, (string) $v), array_values(array_unique($ids)));
            }
        }
        $this->saveMeta(['next_id' => $maxId + 1, 'count' => $count, 'updated' => time()]);
        $this->bulk = false;
        $this->bulkIdx = $this->bulkSec = [];
    }

    /** Reconstruit tous les index à partir des fichiers d'enregistrement. */
    public function rebuild(): int
    {
        return $this->locked(function (): int {
            Fs::rmrf($this->dir . '/idx');
            Fs::rmrf($this->dir . '/sec');
            $this->idxCache = [];
            $idx = [];
            $sec = [];
            $maxId = 0;
            $count = 0;
            foreach (glob($this->dir . '/rec/*/*.json') ?: [] as $file) {
                $r = Fs::readJson($file);
                if (!is_array($r) || !isset($r['id'])) {
                    continue;
                }
                $id = (int) $r['id'];
                $idx[intdiv($id, self::SHARD)][$id] = ($this->indexer)($r);
                foreach ($this->secondary as $field => $mode) {
                    foreach (self::values($r, $field, $mode) as $v) {
                        $sec[$field][$v][] = $id;
                    }
                }
                $maxId = max($maxId, $id);
                $count++;
            }
            foreach ($idx as $s => $rows) {
                ksort($rows);
                $this->saveShard((int) $s, $rows);
            }
            foreach ($sec as $field => $map) {
                foreach ($map as $v => $ids) {
                    sort($ids);
                    Fs::writeJson($this->secPath($field, (string) $v), $ids);
                }
            }
            $meta = $this->freshMeta();
            $this->saveMeta(['next_id' => max((int) ($meta['next_id'] ?? 1), $maxId + 1), 'count' => $count, 'updated' => time()]);
            return $count;
        });
    }

    // ---------------------------------------------------------------- interne

    private function writeRecord(int $id, array $data, ?array $previous): void
    {
        Fs::writeAtomic($this->recPath($id), (string) json_encode($data, Fs::JSON_FLAGS));
        $s = intdiv($id, self::SHARD);
        $rows = $this->loadShard($s, true);
        $rows[$id] = ($this->indexer)($data);
        ksort($rows);
        $this->saveShard($s, $rows);
        foreach ($this->secondary as $field => $mode) {
            $old = $previous ? self::values($previous, $field, $mode) : [];
            $new = self::values($data, $field, $mode);
            foreach (array_diff($old, $new) as $v) {
                $this->secRemove($field, $v, $id);
            }
            foreach (array_diff($new, $old) as $v) {
                $this->secAdd($field, $v, $id);
            }
        }
    }

    private static function values(array $r, string $field, string $mode): array
    {
        $v = $r;
        foreach (explode('.', $field) as $k) {
            if (!is_array($v) || !array_key_exists($k, $v)) {
                return [];
            }
            $v = $v[$k];
        }
        if ($mode === 'many') {
            return is_array($v) ? array_values(array_unique(array_map('strval', array_filter($v, static fn ($x) => $x !== null && $x !== '')))) : [];
        }
        return ($v === null || $v === '' || is_array($v)) ? [] : [(string) $v];
    }

    private function secAdd(string $field, string $v, int $id): void
    {
        $p = $this->secPath($field, $v);
        $ids = Fs::readJson($p, []);
        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
            Fs::writeJson($p, $ids);
        }
    }

    private function secRemove(string $field, string $v, int $id): void
    {
        $p = $this->secPath($field, $v);
        $ids = array_values(array_filter(Fs::readJson($p, []), static fn ($x) => (int) $x !== $id));
        $ids ? Fs::writeJson($p, $ids) : @unlink($p);
    }

    private function loadShard(int $s, bool $fresh = false): array
    {
        if ($fresh || !isset($this->idxCache[$s])) {
            $rows = Fs::readJson($this->dir . '/idx/' . $s . '.json', []);
            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row['id']] = $row;
            }
            $this->idxCache[$s] = $map;
        }
        return $this->idxCache[$s];
    }

    private function saveShard(int $s, array $rows): void
    {
        $path = $this->dir . '/idx/' . $s . '.json';
        if (!$rows) {
            @unlink($path);
            unset($this->idxCache[$s]);
            return;
        }
        Fs::writeJson($path, array_values($rows));
        $this->idxCache[$s] = $rows;
    }

    private function freshMeta(): array
    {
        $this->metaCache = null;
        return $this->meta();
    }

    private function saveMeta(array $meta): void
    {
        Fs::writeJson($this->dir . '/meta.json', $meta);
        $this->metaCache = $meta;
    }

    private function recPath(int $id): string
    {
        return $this->dir . '/rec/' . intdiv($id, self::SHARD) . '/' . $id . '.json';
    }

    private function secPath(string $field, string $value): string
    {
        $safe = preg_match('/^[A-Za-z0-9_\-.]{1,80}$/', $value) ? $value : 'h_' . sha1($value);
        return $this->dir . '/sec/' . str_replace('.', '_', $field) . '/' . $safe . '.json';
    }

    private function locked(callable $fn): mixed
    {
        if ($this->bulk) {
            return $fn();
        }
        return Fs::withLock($this->dir . '/.lock', $fn);
    }

    /** Fusion récursive : les listes sont remplacées, les tableaux associatifs fusionnés. */
    public static function merge(array $base, array $patch): array
    {
        foreach ($patch as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !array_is_list($v) && !array_is_list($base[$k])) {
                $base[$k] = self::merge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }
}
