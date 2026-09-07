<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Config;
use App\Security\Session;

final class Response
{
    public static function securityHeaders(bool $isAdmin = false): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header_remove('X-Powered-By');

        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // CSP : le widget Google Traduction et les avis Google ont besoin
        // de domaines Google ; tout le reste reste verrouillé sur l'origine.
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "img-src 'self' data: https://*.googleusercontent.com https://*.gstatic.com https://*.google.com https://maps.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://translate.googleapis.com https://www.gstatic.com",
            "script-src 'self' 'unsafe-inline' https://translate.google.com https://translate.googleapis.com https://www.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self' https://translate.googleapis.com",
            "frame-src 'self' https://www.google.com https://translate.google.com https://www.google.com/maps/",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));

        if ($isAdmin) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }

    public static function json(mixed $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        // Redirection interne uniquement (anti open-redirect).
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            $url = '/';
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Location: ' . $url);
        }
        exit;
    }

    public static function notFound(): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }
    }

    public static function forceHttps(Request $request): void
    {
        if (!Config::bool('security.force_https', false) || Session::isHttps()) {
            return;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return;
        }
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}
