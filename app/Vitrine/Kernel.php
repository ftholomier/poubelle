<?php
declare(strict_types=1);

namespace App\Vitrine;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Data\Redirects;

/**
 * Site de l'association (www) : distribution des pages.
 *
 * Ordre : domaine redirigé (sans « www ») ; back-office (sur le musée) ; consentement aux
 * cookies ; robots.txt et plan du site ; site fermé (page d'attente) ; pages ; anciennes
 * adresses du site WordPress, qui mènent au musée quand il les connaît ; page introuvable.
 */
final class Kernel
{
    /** Pages servies même quand le site est fermé. */
    private const ALWAYS = ['/mentions-legales/', '/confidentialite/', '/cookies/'];

    /** Requête arrivée sur l'adresse du site de l'association. */
    public static function handle(Request $req): Response
    {
        if (Host::isAlias($req)) {
            return Response::redirect(Host::base() . $req->path . self::qs($req), 301);
        }
        $res = self::dispatch($req);
        if (Site::hidden()) {
            $res->headers['X-Robots-Tag'] ??= 'noindex, nofollow';
        }
        return $res;
    }

    /**
     * Aperçu depuis le back-office du musée (/apercu-association/…) : le site tel qu'il sera,
     * même fermé, contenus « à vérifier » compris. Réservé aux administrateurs.
     */
    public static function preview(Request $req): Response
    {
        Session::start();
        if (!Auth::user()) {
            return Response::redirect('/admin/connexion?r=' . rawurlencode((string) ($req->server['REQUEST_URI'] ?? Host::PREVIEW . '/')));
        }
        if (!Auth::isAdmin()) {
            return \App\Admin\Base::denyUnlessAdmin() ?? Response::redirect('/admin');
        }
        Host::$prefix = Host::PREVIEW;
        Site::$preview = true;
        $path = substr($req->path, strlen(Host::PREVIEW)) ?: '/';
        $res = self::dispatch(new Request($req->method, $path, $req->query, $req->post, $req->files, $req->server, $req->body));
        // Une redirection interne reste dans l'aperçu.
        $to = (string) ($res->headers['Location'] ?? '');
        if (str_starts_with($to, '/') && !str_starts_with($to, '//') && !Host::isPreview($to) && !str_starts_with($to, '/admin')) {
            $res->headers['Location'] = Host::PREVIEW . $to;
        }
        $res->headers['X-Robots-Tag'] = 'noindex, nofollow';
        $res->headers['Cache-Control'] = 'private, no-store';
        return $res;
    }

    private static function dispatch(Request $req): Response
    {
        $path = $req->path;

        // Le back-office est sur le musée.
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return Response::redirect(Host::museum($path), 302);
        }
        // Preuve du consentement aux cookies ; webhooks des paiements (si déclarés à cette adresse).
        if (($path === '/api/consentement' && $req->method === 'POST') || preg_match('#^/api/dons/(stripe|paypal)/webhook$#', $path)) {
            return \App\Front\Api::handle($req);
        }
        if (str_starts_with($path, '/api/')) {
            return Response::json(['error' => 'Adresse inconnue.'], 404);
        }
        if ($path === '/robots.txt') {
            return Seo::robots();
        }
        if ($path === '/sitemap.xml') {
            return Seo::sitemap();
        }
        // Anciens liens WordPress /?p=123 et /?s=… : au musée.
        if ($path === '/' && $req->query && ($to = Redirects::legacyQuery($req->query))) {
            return Response::redirect(Host::museum($to), 301);
        }

        $routes = self::routes();
        $own = $routes->route($path) !== null;

        // Site fermé : page d'attente (l'aperçu du back-office montre le site).
        if (!Site::$preview && !Site::open() && !in_array($path, self::ALWAYS, true) && !str_starts_with($path, '/documents/')) {
            if (!$own && ($to = self::museumTarget($req))) {
                return Response::redirect($to, 301);
            }
            return Pages::waiting();
        }

        $res = $routes->dispatch($req);
        if ($res instanceof Response) {
            return $res;
        }
        // Adresse sans barre finale d'une page du site → avec.
        if ($req->method === 'GET' && !str_ends_with($path, '/') && !preg_match('#\.\w{2,5}$#', $path) && $routes->route($path . '/') !== null) {
            return Response::redirect(Host::url($path . '/') . self::qs($req), 301);
        }
        // Ancienne adresse du site WordPress (fiche, rubrique…) que le musée connaît.
        if ($to = self::museumTarget($req)) {
            return Response::redirect($to, 301);
        }
        return Pages::notFound();
    }

    /** Adresse au musée d'une ancienne page du site www (WordPress), si le musée la connaît. */
    public static function museumTarget(Request $req): ?string
    {
        if (!in_array($req->method, ['GET', 'HEAD'], true) || $req->path === '/' || str_starts_with($req->path, '/admin')) {
            return null;
        }
        [$code, $to] = \App\Kernel::probe($req->path, $req->query);
        if ($code === 200) {
            return Host::museum($req->path) . self::qs($req);
        }
        if (in_array($code, [301, 302], true) && is_string($to) && $to !== '') {
            return preg_match('#^https?://#i', $to) ? $to : Host::museum($to);
        }
        return null;
    }

    private static function qs(Request $req): string
    {
        return $req->query ? '?' . http_build_query($req->query) : '';
    }

    /** Pages du site (méthode, adresse, page). */
    public static function routes(): Router
    {
        $r = new Router();
        $r->get('/', fn ($q) => Pages::home($q));
        $r->get('/association/', fn ($q) => Pages::association($q));
        $r->get('/association/equipe/', fn ($q) => Pages::team($q));
        $r->get('/association/statuts-et-documents/', fn ($q) => Pages::documents($q));
        $r->get('/nos-actions/', fn ($q) => Pages::actions($q));
        $r->get('/nos-actions/{slug}/', fn ($q, $slug) => Pages::action($q, $slug));
        $r->get('/actualites/', fn ($q) => Pages::news($q));
        $r->get('/actualites/{slug}/', fn ($q, $slug) => Pages::newsItem($q, $slug));
        $r->get('/agenda/', fn ($q) => Pages::agenda($q));
        $r->get('/agenda/agenda.ics', fn ($q) => Pages::ics($q));
        $r->get('/agenda/{slug}/', fn ($q, $slug) => Pages::event($q, $slug));
        $r->get('/nous-soutenir/', fn ($q) => Pages::support($q));
        $r->get('/nous-soutenir/adherer/', fn ($q) => Forms::membership($q));
        $r->post('/nous-soutenir/adherer/', fn ($q) => Forms::membershipSend($q));
        $r->get('/nous-soutenir/adherer/merci/', fn ($q) => Forms::membershipThanks($q));
        $r->get('/nous-soutenir/adherer/bulletin/', fn ($q) => Forms::membershipPaper($q));
        $r->get('/nous-soutenir/benevolat/', fn ($q) => Forms::volunteer($q));
        $r->post('/nous-soutenir/benevolat/', fn ($q) => Forms::volunteerSend($q));
        $r->get('/partenaires/', fn ($q) => Pages::partners($q));
        $r->get('/presse/', fn ($q) => Pages::press($q));
        $r->get('/presse/presentation-sochaux-retro.pdf', fn ($q) => Pages::pressKit($q));
        $r->get('/contact/', fn ($q) => Forms::contact($q));
        $r->post('/contact/', fn ($q) => Forms::contactSend($q));
        $r->post('/newsletter/', fn ($q) => Forms::newsletter($q));
        $r->get('/mentions-legales/', fn ($q) => Pages::legal($q, 'mentions'));
        $r->get('/confidentialite/', fn ($q) => Pages::legal($q, 'confidentialite'));
        $r->get('/cookies/', fn ($q) => Pages::legal($q, 'cookies'));
        $r->get('/plan-du-site/', fn ($q) => Pages::siteMap($q));
        $r->get('/documents/{name}', fn ($q, $name) => Documents::serve($name));
        $r->get('/videos/miniature/{id}.jpg', fn ($q, $id) => Videos::thumb($id));
        $r->get('/video/teaser.mp4', fn ($q) => Pages::teaser($q, 'mp4'));
        $r->get('/video/teaser.jpg', fn ($q) => Pages::teaser($q, 'jpg'));
        return $r;
    }
}
