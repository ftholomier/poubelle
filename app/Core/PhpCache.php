<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Caches PHP (`<?php return [...];`, servis par OPcache) modifiés par plusieurs
 * processus à la fois : index des fiches, index de recherche.
 *
 * Chaque mise à jour relit le fichier sous verrou exclusif, applique la
 * modification et réécrit de façon atomique. Un processus qui a chargé le cache
 * plus tôt (tâche planifiée, longue requête) ne peut donc plus écraser avec sa
 * copie ancienne ce que d'autres ont enregistré entre-temps.
 */
final class PhpCache
{
    /** @var array<string,true> verrous déjà tenus par ce processus (appels imbriqués) */
    private static array $held = [];

    /** Lecture fraîche (sans la version compilée d'OPcache) ; null si absent ou illisible. */
    public static function read(string $file): ?array
    {
        clearstatcache(true, $file);
        if (!is_file($file)) {
            return null;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        $data = include $file;
        return is_array($data) ? $data : null;
    }

    public static function write(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Dossier impossible à créer : $dir");
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, '<?php return ' . var_export($data, true) . ";\n", LOCK_EX) === false) {
            throw new RuntimeException("Écriture impossible : $tmp");
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException("Remplacement impossible : $file");
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    /**
     * Lecture-modification-écriture sous verrou exclusif.
     *
     * @param callable(?array):array $fn reçoit le contenu actuel (null si le cache n'existe pas)
     */
    public static function update(string $file, callable $fn): array
    {
        if (isset(self::$held[$file])) {
            $data = $fn(self::read($file));
            self::write($file, $data);
            return $data;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $fp = fopen($file . '.lock', 'c');
        if (!$fp || !flock($fp, LOCK_EX)) {
            throw new RuntimeException("Verrou impossible : $file");
        }
        self::$held[$file] = true;
        try {
            $data = $fn(self::read($file));
            self::write($file, $data);
            return $data;
        } finally {
            unset(self::$held[$file]);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
