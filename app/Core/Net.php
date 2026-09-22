<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Adresse du visiteur et protocole réels, derrière un éventuel proxy.
 *
 * Les en-têtes `X-Forwarded-*` sont envoyés par le client : les croire sans
 * condition laisserait n'importe qui usurper une adresse ou contourner la
 * redirection HTTPS. Ils ne sont donc lus que si la connexion vient d'un proxy
 * déclaré dans `security.trusted_proxies`.
 */
final class Net
{
    /** Adresse du visiteur, résolue une seule fois par requête. */
    public static function clientIp(): string
    {
        static $ip = null;
        if ($ip !== null) {
            return $ip;
        }

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($remote === '') {
            return $ip = '0.0.0.0';
        }
        if (!self::fromTrustedProxy($remote)) {
            return $ip = $remote;
        }

        // Le premier élément de la chaîne est le client d'origine ; les suivants
        // sont les proxys traversés.
        $chain = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        foreach ($chain as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $ip = $candidate;
            }
        }
        return $ip = $remote;
    }

    /** Vrai si la requête est arrivée en HTTPS, proxy de confiance compris. */
    public static function isHttps(): bool
    {
        $https = (string) ($_SERVER['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443) {
            return true;
        }
        if (!self::fromTrustedProxy((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
            return false;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on';
    }

    private static function fromTrustedProxy(string $remote): bool
    {
        if ($remote === '') {
            return false;
        }
        foreach ((array) Config::get('security.trusted_proxies', []) as $range) {
            if (self::inRange($remote, (string) $range)) {
                return true;
            }
        }
        return false;
    }

    /** Comparaison IP / plage CIDR, en IPv4 comme en IPv6. */
    public static function inRange(string $ip, string $range): bool
    {
        $range = trim($range);
        if ($range === '') {
            return false;
        }
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $binIp = @inet_pton($ip);
        $binSubnet = @inet_pton($subnet);
        if ($binIp === false || $binSubnet === false || strlen($binIp) !== strlen($binSubnet)) {
            return false;
        }

        $bits = max(0, min(strlen($binIp) * 8, (int) $bits));
        $whole = intdiv($bits, 8);
        $rest  = $bits % 8;

        if ($whole > 0 && strncmp($binIp, $binSubnet, $whole) !== 0) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = ~((1 << (8 - $rest)) - 1) & 0xFF;
        return (ord($binIp[$whole]) & $mask) === (ord($binSubnet[$whole]) & $mask);
    }
}
