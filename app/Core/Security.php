<?php
declare(strict_types=1);

namespace App\Core;

/** En-têtes de sécurité et redirection HTTPS. */
final class Security
{
    /** Nonce CSP régénéré à chaque requête, posé sur les <script> internes. */
    private static string $nonce = '';

    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * @param bool|null $allowAds null = déduit du consentement du visiteur.
     */
    public static function sendHeaders(?bool $allowAds = null): void
    {
        if (headers_sent()) {
            return;
        }
        $allowAds ??= self::adsConsented();

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');

        if (Config::get('security.hsts') && Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        $nonce = self::nonce();

        // Les domaines publicitaires ne sont autorisés qu'une fois le consentement donné.
        $adHosts = $allowAds
            ? ' https://pagead2.googlesyndication.com https://googleads.g.doubleclick.net https://tpc.googlesyndication.com https://www.google.com'
            : '';

        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'nonce-{$nonce}'" . $adHosts,
            "connect-src 'self'" . $adHosts,
            "frame-src 'self'" . $adHosts,
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    /**
     * Le consentement est mémorisé côté navigateur, mais le CSP se décide côté
     * serveur : le choix du visiteur est donc aussi déposé dans un cookie, seul
     * moyen pour PHP de savoir s'il doit ouvrir les domaines publicitaires.
     * Ce cookie sert uniquement à mémoriser un refus ou un accord : à ce titre
     * il est exempté de consentement.
     */
    private static function adsConsented(): bool
    {
        if (($_COOKIE['imtt_consent'] ?? '') !== 'all') {
            return false;
        }
        return trim((string) Config::get('ads.client', '')) !== '';
    }

    /** Redirection HTTP → HTTPS. Le certificat doit être réparé côté hébergeur. */
    public static function forceHttps(): ?Response
    {
        if (PHP_SAPI === 'cli' || !Config::get('security.force_https') || Session::isHttps()) {
            return null;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '' || str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1')) {
            return null;
        }
        return Response::redirect('https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
    }
}
