<?php
declare(strict_types=1);

namespace App\Core;

final class Net
{
    /** Vérifie si une IP appartient à une plage (IP exacte ou CIDR, IPv4/IPv6). */
    public static function ipInRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }
        [$subnet, $bits] = explode('/', $range, 2);
        $ipBin = @inet_pton($ip);
        $subBin = @inet_pton($subnet);
        if ($ipBin === false || $subBin === false || strlen($ipBin) !== strlen($subBin)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subBin, 0, $bytes)) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $rem)) & 0xFF);
        return (($ipBin[$bytes] & $mask) === ($subBin[$bytes] & $mask));
    }

    public static function ipInList(string $ip, array|string $list): bool
    {
        $items = is_array($list) ? $list : preg_split('/[\s,;]+/', $list);
        foreach ($items as $r) {
            $r = trim((string) $r);
            if ($r !== '' && self::ipInRange($ip, $r)) {
                return true;
            }
        }
        return false;
    }
}
