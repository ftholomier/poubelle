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

// Anniversaires.
\App\Services\WebPush::$dir = "$tmp/push";
$r = C::create('fidele@example.org');
$fid = $r['carnet']['id'];
$m1 = C::seasonMatches('1987-1988')[3];
$m2 = C::seasonMatches('2013-2014')[5];
C::setMatches($fid, [$m1['id'], $m2['id']], true);
$day = (string) (date('Y') + 1) . substr((string) $m1['date'], 4);
$a = C::anniversaryOf([$m1['id'], $m2['id']], $day);
$eq('anniversaire : le match de ce jour, années comptées', [$a['m']['id'] ?? null, $a['years'] ?? null], [$m1['id'], (int) date('Y') + 1 - (int) substr((string) $m1['date'], 0, 4)]);
$eq('pas d’anniversaire l’année même du match ni un autre jour', [C::anniversaryOf([$m1['id']], (string) $m1['date']), C::anniversaryOf([$m1['id']], substr($day, 0, 5) . (substr($day, 5, 2) === '01' ? '02' : '01') . substr($day, 7))], [null, null]);
$msg = C::anniversaryMessage($a);
$eq('message : titre, score, lien vers la fiche', [str_contains($msg['title'], 'jour pour jour'), str_contains($msg['body'], (string) $m1['away']), $msg['url']], [true, true, $m1['path']]);
$eq('sans rappel demandé : rien', C::anniversaries($day, 10), ['emails' => 0, 'push' => 0]);
@mkdir("$tmp/push", 0775, true);
file_put_contents("$tmp/push/abonnes.json", json_encode(['abonne-test' => ['e' => 'https://push.example.org/x', 'k' => 'k', 'a' => 'a', 'l' => 'fr', 't' => []]]));
C::setReminders($fid, true, 'abonne-test');
C::confirm($fid);
$eq('avant 9 h : rien', C::anniversaries($day, 8), ['emails' => 0, 'push' => 0]);
C::anniversaries($day, 10);
$job = (json_decode((string) @file_get_contents("$tmp/push/file.json"), true) ?: [])[0] ?? [];
$eq('notification : vers le seul appareil choisi, cachée de l’historique', [$job['ids'] ?? null, $job['hidden'] ?? null, $job['title'] ?? null], [['abonne-test'], true, $msg['title']]);
$eq('rappel noté : jamais deux fois le même jour', [C::get($fid)['reminded'], C::anniversaries($day, 11)], [$day, ['emails' => 0, 'push' => 0]]);
$eq('lien « ne plus recevoir » signé', [C::stopSig($fid) === C::stopSig($fid), C::stopSig($fid) !== C::stopSig(str_repeat('a', 16))], [true, true]);
C::setReminders($fid, false, null, true);
$eq('rappels arrêtés', [C::get($fid)['remind_email'], C::get($fid)['remind_push']], [false, []]);

// Poster « Ma vie en jaune et bleu ».
use App\Shop\CarnetPoster as CP;
$m = \App\Shop\Catalog::find('c4a7e1b3d9');
$eq('modèle du poster : genre carnet, champs carnet + dédicace', [\App\Shop\Poster::kind($m), array_keys(\App\Shop\Catalog::fields($m))], ['carnet', ['poster_carnet', 'poster_prenom', 'poster_nom']]);
$eq('exemple : dessiné, jamais commandable', [count(CP::layers(['x' => 0, 'y' => 0, 'w' => 297, 'h' => 420], ['poster_carnet' => 'exemple'])) > 50, CP::eligible('exemple')], [true, false]);
$p = C::create('poster@example.org');
$pid = $p['carnet']['id'];
C::setMatches($pid, array_slice($ids, 0, 12), true);
unset($_COOKIE[C::COOKIE]);
$eq('carnet d’un autre (privé) : refusé', CP::eligible($pid), false);
$_COOKIE[C::COOKIE] = $p['credential'];
$eq('son propre carnet (cookie) : commandable', CP::eligible($pid), true);
$chk = \App\Shop\Catalog::check($m, ['poster_carnet' => $pid, 'poster_prenom' => 'Jean', 'poster_nom' => 'Lionceau']);
$eq('contrôle de la commande : accepté', $chk['errors'], []);
$frozen = implode(',', CP::idsFor($pid));
$d = CP::data(CP::idsFor($pid));
$eq('données du poster : 12 matchs, saisons, grands matchs', [$d['n'], array_sum(array_column($d['seasons_list'], 'm')), count($d['big']) >= 2], [12, 12, true]);
$face = ['w' => 297, 'h' => 420, 'bg' => '#0E1F4D', 'layers' => [['id' => 'pc', 'type' => 'poster', 'kind' => 'carnet', 'x' => 0, 'y' => 0, 'w' => 297, 'h' => 420]]];
$svgMine = \App\Shop\Vector::svg($face, ['poster_carnet' => $pid, 'poster_prenom' => 'Jean', 'poster_nom' => 'Lionceau']);
C::delete($pid);
unset($_COOKIE[C::COOKIE]);
$svgFrozen = \App\Shop\Vector::svg($face, ['poster_carnet' => $pid, '_carnet_ids' => $frozen, 'poster_prenom' => 'Jean', 'poster_nom' => 'Lionceau']);
$eq('fichier d’impression figé : identique même après suppression du carnet', $svgFrozen === $svgMine, true);
[$mm] = \App\Shop\Catalog::applyOptions($m, []);
$pdf = \App\Shop\Catalog::printPdf($mm, ['poster_carnet' => $pid, '_carnet_ids' => $frozen, 'poster_prenom' => 'Jean', 'poster_nom' => 'Lionceau', '_poster_no' => '2026-0001'], 'Poster', [], 'A3');
$eq('PDF d’impression produit', str_starts_with($pdf, '%PDF') && strlen($pdf) > 20000, true);

array_map('unlink', glob("$tmp/push/*") ?: []);
@rmdir("$tmp/push");
array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
echo $fail ? "\n$fail échec(s).\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
