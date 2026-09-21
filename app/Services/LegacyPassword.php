<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Vérification des empreintes de mot de passe héritées de WordPress.
 *
 * Deux formats coexistent dans la base reprise :
 *   • `$wp$2y$…` — WordPress 6.8 et plus : bcrypt appliqué à un HMAC-SHA384
 *     du mot de passe (778 comptes) ;
 *   • `$P$B…`    — phpass portable, MD5 itéré (216 comptes).
 *
 * Aucune empreinte héritée n'est conservée : dès qu'une connexion réussit,
 * Auth réécrit le mot de passe en Argon2id et efface l'ancienne (voir Auth::login).
 */
final class LegacyPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function verify(string $password, string $hash): bool
    {
        $hash = trim($hash);
        if ($password === '' || $hash === '') {
            return false;
        }

        // WordPress ≥ 6.8 : le mot de passe est « poivré » avant bcrypt.
        if (str_starts_with($hash, '$wp$')) {
            $peppered = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
            return password_verify($peppered, substr($hash, 3));
        }

        // bcrypt nu, posé par certaines extensions.
        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2b$')) {
            return password_verify($password, $hash);
        }

        // phpass portable.
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return hash_equals($hash, self::phpass($password, $hash));
        }

        // Très anciens comptes : MD5 nu.
        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals($hash, md5($password));
        }

        return false;
    }

    /** Implémentation de l'empreinte portable phpass (MD5 itéré, sel de 8 octets). */
    private static function phpass(string $password, string $setting): string
    {
        if (strlen($setting) < 12) {
            return '*';
        }

        $countLog2 = strpos(self::ITOA64, $setting[3]);
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return '*';
        }
        $count = 1 << $countLog2;
        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return '*';
        }

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        return substr($setting, 0, 12) . self::encode64($hash, 16);
    }

    private static function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }

    /** Empreinte phpass, uniquement pour vérifier l'implémentation en test. */
    public static function makePhpass(string $password, string $salt, int $countLog2 = 13): string
    {
        $setting = '$P$' . self::ITOA64[$countLog2] . substr($salt . '00000000', 0, 8);
        return self::phpass($password, $setting);
    }

    /** Vrai si l'empreinte est d'un format hérité (donc à convertir). */
    public static function isLegacy(string $hash): bool
    {
        return $hash !== '' && !str_starts_with($hash, '$argon2');
    }
}
