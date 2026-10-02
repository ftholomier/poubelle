<?php
declare(strict_types=1);

namespace App\Core;

/** Double authentification TOTP (RFC 6238), compatible Google Authenticator, Authy, etc. */
final class Totp
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private static int $lastStep = 0;

    public static function secret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function code(string $secret, ?int $step = null): string
    {
        $step ??= intdiv(time(), 30);
        $key = self::base32Decode($secret);
        $bin = pack('N*', 0) . pack('N*', $step);
        $h = hash_hmac('sha1', $bin, $key, true);
        $o = ord($h[19]) & 0x0F;
        $n = ((ord($h[$o]) & 0x7F) << 24) | ((ord($h[$o + 1]) & 0xFF) << 16) | ((ord($h[$o + 2]) & 0xFF) << 8) | (ord($h[$o + 3]) & 0xFF);
        return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Vérifie un code (fenêtre ±1 pas) en refusant la réutilisation d'un pas déjà consommé. */
    public static function verify(string $secret, string $code, int $lastUsedStep = 0): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }
        $now = intdiv(time(), 30);
        for ($i = -1; $i <= 1; $i++) {
            $step = $now + $i;
            if ($step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $step), $code)) {
                self::$lastStep = $step;
                return true;
            }
        }
        return false;
    }

    public static function lastStep(): int
    {
        return self::$lastStep;
    }

    /** @return array{codes:string[], hashes:string[]} */
    public static function recoveryCodes(int $n = 8): array
    {
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < $n; $i++) {
            $c = strtoupper(Str::random(5) . '-' . Str::random(5));
            $codes[] = $c;
            $hashes[] = password_hash(str_replace('-', '', $c), PASSWORD_DEFAULT);
        }
        // la vérification retire les espaces et met en majuscules : on stocke sans tiret
        return ['codes' => $codes, 'hashes' => $hashes];
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
        $bits = '';
        foreach (str_split($s) as $c) {
            $p = strpos(self::B32, $c);
            if ($p === false) {
                continue;
            }
            $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
