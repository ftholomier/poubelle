<?php
declare(strict_types=1);

namespace App\Core;

/** Signatures HMAC, jetons et chiffrement (libsodium) à partir de APP_KEY. */
final class Crypto
{
    private static ?string $key = null;

    public static function key(): string
    {
        if (self::$key === null) {
            $raw = (string) Env::get('APP_KEY', '');
            if (str_starts_with($raw, 'base64:')) {
                $raw = (string) base64_decode(substr($raw, 7), true);
            }
            if (strlen($raw) < 32) {
                $raw = hash('sha256', $raw . __FILE__, true);
            }
            self::$key = substr($raw, 0, 32);
        }
        return self::$key;
    }

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string
    {
        $r = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        return $r === false ? '' : $r;
    }

    public static function token(int $bytes = 32): string
    {
        return self::b64u(random_bytes($bytes));
    }

    public static function hmac(string $data, string $purpose = ''): string
    {
        return hash_hmac('sha256', $purpose . '|' . $data, self::key());
    }

    /** Signe une charge utile (tableau) : jeton compact base64url.payload.signature */
    public static function sign(array $payload, string $purpose, ?int $ttl = null): string
    {
        if ($ttl !== null) {
            $payload['exp'] = time() + $ttl;
        }
        $data = self::b64u((string) json_encode($payload, Fs::JSON_FLAGS));
        return $data . '.' . substr(self::b64u(hash_hmac('sha256', $purpose . '|' . $data, self::key(), true)), 0, 32);
    }

    public static function verify(string $token, string $purpose): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$data, $sig] = $parts;
        $expected = substr(self::b64u(hash_hmac('sha256', $purpose . '|' . $data, self::key(), true)), 0, 32);
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $payload = json_decode(self::b64uDecode($data), true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }
        return $payload;
    }

    public static function encrypt(string $plain): string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            $iv = random_bytes(12);
            $tag = '';
            $c = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
            return 'g:' . self::b64u($iv . $tag . $c);
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 's:' . self::b64u($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(string $cipher): ?string
    {
        if (str_starts_with($cipher, 's:') && function_exists('sodium_crypto_secretbox_open')) {
            $raw = self::b64uDecode(substr($cipher, 2));
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $r = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, self::key());
            return $r === false ? null : $r;
        }
        if (str_starts_with($cipher, 'g:')) {
            $raw = self::b64uDecode(substr($cipher, 2));
            $r = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return $r === false ? null : $r;
        }
        return null;
    }

    /** Hachage des mots de passe : Argon2id (paramètres OWASP) ou bcrypt en repli. */
    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1]);
        }
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 11]);
    }
}
