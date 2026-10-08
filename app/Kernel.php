<?php
declare(strict_types=1);

namespace App;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Settings;
use App\Core\View;
use App\Core\Auth;
use App\Data\Redirects;
use App\Services\I18n;
use App\Services\Images;

/**
 * Noyau HTTP : langue, page d'attente, routes, redirections 301, 404.
 */
final class Kernel
{
    public static function handle(Request $req): Response
    {
        // La version de PHP n'a pas à être annoncée.
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }
        // Mise à jour du code en cours (Système › Mises à jour) : quelques secondes de pause.
        $flag = STORAGE_PATH . '/update/maintenance';
        if (is_file($flag) && (int) @filemtime($flag) > time() - 600) {
            return new Response('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="robots" content="noindex"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Mise à jour en cours</title></head>'
                . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#F3EDDF;color:#0E1F4D;font-family:Georgia,serif;text-align:center;padding:24px">'
                . '<div><h1 style="font-family:Arial,sans-serif;text-transform:uppercase">Mise à jour en cours</h1><p>Le musée revient dans quelques secondes.</p></div></body></html>',
                503, ['Content-Type' => 'text/html; charset=utf-8', 'Retry-After' => '30', 'Cache-Control' => 'no-store']);
        }
        try {
            $res = self::dispatch($req);
            self::unindexed($req, $res);
        } catch (\Throwable $e) {
            error_log((string) $e);
            if (Settings::get('general.debug', false)) {
                $res = Response::html('<pre>' . htmlspecialchars((string) $e) . '</pre>', 500);
            } elseif ($req->wantsJson()) {
                $res = Response::json(['error' => 'Erreur interne'], 500);
            } else {
                $res = Response::html(View::render('errors/500', ['admin' => self::errorForAdmin($e)]), 500);
            }
        }
        // Politique de sécurité du contenu sur toutes les pages HTML (site et back-office).
        if ($res->file === null && str_starts_with((string) ($res->headers['Content-Type'] ?? ''), 'text/html')) {
            $res->headers['Content-Security-Policy'] ??= self::csp($req);
            // PageSpeed (module d'o2switch) : pas de réécriture des pages. Il fusionne les styles sous
            // des adresses à lui et ajoute des scripts en ligne que la politique ci-dessus bloque ;
            // le site versionne déjà ses fichiers et o2switch les compresse.
            $res->headers['PageSpeed'] = 'off';
        }
        return $res;
    }

    /**
     * Pour un administrateur connecté (jamais pour le public) : l'erreur et les réglages du serveur
     * à revoir, sur la page « Arrêt de jeu », pour corriger sans chercher dans le journal.
     * @return array{error:string,checks:list<string>}|null
     */
    private static function errorForAdmin(\Throwable $e): ?array
    {
        try {
            if (!Auth::isAdmin()) {
                return null;
            }
            return [
                'error' => get_class($e) . ' : ' . $e->getMessage() . ' (' . str_replace(APP_ROOT . '/', '', $e->getFile()) . ', ligne ' . $e->getLine() . ')',
                'checks' => Services\ServerCheck::problems(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Site fermé au public (page d'attente, mot de passe d'accès) ou masqué aux moteurs : rien
     * n'est indexé, pas même ce que voit l'équipe connectée, qui n'est gardé dans aucun cache.
     */
    private static function unindexed(Request $req, Response $res): void
    {
        if ($req->path === '/admin' || str_starts_with($req->path, '/admin/') || !Front\Seo::hidden()) {
            return;
        }
        // Site de l'association : ses propres règles (Vitrine\Kernel).
        if (Vitrine\Host::matches($req) || Vitrine\Host::isPreview($req->path)) {
            return;
        }
        $res->headers['X-Robots-Tag'] ??= 'noindex, nofollow';
        if (Front\Seo::closed() && Auth::user()) {
            $res->headers['Cache-Control'] = 'private, no-store';
        }
    }

    /**
     * Scripts : uniquement ceux du site (plus le court script d'en-tête, signé par un jeton).
     * Cadres : lecteurs vidéo reconnus. Envois de formulaire : le site et les pages de paiement.
     */
    private static function csp(Request $req): string
    {
        $frames = 'https://www.youtube-nocookie.com https://www.youtube.com https://geo.dailymotion.com https://www.dailymotion.com https://player.vimeo.com https://rutube.ru';
        $pay = 'https://checkout.stripe.com https://www.paypal.com https://www.sandbox.paypal.com';
        $rules = [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . csp_nonce() . "'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https://tile.openstreetmap.org",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self'",
            "frame-src 'self' $frames",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' $pay",
            "frame-ancestors 'self'",
        ];
        if (($req->server['HTTPS'] ?? '') === 'on' || ($req->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            $rules[] = 'upgrade-insecure-requests';
        }
        return implode('; ', $rules);
    }

    private static function dispatch(Request $req): Response
    {
        $path = $req->path;

        // Anti-aspiration : robots d'IA, aspirateurs, faux moteurs, visiteurs trop rapides
        if ($deny = Core\Shield::check($req)) {
            return $deny;
        }

        // Images à la volée
        if (preg_match('#^/media/(\d+|full)/(.+)$#', $path, $m)) {
            return Images::serve($m[1], $m[2]);
        }
        // Anciennes images WordPress (liens externes, moteurs) → médiathèque
        if (preg_match('#^/wp-content/uploads/(.+?)(-\d+x\d+)?(\.\w+)$#', $path, $m)) {
            return Response::redirect(img($m[1] . $m[3], 1200), 301);
        }

        // Site de l'association (www) : même application, autre adresse. Aperçu depuis le
        // back-office du musée (administrateurs) : /apercu-association/…
        if (Vitrine\Host::matches($req)) {
            return Vitrine\Kernel::handle($req);
        }
        if (Vitrine\Host::isPreview($path)) {
            return Vitrine\Kernel::preview($req);
        }

        // Espace de l'imprimeur de la boutique (séparé du back-office)
        if ($path === '/imprimeur' || str_starts_with($path, '/imprimeur/')) {
            return Shop\PrinterSpace::handle($req);
        }

        // Back-office
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return Admin\Router::handle($req);
        }

        // Langue : préfixe /en
        if (preg_match('#^/en(/.*)?$#', $path, $m)) {
            I18n::set('en');
            $path = $m[1] ?? '/';
            if ($path === '') {
                $path = '/';
            }
            $req = new Request($req->method, $path, $req->query, $req->post, $req->files, $req->server, $req->body);
        }

        // Webhooks des paiements (Stripe, PayPal), consentement aux cookies et notifications de
        // l'appli (« Prévenez-moi de l'ouverture » de la page d'attente) : jamais bloqués par la
        // page d'attente ni par le mot de passe d'accès.
        $api = str_starts_with($path, '/api/');
        if ($api && (preg_match('#^/api/dons/(stripe|paypal)/webhook$#', $path) || $path === '/api/consentement' || str_starts_with($path, '/api/push/'))) {
            return Front\Api::handle($req);
        }

        // Teaser vidéo de la page d'attente : servi seulement s'il est activé, ou à l'équipe connectée
        // (avant les verrous du site fermé, qui renverraient la page d'attente à sa place) ; sinon
        // l'adresse suit le chemin ordinaire (page d'attente, ou page introuvable).
        if (($path === '/video/teaser.mp4' || $path === '/video/teaser.jpg') && ($res = Front\Pages::teaser($req, $path))) {
            return $res;
        }
        // Aperçu de la page d'attente depuis le back-office (« &teaser=1 » : avec le teaser)
        if ($path === '/' && isset($req->query['apercu-attente']) && Auth::user()) {
            return Front\Pages::waiting(isset($req->query['teaser']));
        }
        // Page d'attente (les membres connectés du back-office voient le site)
        if (Settings::get('waiting.enabled', false) && !Auth::user()) {
            if ($api) {
                return Response::json(['error' => t('Le site ouvrira bientôt.')], 503);
            }
            // Le suivi d'une commande de la boutique reste accessible (lien envoyé au client).
            if (!in_array($path, ['/robots.txt', '/mentions-legales/', '/confidentialite/', '/cookies/'], true) && !str_starts_with($path, '/boutique/commande/')) {
                return Front\Pages::waiting();
            }
        }
        // Accès restreint par mot de passe (pré-lancement)
        if (($pwd = Settings::get('general.front_password', '')) && !Auth::user()) {
            if ($api && !Front\Pages::gateOpen((string) $pwd)) {
                return Response::json(['error' => t('Accès réservé.')], 401);
            }
            $gate = $api ? null : Front\Pages::gate($req, (string) $pwd);
            if ($gate) {
                return $gate;
            }
        }
        if ($api) {
            return Front\Api::handle($req);
        }

        // Adresses sans barre finale → avec (une seule adresse par page)
        if ($req->method === 'GET' && $path !== '/' && !str_ends_with($path, '/') && !preg_match('#\.\w{2,5}$#', $path)) {
            $qs = $req->query ? '?' . http_build_query($req->query) : '';
            return Response::redirect(url($path . '/') . $qs, 301);
        }

        // Anciens liens WordPress /?p=123 et /?s=… : 301 plutôt que la page d'accueil
        if ($path === '/' && $req->method === 'GET' && $req->query && ($to = Redirects::legacyQuery($req->query))) {
            return Response::redirect(url($to), 301);
        }

        $r = self::routes();
        $res = $r->dispatch($req);
        if ($res instanceof Response) {
            return $res;
        }

        // Fiche ou rubrique à cette adresse ?
        $res = Front\Pages::byPath($req);
        if ($res) {
            return $res;
        }

        // Page publique d'un carnet du supporter : /pseudo/ (jamais à la place d'une page du musée).
        if ($req->method === 'GET' && preg_match('#^/([a-z0-9][a-z0-9-]{1,29})/$#', $req->path, $m) && Services\Carnet::bySlug($m[1])) {
            return Front\CarnetPages::publicPage($req, $m[1]);
        }

        // Ancienne adresse → 301
        if ($to = Redirects::find($req->path, $req->query)) {
            return Response::redirect(url($to), 301);
        }
        if ($req->method === 'GET') {
            Redirects::log404($req->path, (string) ($req->server['HTTP_REFERER'] ?? ''));
        }
        return Front\Pages::notFound();
    }

    /**
     * Ce que donnerait la visite d'une adresse du site (GET), sans exécuter la page :
     * [200, null, …] page affichée, [301|302, cible, raison] redirection, [404, null, …] page
     * introuvable ou image absente. Raisons : fichier, page, barre (« / » final ajouté), ancien
     * lien (?p=), page (adresse rectifiée par la page elle-même), redirection (data/redirects.json).
     * Même ordre que dispatch() ; une page calculée à paramètre (saison, face-à-face, bilan,
     * Rétro-Direct, Fil jaune, réserves) fait la même vérification que la page elle-même.
     * Sert aux vérifications des redirections dans l'écran Qualité.
     * @return array{0:int,1:?string,2:string}
     */
    public static function probe(string $path, array $query = []): array
    {
        static $routes = null;
        $routes ??= self::routes();
        // Fichier présent dans public/ : servi directement par le serveur web (jamais hors du dossier).
        if (preg_match('#\.\w{2,5}$#', $path) && !str_contains($path, "\0")) {
            $public = realpath(PUBLIC_PATH);
            $real = $public ? realpath(PUBLIC_PATH . $path) : false;
            if ($real && str_starts_with($real, $public . DIRECTORY_SEPARATOR) && is_file($real)) {
                return [200, null, 'fichier'];
            }
        }
        if (preg_match('#^/media/(\d+|full)/(.+)$#', $path, $m)) {
            $rel = Data\Media::safeRel($m[2]);
            $src = str_ends_with($rel, '.webp') && !Data\Media::file($rel) ? substr($rel, 0, -5) : $rel;
            $width = $m[1] === 'full' || in_array((int) $m[1], Images::WIDTHS, true);
            return [$width && $src !== '' && Data\Media::file($src) ? 200 : 404, null, 'fichier'];
        }
        if (preg_match('#^/wp-content/uploads/(.+?)(-\d+x\d+)?(\.\w+)$#', $path, $m)) {
            return [301, (string) preg_replace('/\?.*$/', '', img($m[1] . $m[3], 1200)), 'fichier'];
        }
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return [200, null, 'page'];
        }
        $pre = '';
        if (preg_match('#^/en(/.*)?$#', $path, $m)) {
            $pre = '/en';
            $path = ($m[1] ?? '') ?: '/';
        }
        if (str_starts_with($path, '/api/')) {
            return [200, null, 'page'];
        }
        if ($path !== '/' && !str_ends_with($path, '/') && !preg_match('#\.\w{2,5}$#', $path)) {
            return [301, $pre . $path . '/' . ($query ? '?' . http_build_query($query) : ''), 'barre'];
        }
        if ($path === '/' && $query && ($to = Redirects::legacyQuery($query))) {
            return [301, $pre . $to, 'ancien lien'];
        }
        if ($route = $routes->route($path)) {
            $r = self::probeRoute($route[0], $route[1], $pre);
            if ($r !== null) {
                return $r;
            }
        }
        // Fiche ou rubrique (Pages::byPath) : une fiche non publiée laisse passer aux redirections.
        if ($s = Data\Index::byPath($path)) {
            if (Data\Index::visible($s)) {
                return [200, null, 'page'];
            }
        } elseif (Data\Categories::byPath($path) || in_array($path, ['/nos-lions/', '/matchs/'], true)) {
            return [200, null, 'page'];
        }
        if (preg_match('#^/([a-z0-9][a-z0-9-]{1,29})/$#', $path, $m) && Services\Carnet::bySlug($m[1])) {
            return [200, null, 'carnet'];
        }
        if ($to = Redirects::find($path, $query)) {
            return [301, preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $to) ? $to : $pre . $to, 'redirection'];
        }
        return [404, null, 'page'];
    }

    /** Page calculée à paramètre : même vérification que la page ; null = elle passe la main (404 de la route). */
    private static function probeRoute(string $pattern, array $p, string $pre): ?array
    {
        switch ($pattern) {
            case '/matchs/{season}/':
                $season = (string) $p['season'];
                if (!preg_match('/^(\d{4})-(\d{4})$/', $season, $m) || (int) $m[2] !== (int) $m[1] + 1) {
                    return null;
                }
                foreach (Data\Categories::all() as $c) {
                    if (($c['season'] ?? null) === $season) {
                        return [200, null, 'page'];
                    }
                }
                return isset(Data\Derived::part('seasons')[$season]) ? [200, null, 'page'] : null;
            case '/face-a-face/{club}/':
                $club = (string) $p['club'];
                if (isset(Data\Derived::part('clubs')[$club])) {
                    return [200, null, 'page'];
                }
                $key = Data\Names::clubKey(str_replace('-', ' ', $club));
                return $key !== $club && isset(Data\Derived::part('clubs')[$key]) ? [301, $pre . '/face-a-face/' . $key . '/', 'page'] : null;
            case '/bilans/{key}/':
                $key = (string) $p['key'];
                if ($key === 'auguste-bonal' || $key === 'bonal') {
                    return [301, $pre . '/bilans/stade-auguste-bonal/', 'page'];
                }
                if (isset(Front\Mosaic::COMPS[$key])) {
                    $family = Front\Mosaic::COMPS[$key][2];
                    foreach (Data\Derived::part('matches') as $x) {
                        if ($x['v'] && $x['comp'] === $family) {
                            return [200, null, 'page'];
                        }
                    }
                    return null;
                }
                return str_starts_with($key, 'stade-') && isset(Data\Derived::part('stades')[substr($key, 6)]) ? [200, null, 'page'] : null;
            case '/interactif/retro-direct/{slug}/':
                $s = Services\RetroDirect::bySlug((string) $p['slug']);
                $doc = $s ? Data\Fiches::get((int) $s['id']) : null;
                if (!$s || !$doc) {
                    return null;
                }
                return Services\RetroDirect::playable($doc) ? [200, null, 'page'] : [302, $pre . $s['path'], 'page'];
            case '/interactif/fil-jaune/{a}/':
                $a = Services\FilJaune::find((string) $p['a']);
                return $a === null ? null : ((string) $p['a'] === Services\FilJaune::slug($a) ? [200, null, 'page'] : [301, $pre . '/interactif/fil-jaune/' . Services\FilJaune::slug($a) . '/', 'page']);
            case '/interactif/fil-jaune/{a}/{b}/':
                $a = Services\FilJaune::find((string) $p['a']);
                $b = Services\FilJaune::find((string) $p['b']);
                if ($a === null || $b === null) {
                    return null;
                }
                $ok = (string) $p['a'] === Services\FilJaune::slug($a) && (string) $p['b'] === Services\FilJaune::slug($b);
                return $ok ? [200, null, 'page'] : [301, $pre . '/interactif/fil-jaune/' . Services\FilJaune::slug($a) . '/' . Services\FilJaune::slug($b) . '/', 'page'];
            case '/reserves/{collection}/':
                foreach (Front\Pages::reserves() as $c) {
                    if ($c['slug'] === (string) $p['collection']) {
                        return [200, null, 'page'];
                    }
                }
                return null;
            default:
                return [200, null, 'page']; // page fixe (ou formulaire, PDF, image de partage)
        }
    }

    private static function routes(): Router
    {
        $r = new Router();
        $r->get('/', fn ($q) => Front\Pages::home($q));
        $r->get('/robots.txt', fn () => Front\Seo::robots());
        $r->get('/sitemap.xml', fn () => Front\Seo::sitemap());
        $r->get('/recherche/', fn ($q) => Front\Pages::search($q));
        $r->get('/partage/{id}.png', fn ($q, $id) => ctype_digit($id) ? Front\Share::image((int) $id) : null);
        // Export PDF (vrai document mis en page, pas une impression)
        $r->get('/pdf/fiche/{id}.pdf', fn ($q, $id) => ctype_digit($id) ? Front\PdfExport::fiche($q, (int) $id) : null);
        $r->get('/pdf/saison/{season}.pdf', fn ($q, $season) => Front\PdfExport::season($q, $season));
        $r->get('/pdf/face-a-face/{club}.pdf', fn ($q, $club) => Front\PdfExport::opponent($q, $club));
        $r->get('/pdf/bilan/{key}.pdf', fn ($q, $key) => Front\PdfExport::bilan($q, $key));
        $r->get('/pdf/records.pdf', fn ($q) => Front\PdfExport::records($q));
        $r->get('/partage/face-a-face/{club}.png', fn ($q, $club) => Front\Share::faceToFace($club));

        // Explorer l'histoire (calculé)
        $r->get('/matchs/{season}/', fn ($q, $season) => preg_match('/^\d{4}-\d{4}$/', $season) ? Front\Explore::season($q, $season) : null);
        $r->get('/saisons/', fn ($q) => Front\Explore::seasons($q));
        $r->get('/face-a-face/', fn ($q) => Front\Explore::opponents($q));
        $r->get('/face-a-face/{club}/', fn ($q, $club) => Front\Explore::opponent($q, $club));
        $r->get('/bilans/{key}/', fn ($q, $key) => Front\Explore::bilan($q, $key));
        $r->get('/records/', fn ($q) => Front\Explore::records($q));
        $r->get('/palmares/', fn ($q) => Front\Palmares::page($q));
        $r->get('/chiffres/', fn ($q) => Front\Explore::chiffres($q));

        // Interactif
        $r->get('/interactif/', fn ($q) => Front\Interactive::landing($q));
        $r->get('/interactif/quiz/', fn ($q) => Front\Interactive::quiz($q));
        $r->get('/interactif/quiz-live/', fn ($q) => Front\QuizLivePages::join($q));
        $r->get('/interactif/defi/', fn ($q) => Front\DailyQuizPages::page($q));
        $r->get('/interactif/quiz-live/championnat/', fn ($q) => Front\QuizLivePages::championship($q));
        $r->get('/interactif/quiz-live/ecran/{code}/{key}/', fn ($q, $code, $key) => Front\QuizLivePages::screen($q, $code, $key));
        $r->get('/interactif/album/', fn ($q) => Front\Interactive::album($q));
        $r->get('/interactif/maillots/', fn ($q) => Front\Interactive::jerseys($q));
        $r->get('/interactif/frise/', fn ($q) => Front\Interactive::timeline($q));
        $r->get('/interactif/carto/', fn ($q) => Front\Interactive::carto($q));
        $r->get('/interactif/retro-direct/', fn ($q) => Front\Retro::landing($q));
        $r->get('/interactif/retro-direct/agenda.ics', fn ($q) => Front\Retro::ics($q));
        $r->get('/interactif/retro-direct/{slug}/', fn ($q, $slug) => Front\Retro::show($q, $slug));
        $r->get('/interactif/fil-jaune/', fn ($q) => Front\Fil::landing($q));
        $r->get('/interactif/fil-jaune/{a}/', fn ($q, $a) => Front\Fil::star($q, $a));
        $r->get('/interactif/fil-jaune/{a}/{b}/', fn ($q, $a, $b) => Front\Fil::chain($q, $a, $b));
        $r->get('/interactif/planche-contact/', fn ($q) => Front\Walls::contactSheet($q));
        $r->get('/interactif/le-lion-illustre/', fn ($q) => Front\Walls::newspaper($q));
        $r->get('/interactif/mur-du-vestiaire/', fn ($q) => Front\Walls::lockerRoom($q));
        $r->get('/interactif/mosaique/', fn ($q) => Front\Walls::mosaic($q));
        // Export PDF du tirage affiché (formulaire du bouton « Télécharger en PDF » ; en GET : retour au mur)
        foreach (Front\Walls::PDF as $kind => $pdfPath) {
            $r->any($pdfPath, fn ($q) => Front\WallPdf::handle($q, $kind));
        }
        $r->get('/interactif/souvenirs/', fn ($q) => Front\Kit::landing($q));
        $r->get('/carnet/', fn ($q) => Front\CarnetPages::home($q));
        $r->get('/carnet/saisons/', fn ($q) => Front\CarnetPages::seasons($q));
        $r->get('/carnet/carte.png', fn ($q) => Front\CarnetPages::card($q));
        $r->get('/carnet/carte-{style}.pdf', fn ($q, $style) => Front\CarnetPages::cardFile($q, $style, 'pdf'));
        $r->get('/carnet/carte-{style}.svg', fn ($q, $style) => Front\CarnetPages::cardFile($q, $style, 'svg'));
        $r->get('/carnet/acces/{cred}/', fn ($q, $cred) => Front\CarnetPages::access($q, $cred));
        $r->get('/carnet/rappels/arret/{id}/{sig}/', fn ($q, $id, $sig) => Front\CarnetPages::stop($q, $id, $sig));
        $r->get('/carnet/p/{slug}/', fn ($q, $slug) => Front\CarnetPages::publicPage($q, $slug, true));
        $r->get('/carnet/p/{slug}/carte.png', fn ($q, $slug) => Front\CarnetPages::card($q, $slug));
        $r->get('/interactif/souvenirs/{ym}.pdf', fn ($q, $ym) => Front\Kit::pdf($q, $ym));
        $r->get('/souvenir/', fn ($q) => Front\Kit::souvenir($q));
        $r->get('/souvenir/{id}/', fn ($q, $id) => Front\Kit::souvenir($q, $id));
        $r->get('/centenaire/', fn ($q) => Front\Interactive::centenary($q));
        $r->get('/centenaire/100-moments/', fn ($q) => Front\Interactive::moments($q));
        $r->get('/reserves/', fn ($q) => Front\Interactive::reserves($q));
        $r->get('/reserves/{collection}/', fn ($q, $collection) => Front\Interactive::reserves($q, $collection));

        // Communauté
        $r->get('/contact/', fn ($q) => Front\Community::contact($q));
        $r->post('/contact/', fn ($q) => Front\Community::contactSend($q));
        $r->get('/contribuer/', fn ($q) => Front\Community::contribute($q));
        $r->post('/contribuer/', fn ($q) => Front\Community::contributeSend($q));
        $r->get('/mentions-legales/', fn ($q) => Front\Legal::page($q, 'mentions'));
        $r->get('/confidentialite/', fn ($q) => Front\Legal::page($q, 'confidentialite'));
        $r->get('/cookies/', fn ($q) => Front\Legal::page($q, 'cookies'));
        $r->get('/boutique/', fn ($q) => Shop\ShopPages::index($q));
        $r->get('/boutique/panier/', fn ($q) => Shop\ShopPages::cartPage($q));
        $r->post('/boutique/panier/', fn ($q) => Shop\ShopPages::cartAction($q));
        $r->post('/boutique/commander/', fn ($q) => Shop\ShopPages::checkout($q));
        $r->post('/boutique/apercu/', fn ($q) => Shop\ShopPages::livePreview($q));
        $r->post('/boutique/anecdote/', fn ($q) => Shop\ShopPages::anecdote($q));
        $r->get('/boutique/anecdote/sujets/', fn ($q) => Shop\ShopPages::anecdoteTopics($q));
        $r->get('/boutique/poster/matchs/', fn ($q) => Shop\ShopPages::posterMatches($q));
        $r->get('/boutique/poster/joueurs/', fn ($q) => Shop\ShopPages::posterPlayers($q));
        $r->post('/boutique/poster/preparer/', fn ($q) => Shop\ShopPages::posterPrepare($q));
        $r->get('/boutique/commande/{token}/', fn ($q, $token) => Shop\ShopPages::track($q, $token));
        $r->get('/boutique/commande/{token}/livre/{n}/', fn ($q, $token, $n) => Shop\ShopPages::bookDownload($q, $token, $n));
        $r->get('/boutique/livre/', fn ($q) => Shop\ShopPages::book($q));
        $r->post('/boutique/livre/fichier/', fn ($q) => Shop\ShopPages::bookUpload($q));
        $r->post('/boutique/livre/extrait/', fn ($q) => Shop\ShopPages::bookExcerpt($q));
        $r->get('/boutique/livre/couvertures/', fn ($q) => Shop\ShopPages::bookCovers($q));
        $r->get('/boutique/livre/joueurs/', fn ($q) => Shop\ShopPages::bookPlayers($q));
        $r->post('/boutique/commande/{token}/', fn ($q, $token) => Shop\ShopPages::trackMessage($q, $token));
        $r->get('/boutique/{id}/', fn ($q, $id) => Shop\ShopPages::product($q, $id));
        $r->get('/faire-un-don/', fn ($q) => Front\Donations::page($q));
        $r->post('/faire-un-don/', fn ($q) => Front\Donations::start($q));
        $r->get('/faire-un-don/merci/', fn ($q) => Front\Donations::thanks($q));
        $r->any('/faire-un-don/gerer/{token}/', fn ($q, $token) => Front\Donations::manage($q, $token));
        $r->get('/faire-un-don/gerer/{token}/recu/{num}/', fn ($q, $token, $num) => Front\Donations::receiptDownload($q, $token, $num));
        $r->get('/appli/', fn ($q) => Front\Appli::page($q));
        $r->get('/newsletter/', fn ($q) => Front\Community::newsletter($q));
        $r->get('/newsletter/confirmer/{token}/', fn ($q, $token) => Front\Community::newsletterConfirm($q, $token));
        $r->post('/newsletter/confirmer/{token}/', fn ($q, $token) => Front\Community::newsletterConfirm($q, $token));
        $r->get('/newsletter/desinscription/{token}/', fn ($q, $token) => Front\Community::newsletterUnsubscribe($q, $token));
        $r->post('/newsletter/desinscription/{token}/', fn ($q, $token) => Front\Community::newsletterUnsubscribe($q, $token));
        $r->get('/partage-et-newsletter/', fn ($q) => Front\Community::sharePage($q));
        return $r;
    }
}
