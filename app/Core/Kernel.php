<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController;
use App\Controllers\AdsTxtController;
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
use App\Services\NotFound;
use App\Services\Seo;
use App\Storage\Audit;

/** Assemble les routes, choisit la langue, exécute l'action et renvoie la réponse. */
final class Kernel
{
    public function handle(Request $request): Response
    {
        if (($redirect = Security::forceHttps()) !== null) {
            return $redirect;
        }

        // La session n'est ouverte que si le visiteur en porte déjà une : une
        // visite anonyme ne pose aucun cookie et reste donc cachable.
        Session::startIfExists();

        // Le back-office est en français : il suivait la langue du navigateur,
        // ce qui affichait ses dates au format anglais.
        I18n::boot(str_starts_with($request->path, '/admin')
            ? 'fr'
            : I18n::detect($request->path));

        $router = $this->routes();

        try {
            $match = $router->match($request->method, $request->path);
        } catch (\RuntimeException $e) {
            return $this->fail($request, 405, $e);
        }

        // Aucune route : peut-être un chemin sans langue, sinon 404.
        if ($match === null) {
            return $this->notFound($request, $router);
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

        return $this->withCaching($request, $response);
    }

    /**
     * Politique de cache de la réponse.
     *
     * Une page publique servie à un visiteur anonyme peut vivre quelques
     * minutes dans le navigateur et dans le cache du serveur ; `stale-while-
     * revalidate` évite que l'expiration se traduise par une attente. Dès
     * qu'une session existe — donc dès qu'une page est personnalisée — plus
     * rien n'est partagé.
     */
    private function withCaching(Request $request, Response $response): Response
    {
        if ($request->method !== 'GET' && $request->method !== 'HEAD') {
            return $response;
        }
        // Une réponse qui a déjà choisi sa politique la garde.
        if ($response->hasHeader('Cache-Control')) {
            return $response;
        }
        if (str_starts_with($request->path, '/admin')) {
            return $response->withHeader('Cache-Control', 'private, no-store');
        }
        if (Session::exists()) {
            return $response->withHeader('Cache-Control', 'private, no-cache');
        }
        if ($response->status() !== 200) {
            return $response;
        }
        return $response->withHeader(
            'Cache-Control',
            'public, max-age=300, stale-while-revalidate=600',
        );
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
        // Les adresses viennent de la rubrique SEO du back-office : « /offres »
        // peut devenir « /emplois » sans toucher au code, et l'ancienne adresse
        // continue de répondre par une redirection permanente.
        $jobs      = Seo::routePath('jobs');
        $job       = Seo::routePath('job');
        $cvs       = Seo::routePath('cvs');
        $cv        = Seo::routePath('cv');
        $employers = Seo::routePath('employers');
        $employer  = Seo::routePath('employer');

        $public = [
            ['GET',  Seo::routePath('home'),      [HomeController::class,     'index']],
            ['GET',  $jobs,                       [JobController::class,      'index']],
            ['GET',  $job . '/{slug}',            [JobController::class,      'show']],
            ['POST', $job . '/{slug}',            [JobController::class,      'apply']],
            ['GET',  $cvs,                        [CvController::class,       'index']],
            ['GET',  $cv . '/{slug}',             [CvController::class,       'show']],
            ['POST', $cv . '/{slug}',             [CvController::class,       'contact']],
            ['GET',  $employers,                  [EmployerController::class, 'index']],
            ['GET',  $employer . '/{slug}',       [EmployerController::class, 'show']],
            ['GET',  Seo::routePath('post_cv'),   [SubmitController::class,   'cvForm']],
            ['POST', Seo::routePath('post_cv'),   [SubmitController::class,   'cvSubmit']],
            ['GET',  Seo::routePath('post_job'),  [SubmitController::class,   'jobForm']],
            ['POST', Seo::routePath('post_job'),  [SubmitController::class,   'jobSubmit']],
            ['GET',  Seo::routePath('resources'), [PageController::class,     'resources']],
        ];
        foreach ($public as [$method, $pattern, $handler]) {
            $router->addLocalized($method, $pattern, $handler);
        }

        // Adresses abandonnées après un changement de réglage : 301 vers la
        // nouvelle, en conservant le slug pour les fiches.
        foreach (array_keys(Seo::ROUTES) as $key) {
            $isPrefix = !empty(Seo::ROUTES[$key]['prefix']);
            $target = Seo::routePath($key);
            foreach (Seo::formerPaths($key) as $former) {
                $pattern = $isPrefix ? $former . '/{slug}' : $former;
                $router->addLocalized('GET', $pattern,
                    static function (Request $request, array $params) use ($target, $isPrefix): Response {
                        // Adresse déjà publique : on la préfixe de la langue
                        // sans repasser par la table de correspondance.
                        $lang = (string) ($params['lang'] ?? 'fr');
                        $path = $isPrefix ? $target . '/' . ($params['slug'] ?? '') : $target;
                        $path = $path === '/' ? '/' . $lang . '/' : '/' . $lang . rtrim($path, '/');
                        return Response::redirect($path, 301);
                    });
            }
        }

        // Page éditoriale : motif le plus large, monté en dernier parmi les routes de langue.
        $router->addLocalized('GET', '/{slug}', [PageController::class, 'show']);

        $router->get('/sitemap.xml', [SitemapController::class, 'xml']);
        // Exigé par AdSense à la racine exacte du domaine.
        $router->get('/ads.txt', [AdsTxtController::class, 'txt']);

        // --- API interne ----------------------------------------------------
        $router->get('/api/search/jobs',     [ApiController::class, 'jobs']);
        $router->get('/api/search/cv',       [ApiController::class, 'cv']);
        $router->get('/api/search/employers',[ApiController::class, 'employers']);
        $router->get('/api/suggest',         [ApiController::class, 'suggest']);
        $router->get('/api/places',          [ApiController::class, 'places']);
        $router->get('/api/jeton',           [ApiController::class, 'token']);
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
        $router->any('/admin/alertes',             [AdminController::class, 'alerts']);
        $router->any('/admin/referencement',       [AdminController::class, 'seo']);
        $router->any('/admin/cles-api',            [AdminController::class, 'settings']);

        return $router;
    }

    /**
     * Chemin sans langue reconnue : on tente une redirection propre avant le 404.
     *
     * La redirection ne vaut que pour une route qui existe vraiment. Rediriger
     * « /wp-content/machin.php » vers « /fr/wp-content/machin.php » pour y
     * répondre 404 ne faisait qu'ajouter un saut inutile, et les moteurs
     * comptent ces chaînes contre le site.
     */
    private function notFound(Request $request, ?Router $router = null): Response
    {
        $path = rtrim($request->path, '/');

        if ($path !== '' && $router !== null && !preg_match('#^/[a-z]{2}(/|$)#', $path)) {
            $candidate = I18n::url($path);
            try {
                if ($router->match('GET', rtrim($candidate, '/') ?: '/') !== null) {
                    return Response::redirect($candidate, 301);
                }
            } catch (\RuntimeException) {
                // Chemin connu mais réservé à un autre verbe : pas de redirection.
            }
        }

        return NotFound::response('/', $request->path, $request->wantsJson());
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
