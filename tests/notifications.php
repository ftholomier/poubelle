<?php
/**
 * Notifications de l'appli du musée (Web Push) : chiffrement RFC 8291 relu par un déchiffrement
 * indépendant, jeton VAPID vérifié avec la clé publique, adresses acceptées (services de
 * notifications reconnus seulement), abonnements (clés contrôlées, sujets, langue), file
 * d'envoi vers un faux service de notifications local (reçu, abonnement disparu effacé, échec
 * compté), message dans la langue de l'abonné, envoi jamais doublé, ouvertures, envois
 * automatiques (Rétro-Direct 30 minutes avant, « Ce jour-là » à l'heure réglée, rien la nuit,
 * rien d'antérieur à la mise en route).
 * Usage : php tests/notifications.php (code de sortie 1 en cas d'échec). Tout se passe dans un
 * dossier temporaire : les abonnés et clés réels ne sont pas touchés.
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Notifications;
use App\Services\RetroDirect;
use App\Services\WebPush;

$fail = 0;
$eq = function (string $label, $got, $exp) use (&$fail) {
    $ok = $got === $exp;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . ($ok ? '' : ' => ' . json_encode($got, JSON_UNESCAPED_UNICODE) . ' attendu ' . json_encode($exp, JSON_UNESCAPED_UNICODE)) . "\n";
};
$tmp = sys_get_temp_dir() . '/sr-push-test-' . getmypid();
@mkdir($tmp, 0777, true);
WebPush::$dir = $tmp;
WebPush::$allowLocal = true;

if (!WebPush::available()) {
    echo "OpenSSL (P-256, ECDH, AES-GCM) ou cURL indisponible : test impossible.\n";
    exit(1);
}

// Navigateur abonné simulé : sa clé P-256 et son secret.
$browser = function (): array {
    $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $d = openssl_pkey_get_details($k);
    $raw = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    return ['key' => $k, 'p256dh' => $raw, 'auth' => random_bytes(16)];
};
$pubPem = fn (string $raw) => "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
// Déchiffrement côté navigateur (RFC 8291), écrit indépendamment de WebPush::encrypt().
$decrypt = function (string $body, array $b) use ($pubPem): ?string {
    $salt = substr($body, 0, 16);
    $idlen = ord($body[20]);
    $asPublic = substr($body, 21, $idlen);
    $ct = substr($body, 21 + $idlen);
    $ecdh = openssl_pkey_derive(openssl_pkey_get_public($pubPem($asPublic)), $b['key']);
    $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $b['p256dh'] . $asPublic, $b['auth']);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $plain = openssl_decrypt(substr($ct, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ct, -16));
    return $plain === false ? null : rtrim(substr($plain, 0, (int) strrpos($plain, "\x02")), "\0");
};

// 1. Chiffrement et jeton VAPID.
$b1 = $browser();
$msg = '{"title":"Rétro-Direct · coup d’envoi","body":"Sochaux 3-1 Monaco ⚽"}';
$enc = WebPush::encrypt($msg, $b1['p256dh'], $b1['auth']);
$eq('chiffré : en-tête RFC 8188 (sel, taille d’enregistrement 4096, clé éphémère de 65 octets)', [unpack('N', substr($enc, 16, 4))[1], ord($enc[20]), $enc[21]], [4096, 65, "\x04"]);
$eq('déchiffré par le navigateur : message identique', $decrypt($enc, $b1), $msg);
$eq('deux envois du même message : chiffrés différents (sel et clé éphémère neufs)', WebPush::encrypt($msg, $b1['p256dh'], $b1['auth']) !== $enc, true);
$eq('message trop long refusé', (function () use ($b1) {
    try {
        WebPush::encrypt(str_repeat('x', 5000), $b1['p256dh'], $b1['auth']);
        return false;
    } catch (\LengthException) {
        return true;
    }
})(), true);
[$h, $p, $s] = explode('.', WebPush::jwt('https://fcm.googleapis.com/fcm/send/abc', 'mailto:contact@example.org'));
$claims = json_decode(WebPush::unb64($p), true);
$sig = WebPush::unb64($s);
$int = fn (string $x) => "\x02" . chr(strlen($x = (ord(($x = ltrim($x, "\0") ?: "\0")[0]) & 0x80 ? "\0" . $x : $x))) . $x;
$der = "\x30" . chr(strlen($rs = $int(substr($sig, 0, 32)) . $int(substr($sig, 32)))) . $rs;
$eq('jeton VAPID : public, origine du service, expiration < 24 h', [$claims['aud'], $claims['sub'], $claims['exp'] > time() && $claims['exp'] <= time() + 86400], ['https://fcm.googleapis.com', 'mailto:contact@example.org', true]);
$eq('jeton VAPID : signature ES256 valable avec la clé publique du musée', openssl_verify("$h.$p", $der, $pubPem(WebPush::unb64(WebPush::publicKeyB64())), OPENSSL_ALGO_SHA256), 1);
$eq('clés VAPID gardées (même clé publique ensuite)', WebPush::publicKeyB64() === WebPush::vapid()['public'] && strlen(WebPush::unb64(WebPush::publicKeyB64())) === 65, true);

// 2. Adresses d'abonnement.
WebPush::$allowLocal = false;
$eq('services reconnus acceptés', array_map([WebPush::class, 'validEndpoint'], ['https://fcm.googleapis.com/fcm/send/x', 'https://updates.push.services.mozilla.com/wpush/v2/x', 'https://web.push.apple.com/x', 'https://wns2-par02p.notify.windows.com/w/?token=x']), [true, true, true, true]);
$eq('toute autre adresse refusée', array_map([WebPush::class, 'validEndpoint'], ['http://fcm.googleapis.com/x', 'https://evil.example/fcm.googleapis.com', 'https://fcm.googleapis.com.evil.example/x', 'https://fcm.googleapis.com:8443/x', 'https://u:p@fcm.googleapis.com/x', 'http://127.0.0.1:8093/p/ok/1', 'file:///etc/passwd']), [false, false, false, false, false, false, false]);
WebPush::$allowLocal = true;

// 3. Faux service de notifications local : enregistre ce qu'il reçoit, répond selon l'adresse.
$port = 8093;
$log = "$tmp/recu.jsonl";
file_put_contents("$tmp/fake.php", '<?php $p = $_SERVER["REQUEST_URI"]; file_put_contents(' . var_export($log, true) . ', json_encode(["path" => $p, "auth" => $_SERVER["HTTP_AUTHORIZATION"] ?? "", "enc" => $_SERVER["HTTP_CONTENT_ENCODING"] ?? "", "ttl" => $_SERVER["HTTP_TTL"] ?? "", "urgency" => $_SERVER["HTTP_URGENCY"] ?? "", "body" => base64_encode(file_get_contents("php://input"))]) . "\n", FILE_APPEND);'
    . ' http_response_code(str_contains($p, "/gone/") ? 410 : (str_contains($p, "/fail/") ? 500 : 201));');
$srv = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", "$tmp/fake.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
usleep(400000);
$sub = fn (string $kind, array $b, string $n) => ['endpoint' => "http://127.0.0.1:$port/p/$kind/$n", 'keys' => ['p256dh' => WebPush::b64($b['p256dh']), 'auth' => WebPush::b64($b['auth'])]];
$received = function () use ($log): array {
    $out = [];
    foreach (is_file($log) ? file($log, FILE_IGNORE_NEW_LINES) : [] as $l) {
        $out[] = json_decode($l, true);
    }
    @unlink($log);
    return $out;
};

// 4. Abonnements.
$b2 = $browser();
$b3 = $browser();
$b4 = $browser();
$eq('clé de navigateur invalide refusée', Notifications::subscribe(['endpoint' => "http://127.0.0.1:$port/p/ok/x", 'keys' => ['p256dh' => WebPush::b64(random_bytes(65)), 'auth' => WebPush::b64(random_bytes(16))]], ['retro'], 'fr')['ok'], false);
$eq('secret d’authentification de mauvaise taille refusé', Notifications::subscribe(['endpoint' => "http://127.0.0.1:$port/p/ok/x", 'keys' => ['p256dh' => WebPush::b64($b1['p256dh']), 'auth' => WebPush::b64(random_bytes(8))]], ['retro'], 'fr')['ok'], false);
$eq('abonné français : Rétro-Direct et nouvelles', Notifications::subscribe($sub('ok', $b1, 'a'), ['retro', 'nouvelles', 'pirate'], 'fr')['ok'], true);
$eq('abonné anglais : nouvelles', Notifications::subscribe($sub('ok', $b2, 'b'), ['nouvelles'], 'en')['ok'], true);
Notifications::subscribe($sub('gone', $b3, 'c'), ['nouvelles'], 'fr');
Notifications::subscribe($sub('fail', $b4, 'd'), ['nouvelles'], 'fr');
$eq('sujet inconnu écarté', Notifications::find($sub('ok', $b1, 'a')['endpoint'])['t'], ['retro', 'nouvelles']);
$eq('sujets modifiés', [Notifications::setTopics($sub('ok', $b1, 'a')['endpoint'], ['retro', 'nouvelles', 'jour']), Notifications::find($sub('ok', $b1, 'a')['endpoint'])['t']], [true, ['retro', 'nouvelles', 'jour']]);
$st = Notifications::stats();
$eq('chiffres : 4 abonnés, 4 aux nouvelles, 1 en anglais', [$st['total'], $st['topics']['nouvelles'], $st['langs']['en']], [4, 4, 1]);

// 5. Envoi de l'équipe : chacun dans sa langue ; disparu effacé ; échec compté.
$r = Notifications::enqueue('manuel:test1', 'nouvelles', ['fr' => ['title' => 'Le Onze de légende', 'body' => 'À vous de voter !', 'url' => '/centenaire/'], 'en' => ['title' => 'The Legendary XI', 'body' => 'Your vote!', 'url' => '/en/centenaire/']]);
$eq('mis en file pour les 4 abonnés aux nouvelles', [$r['ok'], $r['n']], [true, 4]);
$eq('même clé : jamais envoyée deux fois', Notifications::enqueue('manuel:test1', 'nouvelles', ['fr' => ['title' => 'x']])['ok'], false);
$res = Notifications::process(20);
$eq('envoi : 4 envoyées, 2 reçues, 1 disparue, 1 échec', [$res['sent'], $res['ok'], $res['gone'], $res['failed'], $res['left']], [4, 2, 1, 1, 0]);
$got = $received();
$byPath = [];
foreach ($got as $g) {
    $byPath[basename($g['path'])] = $g;
}
$fr = json_decode((string) $decrypt(base64_decode($byPath['a']['body']), $b1), true);
$en = json_decode((string) $decrypt(base64_decode($byPath['b']['body']), $b2), true);
$eq('abonné français : message français, lien du musée', [$fr['title'] ?? null, $fr['url'] ?? null, $fr['lang'] ?? null], ['Le Onze de légende', '/centenaire/', 'fr']);
$eq('abonné anglais : message anglais', [$en['title'] ?? null, $en['url'] ?? null], ['The Legendary XI', '/en/centenaire/']);
$eq('en-têtes : chiffrement aes128gcm, durée de vie, jeton VAPID et clé', [$byPath['a']['enc'], $byPath['a']['ttl'], (bool) preg_match('/^vapid t=[\w-]+\.[\w-]+\.[\w-]+, k=[\w-]{87}$/', $byPath['a']['auth'])], ['aes128gcm', '86400', true]);
$eq('abonnement disparu (410) effacé, l’abonné en échec gardé', [Notifications::find($sub('gone', $b3, 'c')['endpoint']), Notifications::find($sub('fail', $b4, 'd')['endpoint'])['f'] ?? null], [null, 1]);
$h = Notifications::history();
$eq('historique : titre, 4 destinataires, 2 reçues, erreur notée', [$h[0]['title'], $h[0]['n'], $h[0]['ok'], $h[0]['gone'], count($h[0]['errors'] ?? [])], ['Le Onze de légende', 4, 2, 1, 1]);
Notifications::opened($h[0]['id']);
Notifications::opened('pas-un-id');
$eq('ouverture comptée', Notifications::history()[0]['opened'], 1);
Notifications::enqueue('manuel:test2', 'jour', ['fr' => ['title' => 'x', 'url' => 'https://evil.example/']]);
$eq('lien hors du musée remplacé par l’accueil', json_decode(Notifications::queue()[0]['p']['fr'], true)['url'], '/');
Notifications::process(10);
$received();

// 6. Essai pour un seul abonné, hors historique.
$n = count(Notifications::history());
$r = Notifications::enqueue('essai:x', 'essai', ['fr' => ['title' => 'Essai']], ['only' => [Notifications::idOf($sub('ok', $b2, 'b')['endpoint'])], 'hidden' => true]);
Notifications::process(10);
$got = $received();
$eq('essai : un seul destinataire, absent de l’historique', [$r['n'], count($got), count(Notifications::history())], [1, 1, $n]);

// 7. Renouvellement et désabonnement.
$b5 = $browser();
Notifications::renew($sub('ok', $b1, 'a')['endpoint'], $sub('ok', $b5, 'e'), 'fr');
$eq('renouvellement : sujets gardés, ancienne adresse effacée', [Notifications::find($sub('ok', $b5, 'e')['endpoint'])['t'] ?? null, Notifications::find($sub('ok', $b1, 'a')['endpoint'])], [['retro', 'nouvelles', 'jour'], null]);
$eq('désabonnement', [Notifications::unsubscribe($sub('ok', $b2, 'b')['endpoint']), Notifications::find($sub('ok', $b2, 'b')['endpoint'])], [true, null]);

// 8. Envois automatiques.
$match = null;
foreach (\App\Data\Index::published('match') as $s) {
    $doc = \App\Data\Fiches::get((int) $s['id']);
    if ($doc && RetroDirect::playable($doc)) {
        $match = $s;
        break;
    }
}
$start = strtotime('2031-05-17 20:00:00');
RetroDirect::$entries = [['id' => (int) $match['id'], 'date' => '2031-05-17', 'time' => '20:00', 'intro' => '']];
Notifications::$now = $start - 3600;
$keys = fn () => array_column(Notifications::due(), 0);
$eq('Rétro-Direct : rien une heure avant', in_array('retro:' . $match['id'] . ':2031-05-17', $keys(), true), false);
Notifications::$now = $start - 25 * 60;
$due = array_values(array_filter(Notifications::due(), fn ($d) => $d[1] === 'retro'));
$eq('Rétro-Direct : prévu 25 minutes avant, urgent, en français et en anglais', [$due[0][0] ?? null, $due[0][3]['urgency'] ?? null, str_contains($due[0][2]['fr']['title'] ?? '', 'coup d’envoi à 20 h 00'), str_contains($due[0][2]['en']['title'] ?? '', 'kick-off at 8:00 pm'), str_starts_with($due[0][2]['en']['url'] ?? '', '/en/interactif/retro-direct/')],
    ['retro:' . $match['id'] . ':2031-05-17', 'high', true, true, true]);
Notifications::$now = $start + 20 * 60;
$eq('Rétro-Direct : plus rien 20 minutes après le coup d’envoi', in_array('retro:' . $match['id'] . ':2031-05-17', $keys(), true), false);
RetroDirect::$entries = [];
// « Ce jour-là » : à l'heure réglée (9 h), pas la nuit, pas deux fois.
$day = null;
foreach (['06-11', '05-17', '03-08', '12-05', '01-01'] as $md) {
    if (\App\Data\Derived::onThisDay($md)) {
        $day = $md;
        break;
    }
}
Notifications::$now = strtotime("2031-$day 09:30:00");
$eq('« Ce jour-là » : à 9 h 30', in_array("jour:2031-$day", $keys(), true), true);
Notifications::$now = strtotime("2031-$day 07:30:00");
$eq('rien la nuit (7 h 30)', in_array("jour:2031-$day", $keys(), true), false);
Notifications::$now = strtotime("2031-$day 09:30:00");
Notifications::enqueue("jour:2031-$day", 'jour', ['fr' => ['title' => 'x']]);
$eq('déjà envoyé : plus proposé', in_array("jour:2031-$day", $keys(), true), false);
// Moments du centenaire : jamais ceux parus avant la mise en route.
$eq('aucun moment antérieur à la mise en route', array_values(array_filter($keys(), fn ($k) => str_starts_with($k, 'moment:'))), []);

proc_terminate($srv);
proc_close($srv);
Notifications::$now = null;
array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);
echo $fail ? "\n$fail échec(s)\n" : "\nTout est bon.\n";
exit($fail ? 1 : 0);
