<?php
/**
 * Point d'entrée unique du site (seul dossier exposé : /public).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$request = \App\Core\Request::fromGlobals();
// Pages du musée pour les visiteurs anonymes : resservies depuis le cache quand c'est possible.
$response = \App\Core\PageCache::serve($request) ?? \App\Core\PageCache::store($request, \App\Kernel::handle($request));
$response->send();

// Mesure d'audience anonyme (pages HTML publiques uniquement), après l'envoi de la page ; rien
// tant que le site est fermé au public (seule l'équipe connectée le voit). Le site de
// l'association a sa propre mesure (jamais l'aperçu du back-office).
if ($request->method === 'GET' && in_array($response->status, [200, 304], true) && !str_starts_with($request->path, '/admin') && !str_starts_with($request->path, '/api/')
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

// Juste après une mise à jour : index et données calculées refaits avec le nouveau code, après
// l'envoi de la page (la version précédente sert en attendant : personne n'attend leur calcul).
if (is_file(STORAGE_PATH . '/cache/apres-mise-a-jour')) {
    \App\Services\Updater::refresh();
}
