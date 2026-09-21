<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\CvController;
use App\Controllers\EmployerController;
use App\Controllers\HomeController;
use App\Controllers\JobController;
use App\Controllers\MediaController;
use App\Controllers\PageController;
use App\Controllers\SitemapController;
use App\Controllers\SubmitController;
use App\Services\I18n;
use App\Storage\Audit;

/** Assemble les routes, choisit la langue, exécute l'action et renvoie la réponse. */
final class Kernel
{
    public function handle(Request $request): Response
    {
        if (($redirect = Security::forceHttps()) !== null) {
            return $redirect;
        }

        Session::start();
        I18n::boot(I18n::detect($request->path));

        $router = $this->routes();

        try {
            $match = $router->match($request->method, $request->path);
        } catch (\RuntimeException $e) {
            return $this->fail($request, 405, $e);
        }

        // Aucune route : peut-être une page éditoriale, sinon 404.
        if ($match === null) {
            return $this->notFound($request);
        }

        [$handler, $params] = $match;
        if (isset($params['lang'])) {
            I18n::boot((string) $params['lang']);
        }

        Security::sendHeaders();

        try {
            $response = $this->invoke($handler, $request, $params);
        } catch (\Throwable $e) {
            return $this->fail($request, 500, $e);
        }

        return $response;
    }

    private function invoke(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $handler = [new $class(), $method];
        }
        $result = $handler($request, $params);
        return $result instanceof Response ? $result : Response::html((string) $result);
    }

    private function routes(): Router
    {
        $router = new Router();

        // Racine : on redirige vers la langue détectée.
        $router->get('/', static fn(): Response => Response::redirect(I18n::url('/'), 302));

        // --- pages publiques, déclinées par langue -------------------------
        $public = [
            ['GET',  '/',                     [HomeController::class,     'index']],
            ['GET',  '/offres',               [JobController::class,      'index']],
            ['GET',  '/offre/{slug}',         [JobController::class,      'show']],
            ['GET',  '/cv',                   [CvController::class,       'index']],
            ['GET',  '/cv/{slug}',            [CvController::class,       'show']],
            ['GET',  '/employeurs',           [EmployerController::class, 'index']],
            ['GET',  '/employeur/{slug}',     [EmployerController::class, 'show']],
            ['GET',  '/deposer-un-cv',        [SubmitController::class,   'cvForm']],
            ['POST', '/deposer-un-cv',        [SubmitController::class,   'cvSubmit']],
            ['GET',  '/deposer-une-annonce',  [SubmitController::class,   'jobForm']],
            ['POST', '/deposer-une-annonce',  [SubmitController::class,   'jobSubmit']],
            ['GET',  '/ressources',           [PageController::class,     'resources']],
        ];
        foreach ($public as [$method, $pattern, $handler]) {
            $router->addLocalized($method, $pattern, $handler);
        }

        // Page éditoriale : motif le plus large, monté en dernier parmi les routes de langue.
        $router->addLocalized('GET', '/{slug}', [PageController::class, 'show']);

        $router->get('/sitemap.xml', [SitemapController::class, 'xml']);

        // --- API interne ----------------------------------------------------
        $router->get('/api/search/jobs',     [ApiController::class, 'jobs']);
        $router->get('/api/search/cv',       [ApiController::class, 'cv']);
        $router->get('/api/search/employers',[ApiController::class, 'employers']);
        $router->get('/api/suggest',         [ApiController::class, 'suggest']);
        $router->get('/api/places',          [ApiController::class, 'places']);
        $router->post('/api/regie',          [ApiController::class, 'regie']);
        $router->post('/api/report',         [ApiController::class, 'report']);

        // --- fichiers servis depuis /data, hors racine web ------------------
        $router->get('/media/{kind}/{name}', [MediaController::class, 'serve']);

        // --- back-office ----------------------------------------------------
        $router->any('/admin',                     [AdminController::class, 'login']);
        $router->any('/admin/mot-de-passe-oublie', [AdminController::class, 'forgot']);
        $router->any('/admin/reinitialiser',       [AdminController::class, 'reset']);
        $router->post('/admin/deconnexion',        [AdminController::class, 'logout']);
        $router->get('/admin/tableau-de-bord',     [AdminController::class, 'dashboard']);
        $router->any('/admin/contenus',            [AdminController::class, 'contents']);
        $router->any('/admin/contenu/{slug}',      [AdminController::class, 'editor']);
        $router->any('/admin/offres',              [AdminController::class, 'jobs']);
        $router->any('/admin/cv',                  [AdminController::class, 'cvs']);
        $router->any('/admin/employeurs',          [AdminController::class, 'employers']);
        $router->any('/admin/documents',           [AdminController::class, 'documents']);
        $router->any('/admin/traductions',         [AdminController::class, 'translations']);
        $router->any('/admin/sauvegardes',         [AdminController::class, 'backups']);
        $router->any('/admin/utilisateurs',        [AdminController::class, 'users']);
        $router->any('/admin/publicite',           [AdminController::class, 'ads']);
        $router->any('/admin/offres-externes',     [AdminController::class, 'sources']);

        return $router;
    }

    /** Chemin sans langue reconnue : on tente une redirection propre avant le 404. */
    private function notFound(Request $request): Response
    {
        $path = rtrim($request->path, '/');

        // /offres -> /fr/offres
        if ($path !== '' && !preg_match('#^/[a-z]{2}(/|$)#', $path)) {
            return Response::redirect(I18n::url($path), 301);
        }

        Security::sendHeaders();
        return Response::html(
            View::render('pages/error', [
                'code'  => 404,
                'title' => I18n::t('error.404_title'),
                'body'  => I18n::t('error.404_body'),
                'path'  => '/',
            ]),
            404,
        );
    }

    private function fail(Request $request, int $code, \Throwable $e): Response
    {
        Audit::log('http.' . $code, [
            'path'    => $request->path,
            'message' => $e->getMessage(),
            'file'    => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
        error_log(sprintf('[%d] %s — %s:%d', $code, $e->getMessage(), $e->getFile(), $e->getLine()));

        if ($request->wantsJson()) {
            return Response::json(['error' => $code === 405 ? 'method-not-allowed' : 'server-error'], $code);
        }

        Security::sendHeaders();
        try {
            $html = View::render('pages/error', [
                'code'  => $code,
                'title' => I18n::t('error.500_title'),
                'body'  => I18n::t('error.500_body'),
                'path'  => '/',
            ]);
        } catch (\Throwable) {
            // Le gabarit d'erreur lui-même a échoué : réponse minimale.
            $html = '<!doctype html><meta charset="utf-8"><title>Erreur</title>'
                  . '<p style="font-family:sans-serif;padding:40px">Incident technique. Réessayez dans un instant.</p>';
        }
        return Response::html($html, $code);
    }
}
