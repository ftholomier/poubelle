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
        try {
            $res = self::dispatch($req);
        } catch (\Throwable $e) {
            error_log((string) $e);
            if (Settings::get('general.debug', false)) {
                $res = Response::html('<pre>' . htmlspecialchars((string) $e) . '</pre>', 500);
            } elseif ($req->wantsJson()) {
                $res = Response::json(['error' => 'Erreur interne'], 500);
            } else {
                $res = Response::html(View::render('errors/500'), 500);
            }
        }
        // Politique de sécurité du contenu sur toutes les pages HTML (site et back-office).
        if ($res->file === null && str_starts_with((string) ($res->headers['Content-Type'] ?? ''), 'text/html')) {
            $res->headers['Content-Security-Policy'] ??= self::csp($req);
        }
        return $res;
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

        // Images à la volée
        if (preg_match('#^/media/(\d+|full)/(.+)$#', $path, $m)) {
            return Images::serve($m[1], $m[2]);
        }
        // Anciennes images WordPress (liens externes, moteurs) → médiathèque
        if (preg_match('#^/wp-content/uploads/(.+?)(-\d+x\d+)?(\.\w+)$#', $path, $m)) {
            return Response::redirect(img($m[1] . $m[3], 1200), 301);
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

        // Webhooks et API : jamais bloqués par la page d'attente
        if (str_starts_with($path, '/api/')) {
            return Front\Api::handle($req);
        }

        // Aperçu de la page d'attente depuis le back-office
        if ($path === '/' && isset($req->query['apercu-attente']) && Auth::user()) {
            return Front\Pages::waiting();
        }
        // Page d'attente (les membres connectés du back-office voient le site)
        if (Settings::get('waiting.enabled', false) && !Auth::user()) {
            if (!in_array($path, ['/robots.txt', '/mentions-legales/', '/confidentialite/', '/cookies/'], true)) {
                return Front\Pages::waiting();
            }
        }
        // Accès restreint par mot de passe (pré-lancement)
        if (($pwd = Settings::get('general.front_password', '')) && !Auth::user()) {
            $gate = Front\Pages::gate($req, (string) $pwd);
            if ($gate) {
                return $gate;
            }
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

        // Ancienne adresse → 301
        if ($to = Redirects::find($req->path, $req->query)) {
            return Response::redirect(url($to), 301);
        }
        if ($req->method === 'GET') {
            Redirects::log404($req->path, (string) ($req->server['HTTP_REFERER'] ?? ''));
        }
        return Front\Pages::notFound();
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

        // Interactif
        $r->get('/interactif/', fn ($q) => Front\Interactive::landing($q));
        $r->get('/interactif/quiz/', fn ($q) => Front\Interactive::quiz($q));
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
        $r->get('/faire-un-don/', fn ($q) => Front\Donations::page($q));
        $r->post('/faire-un-don/', fn ($q) => Front\Donations::start($q));
        $r->get('/faire-un-don/merci/', fn ($q) => Front\Donations::thanks($q));
        $r->any('/faire-un-don/gerer/{token}/', fn ($q, $token) => Front\Donations::manage($q, $token));
        $r->get('/faire-un-don/gerer/{token}/recu/{num}/', fn ($q, $token, $num) => Front\Donations::receiptDownload($q, $token, $num));
        $r->get('/newsletter/', fn ($q) => Front\Community::newsletter($q));
        $r->get('/newsletter/confirmer/{token}/', fn ($q, $token) => Front\Community::newsletterConfirm($q, $token));
        $r->get('/newsletter/desinscription/{token}/', fn ($q, $token) => Front\Community::newsletterUnsubscribe($q, $token));
        $r->get('/partage-et-newsletter/', fn ($q) => Front\Community::sharePage($q));
        return $r;
    }
}
