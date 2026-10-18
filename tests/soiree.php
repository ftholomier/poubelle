<?php
/**
 * Mode soirée (wifi partagé, App\Core\Venue) : l'adresse de la salle n'est pas freinée par le bouclier
 * et ses autres limites sont multipliées, jusqu'à l'heure de fin seulement ; les autres adresses
 * gardent les limites habituelles ; contrôles de l'écran Réglages. Réglages remis en état.
 * Usage : php tests/soiree.php (code 1 si échec).
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Shield;
use App\Core\Venue;

$fail = 0;
$ok = function (bool $c, string $m) use (&$fail) { echo ($c ? '  ok  ' : '  ÉCHEC ') . $m . "\n"; $fail += $c ? 0 : 1; };
$req = fn (string $ip) => new Request('GET', '/joueurs/', [], [], [], ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/129.0 Mobile Safari/537.36', 'HTTP_HOST' => 'musee.fcsochauxretro.com'], '');
$set = function (array $v) { Settings::save($v); Settings::reset(); };
$file = Settings::FILE;
$saved = is_file($file) ? (string) file_get_contents($file) : null;
$salle = '198.51.100.' . random_int(1, 250);
$autre = '192.0.2.' . random_int(1, 250);
$m = new ReflectionMethod(\App\Admin\System::class, 'checkVenue');
$check = fn (array $c) => $m->invokeArgs(null, [&$c]);

try {
    $set(['soiree.enabled' => true, 'soiree.ips' => $salle, 'soiree.until' => date('Y-m-d\TH:i', time() + 3600)]);
    $ok(Venue::active() && Venue::covers($salle) && !Venue::covers($autre), 'mode soirée en cours : seule l’adresse de la salle est concernée');
    $refus = 0;
    for ($i = 0; $i < Shield::PAGES_MIN + 20; $i++) {
        $r = Shield::check($req($salle));
        $refus += $r && $r->status === 429 ? 1 : 0;
    }
    $ok($refus === 0, 'wifi de la salle : ' . (Shield::PAGES_MIN + 20) . ' pages en une minute, aucune refusée');
    $refus = 0;
    for ($i = 0; $i < Shield::PAGES_MIN + 5; $i++) {
        $r = Shield::check($req($autre));
        $refus += $r && $r->status === 429 ? 1 : 0;
    }
    $ok($refus === 5, 'autre adresse : toujours freinée au-delà de ' . Shield::PAGES_MIN . ' pages (' . $refus . ' refus)');
    $r = Shield::check(new Request('GET', '/joueurs/', [], [], [], ['REMOTE_ADDR' => $salle, 'HTTP_USER_AGENT' => 'GPTBot/1.1', 'HTTP_HOST' => 'musee.fcsochauxretro.com'], ''));
    $ok($r !== null && $r->status === 403, 'wifi de la salle : les robots d’IA restent refusés');
    $n = 0;
    for ($i = 0; $i < 35; $i++) {
        $n += RateLimiter::hit('essai-soiree', $salle, 30, 60) ? 1 : 0;
    }
    $ok($n === 35, 'autres limites de la salle multipliées par ' . Venue::FACTOR . ' (recherche : 35 sur 30 acceptées)');
    $n = 0;
    for ($i = 0; $i < 35; $i++) {
        $n += RateLimiter::hit('essai-soiree', $autre, 30, 60) ? 1 : 0;
    }
    $ok($n === 30, 'autre adresse : limite habituelle (30 sur 35 acceptées)');
    $ok(RateLimiter::hit('essai-soiree-cle', $salle . '|42', 1, 60) && RateLimiter::hit('essai-soiree-cle', $salle . '|42', 1, 60), 'clé « adresse|… » : reconnue comme l’adresse de la salle');

    $set(['soiree.ips' => '']);
    $ok(!Venue::active() && !Venue::covers($salle), 'soirée prête sans adresse : ne s’applique à personne');
    $set(['soiree.ips' => '2001:db8:1:2::1']);
    $ok(Venue::covers('2001:db8:1:2:abcd::9') && !Venue::covers('2001:db8:1:3::1'), 'IPv6 : tout le bloc /64 de la salle, pas le bloc voisin');

    $set(['soiree.ips' => $salle, 'soiree.until' => date('Y-m-d\TH:i', time() - 60)]);
    $ok(!Venue::active() && !Venue::covers($salle), 'heure de fin passée : retour à la normale tout seul');
    $set(['soiree.enabled' => false, 'soiree.until' => date('Y-m-d\TH:i', time() + 3600)]);
    $ok(!Venue::covers($salle), 'mode soirée décoché : aucune adresse concernée');

    $ok(Venue::parse(' 203.0.113.4, foo ; 203.0.113.4 2001:DB8::1') === [['203.0.113.4', '2001:db8::1'], ['foo']], 'saisie des adresses : virgules, espaces, doublons, adresse invalide repérée');
    $c = ['soiree.enabled' => true, 'soiree.ips' => '203.0.113.4', 'soiree.until' => date('Y-m-d\TH:i', time() + 7200)];
    $ok($check($c) === null, 'Réglages : adresse et heure de fin valides acceptées');
    $c = ['soiree.enabled' => true, 'soiree.ips' => '', 'soiree.until' => date('Y-m-d\TH:i', time() + 7200)];
    $ok($check($c) === null, 'Réglages : soirée préparée sans adresse acceptée (on l’ajoute sur place)');
    $c = ['soiree.enabled' => true, 'soiree.ips' => '203.0.113.4', 'soiree.until' => ''];
    $ok(($check($c)['field'] ?? '') === 'until', 'Réglages : heure de fin obligatoire');
    $c = ['soiree.enabled' => true, 'soiree.ips' => '203.0.113.4', 'soiree.until' => date('Y-m-d\TH:i', time() + 4 * 86400)];
    $ok(str_contains((string) ($check($c)['error'] ?? ''), '3 jours'), 'Réglages : 3 jours au plus');
    $c = ['soiree.enabled' => false, 'soiree.ips' => '999.1.1.1', 'soiree.until' => ''];
    $ok(($check($c)['field'] ?? '') === 'ips', 'Réglages : adresse invalide refusée');
    $c = ['soiree.enabled' => false, 'soiree.ips' => '', 'soiree.until' => ''];
    $ok($check($c) === null, 'Réglages : mode soirée décoché, rien d’obligatoire');
} finally {
    $saved === null ? @unlink($file) : file_put_contents($file, $saved);
    Settings::reset();
    foreach (['essai-soiree', 'essai-soiree-cle', 'aspi-pages', 'aspi-heure'] as $b) {
        foreach ([$salle, $autre, $salle . '|42'] as $k) {
            RateLimiter::clear($b, $k);
        }
    }
}
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
