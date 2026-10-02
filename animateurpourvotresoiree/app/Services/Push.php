<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Crypto;
use App\Core\Env;
use App\Core\Http;
use App\Core\Logger;

/**
 * Notifications Web Push natives (RFC 8030 / 8291 / 8292) : clés VAPID ES256,
 * chiffrement aes128gcm via OpenSSL, sans bibliothèque externe.
 */
final class Push
{
    /** Services push des navigateurs (Chrome/Opera/Samsung, Firefox, Safari, Edge). */
    private const HOSTS = ['fcm.googleapis.com', 'android.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com'];

    public static function available(): bool
    {
        return function_exists('openssl_pkey_derive') && defined('OPENSSL_KEYTYPE_EC') && (bool) Settings::get('features.push', true);
    }

    /** Génère les clés VAPID si elles n'existent pas encore (stockées dans config/.env). */
    public static function ensureKeys(): bool
    {
        if (!self::available()) {
            return false;
        }
        if ((string) Env::get('VAPID_PUBLIC_KEY', '') !== '' && (string) Env::get('VAPID_PRIVATE_KEY', '') !== '') {
            return true;
        }
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) {
            return false;
        }
        openssl_pkey_export($key, $pem);
        $det = openssl_pkey_get_details($key);
        $pub = "\x04" . str_pad($det['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($det['ec']['y'], 32, "\0", STR_PAD_LEFT);
        \App\Core\Env::write([
            'VAPID_PUBLIC_KEY' => Crypto::b64u($pub),
            'VAPID_PRIVATE_KEY' => base64_encode($pem),
            'VAPID_SUBJECT' => (string) Env::get('VAPID_SUBJECT', '') ?: 'mailto:' . (string) Env::get('CONTACT_EMAIL', 'contact@example.com'),
        ]);
        return true;
    }

    public static function publicKey(): string
    {
        return (string) Env::get('VAPID_PUBLIC_KEY', '');
    }

    public static function subscribe(string $ownerType, int $ownerId, array $sub, string $ua = ''): ?array
    {
        $endpoint = (string) ($sub['endpoint'] ?? '');
        $p256 = (string) ($sub['keys']['p256dh'] ?? '');
        $auth = (string) ($sub['keys']['auth'] ?? '');
        if (!self::endpointAllowed($endpoint) || strlen(Crypto::b64uDecode($p256)) !== 65 || strlen(Crypto::b64uDecode($auth)) < 16) {
            return null;
        }
        $hash = sha1($endpoint);
        $col = Store::pushSubs();
        foreach ($col->ids('endpoint_hash', $hash) as $id) {
            $col->delete($id);
        }
        return $col->insert([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'owner' => $ownerType . ':' . $ownerId,
            'endpoint' => $endpoint,
            'endpoint_hash' => $hash,
            'p256dh' => $p256,
            'auth' => $auth,
            'ua' => mb_substr($ua, 0, 200),
            'fails' => 0,
        ]);
    }

    /**
     * Seuls les services push des navigateurs sont acceptés comme destination : l'adresse est fournie par le
     * navigateur, on ne laisse donc pas le serveur appeler n'importe quelle URL (réseau interne, etc.).
     */
    public static function endpointAllowed(string $endpoint): bool
    {
        if (strlen($endpoint) > 1000 || !preg_match('#^https://([a-z0-9.-]+)(?::443)?/#i', $endpoint, $m)) {
            return false;
        }
        $host = strtolower($m[1]);
        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }

    public static function unsubscribe(string $endpoint): void
    {
        $col = Store::pushSubs();
        foreach ($col->ids('endpoint_hash', sha1($endpoint)) as $id) {
            $col->delete($id);
        }
    }

    public static function toAdmins(string $title, string $body, string $url, string $tag = 'admin'): int
    {
        $n = 0;
        foreach (Store::admins()->iterate() as $a) {
            if ($a['status'] === 'active') {
                $n += self::toOwner('admin', (int) $a['id'], $title, $body, $url, $tag);
            }
        }
        return $n;
    }

    public static function toOwner(string $type, int $id, string $title, string $body, string $url, string $tag = ''): int
    {
        if (!self::available() || self::publicKey() === '') {
            return 0;
        }
        $col = Store::pushSubs();
        $sent = 0;
        foreach ($col->ids('owner', $type . ':' . $id) as $sid) {
            $sub = $col->get($sid);
            if (!$sub) {
                continue;
            }
            $res = self::send($sub, ['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag, 'icon' => '/assets/img/icon-192.png', 'badge' => '/assets/img/badge-72.png']);
            if ($res['ok']) {
                $sent++;
                if (($sub['fails'] ?? 0) > 0) {
                    $col->update($sid, ['fails' => 0, 'last_ok' => date('c')], false);
                }
            } elseif ($res['gone'] || ($sub['fails'] ?? 0) >= 5) {
                $col->delete($sid);
            } else {
                $col->update($sid, ['fails' => (int) ($sub['fails'] ?? 0) + 1], false);
            }
        }
        return $sent;
    }

    /** @return array{ok:bool, gone:bool, status:int} */
    public static function send(array $sub, array $payload): array
    {
        if (!self::endpointAllowed((string) ($sub['endpoint'] ?? ''))) {
            return ['ok' => false, 'gone' => true, 'status' => 0];
        }
        try {
            $body = self::encrypt((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) $sub['p256dh'], (string) $sub['auth']);
            $res = Http::request('POST', (string) $sub['endpoint'], [
                'TTL' => '86400',
                'Urgency' => 'normal',
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'Authorization' => 'vapid t=' . self::jwt((string) $sub['endpoint']) . ', k=' . self::publicKey(),
            ], $body, 15, false);
            $ok = $res['status'] >= 200 && $res['status'] < 300;
            if (!$ok) {
                Logger::log('push', 'Push refusé', ['status' => $res['status'], 'body' => mb_substr($res['body'], 0, 300)], 'warning');
            }
            return ['ok' => $ok, 'gone' => in_array($res['status'], [404, 410], true), 'status' => $res['status']];
        } catch (\Throwable $e) {
            Logger::log('push', 'Erreur push', ['error' => $e->getMessage()], 'error');
            return ['ok' => false, 'gone' => false, 'status' => 0];
        }
    }

    private static function jwt(string $endpoint): string
    {
        $p = parse_url($endpoint);
        $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $header = Crypto::b64u('{"typ":"JWT","alg":"ES256"}');
        $claims = Crypto::b64u((string) json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => (string) Env::get('VAPID_SUBJECT', 'mailto:contact@example.com')], JSON_UNESCAPED_SLASHES));
        $input = $header . '.' . $claims;
        $pk = openssl_pkey_get_private((string) base64_decode((string) Env::get('VAPID_PRIVATE_KEY', '')));
        if ($pk === false || !openssl_sign($input, $der, $pk, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Signature VAPID impossible');
        }
        return $input . '.' . Crypto::b64u(self::derToRaw($der));
    }

    /** Signature ECDSA DER → r||s (64 octets). */
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        if (ord($der[1]) & 0x80) {
            $pos = 2 + (ord($der[1]) & 0x7F);
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $pos++; // 0x02
            $len = ord($der[$pos++]);
            $int = substr($der, $pos, $len);
            $pos += $len;
            $int = ltrim($int, "\0");
            $parts[] = str_pad($int, 32, "\0", STR_PAD_LEFT);
        }
        return $parts[0] . $parts[1];
    }

    private static function encrypt(string $payload, string $p256dhB64, string $authB64): string
    {
        $uaPublic = Crypto::b64uDecode($p256dhB64);
        $authSecret = Crypto::b64uDecode($authB64);
        $local = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $det = openssl_pkey_get_details($local);
        $asPublic = "\x04" . str_pad($det['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($det['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $uaPublic;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $peer = openssl_pkey_get_public($pem);
        $shared = openssl_pkey_derive($peer, $local, 32);
        if ($shared === false) {
            throw new \RuntimeException('Échange de clés ECDH impossible');
        }
        $prkKey = hash_hmac('sha256', $shared, $authSecret, true);
        $ikm = hash_hmac('sha256', "WebPush: info\0" . $uaPublic . $asPublic . "\x01", $prkKey, true);
        $salt = random_bytes(16);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }
}
