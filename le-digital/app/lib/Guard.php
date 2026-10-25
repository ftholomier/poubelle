<?php
declare(strict_types=1);

namespace LD;

/**
 * Protection des API publiques sans cookie : jeton signé (HMAC) injecté dans chaque page,
 * vérification d'origine et champ « pot de miel » contre les robots.
 */
final class Guard
{
    private const TTL = 172800; // 48 h

    public static function token(): string
    {
        $ts = (string) time();
        return $ts . '.' . substr(hash_hmac('sha256', 'pub|' . $ts, Settings::appSecret()), 0, 32);
    }

    public static function valid(?string $token): bool
    {
        if (!$token || !str_contains($token, '.')) {
            return false;
        }
        [$ts, $sig] = explode('.', $token, 2);
        if (!ctype_digit($ts) || (time() - (int) $ts) > self::TTL || (int) $ts > time() + 300) {
            return false;
        }
        $expected = substr(hash_hmac('sha256', 'pub|' . $ts, Settings::appSecret()), 0, 32);
        return hash_equals($expected, $sig);
    }

    /** À appeler en tête de chaque endpoint public POST. */
    public static function api(): void
    {
        if (Http::method() !== 'POST') {
            Http::fail('Méthode non autorisée', 405);
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        if ($origin !== '') {
            $host = parse_url($origin, PHP_URL_HOST);
            $self = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
            if ($host && $self && strcasecmp($host, $self) !== 0) {
                Http::fail('Origine refusée', 403);
            }
        }
        if (!self::valid($_SERVER['HTTP_X_LD_TOKEN'] ?? (string) Http::input('_t', ''))) {
            Http::fail('Session expirée : recharge la page et réessaie.', 419);
        }
        if (trim((string) Http::input('website', '')) !== '') {
            // Pot de miel rempli : on fait semblant que tout va bien.
            Http::sendJson(['ok' => true]);
        }
        RateLimit::gc();
    }
}
