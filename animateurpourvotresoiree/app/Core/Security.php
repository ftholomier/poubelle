<?php
declare(strict_types=1);

namespace App\Core;

/** En-têtes de sécurité HTTP et nonce CSP. */
final class Security
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= Crypto::b64u(random_bytes(18));
    }

    public static function setNonce(string $nonce): void
    {
        self::$nonce = $nonce;
    }

    /** Ajoute le nonce aux balises <script> d'un extrait HTML saisi dans le back-office (codes de suivi). */
    public static function withNonce(string $html): string
    {
        if ($html === '') {
            return '';
        }
        return (string) preg_replace('/<script\b(?![^>]*\bnonce=)/i', '<script nonce="' . self::nonce() . '"', $html);
    }

    public static function attr(): string
    {
        return ' nonce="' . self::nonce() . '"';
    }

    /** CSP stricte basée sur un nonce ('strict-dynamic' permet aux scripts AdSense de charger leurs dépendances). */
    public static function csp(bool $admin = false): string
    {
        $n = self::nonce();
        $frames = "https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com https://challenges.cloudflare.com https://*.googlesyndication.com https://*.doubleclick.net https://*.google.com https://fundingchoicesmessages.google.com";
        $directives = [
            "default-src 'self'",
            "script-src 'nonce-$n' 'strict-dynamic' https: 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' https:",
            "media-src 'self' https:",
            "frame-src 'self' $frames",
            "worker-src 'self'",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'" . ($admin ? '' : ' https://challenges.cloudflare.com'),
            "frame-ancestors 'self'",
        ];
        if (Request::isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }
        return implode('; ', $directives);
    }

    public static function headers(Response $res, bool $admin = false): void
    {
        $res->headers += [
            'Content-Security-Policy' => self::csp($admin),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), payment=(), usb=(), geolocation=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin-allow-popups',
        ];
        if (Request::isSecure()) {
            $res->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        if ($admin) {
            $res->headers['X-Robots-Tag'] = 'noindex, nofollow';
            $res->headers['Cache-Control'] = 'no-store, private';
        }
    }
}
