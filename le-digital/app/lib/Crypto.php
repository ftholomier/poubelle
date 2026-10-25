<?php
declare(strict_types=1);

namespace LD;

/**
 * Chiffrement des secrets au repos (clés API, mots de passe SMTP…).
 * libsodium (secretbox) si disponible, sinon AES-256-GCM via OpenSSL.
 */
final class Crypto
{
    private static ?string $key = null;

    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        $file = Store::path('secrets/app.key');
        if (!is_file($file)) {
            $raw = random_bytes(32);
            file_put_contents($file, base64_encode($raw), LOCK_EX);
            @chmod($file, 0600);
        }
        $key = base64_decode(trim((string) file_get_contents($file)), true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('Clé de chiffrement invalide (storage/secrets/app.key).');
        }
        return self::$key = $key;
    }

    public static function encrypt(string $plain): string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'o1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): ?string
    {
        [$scheme, $b64] = array_pad(explode(':', $payload, 2), 2, '');
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return null;
        }
        if ($scheme === 's1' && function_exists('sodium_crypto_secretbox_open')) {
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $box = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($box, $nonce, self::key());
            return $plain === false ? null : $plain;
        }
        if ($scheme === 'o1') {
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
            return $plain === false ? null : $plain;
        }
        return null;
    }
}
