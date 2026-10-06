<?php
/**
 * Carnet du supporter (App\Services\Carnet) : création avec e-mail, un seul carnet par e-mail,
 * accès par lien (plusieurs appareils), matchs ajoutés et retirés, bilan, porte-bonheur,
 * badges, page publique sous pseudo, suppression, ménage, carte à partager.
 * Usage : php tests/carnet.php (code de sortie 1 en cas d'échec). N'écrit que dans un dossier
 * temporaire (et le cache des images de partage).
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Carnet as C;

$tmp = sys_get_temp_dir() . '/carnet-test-' . bin2hex(random_bytes(4));
C::$dir = $tmp;
$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};

// Création, un carnet par e-mail.
$r = C::create('Supporter@Example.org');
$id = $r['carnet']['id'];
$eq('carnet créé, identifiant et lien', [$r['existing'], (bool) preg_match('/^[a-f0-9]{16}$/', $id), (bool) preg_match('/^[a-f0-9]{16}\.[a-f0-9]{32}$/', (string) $r['credential'])], [false, true, true]);
$eq('jeton jamais gardé en clair', str_contains((string) file_get_contents("$tmp/$id.json"), explode('.', $r['credential'])[1]), false);
$r2 = C::create('supporter@example.org ');
$eq('même e-mail (casse, espaces) : pas de second carnet', [$r2['existing'], $r2['credential'], $r2['carnet']['id'] ?? null], [true, null, $id]);
$eq('ouverture par le lien', C::open($r['credential'])['id'] ?? null, $id);
$eq('lien faux ou abîmé refusé', [C::open($id . '.' . str_repeat('0', 32)), C::open('x'), C::open(substr($r['credential'], 0, -1))], [null, null, null]);
$cred2 = C::newCredential($id);
$eq('nouveau lien (autre appareil) : les deux marchent', [C::open($cred2)['id'] ?? null, C::open($r['credential'])['id'] ?? null], [$id, $id]);
$eq('carnet retrouvé par e-mail', C::byEmail('SUPPORTER@example.org')['id'] ?? null, $id);

// Matchs.
$season = C::seasonMatches('1987-1988');
$ids = array_column($season, 'id');
$eq('saison 1987-1988 : matchs dans l’ordre des dates', count($season) > 30 && array_column($season, 'date') === (function ($d) { sort($d); return $d; })(array_column($season, 'date')), true);
$list = C::setMatches($id, array_merge($ids, [999999999]), true, $added);
$eq('matchs ajoutés (match inconnu ignoré)', [count($list), count($added)], [count($ids), count($ids)]);
C::setMatches($id, [$ids[0]], true, $added);
$eq('un match déjà présent n’est pas recompté', $added, []);
$list = C::setMatches($id, [$ids[0]], false);
$eq('match retiré', in_array($ids[0], $list, true), false);

// Bilan.
$s = C::stats($list);
$eq('bilan : total = V + N + D, domicile + extérieur', [$s['n'], $s['v'] + $s['nul'] + $s['d'], $s['home'] + $s['away']], [count($list), count($list), count($list)]);
$eq('bilan : premier match avant le dernier, liste du plus récent au plus ancien', $s['first']['date'] <= $s['last']['date'] && $s['list'][0]['date'] === $s['last']['date'], true);
$eq('plus belle victoire : une victoire', $s['best']['result'] ?? null, 'V');
$eq('porte-bonheur calculé (5 matchs ou plus)', is_array($s['luck']) && $s['luck']['mine'] >= 0 && $s['luck']['mine'] <= 100, true);
$eq('porte-bonheur absent sous 5 matchs', C::stats(array_slice($list, 0, 3))['luck'], null);
$eq('joueurs vus : entraîneurs exclus, triés', $s['players'] && $s['players'][0]['n'] >= end($s['players'])['n'], true);
$on = array_column(array_filter($s['badges'], fn ($b) => $b['on']), 'key');
$eq('badges : premier match, 10 matchs, pas de centurion', [in_array('premier', $on, true), in_array('m10', $on, true), in_array('m100', $on, true)], [true, true, false]);
$eq('carnet vide : bilan à zéro, aucun badge', [C::stats([])['n'], count(array_filter(C::stats([])['badges'], fn ($b) => $b['on']))], [0, 0]);

// Page publique.
$eq('pseudo trop court refusé', C::setPublic($id, 'x', true) !== null, true);
$eq('page publique ouverte', C::setPublic($id, 'Lionceau <b>88</b>', true), null);
$c = C::get($id);
$eq('pseudo nettoyé, adresse avec pseudo', [$c['pseudo'], $c['slug']], ['Lionceau 88', 'lionceau-88-' . substr($id, 0, 4)]);
$eq('page publique trouvée', C::bySlug($c['slug'])['id'] ?? null, $id);
C::setPublic($id, 'Lionceau 88', false);
$eq('page publique fermée', C::bySlug($c['slug']), null);

// Carte à partager.
$res = \App\Front\Share::carnet($s, 'Lionceau 88');
$eq('carte : image PNG 1200 × 630', [$res->headers['Content-Type'], getimagesizefromstring($res->body)[0] ?? 0, getimagesizefromstring($res->body)[1] ?? 0], ['image/png', 1200, 630]);

// Vue d'ensemble, ménage, suppression.
$o = C::overview();
$eq('vue d’ensemble : 1 carnet, ses matchs', [$o['count'], $o['ticks']], [1, count($list)]);
$old = C::create('ancien@example.org');
\App\Core\JsonStore::update("$tmp/{$old['carnet']['id']}.json", fn ($c) => ['created' => date('c', strtotime('-100 days'))] + $c, null);
$eq('ménage : carnet jamais confirmé et vide, après 90 jours', [C::purge(), C::get($old['carnet']['id']), C::get($id) !== null], [1, null, true]);
C::delete($id);
$eq('suppression : carnet, lien et e-mail oubliés', [C::get($id), C::open($cred2), C::byEmail('supporter@example.org')], [null, null, null]);
$eq('après suppression, le même e-mail peut recréer un carnet', C::create('supporter@example.org')['existing'], false);

array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
