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
// tant que le site est fermé au public (seule l'équipe connectée le voit). Le site de
// l'association a sa propre mesure (jamais l'aperçu du back-office).
if ($request->method === 'GET' && $response->status === 200 && !str_starts_with($request->path, '/admin') && !str_starts_with($request->path, '/api/')
    && str_contains((string) ($response->headers['Content-Type'] ?? 'text/html'), 'text/html') && !\App\Vitrine\Host::isPreview($request->path)) {
    if (\App\Vitrine\Host::matches($request)) {
        if (\App\Vitrine\Site::open()) {
            \App\Core\Response::detach();
            \App\Vitrine\Stats::hit($request->path, (string) ($request->server['HTTP_USER_AGENT'] ?? ''));
        }
    } elseif (!\App\Front\Seo::closed()) {
        \App\Core\Response::detach();
        \App\Services\Stats::hit($request->path, (string) ($request->server['HTTP_USER_AGENT'] ?? ''), \App\Services\I18n::lang());
    }
}
