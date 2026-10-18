<?php
/**
 * Lot 2 — serveur : PhpCache::load (une version réécrite dans la même seconde avec la même taille
 * n'est jamais servie périmée), vidage de Redis limité au musée (SCAN, préfixe par musée), mémoire
 * Redis sans limite réglée (pas de limite inventée), alertes mémoire communes à tous les musées
 * (un seul e-mail par adresse, marque « lien utilisé » jamais perdue). Un serveur Redis d'essai est
 * lancé sur une prise temporaire s'il est installé. Usage : php tests/lot2-serveur.php
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\JsonStore;
use App\Core\OpcacheGuard;
use App\Core\PhpCache;
use App\Services\ServerCheck;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$tmp = sys_get_temp_dir() . '/lot2-serveur-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
$srv = null;
try {
    // PhpCache : deux versions de même taille, écrites dans la même seconde.
    $file = "$tmp/cache/index-1.php";
    $n = PhpCache::BIG + 10;
    PhpCache::write($file, ['v' => str_repeat('a', $n)]);
    $eq('gros cache : chargeur + .ser', [is_file("$file.ser"), str_contains((string) file_get_contents($file), 'PhpCache::load')], [true, true]);
    $eq('première version lue', substr((string) (include $file)['v'], 0, 3), 'aaa');
    $t = filemtime("$file.ser");
    PhpCache::write($file, ['v' => str_repeat('b', $n)]);
    touch("$file.ser", $t);
    clearstatcache();
    $eq('même taille, même date : nouvelle version lue', [filemtime("$file.ser") === $t, substr((string) (include $file)['v'], 0, 3)], [true, 'bbb']);
    $eq('.ser : empreinte du contenu en tête', (string) file_get_contents("$file.ser", false, null, 0, 5), '#PHC ');
    // Ancien .ser sans empreinte (écrit avant la mise à jour) : encore lu.
    file_put_contents("$file.ser", serialize(['v' => 'ancien']));
    $eq('ancien .ser sans empreinte : lu', (include $file)['v'], 'ancien');

    // Redis d'essai (prise temporaire) : vidage du seul musée, SCAN, mémoire sans limite.
    $bin = trim((string) shell_exec('command -v redis-server'));
    if ($bin !== '' && class_exists('Redis')) {
        $sock = "$tmp/redis.sock";
        $srv = proc_open([$bin, '--port', '0', '--unixsocket', $sock, '--save', '', '--appendonly', 'no', '--maxmemory', '0'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        for ($i = 0; $i < 50 && !file_exists($sock); $i++) {
            usleep(100000);
        }
        $mk = function (string $prefix) use ($sock) {
            $r = new Redis();
            $r->connect($sock);
            $r->setOption(Redis::OPT_PREFIX, $prefix);
            return $r;
        };
        $mine = $mk('musee:' . CLUB_ID . ':');
        $other = $mk('musee:autre-musee:');
        for ($i = 0; $i < 2500; $i++) {
            $mine->set("k$i", 'x');
        }
        $mine->hSet('idx', 'k1', '/x');
        $other->set('k1', 'y');
        $other->hSet('idx', 'k1', '/y');
        $eq('SCAN : toutes les clés du musée', array_sum(array_map('count', iterator_to_array(PhpCache::scan($mine, 'musee:' . CLUB_ID . ':*'), false))), 2501);
        $info = ServerCheck::redis($mine);
        $eq('sans maxmemory : pas de limite inventée (max 0)', $info['max'] ?? null, 0);
        $eq('entrées du musée comptées', $info['keys'] ?? null, 2501);
        $eq('vidage : seulement ce musée', PhpCache::flushRedis($mine), 2501);
        $eq('l’autre musée garde ses entrées', [$other->get('k1'), $other->hGet('idx', 'k1')], ['y', '/y']);
        $eq('ce musée est vide', $mine->dbSize(), 2);
        $mine->config('SET', 'maxmemory', (string) (64 * 1048576));
        $eq('avec maxmemory : limite lue', ServerCheck::redis($mine)['max'] ?? null, 64.0);
    } else {
        echo "--   Redis non installé : essais de vidage sautés\n";
    }

    // Alertes mémoire : état commun, un seul e-mail par adresse et par jour, même pour plusieurs musées.
    OpcacheGuard::$alertsFile = "$tmp/memoire-alertes.json";
    $sent = [];
    OpcacheGuard::$mailer = function (string $to, string $subject, string $html) use (&$sent) {
        $sent[] = $to;
        return true;
    };
    // Comptes simulés (en mémoire seulement) : deux administrateurs, dont un en majuscules.
    $users = ['u1' => ['id' => 'u1', 'name' => 'A', 'email' => 'Admin@Exemple.fr', 'role' => 'admin', 'status' => 'active'],
        'u2' => ['id' => 'u2', 'name' => 'B', 'email' => 'b@exemple.fr', 'role' => 'admin', 'status' => 'active'],
        'u3' => ['id' => 'u3', 'name' => 'C', 'email' => 'c@exemple.fr', 'role' => 'user', 'status' => 'active']];
    $cache = new ReflectionProperty(JsonStore::class, 'cache');
    $c = $cache->getValue();
    $c[APP_ROOT . '/storage/users.json'] = $users;
    $cache->setValue(null, $c);
    $alerts = new ReflectionMethod(OpcacheGuard::class, 'alerts');
    $alerts->invoke(null, 0.95, 512 * 1048576);
    sort($sent);
    $eq('alerte : chaque administrateur une fois', $sent, ['admin@exemple.fr', 'b@exemple.fr']);
    // Un lien « vider » utilisé, puis le même contrôle depuis un autre musée (même serveur).
    $e = time() + 3600;
    $sig = (new ReflectionMethod(OpcacheGuard::class, 'sig'))->invoke(null, 'redis', $e);
    $eq('lien utilisé une première fois', OpcacheGuard::fromLink('redis', $e, $sig) !== null, true);
    $sent = [];
    $alerts->invoke(null, 0.95, 512 * 1048576);
    $eq('deuxième musée, même jour : pas de second e-mail', $sent, []);
    $eq('lien utilisé : marque gardée', OpcacheGuard::fromLink('redis', $e, $sig), null);
    JsonStore::forget(OpcacheGuard::$alertsFile);
    $state = JsonStore::read(OpcacheGuard::$alertsFile, []);
    $eq('état commun : envois et liens dans le même fichier', [count($state['mails']['opcache'] ?? []), count($state['used'] ?? [])], [2, 1]);
    $eq('fichier commun à tous les musées', (new ReflectionProperty(OpcacheGuard::class, 'alertsFile'))->getDefaultValue(), APP_ROOT . '/storage/memoire-alertes.json');
} finally {
    if ($srv) {
        proc_terminate($srv);
        proc_close($srv);
    }
    exec('rm -rf ' . escapeshellarg($tmp));
}
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
