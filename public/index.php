<?php
/**
 * Point d'entrée unique du site (seul dossier exposé : /public).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$request = \App\Core\Request::fromGlobals();
$response = \App\Kernel::handle($request);
$response->send();

// Mesure d'audience anonyme (pages HTML publiques uniquement), après l'envoi de la page ; rien
// tant que le site est fermé au public (seule l'équipe connectée le voit).
if ($request->method === 'GET' && $response->status === 200 && !str_starts_with($request->path, '/admin') && !str_starts_with($request->path, '/api/')
    && str_contains((string) ($response->headers['Content-Type'] ?? 'text/html'), 'text/html') && !\App\Front\Seo::closed()) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    \App\Services\Stats::hit($request->path, (string) ($request->server['HTTP_USER_AGENT'] ?? ''), \App\Services\I18n::lang());
}
