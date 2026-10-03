<?php
/**
 * Verrou de modification (App\Services\EditLock) : prise, observation, prise de main, onglets,
 * libération, expiration, inactivité. Usage : php tests/verrou.php (code de sortie 1 en cas
 * d'échec). N'écrit que dans un fichier temporaire.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Admin\Base;
use App\Core\JsonStore;
use App\Services\EditLock as L;

$tmp = sys_get_temp_dir() . '/verrou-test-' . bin2hex(random_bytes(4)) . '.json';
L::$file = $tmp;
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$marie = ['id' => 'u1', 'name' => 'Marie Dupont'];
$paul = ['id' => 'u2', 'name' => 'Paul Martin'];
$k = 'fiche:22054';

$eq('clés valides', [L::validKey('fiche:12'), L::validKey('collection:quiz'), L::validKey('ecran:rubrique-annees-90')], [true, true, true]);
$eq('clés refusées', [L::validKey('fiche:abc'), L::validKey('../settings'), L::validKey('ecran:A B')], [false, false, false]);

// Marie ouvre la fiche, Paul arrive ensuite.
$r = L::ping($k, $marie, 'hold', 'aaaaaa');
$eq('fiche libre : Marie la prend', [$r['mine'], $r['holder']], [true, null]);
$r = L::ping($k, $paul, 'hold', 'bbbbbb');
$eq('Paul voit Marie', [$r['mine'], $r['holder']['name'] ?? null], [false, 'Marie Dupont']);
$eq('Paul n’a rien pris', L::active()[$k]['uid'], 'u1');
$eq('contrôle à l’enregistrement', [L::holder($k, 'u2')['name'] ?? null, L::holder($k, 'u1')], ['Marie Dupont', null]);
$r = L::ping($k, $paul, 'watch', 'bbbbbb');
$eq('observation', [$r['mine'], $r['holder']['name'] ?? null, $r['taken']], [false, 'Marie Dupont', null]);
$eq('liste des fiches ouvertes', array_keys(L::fiches()), [22054]);

// Deux onglets pour Marie : le verrou tombe avec le dernier.
L::ping($k, $marie, 'hold', 'cccccc');
L::ping($k, $marie, 'release', 'aaaaaa');
$eq('un onglet fermé, l’autre garde la fiche', L::holder($k, 'u2')['name'] ?? null, 'Marie Dupont');
L::ping($k, $marie, 'release', 'cccccc');
$eq('dernier onglet fermé : fiche libre', L::holder($k, 'u2'), null);
$eq('Paul ne peut pas libérer la fiche de Marie', (function () use ($k, $marie, $paul) {
    L::ping($k, $marie, 'hold', 'aaaaaa');
    L::ping($k, $paul, 'release', 'aaaaaa');
    return L::holder($k, 'u2')['name'] ?? null;
})(), 'Marie Dupont');

// Paul prend la main : Marie en est prévenue.
$r = L::ping($k, $paul, 'take', 'bbbbbb');
$eq('prise de main', [$r['mine'], $r['took']], [true, 'Marie Dupont']);
$r = L::ping($k, $marie, 'hold', 'aaaaaa');
$eq('Marie est prévenue', [$r['mine'], $r['holder']['name'] ?? null, $r['taken']['by'] ?? null], [false, 'Paul Martin', 'Paul Martin']);
$r = L::ping($k, ['id' => 'u3', 'name' => 'Lucie'], 'hold', 'dddddd');
$eq('une troisième personne voit Paul, sans alerte', [$r['holder']['name'] ?? null, $r['taken']], ['Paul Martin', null]);
$r = L::ping($k, $paul, 'hold', 'bbbbbb');
$eq('Paul garde la main', [$r['mine'], $r['taken']], [true, null]);
L::ping($k, $paul, 'release', 'bbbbbb');
$r = L::ping($k, $marie, 'watch', 'aaaaaa');
$eq('Paul parti : fiche libre pour Marie', [$r['mine'], $r['holder'], $r['taken']], [false, null, null]);

// Inactivité et expiration.
$r = L::ping($k, $marie, 'hold', 'aaaaaa', 25 * 60);
$h = L::holder($k, 'u2');
$eq('inactivité signalée', Base::lockInfo($h)['idle'] >= 24, true);
$eq('heure de début', Base::lockInfo($h)['since'], date('G \h i'));
JsonStore::update($tmp, function ($all) use ($k) {
    $all[$k]['tabs']['aaaaaa'] = time() - L::TTL - 5;
    return $all;
}, []);
$eq('onglet muet depuis plus de 2 minutes : fiche libre', [L::holder($k, 'u2'), L::ping($k, $paul, 'hold', 'bbbbbb')['mine']], [null, true]);
$eq('identifiant d’onglet invalide ramené à un seul', (function () use ($marie) {
    L::ping('fiche:1', $marie, 'hold', '../../x');
    return array_keys(JsonStore::read(L::$file, [])['fiche:1']['tabs']);
})(), ['x']);
$eq('clé d’écran', Base::lockKey('ecran:rubrique-', 'Matchs/Années 90'), 'ecran:rubrique-matchs-ann-es-90');

@unlink($tmp);
@unlink($tmp . '.lock');
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
