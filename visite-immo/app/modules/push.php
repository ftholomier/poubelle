<?php
// Notifications sur le téléphone (Web Push, standard du web : Android, iPhone avec l'appli ajoutée à l'écran
// d'accueil, ordinateur). En PHP natif : clés VAPID (RFC 8292) et chiffrement aes128gcm (RFC 8291) avec OpenSSL.

function b64u(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64u_dec(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/** Point P-256 non compressé (65 octets) → clé publique OpenSSL. */
function cle_publique_p256(string $point)
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;
    return openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
}

function point_public($cle): string
{
    $d = openssl_pkey_get_details($cle)['ec'];
    return "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
}

/** Clés VAPID de l'appli, créées au premier usage et gardées dans les réglages. */
function cles_vapid(): array
{
    global $CONFIG;
    if (!empty($CONFIG['vapid_prive']) && !empty($CONFIG['vapid_public'])) return [$CONFIG['vapid_public'], $CONFIG['vapid_prive']];
    $cle = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    openssl_pkey_export($cle, $pem);
    $pub = b64u(point_public($cle));
    $s = read_json(SETTINGS_FILE, []);
    $s['vapid_public'] = $pub;
    $s['vapid_prive'] = $pem;
    write_json(SETTINGS_FILE, $s);
    @chmod(SETTINGS_FILE, 0600);
    $CONFIG['vapid_public'] = $pub;
    $CONFIG['vapid_prive'] = $pem;
    return [$pub, $pem];
}

function hkdf_etendre(string $prk, string $info, int $longueur): string
{
    return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $longueur);
}

/** Chiffre le message pour un abonnement (RFC 8291, contenu aes128gcm). */
function chiffrer_push(string $message, string $p256dh, string $auth): string
{
    $ua = b64u_dec($p256dh);
    $secret = b64u_dec($auth);
    $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $as = point_public($eph);
    $partage = openssl_pkey_derive(cle_publique_p256($ua), $eph, 32);
    if ($partage === false) throw new RuntimeException('Clé de l\'abonnement invalide.');
    $ikm = hkdf_etendre(hash_hmac('sha256', $partage, $secret, true), "WebPush: info\0" . $ua . $as, 32);
    $sel = random_bytes(16);
    $prk = hash_hmac('sha256', $ikm, $sel, true);
    $cek = hkdf_etendre($prk, "Content-Encoding: aes128gcm\0", 16);
    $nonce = hkdf_etendre($prk, "Content-Encoding: nonce\0", 12);
    $chiffre = openssl_encrypt($message . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    return $sel . pack('N', 4096) . chr(65) . $as . $chiffre . $tag;
}

/** Signature ES256 au format JWT (r || s), à partir de la signature DER d'OpenSSL. */
function jwt_vapid(string $audience, string $pem, string $sujet): string
{
    $tete = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $corps = b64u(json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => $sujet]));
    openssl_sign("$tete.$corps", $der, $pem, OPENSSL_ALGO_SHA256);
    $pos = 3;
    $lire = function () use ($der, &$pos) {
        $n = ord($der[$pos]);
        $v = substr($der, $pos + 1, $n);
        $pos += $n + 2;
        return str_pad(ltrim($v, "\0"), 32, "\0", STR_PAD_LEFT);
    };
    $r = $lire();
    $s = $lire();
    return "$tete.$corps." . b64u($r . $s);
}

/** Envoie une notification à un abonnement ; renvoie le code HTTP (404/410 : abonnement expiré). */
function envoyer_push(array $abo, array $donnees): int
{
    global $CONFIG;
    [$pub, $prive] = cles_vapid();
    $u = parse_url($abo['endpoint']);
    $audience = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $sujet = !empty($CONFIG['vapid_sujet']) ? $CONFIG['vapid_sujet'] : 'mailto:' . (($CONFIG['email_expediteur'] ?? '') ?: 'contact@synapse.immo');
    $corps = chiffrer_push(json_encode($donnees, JSON_UNESCAPED_UNICODE), $abo['keys']['p256dh'], $abo['keys']['auth']);
    $ch = curl_init($abo['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corps, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 86400', 'Urgency: normal',
            'Authorization: vapid t=' . jwt_vapid($audience, $prive, $sujet) . ', k=' . $pub],
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

/** Prévient l'agent sur tous ses appareils abonnés (les abonnements expirés sont retirés). */
function notifier(array $agent, string $titre, string $texte, string $lien = '#/'): int
{
    $u = user_by_id($agent['id']);
    $abos = $u['push'] ?? [];
    if (!$abos) return 0;
    $ok = 0;
    $expires = [];
    foreach ($abos as $i => $abo) {
        try {
            $code = envoyer_push($abo, ['titre' => $titre, 'texte' => $texte, 'lien' => $lien]);
            if ($code >= 200 && $code < 300) $ok++;
            if (in_array($code, [404, 410], true)) $expires[] = $abo['endpoint'];
        } catch (Throwable $e) {
            error_log('push : ' . $e->getMessage());
        }
    }
    if ($expires) update_json(USERS_FILE, fn (array $users) => array_map(fn ($x) => $x['id'] === $agent['id'] ? ['push' => array_values(array_filter($x['push'] ?? [], fn ($a) => !in_array($a['endpoint'], $expires, true)))] + $x : $x, $users));
    return $ok;
}

route('GET push_cle', fn () => send_json(['cle' => cles_vapid()[0]]));

route('POST push_abonnement', function () {
    $me = require_user();
    $in = json_input();
    $abo = $in['abonnement'] ?? null;
    if (!is_array($abo) || !preg_match('#^https://#', (string) ($abo['endpoint'] ?? '')) || empty($abo['keys']['p256dh']) || empty($abo['keys']['auth'])) {
        if (!getenv('VI_DATA_DIR') || !preg_match('#^http://127\.0\.0\.1#', (string) ($abo['endpoint'] ?? ''))) fail(400, 'Abonnement invalide.'); // tests : service local
    }
    $abo = ['endpoint' => (string) $abo['endpoint'], 'keys' => ['p256dh' => (string) $abo['keys']['p256dh'], 'auth' => (string) $abo['keys']['auth']], 'le' => date('c')];
    update_json(USERS_FILE, fn (array $users) => array_map(fn ($u) => $u['id'] === $me['id']
        ? ['push' => array_values(array_merge(array_filter($u['push'] ?? [], fn ($a) => $a['endpoint'] !== $abo['endpoint']), [$abo]))] + $u : $u, $users));
    send_json(['ok' => true]);
});

route('POST push_test', fn () => send_json(['envoyes' => notifier(require_user(), 'Notifications activées ✓', 'Vous serez prévenu ici des visites réservées, offres et signatures.', '#/')]));
