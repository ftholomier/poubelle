<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Envoi de notifications Web Push, sans bibliothèque : OpenSSL de PHP suffit.
 *
 * - Clés VAPID du musée (courbe P-256), créées une fois et gardées dans storage/push/vapid.json :
 *   la clé publique est donnée aux navigateurs à l'abonnement ; la clé privée signe un jeton
 *   (JWT ES256, RFC 8292) à chaque envoi, pour que le service de notifications (Google, Mozilla,
 *   Apple, Microsoft) sache que le message vient bien du musée.
 * - Chiffrement du message pour le navigateur abonné (RFC 8291, « aes128gcm », RFC 8188) :
 *   clé éphémère, accord ECDH avec la clé du navigateur, dérivations HKDF, AES-128-GCM. Seul le
 *   navigateur peut lire le message ; le service de notifications ne voit qu'un bloc chiffré.
 * - Envoi en parallèle (curl_multi), uniquement vers les services de notifications reconnus
 *   (jamais vers une adresse quelconque fournie par un navigateur).
 */
final class WebPush
{
    public static string $dir = STORAGE_PATH . '/push';
    /** Services de notifications reconnus (fin du nom d'hôte). */
    public const HOSTS = ['fcm.googleapis.com', 'android.googleapis.com', 'updates.push.services.mozilla.com', 'push.services.mozilla.com', 'web.push.apple.com', 'notify.windows.com'];
    /** Tests seulement : adresses locales acceptées (faux service de notifications). */
    public static bool $allowLocal = false;
    /** Taille maximale du message en clair (4 096 octets chiffrés au plus chez tous les services). */
    public const MAX_PAYLOAD = 3800;
    /** En-tête DER d'une clé publique P-256 non compressée (SubjectPublicKeyInfo). */
    private const P256_SPKI = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    // ------------------------------------------------------------------ base64url

    public static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string
    {
        $s = strtr(trim($s), '-_', '+/');
        $d = base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $d === false ? '' : $d;
    }

    // ------------------------------------------------------------------ disponibilité et clés

    /** OpenSSL sait-il faire ce qu'il faut (courbe P-256, ECDH, AES-GCM) ? */
    public static function available(): bool
    {
        return extension_loaded('openssl') && function_exists('openssl_pkey_derive') && function_exists('curl_multi_init')
            && in_array('prime256v1', openssl_get_curve_names() ?: [], true) && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    }

    /** Paire de clés P-256 : [clé privée PEM, clé publique brute de 65 octets]. */
    private static function newKey(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$k || !openssl_pkey_export($k, $pem)) {
            throw new \RuntimeException('OpenSSL ne sait pas créer de clé P-256.');
        }
        return [$pem, self::rawPublic($k)];
    }

    /** Clé publique brute (0x04 ‖ X ‖ Y) d'une clé OpenSSL. */
    private static function rawPublic(\OpenSSLAsymmetricKey $k): string
    {
        $d = openssl_pkey_get_details($k);
        $x = str_pad((string) ($d['ec']['x'] ?? ''), 32, "\0", STR_PAD_LEFT);
        $y = str_pad((string) ($d['ec']['y'] ?? ''), 32, "\0", STR_PAD_LEFT);
        return "\x04" . $x . $y;
    }

    /** Clé publique brute d'un navigateur → clé OpenSSL. */
    private static function publicKey(string $raw): \OpenSSLAsymmetricKey
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::P256_SPKI) . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $k = openssl_pkey_get_public($pem);
        if (!$k) {
            throw new \InvalidArgumentException('Clé du navigateur illisible.');
        }
        return $k;
    }

    /** Clé publique d'abonnement valable : 65 octets, point non compressé de la courbe P-256. */
    public static function validPublic(string $raw): bool
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
            return false;
        }
        try {
            self::publicKey($raw);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Clés VAPID du musée (créées au premier besoin). @return array{private:string,public:string}
     * public : base64url de la clé brute (applicationServerKey des navigateurs).
     */
    public static function vapid(): array
    {
        $f = self::$dir . '/vapid.json';
        $v = \App\Core\JsonStore::read($f);
        if (is_array($v) && !empty($v['private']) && !empty($v['public'])) {
            return $v;
        }
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
        $out = null;
        // Créées une seule fois, même si deux pages le demandent en même temps.
        \App\Core\JsonStore::update($f, function ($cur) use (&$out) {
            if (is_array($cur) && !empty($cur['private']) && !empty($cur['public'])) {
                return $out = $cur;
            }
            [$pem, $raw] = self::newKey();
            return $out = ['private' => $pem, 'public' => self::b64($raw), 'created' => date('c')];
        });
        @chmod($f, 0600);
        return $out;
    }

    public static function publicKeyB64(): string
    {
        return self::vapid()['public'];
    }

    // ------------------------------------------------------------------ jeton VAPID (RFC 8292)

    /** Jeton ES256 pour l'origine du service de notifications. */
    public static function jwt(string $endpoint, string $subject, ?int $exp = null): string
    {
        $u = parse_url($endpoint);
        $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
        $head = self::b64((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $body = self::b64((string) json_encode(['aud' => $aud, 'exp' => $exp ?? time() + 12 * 3600, 'sub' => $subject], JSON_UNESCAPED_SLASHES));
        $key = openssl_pkey_get_private(self::vapid()['private']);
        if (!$key || !openssl_sign("$head.$body", $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Signature VAPID impossible.');
        }
        return "$head.$body." . self::b64(self::derToRaw($der));
    }

    /** Signature ECDSA DER → r ‖ s (64 octets), forme attendue par JWT. */
    private static function derToRaw(string $der): string
    {
        $pos = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $pos += ord($der[1]) & 0x7f;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $out;
    }

    // ------------------------------------------------------------------ chiffrement (RFC 8291)

    /**
     * Corps chiffré « aes128gcm » d'un message pour un navigateur abonné.
     * $p256dh : clé publique brute du navigateur (65 octets), $auth : secret d'authentification (16 octets).
     * $salt et $ephemeral (clé privée PEM) : imposés par les tests seulement.
     */
    public static function encrypt(string $payload, string $p256dh, string $auth, ?string $salt = null, ?string $ephemeral = null): string
    {
        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new \LengthException('Message trop long.');
        }
        $ua = self::publicKey($p256dh);
        if ($ephemeral !== null) {
            $as = openssl_pkey_get_private($ephemeral);
            $asPublic = self::rawPublic($as);
        } else {
            [$pem, $asPublic] = self::newKey();
            $as = openssl_pkey_get_private($pem);
        }
        $ecdh = openssl_pkey_derive($ua, $as);
        if ($ecdh === false || strlen($ecdh) !== 32) {
            throw new \RuntimeException('Accord de clés ECDH impossible.');
        }
        $salt ??= random_bytes(16);
        // IKM = HKDF(auth, ecdh, "WebPush: info" ‖ 0 ‖ clé navigateur ‖ clé éphémère, 32)
        $prkKey = hash_hmac('sha256', $ecdh, $auth, true);
        $ikm = hash_hmac('sha256', "WebPush: info\0" . $p256dh . $asPublic . "\x01", $prkKey, true);
        // CEK (16 octets) et nonce (12 octets) dérivés du sel.
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);
        // Un seul enregistrement : le message suivi du délimiteur 0x02.
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Chiffrement AES-GCM impossible.');
        }
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    // ------------------------------------------------------------------ envoi

    /** Adresse d'abonnement acceptée : HTTPS chez un service de notifications reconnu. */
    public static function validEndpoint(string $endpoint): bool
    {
        if (strlen($endpoint) > 1000 || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return false;
        }
        $u = parse_url($endpoint);
        $host = strtolower((string) ($u['host'] ?? ''));
        // Faux service de notifications local : tests, et serveur de développement lancé avec PUSH_ALLOW_LOCAL=1.
        if ((self::$allowLocal || (PHP_SAPI === 'cli-server' && getenv('PUSH_ALLOW_LOCAL') === '1')) && ($u['scheme'] ?? '') === 'http' && $host === '127.0.0.1') {
            return true;
        }
        if (($u['scheme'] ?? '') !== 'https' || isset($u['user']) || (isset($u['port']) && (int) $u['port'] !== 443)) {
            return false;
        }
        foreach (self::HOSTS as $h) {
            if ($host === $h || str_ends_with($host, '.' . $h)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Envoie des messages en parallèle. $jobs : [clé => [endpoint, p256dh brut, auth brut, message JSON]].
     * $o : ttl (secondes), urgency (very-low, low, normal, high), topic (remplace un message en attente
     * de même sujet), concurrency, timeout.
     * @return array<string,array{status:int,error:string}> statut HTTP par clé (0 : réseau ou chiffrement)
     */
    public static function send(array $jobs, string $subject, array $o = []): array
    {
        $out = [];
        $ttl = (int) ($o['ttl'] ?? 86400);
        $urgency = in_array($o['urgency'] ?? '', ['very-low', 'low', 'normal', 'high'], true) ? $o['urgency'] : 'normal';
        $topic = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($o['topic'] ?? ''));
        $max = max(1, (int) ($o['concurrency'] ?? 20));
        $timeout = max(2, (int) ($o['timeout'] ?? 10));
        $public = self::publicKeyB64();
        $jwts = [];
        $queue = $jobs;
        $mh = curl_multi_init();
        $running = [];
        $start = function () use (&$queue, &$running, &$out, &$jwts, $mh, $subject, $public, $ttl, $urgency, $topic, $timeout) {
            $key = array_key_first($queue);
            [$endpoint, $p256dh, $auth, $payload] = $queue[$key];
            unset($queue[$key]);
            if (!self::validEndpoint($endpoint)) {
                $out[$key] = ['status' => 0, 'error' => 'adresse refusée'];
                return;
            }
            try {
                $body = self::encrypt($payload, $p256dh, $auth);
                $u = parse_url($endpoint);
                $origin = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
                $jwts[$origin] ??= self::jwt($endpoint, $subject);
            } catch (\Throwable $e) {
                $out[$key] = ['status' => 0, 'error' => $e->getMessage()];
                return;
            }
            $h = ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: ' . $ttl, 'Urgency: ' . $urgency,
                'Authorization: vapid t=' . $jwts[$origin] . ', k=' . $public];
            if ($topic !== '') {
                $h[] = 'Topic: ' . substr($topic, 0, 32);
            }
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | (self::$allowLocal || (PHP_SAPI === 'cli-server' && getenv('PUSH_ALLOW_LOCAL') === '1') ? CURLPROTO_HTTP : 0)]);
            curl_multi_add_handle($mh, $ch);
            $running[(int) $ch] = [$key, $ch];
        };
        while ($queue || $running) {
            while ($queue && count($running) < $max) {
                $start();
            }
            if (!$running) {
                continue;
            }
            curl_multi_exec($mh, $active);
            curl_multi_select($mh, 0.5);
            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                [$key] = $running[(int) $ch];
                $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $err = $info['result'] !== CURLE_OK ? curl_error($ch) : ($status >= 400 ? mb_substr(trim((string) curl_multi_getcontent($ch)), 0, 160) : '');
                $out[$key] = ['status' => $info['result'] === CURLE_OK ? $status : 0, 'error' => $err];
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($running[(int) $ch]);
            }
        }
        curl_multi_close($mh);
        return $out;
    }
}
