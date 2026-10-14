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

    /** Prise ou hôte Redis utilisé (affiché dans Tâches › Serveur). */
    public static ?string $redisWhere = null;
    /** Dernière erreur de connexion à Redis (diagnostic affiché dans Tâches › Serveur). */
    public static ?string $redisError = null;

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

    /**
     * Au-delà, les données vont dans un fichier à côté (« .ser ») et le .php n'est qu'un chargeur.
     * Seuls les très gros caches (recherche, médiathèque complète) : en dessous, OPcache reste bien plus
     * rapide (index des fiches, calculs des matchs, lus à chaque page, sans rien relire).
     */
    public const BIG = 5_000_000;
    /** Plafond des caches « chauds » dans OPcache quand Redis est là. */
    public const HOT_REDIS = 1_500_000;

    /**
     * Seuil effectif. OPcache est partagé par tous les musées (même code, mêmes processus PHP) : avec
     * beaucoup de musées, seules les petites données « chaudes » y restent, le reste passe par Redis
     * (ou le .ser sur disque), pour que 512 Mo suffisent même à 20 musées.
     */
    public static function big(): int
    {
        static $big = null;
        if ($big === null) {
            $n = count(glob(APP_ROOT . '/config/clubs/*.php') ?: []);
            $big = $n <= 4 ? self::BIG : max(300_000, intdiv(self::BIG * 4, $n));
            // Avec Redis : les données de plus de 100 Ko y vont. Elles changent à chaque fiche modifiée
            // (tâches planifiées : reprise, feuilles, traductions, correcteur…) ; dans OPcache, chaque
            // nouvelle version laissait l'ancienne en mémoire « perdue » jusqu'au redémarrage.
            if (self::redis()) {
                $big = min($big, 100_000);
            }
        }
        return $big;
    }

    /**
     * Caches « chauds », lus à chaque page (index des fiches, données calculées) : gardés dans OPcache
     * jusqu'à BIG, car les relire depuis Redis coûterait ~9 ms par page. Leurs anciennes versions
     * sont récupérées par OpcacheGuard. Avec Redis, tous les autres (médiathèque, mémos, albums,
     * recherche…) y passent, quelle que soit leur taille.
     */
    private static function threshold(string $file): int
    {
        if (preg_match('#/cache/(index-\d+\.php|derived/)#', str_replace('\\', '/', $file))) {
            // Avec Redis, les très grosses parties (toutes les apparitions de joueurs : plusieurs Mo,
            // déjà découpées par décennie pour les pages) n'occupent plus OPcache : elles y prenaient
            // à elles seules des dizaines de Mo par musée, pour quelques pages et tâches seulement.
            return self::redis() ? self::HOT_REDIS : self::BIG;
        }
        // Avec Redis, tous les autres y vont, même petits : albums (un fichier par album), mémos…
        // Des centaines de fichiers refaits à chaque recalcul ne s'empilent plus dans OPcache.
        return self::redis() ? 0 : self::big();
    }

    /**
     * Redis de l'hébergement (o2switch : cPanel › Redis), partagé par tous les musées, en mémoire vive.
     * o2switch : prise ~/.cpanel/redis/redis.sock et mot de passe trouvés seuls. Sinon, réglage dans storage/redis.json : {"socket": "/home/compte/.redis/redis.sock"} ou {"host": "127.0.0.1",
     * "port": 6379, "password": "…"} ; sans fichier, les emplacements habituels de la prise sont essayés.
     * Absent ou en panne : null, et tout continue avec les fichiers .ser (rien ne casse).
     */
    public static function redis(bool $retry = false): ?\Redis
    {
        static $r = false;
        if ($retry) {
            $r = false;
        }
        if ($r !== false) {
            return $r;
        }
        $r = null;
        if (!class_exists('Redis')) {
            return null;
        }
        $cfg = @json_decode((string) @file_get_contents(APP_ROOT . '/storage/redis.json'), true);
        $cfg = is_array($cfg) ? $cfg : [];
        if (!empty($cfg['disabled'])) {
            return null;
        }
        $targets = [];
        if (!empty($cfg['socket'])) {
            $targets[] = [(string) $cfg['socket'], 0];
        } elseif (!empty($cfg['host'])) {
            $targets[] = [(string) $cfg['host'], (int) ($cfg['port'] ?? 6379)];
        } else {
            // Dossier du compte : sous LiteSpeed, HOME est souvent vide ; on le déduit du chemin du site
            // (/home/compte/…). Pas de file_exists : open_basedir peut le refuser alors que la prise marche.
            $homes = array_filter(array_unique([
                preg_match('#^(/home\d*/[^/]+)/#', APP_ROOT . '/', $m) ? $m[1] : '',
                (string) getenv('HOME'),
                function_exists('posix_getpwuid') ? (string) (@posix_getpwuid(posix_geteuid())['dir'] ?? '') : '',
            ]));
            foreach ($homes as $h) {
                foreach (['/.cpanel/redis/redis.sock', '/.redis/redis.sock'] as $sock) {
                    $targets[] = [$h . $sock, 0];
                }
            }
            // o2switch (cPanel › Redis Manager) : mot de passe lu dans la configuration du serveur Redis.
            if (empty($cfg['password'])) {
                foreach (array_merge(...array_map(fn ($h) => glob($h . '/.cpanel/redis/*.conf') ?: [], array_values($homes) ?: [''])) as $conf) {
                    if (preg_match('/^\s*requirepass\s+"?([^"\s]+)"?/m', (string) @file_get_contents($conf), $m)) {
                        $cfg['password'] = $m[1];
                        break;
                    }
                }
            }
        }
        foreach ($targets as [$where, $port]) {
            try {
                $c = new \Redis();
                if (!@$c->connect($where, $port ?: 0, 0.2)) {
                    self::$redisError ??= "prise $where injoignable";
                    continue;
                }
                if (!empty($cfg['password']) && !$c->auth((string) $cfg['password'])) {
                    self::$redisError ??= "mot de passe refusé par $where";
                    continue;
                }
                $c->ping();
                $c->setOption(\Redis::OPT_SERIALIZER, defined('Redis::SERIALIZER_IGBINARY') ? \Redis::SERIALIZER_IGBINARY : \Redis::SERIALIZER_PHP);
                $c->setOption(\Redis::OPT_PREFIX, 'musee:');
                self::$redisWhere = $where;
                return $r = $c;
            } catch (\Throwable $e) {
                self::$redisError ??= $where . " : " . $e->getMessage();
            }
        }
        return null;
    }

    /**
     * Code PHP d'un cache de données. Petites données : `<?php return [...]`. Grosses données (index,
     * recherche, médiathèque, calculs) : sérialisées dans « $file.ser » (écrit ici, atomiquement) et le
     * .php devient un chargeur toujours identique. Ainsi OPcache ne garde que ce chargeur de quelques
     * octets : réécrire les données ne laisse plus de copies périmées de plusieurs Mo en mémoire
     * (OPcache qui se remplissait sans cesse et ralentissait tout le site).
     */
    public static function export(string $file, mixed $data): string
    {
        $ser = serialize($data);
        if (strlen($ser) < self::threshold($file)) {
            if (is_file($file . '.ser')) {
                @unlink($file . '.ser');
            }
            return '<?php return ' . var_export($data, true) . ";\n";
        }
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Dossier impossible à créer : $dir");
        }
        $target = $file . '.ser';
        if (!(is_file($target) && filesize($target) === strlen($ser) && md5_file($target) === md5($ser))) {
            $tmp = $target . '.' . bin2hex(random_bytes(4));
            if (file_put_contents($tmp, $ser, LOCK_EX) === false || !rename($tmp, $target)) {
                @unlink($tmp);
                throw new RuntimeException("Écriture impossible : $target");
            }
        }
        return "<?php return \\App\\Core\\PhpCache::load(__FILE__);\n";
    }

    /** Vide les entrées du musée dans Redis (elles se refont seules) ; nombre effacé, ou null sans Redis. */
    public static function flushRedis(): ?int
    {
        $r = self::redis();
        if (!$r) {
            return null;
        }
        $n = 0;
        foreach ($r->keys('*') ?: [] as $k) {
            $n += (int) $r->unlink(substr((string) $k, 6));
        }
        return $n;
    }

    /** Données d'un cache écrit par export() (relues une seule fois par requête tant qu'elles ne changent pas). */
    public static function load(string $file): mixed
    {
        static $memo = [];
        $ser = $file . '.ser';
        clearstatcache(true, $ser);
        $key = $ser . '|' . @filemtime($ser) . '|' . @filesize($ser);
        if (!array_key_exists($key, $memo)) {
            // Redis d'abord (déjà décodé, en mémoire, partagé par tous les processus) ; la clé contient
            // la date et la taille du fichier : une donnée réécrite n'est jamais servie périmée.
            $rk = md5($key);
            $redis = self::redis();
            $val = null;
            if ($redis) {
                try {
                    $got = $redis->get($rk);
                    $val = $got === false ? null : $got;
                } catch (\Throwable) {
                    $redis = null;
                }
            }
            if ($val === null) {
                $raw = @file_get_contents($ser);
                $val = $raw === false ? null : @unserialize($raw, ['allowed_classes' => false]);
                if ($redis && $val !== null) {
                    try {
                        // Une seule version par cache dans Redis : la précédente (fichier réécrit depuis)
                        // est effacée tout de suite, sinon elles s'empileraient jusqu'à expiration.
                        $ptr = 'v:' . md5($ser);
                        $old = $redis->get($ptr);
                        if (is_string($old) && $old !== $rk) {
                            $redis->unlink($old);
                            $redis->hDel('idx', $old);
                        }
                        $redis->setex($rk, 86400, $val);
                        $redis->setex($ptr, 86400, $rk);
                        // Répertoire des entrées (pavé Serveur : ce qui occupe Redis, par dossier).
                        $redis->hSet('idx', $rk, $ser);
                    } catch (\Throwable) {
                    }
                }
            }
            $memo = [$key => $val] + (count($memo) > 40 ? [] : $memo);
        }
        return $memo[$key];
    }

    public static function write(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Dossier impossible à créer : $dir");
        }
        $code = self::export($file, $data);
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
