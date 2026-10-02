<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Cron;
use App\Services\Redirects;
use App\Services\Settings;

/** Noyau HTTP : sécurité, maintenance, cache de pages, routage, pages d'erreur. */
final class App
{
    /** @var callable[] travaux exécutés après l'envoi de la réponse */
    private static array $deferred = [];

    /** Diffère un traitement lent (notification push…) après l'envoi de la réponse au visiteur. */
    public static function defer(callable $fn): void
    {
        if (PHP_SAPI === 'cli') {
            $fn();
            return;
        }
        self::$deferred[] = $fn;
    }

    public static function run(): void
    {
        $path = Request::path();
        $method = Request::method();
        $isAdmin = is_admin_path();

        // HTTPS et domaine canonique
        $canonicalHost = strtolower((string) parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST));
        if (PHP_SAPI !== 'cli' && Env::bool('FORCE_HTTPS', true) && !Request::isSecure() && !self::isLocal()) {
            $host = Request::host();
            if ($canonicalHost !== '' && ($host === $canonicalHost || str_replace('www.', '', $host) === str_replace('www.', '', $canonicalHost) || !preg_match('/^[a-z0-9.-]+(:\d+)?$/', $host))) {
                $host = $canonicalHost; // www / sans www, ou en-tête Host invalide : domaine officiel
            }
            (Response::redirect('https://' . $host . Request::uri(), 301))->send();
            return;
        }
        if ($canonicalHost !== '' && Request::host() !== $canonicalHost && !self::isLocal() && in_array($method, ['GET', 'HEAD'], true)
            && str_replace('www.', '', Request::host()) === str_replace('www.', '', $canonicalHost)) {
            (Response::redirect(rtrim((string) Env::get('APP_URL'), '/') . Request::uri(), 301))->send();
            return;
        }

        // Première visite après l'envoi par FTP : installation automatique des données livrées avec le site
        if (\App\Services\Import\Bundle::pending()) {
            $res = \App\Services\Import\Bundle::handle();
            Security::headers($res);
            $res->send();
            return;
        }

        // Maintenance (le back-office et les IP autorisées restent accessibles)
        if (Env::bool('MAINTENANCE_MODE') && !$isAdmin && !Net::ipInList(Request::ip(), (string) Env::get('MAINTENANCE_ALLOWED_IPS', '')) && !self::adminSession()) {
            $res = Response::html(View::render('errors/maintenance', ['message' => Settings::get('maintenance.message')], null), 503);
            $res->header('Retry-After', '3600');
            Security::headers($res);
            $res->send();
            return;
        }

        // Cache de pages pour les visiteurs anonymes : domaine canonique et adresses sans paramètre (sauf une
        // pagination), pour qu'on ne puisse pas remplir le disque avec des variantes d'une même page.
        $plainQuery = $_GET === [] || (array_keys($_GET) === ['page'] && is_string($_GET['page']) && preg_match('/^\d{1,4}$/', $_GET['page']) === 1);
        $cacheable = in_array($method, ['GET', 'HEAD'], true) && !$isAdmin && Env::bool('PAGE_CACHE', true)
            && !isset($_COOKIE['apvs_sid']) && !str_starts_with($path, '/api/') && !str_starts_with($path, '/espace-pro')
            && !self::isLocalDev() && $plainQuery && ($canonicalHost === '' || Request::host() === $canonicalHost || self::isLocal());
        $cacheKey = 'page:' . Request::host() . $path . ($plainQuery && $_GET !== [] ? '?page=' . $_GET['page'] : '') . '|' . self::codeStamp();
        if ($cacheable && ($hit = Cache::pageGet($cacheKey)) !== null) {
            Security::setNonce($hit['nonce']);
            $res = new Response($hit['body'], 200, $hit['headers'] + ['X-Cache' => 'HIT']);
            Security::headers($res);
            $res->send();
            self::afterResponse();
            return;
        }

        $router = new Router();
        require APP_PATH . '/routes.php';
        try {
            $res = $router->dispatch($method, $path);
        } catch (HttpException $e) {
            $res = self::errorResponse($e, $path, $isAdmin);
        } catch (\Throwable $e) {
            ErrorHandler::report($e);
            $res = self::errorResponse(new HttpException(500, Env::bool('APP_DEBUG') ? $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine() : ''), $path, $isAdmin);
        }

        Security::headers($res, $isAdmin);
        if ($cacheable && $res->status === 200 && $res->file === null && str_starts_with((string) ($res->headers['Content-Type'] ?? ''), 'text/html')
            && !isset($res->headers['Set-Cookie']) && !Session::active() && empty($res->headers['X-No-Cache'])) {
            Cache::pagePut($cacheKey, ['body' => $res->body, 'headers' => ['Content-Type' => $res->headers['Content-Type']], 'nonce' => Security::nonce()], Env::int('PAGE_CACHE_TTL', 1800));
            $res->headers['X-Cache'] = 'MISS';
        }
        unset($res->headers['X-No-Cache']);
        if (!isset($res->headers['Cache-Control'])) {
            $res->headers['Cache-Control'] = Session::active() ? 'no-store, private' : 'public, max-age=0, must-revalidate';
        }
        $res->send();
        self::afterResponse();
    }

    private static function errorResponse(HttpException $e, string $path, bool $isAdmin): Response
    {
        if ($e->status === 404) {
            $to = Redirects::resolve($path, $_GET);
            if ($to !== null && $to !== $path) {
                return Response::redirect($to, 301);
            }
            Redirects::log404($path);
        }
        if (Request::isAjax() || str_starts_with($path, '/api/')) {
            return Response::json(['error' => $e->getMessage() !== (string) $e->status ? $e->getMessage() : self::label($e->status)], $e->status);
        }
        $layout = $isAdmin && Auth::admin() ? 'admin/layout' : 'front/layout';
        try {
            $html = View::render('errors/error', ['status' => $e->status, 'message' => $e->getMessage() !== (string) $e->status ? $e->getMessage() : '', 'meta' => ['title' => self::label($e->status), 'robots' => 'noindex, follow']], $layout);
        } catch (\Throwable $ex) {
            ErrorHandler::report($ex);
            $html = '<!doctype html><meta charset="utf-8"><title>' . $e->status . '</title><p style="font-family:sans-serif;padding:40px">' . self::label($e->status) . '</p>';
        }
        $res = Response::html($html, $e->status);
        foreach ($e->headers as $k => $v) {
            $res->header($k, $v);
        }
        return $res;
    }

    public static function label(int $status): string
    {
        return match ($status) {
            400 => 'Requête invalide',
            403 => 'Accès refusé',
            404 => 'Page introuvable',
            405 => 'Méthode non autorisée',
            419 => 'Session expirée',
            429 => 'Trop de requêtes',
            503 => 'Service indisponible',
            default => 'Erreur interne',
        };
    }

    private static function adminSession(): bool
    {
        return isset($_COOKIE['apvs_sid']) && Auth::admin() !== null;
    }

    public static function isLocal(): bool
    {
        $h = Request::host();
        return $h === 'localhost' || str_starts_with($h, 'localhost:') || str_starts_with($h, '127.0.0.1') || str_ends_with(explode(':', $h)[0], '.local') || (bool) filter_var(explode(':', $h)[0], FILTER_VALIDATE_IP);
    }

    private static function isLocalDev(): bool
    {
        return Env::get('APP_ENV') === 'development';
    }

    /** Travaux après réponse : cron « du pauvre » si aucune tâche planifiée n'est configurée. */
    /**
     * Empreinte du code et de la configuration : un nouvel envoi par FTP (ou une modification de la
     * configuration) rend aussitôt obsolètes les pages gardées en cache.
     */
    private static function codeStamp(): string
    {
        $t = 0;
        foreach ([APP_PATH . '/routes.php', APP_PATH . '/Views/front/layout.php', PUBLIC_PATH . '/assets/js/app.js', PUBLIC_PATH . '/assets/js/explorer.js', PUBLIC_PATH . '/assets/css/app.css', CONFIG_PATH . '/.env'] as $f) {
            $t = max($t, (int) @filemtime($f));
        }
        return (string) $t;
    }

    /**
     * Vrai quand PHP ne peut pas terminer la réponse avant les tâches de fond (PHP en module Apache, par
     * exemple) : les navigateurs envoient alors un petit signal pour déclencher les tâches planifiées.
     */
    public static function needsTick(): bool
    {
        return !function_exists('fastcgi_finish_request') && !function_exists('litespeed_finish_request');
    }

    private static function afterResponse(): void
    {
        $finished = false;
        if (function_exists('fastcgi_finish_request')) {
            $finished = @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            $finished = @litespeed_finish_request();
        }
        foreach (self::$deferred as $fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                ErrorHandler::report($e);
            }
        }
        self::$deferred = [];
        if (!$finished) {
            return;
        }
        try {
            Cron::maybeRun();
        } catch (\Throwable $e) {
            ErrorHandler::report($e);
        }
    }
}
