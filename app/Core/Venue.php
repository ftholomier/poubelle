<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Mode soirée (Réglages › Soirée · wifi partagé) : pendant un lancement, une projection ou un stand,
 * tout le public branché sur le wifi de la salle sort par la même adresse internet. Jusqu'à l'heure
 * de fin, cette adresse n'est pas freinée par le bouclier anti-aspiration (Shield) et ses autres
 * limites par adresse (recherche, quiz, formulaires…) sont multipliées par FACTOR (RateLimiter).
 * Une adresse IPv6 vaut pour tout son bloc /64, comme dans RateLimiter.
 */
final class Venue
{
    public const FACTOR = 20;
    /** Durée maximale du mode soirée, pour qu'un oubli ne laisse pas l'adresse sans protection. */
    public const MAX_SECONDS = 3 * 86400;

    /** @return list<string> adresses de la salle tant que le mode soirée est en cours, sinon [] */
    public static function ips(): array
    {
        if (!Settings::get('soiree.enabled', false) || self::until() <= time()) {
            return [];
        }
        return self::parse((string) Settings::get('soiree.ips', ''))[0];
    }

    /** Heure de fin (timestamp), 0 si elle n'est pas réglée. */
    public static function until(): int
    {
        $v = (string) Settings::get('soiree.until', '');
        return $v !== '' ? (int) strtotime($v) : 0;
    }

    public static function active(): bool
    {
        return self::ips() !== [];
    }

    /** L'adresse fait partie du wifi de la salle et le mode soirée est en cours. */
    public static function covers(string $ip): bool
    {
        $ips = self::ips();
        if (!$ips || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        $me = self::block($ip);
        foreach ($ips as $x) {
            if (self::block($x) === $me) {
                return true;
            }
        }
        return false;
    }

    /**
     * Liste saisie (virgules, points-virgules ou espaces) : [adresses valides sans doublon, refusées].
     * @return array{0: list<string>, 1: list<string>}
     */
    public static function parse(string $raw): array
    {
        $ok = $bad = [];
        foreach (preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $x) {
            if (filter_var($x, FILTER_VALIDATE_IP)) {
                $ok[strtolower($x)] = strtolower($x);
            } else {
                $bad[] = $x;
            }
        }
        return [array_values($ok), $bad];
    }

    private static function block(string $ip): string
    {
        if (str_contains($ip, ':') && ($bin = @inet_pton($ip)) !== false) {
            return (string) inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
        }
        return $ip;
    }
}
