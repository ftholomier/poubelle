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

    /**
     * Lecture ; null si absent ou illisible. $fresh : sans la version compilée d'OPcache (sous verrou,
     * avant de réécrire). Ailleurs, jamais : forcer la recompilation à chaque lecture remplit OPcache
     * de copies périmées (mémoire « gaspillée ») jusqu'à son redémarrage, et tout le site ralentit.
     * write() invalide déjà le fichier réécrit : une lecture normale voit la nouvelle version.
     */
    public static function read(string $file, bool $fresh = false): ?array
    {
        clearstatcache(true, $file);
        if (!is_file($file)) {
            return null;
        }
        if ($fresh && function_exists('opcache_invalidate')) {
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
        $code = '<?php return ' . var_export($data, true) . ";\n";
        // Contenu identique : pas de réécriture (elle laisserait une copie périmée dans OPcache).
        if (is_file($file) && filesize($file) === strlen($code) && md5_file($file) === md5($code)) {
            return;
        }
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $code, LOCK_EX) === false) {
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
     * Cache manquant : calculé une seule fois, même si plusieurs pages le demandent ensemble (les
     * autres attendent le verrou, puis lisent le fichier tout juste écrit au lieu de tout refaire).
     *
     * @param callable():array $compute
     */
    public static function remember(string $file, callable $compute): array
    {
        return self::update($file, fn (?array $cur) => $cur ?? $compute(), true);
    }

    /**
     * Lecture-modification-écriture sous verrou exclusif.
     *
     * @param callable(?array):array $fn reçoit le contenu actuel (null si le cache n'existe pas)
     */
    public static function update(string $file, callable $fn, bool $onlyIfMissing = false): array
    {
        if (isset(self::$held[$file])) {
            $cur = self::read($file, true);
            $data = $fn($cur);
            if (!($onlyIfMissing && $cur !== null)) {
                self::write($file, $data);
            }
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
            $cur = self::read($file, true);
            $data = $fn($cur);
            // Déjà fait par un autre processus pendant l'attente : rien à réécrire.
            if (!($onlyIfMissing && $cur !== null)) {
                self::write($file, $data);
            }
            return $data;
        } finally {
            unset(self::$held[$file]);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
