<?php

declare(strict_types=1);

/**
 * Stockage JSON (storage/data/*.json, hors du dossier public).
 *
 * Au premier accès, chaque fichier est créé à partir du contenu initial versionné
 * (content/*.json, extrait du site renovest.fr). Le back-office écrit ensuite dans
 * storage/data : une mise à jour du code ne touche jamais au contenu modifié.
 * Écriture atomique (fichier temporaire + renommage) sous verrou exclusif.
 */
final class Store
{
    private static array $memo = [];

    private static function path(string $name): string
    {
        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            throw new InvalidArgumentException('Nom de stockage invalide');
        }
        $dir = cfg('storage') . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return "$dir/$name.json";
    }

    /** Contenu initial versionné (content/<nom>.json), ou null. */
    public static function seed(string $name)
    {
        $f = APP_ROOT . '/content/' . $name . '.json';
        if (!preg_match('/^[a-z0-9_-]+$/', $name) || !is_file($f)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    public static function get(string $name, $default = [])
    {
        if (array_key_exists($name, self::$memo)) {
            return self::$memo[$name];
        }
        $f = self::path($name);
        if (!is_file($f)) {
            $seed = self::seed($name);
            if ($seed !== null) {
                self::put($name, $seed);
                return self::$memo[$name] = $seed;
            }
            return $default;
        }
        $data = json_decode((string) file_get_contents($f), true);
        return self::$memo[$name] = is_array($data) ? $data : $default;
    }

    public static function put(string $name, array $data): void
    {
        $f = self::path($name);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new RuntimeException('Encodage impossible : ' . $name);
        }
        $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new RuntimeException('Écriture impossible dans storage/data (droits du dossier ?).');
        }
        @chmod($tmp, 0640);
        rename($tmp, $f);
        self::$memo[$name] = $data;
    }

    /** Lecture-modification-écriture sous verrou (pas de perte en cas d'accès concurrent). */
    public static function update(string $name, callable $fn, $default = [])
    {
        $lock = fopen(self::path($name) . '.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            unset(self::$memo[$name]);
            $data = $fn(self::get($name, $default));
            self::put($name, $data);
            return $data;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Remet un contenu à sa version initiale (content/). */
    public static function reset(string $name): bool
    {
        $seed = self::seed($name);
        if ($seed === null) {
            return false;
        }
        self::put($name, $seed);
        return true;
    }

    public static function exists(string $name): bool
    {
        return is_file(self::path($name));
    }
}
