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
        $adHosts = $allowAds ? ' ' . implode(' ', self::AD_HOSTS) : '';

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
     * Domaines de la régie et de son CMP. fundingchoicesmessages sert la
     * fenêtre de consentement de Google, adtrafficquality sa vérification
     * anti-fraude : sans eux la fenêtre ne s'affiche pas.
     */
    private const AD_HOSTS = [
        'https://pagead2.googlesyndication.com',
        'https://googleads.g.doubleclick.net',
        'https://tpc.googlesyndication.com',
        'https://www.googletagservices.com',
        'https://adservice.google.com',
        'https://fundingchoicesmessages.google.com',
        'https://ep1.adtrafficquality.google',
        'https://ep2.adtrafficquality.google',
        'https://www.google.com',
    ];

    /**
     * Le CSP se décide côté serveur, alors que le consentement se donne côté
     * navigateur. Deux cas, selon qui le recueille.
     *
     * Le CMP de Google est affiché par le script AdSense : les domaines
     * doivent donc être ouverts dès la première page, sans quoi la fenêtre de
     * consentement ne pourrait jamais apparaître. Ouvrir le CSP ne charge
     * rien par soi-même ; c'est le script qui demande, puis respecte la
     * réponse.
     *
     * Avec le bandeau du site, c'est le choix du visiteur qui commande. Il est
     * déposé dans un cookie, seul canal lisible par PHP ; ce cookie ne sert
     * qu'à mémoriser un accord ou un refus, et est à ce titre exempté de
     * consentement.
     */
    private static function adsConsented(): bool
    {
        if (trim((string) Config::get('ads.client', '')) === '') {
            return false;
        }
        if (\App\Services\Ads::consentMode() === 'google') {
            return true;
        }
        return ($_COOKIE['imtt_consent'] ?? '') === 'all';
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
