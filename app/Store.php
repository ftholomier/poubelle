<?php
declare(strict_types=1);

/**
 * Store — persistance JSON atomique, sans base de données.
 *
 * Chaque « collection » est un fichier data/<nom>.json.
 * Les écritures passent par un verrou exclusif + rename atomique
 * afin d'éviter toute corruption en cas d'accès concurrents.
 */
final class Store
{
    /** @var array<string,array> cache mémoire par requête */
    private static array $cache = [];

    public static function path(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_\-]/i', '', $name) ?? '';
        return DATA_DIR . '/' . $name . '.json';
    }

    public static function read(string $name, array $default = []): array
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $file = self::path($name);
        if (!is_file($file)) {
            return self::$cache[$name] = $default;
        }
        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return self::$cache[$name] = $default;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return self::$cache[$name] = $default;
        }
        return self::$cache[$name] = $data;
    }

    public static function write(string $name, array $data): bool
    {
        $file = self::path($name);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        self::$cache[$name] = $data;
        return true;
    }

    /**
     * Lecture-modification-écriture sous verrou : évite les pertes
     * d'écriture quand deux visiteurs postent en même temps.
     */
    public static function mutate(string $name, callable $fn, array $default = []): array
    {
        $file = self::path($name);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        $lock = fopen($file . '.lock', 'c+');
        if ($lock === false) {
            $data = $fn(self::read($name, $default));
            self::write($name, $data);
            return $data;
        }
        try {
            flock($lock, LOCK_EX);
            unset(self::$cache[$name]);
            $data = $fn(self::read($name, $default));
            self::write($name, $data);
            return $data;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Ajoute un enregistrement horodaté dans une collection de type liste. */
    public static function push(string $name, array $record): array
    {
        $record['id'] = $record['id'] ?? self::uid();
        $record['created_at'] = $record['created_at'] ?? date('c');
        self::mutate($name, static function (array $rows) use ($record): array {
            $rows[] = $record;
            return $rows;
        });
        return $record;
    }

    public static function find(string $name, string $id): ?array
    {
        foreach (self::read($name) as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }
        return null;
    }

    public static function update(string $name, string $id, array $patch): ?array
    {
        $found = null;
        self::mutate($name, static function (array $rows) use ($id, $patch, &$found): array {
            foreach ($rows as $i => $row) {
                if (($row['id'] ?? null) === $id) {
                    $rows[$i] = array_replace($row, $patch, ['id' => $id, 'updated_at' => date('c')]);
                    $found = $rows[$i];
                }
            }
            return $rows;
        });
        return $found;
    }

    public static function delete(string $name, string $id): bool
    {
        $ok = false;
        self::mutate($name, static function (array $rows) use ($id, &$ok): array {
            $out = [];
            foreach ($rows as $row) {
                if (($row['id'] ?? null) === $id) { $ok = true; continue; }
                $out[] = $row;
            }
            return $out;
        });
        return $ok;
    }

    public static function uid(string $prefix = ''): string
    {
        return $prefix . date('ymd') . '-' . bin2hex(random_bytes(5));
    }
}
