<?php
/**
 * Point d'entrée unique du site (seul dossier exposé : /public).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

// Ancienne adresse d'un musée (profil « redirect_from ») : redirection permanente vers son domaine principal.
$host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
if ($host !== '' && in_array($host, (array) \App\Core\Club::get('redirect_from', []), true) && ($to = \App\Core\Club::s('domain')) !== '' && $to !== $host) {
    header('Location: https://' . $to . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

// Médias d'un club autre que le club historique : /m/<club>/… est traité comme /media/….
if (MEDIA_URL !== '/media' && str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), MEDIA_URL . '/')) {
    $_SERVER['REQUEST_URI'] = '/media' . substr((string) $_SERVER['REQUEST_URI'], strlen(MEDIA_URL));
}

$request = \App\Core\Request::fromGlobals();
// Pages du musée pour les visiteurs anonymes : resservies depuis le cache quand c'est possible.
$response = \App\Core\PageCache::serve($request) ?? \App\Core\PageCache::store($request, \App\Kernel::handle($request));
// LSCache (LiteSpeed, o2switch) : mêmes règles, piloté par ces en-têtes.
$response = \App\Core\PageCache::lite($request, $response);
// Membre de l'équipe connecté : ses visites sont comptées à part (jamais dans l'audience du public).
$teamUser = isset($_COOKIE['sr_session']) ? \App\Core\Auth::user() : null;
$team = $teamUser !== null;
$response->send();

// Mesure d'audience anonyme (pages HTML publiques uniquement), après l'envoi de la page ; rien
// tant que le site est fermé au public (seule l'équipe connectée le voit). Le site de
// l'association a sa propre mesure (jamais l'aperçu du back-office).
if ($team && $request->method === 'GET' && $response->status === 200 && str_starts_with($request->path, '/admin')
    && str_contains((string) ($response->headers['Content-Type'] ?? 'text/html'), 'text/html')) {
    // Back-office : pages vues par chaque historien (mesure de l'équipe, à son nom).
    \App\Core\Response::detach();
    \App\Services\Stats::scope('equipe');
    \App\Services\Stats::hit($request->path, (string) ($request->server['HTTP_USER_AGENT'] ?? ''), 'fr', (string) ($teamUser['id'] ?? ''));
} elseif ($request->method === 'GET' && in_array($response->status, [200, 304], true) && !str_starts_with($request->path, '/admin') && !str_starts_with($request->path, '/api/') && !str_starts_with($request->path, '/w/') && !str_starts_with($request->path, '/ecran/')
    && str_contains((string) ($response->headers['Content-Type'] ?? 'text/html'), 'text/html') && !\App\Vitrine\Host::isPreview($request->path)) {
    if (\App\Vitrine\Host::matches($request)) {
        if (\App\Vitrine\Site::open()) {
            \App\Core\Response::detach();
            \App\Vitrine\Stats::hit($request->path, (string) ($request->server['HTTP_USER_AGENT'] ?? ''));
        }
    } elseif (\App\Core\PageCache::$beacon) {
        // Comptée par le navigateur (/api/vu), aussi quand LSCache la sert sans passer par PHP.
    } elseif ($team || !\App\Front\Seo::closed()) {
        \App\Core\Response::detach();
        if ($team) {
            \App\Services\Stats::scope('equipe');
        }
        \App\Services\Stats::hit($request->path, (string) ($request->server['HTTP_USER_AGENT'] ?? ''), \App\Services\I18n::lang(), $team ? (string) ($teamUser['id'] ?? '') : null);
    }
}

// Juste après une mise à jour : index et données calculées refaits avec le nouveau code, après
// l'envoi de la page (la version précédente sert en attendant : personne n'attend leur calcul).
if (is_file(STORAGE_PATH . '/cache/apres-mise-a-jour')) {
    \App\Services\Updater::refresh();
}

// OPcache : anciennes versions des caches réécrits récupérées dès qu'elles pèsent trop (5 min au plus).
\App\Core\OpcacheGuard::tick();
