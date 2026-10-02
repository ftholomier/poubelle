<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Admin;
use App\Controllers\Api;
use App\Controllers\Front;
use App\Controllers\Pro;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Net;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;

// ------------------------------------------------------------- middlewares
$router->middleware('pro', static function () {
    Session::start();
    $pro = Auth::pro();
    if (!$pro) {
        if (Request::isAjax()) {
            return Response::json(['error' => 'Session expirée, reconnectez-vous.'], 401);
        }
        Session::set('intended', Request::uri());
        return Response::redirect('/connexion/');
    }
    // Anciens mots de passe (stockés en clair sur l'ancien site) : changement obligatoire.
    $path = Request::path();
    if (!empty($pro['must_change_password']) && Session::get('impersonate_from') === null
        && !in_array($path, ['/espace-pro/compte/', '/espace-pro/quitter-apercu'], true)) {
        $msg = 'Pour sécuriser votre compte, choisissez d\'abord un nouveau mot de passe.';
        if (Request::isAjax()) {
            return Response::json(['error' => $msg], 403);
        }
        Session::flash('warning', $msg);
        return Response::redirect('/espace-pro/compte/#securite');
    }
    return null;
});
$router->middleware('admin', static function () {
    Session::start();
    $allow = (string) Env::get('ADMIN_IP_ALLOWLIST', '');
    if ($allow !== '' && !Net::ipInList(Request::ip(), $allow)) {
        Logger::security('Accès admin refusé (IP non autorisée)');
        throw new HttpException(404);
    }
    if (!Auth::admin()) {
        if (Request::isAjax()) {
            return Response::json(['error' => 'Session expirée'], 401);
        }
        Session::set('admin_intended', Request::uri());
        return Response::redirect(Url::admin('login'));
    }
    // Mot de passe provisoire ou double authentification obligatoire : passage par « Mon compte ».
    $me = Auth::admin();
    $path = Request::path();
    if (!in_array($path, [Url::admin('mon-compte'), Url::admin('logout')], true)) {
        $block = null;
        if (!empty($me['must_change_password'])) {
            $block = ['Choisissez votre mot de passe personnel pour continuer.', 'mon-compte#securite'];
        } elseif (Env::bool('ADMIN_2FA_REQUIRED') && empty($me['totp_enabled'])) {
            $block = ['La double authentification est obligatoire : activez-la pour continuer.', 'mon-compte#2fa'];
        }
        if ($block !== null) {
            // appels AJAX compris : aucune action possible tant que le compte n'est pas sécurisé
            if (Request::isAjax()) {
                return Response::json(['error' => $block[0]], 403);
            }
            Session::flash('warning', $block[0]);
            return Response::redirect(Url::admin($block[1]));
        }
    }
    return null;
});
$router->middleware('csrf', static function () {
    if (Request::isPost() && !Csrf::check()) {
        Logger::security('CSRF invalide', ['path' => Request::path()]);
        throw new HttpException(419, 'Votre session a expiré. Rechargez la page et réessayez.');
    }
    return null;
});
$perm = static fn (string $p) => static function () use ($p) {
    if (!Auth::adminCan($p)) {
        throw new HttpException(403, "Votre rôle ne permet pas d'accéder à cette section.");
    }
    return null;
};

// ---------------------------------------------------------- fichiers SEO/PWA
$router->get('/robots.txt', [Front\SeoController::class, 'robots']);
$router->get('/ads.txt', [Front\SeoController::class, 'adsTxt']);
$router->get('/sitemap.xml', [Front\SeoController::class, 'sitemapIndex']);
$router->get('/sitemaps/{name:[a-z0-9\-]+}.xml', [Front\SeoController::class, 'sitemap']);
$router->get('/manifest.webmanifest', [Front\SeoController::class, 'manifest']);
$router->get('/sw.js', [Front\SeoController::class, 'serviceWorker']);
$router->get('/hors-ligne/', [Front\SeoController::class, 'offline']);
$router->get('/cron/{token}', [Front\SeoController::class, 'cron']);

// ------------------------------------------------------------------- API
$router->get('/api/pros', [Api\ApiController::class, 'pros']);
$router->get('/api/home-pros', [Api\ApiController::class, 'homePros']);
$router->get('/api/communes', [Api\ApiController::class, 'communes']);
$router->get('/api/favoris', [Api\ApiController::class, 'favoris']);
$router->post('/api/challenge', [Api\ApiController::class, 'challenge']);
$router->post('/api/tick', [Api\ApiController::class, 'tick']);
$router->post('/api/track', [Api\ApiController::class, 'track']);
$router->post('/api/pros/{id:\d+}/phone', [Api\ApiController::class, 'phone']);
$router->post('/api/chat', [Api\ChatController::class, 'message']);
$router->post('/api/push/subscribe', [Api\ApiController::class, 'pushSubscribe']);
$router->post('/api/push/unsubscribe', [Api\ApiController::class, 'pushUnsubscribe']);

// ------------------------------------------------------------ emails
$router->get('/e/o/{token:[A-Za-z0-9_\-.]+}.gif', [Front\EmailController::class, 'open']);
$router->get('/e/c/{token:[A-Za-z0-9_\-.]+}', [Front\EmailController::class, 'click']);
$router->any('/e/u/{token:[A-Za-z0-9_\-.]+}', [Front\EmailController::class, 'unsubscribe']);

// ------------------------------------------------------------ pages publiques
$router->get('/', [Front\HomeController::class, 'index']);
$router->get('/recherche/', [Front\SearchController::class, 'index']);
$router->get('/carte/', [Front\SearchController::class, 'map']);
$router->get('/favoris/', [Front\SearchController::class, 'favoris']);
$router->get('/devis/', [Front\DevisController::class, 'form']);
$router->post('/devis/', [Front\DevisController::class, 'submit']);
$router->get('/devis/merci/', [Front\DevisController::class, 'thanks']);
$router->post('/pro/{id:\d+}/contact', [Front\ProController::class, 'contact']);
$router->post('/pro/{id:\d+}/avis', [Front\ProController::class, 'review']);
$router->get('/pro/{slug:[a-z0-9\-]+}/', [Front\ProController::class, 'bySlug']);
$router->get('/go/{id:\d+}', [Front\ProController::class, 'go']);
$router->get('/avis/confirmer/{token}/', [Front\ProController::class, 'confirmReview']);
$router->any('/avis/invitation/{token}/', [Front\ProController::class, 'invitedReview']);
$router->get('/message/merci/', [Front\ProController::class, 'thanks']);
$router->get('/blog/', [Front\BlogController::class, 'index']);
$router->get('/blog/categorie/{cat:[a-z\-]+}/', [Front\BlogController::class, 'index']);
$router->get('/blog/{slug:[a-z0-9\-]+}/', [Front\BlogController::class, 'show']);
$router->get('/contact/', [Front\PageController::class, 'contact']);
$router->post('/contact/', [Front\PageController::class, 'contactSubmit']);
$router->get('/plan-du-site/', [Front\PageController::class, 'sitemap']);
$router->get('/professionnels/', [Front\PageController::class, 'pros']);

// ------------------------------------------------------------ espace pro
$router->get('/connexion/', [Pro\AuthController::class, 'loginForm']);
$router->post('/connexion/', [Pro\AuthController::class, 'login'], ['csrf']);
$router->post('/deconnexion/', [Pro\AuthController::class, 'logout'], ['csrf']);
$router->get('/mot-de-passe-oublie/', [Pro\AuthController::class, 'forgotForm']);
$router->post('/mot-de-passe-oublie/', [Pro\AuthController::class, 'forgot'], ['csrf']);
$router->get('/reinitialiser/{token}/', [Pro\AuthController::class, 'resetForm']);
$router->post('/reinitialiser/{token}/', [Pro\AuthController::class, 'reset'], ['csrf']);
$router->get('/verifier-email/{token}/', [Pro\AuthController::class, 'verifyEmail']);
$router->get('/inscription-pro/', [Pro\RegisterController::class, 'form']);
$router->post('/inscription-pro/', [Pro\RegisterController::class, 'submit']);
$router->get('/inscription-pro/merci/', [Pro\RegisterController::class, 'thanks']);
$router->group('/espace-pro', ['pro', 'csrf'], static function ($r) {
    $r->get('/', [Pro\DashboardController::class, 'index']);
    $r->get('/fiche/', [Pro\DashboardController::class, 'fiche']);
    $r->post('/fiche/', [Pro\DashboardController::class, 'saveFiche']);
    $r->get('/photos/', [Pro\DashboardController::class, 'photos']);
    $r->post('/photos/', [Pro\DashboardController::class, 'uploadPhotos']);
    $r->post('/photos/action', [Pro\DashboardController::class, 'photoAction']);
    $r->get('/demandes/', [Pro\DashboardController::class, 'requests']);
    $r->get('/demandes/{id:\d+}/', [Pro\DashboardController::class, 'request']);
    $r->post('/demandes/{id:\d+}/', [Pro\DashboardController::class, 'request']);
    $r->get('/messages/', [Pro\DashboardController::class, 'messages']);
    $r->get('/messages/{id:\d+}/', [Pro\DashboardController::class, 'message']);
    $r->get('/avis/', [Pro\DashboardController::class, 'reviews']);
    $r->post('/avis/repondre', [Pro\DashboardController::class, 'replyReview']);
    $r->post('/avis/inviter', [Pro\DashboardController::class, 'inviteReview']);
    $r->get('/statistiques/', [Pro\DashboardController::class, 'stats']);
    $r->get('/compte/', [Pro\DashboardController::class, 'account']);
    $r->post('/compte/', [Pro\DashboardController::class, 'saveAccount']);
    $r->get('/compte/export', [Pro\DashboardController::class, 'export']);
    $r->post('/compte/supprimer', [Pro\DashboardController::class, 'deleteAccount']);
    $r->post('/ia/description', [Pro\DashboardController::class, 'aiDescription']);
    $r->post('/quitter-apercu', [Pro\DashboardController::class, 'stopImpersonate']);
});

// ------------------------------------------------------------ back-office
$admin = '/' . trim((string) Env::get('ADMIN_PATH', 'gestion'), '/');
$router->get($admin . '/login', [Admin\AuthController::class, 'loginForm']);
$router->post($admin . '/login', [Admin\AuthController::class, 'login'], ['csrf']);
$router->get($admin . '/2fa', [Admin\AuthController::class, 'twoFactorForm']);
$router->post($admin . '/2fa', [Admin\AuthController::class, 'twoFactor'], ['csrf']);
$router->any($admin . '/setup', [Admin\AuthController::class, 'setup']);
$router->post($admin . '/logout', [Admin\AuthController::class, 'logout'], ['csrf']);
$router->group($admin, ['admin', 'csrf'], static function ($r) use ($perm) {
    $r->get('/', [Admin\DashboardController::class, 'index']);
    $r->get('/recherche', [Admin\DashboardController::class, 'search']);
    $r->get('/notifications', [Admin\DashboardController::class, 'notifications']);
    $r->post('/notifications/lues', [Admin\DashboardController::class, 'readAll']);
    $r->get('/statistiques', [Admin\DashboardController::class, 'stats'], [$perm('dashboard')]);
    $r->any('/memo', [Admin\DashboardController::class, 'memo']);
    $r->any('/mon-compte', [Admin\UsersController::class, 'me']);

    $r->get('/pros', [Admin\ProsController::class, 'index'], [$perm('pros')]);
    $r->get('/pros/export.csv', [Admin\ProsController::class, 'export'], [$perm('pros')]);
    $r->post('/pros/lot', [Admin\ProsController::class, 'bulk'], [$perm('pros')]);
    $r->any('/pros/nouveau', [Admin\ProsController::class, 'create'], [$perm('pros')]);
    $r->get('/pros/{id:\d+}', [Admin\ProsController::class, 'edit'], [$perm('pros')]);
    $r->post('/pros/{id:\d+}', [Admin\ProsController::class, 'save'], [$perm('pros')]);
    $r->post('/pros/{id:\d+}/action', [Admin\ProsController::class, 'action'], [$perm('pros')]);
    $r->post('/pros/{id:\d+}/photos', [Admin\ProsController::class, 'photos'], [$perm('pros')]);

    $r->get('/demandes', [Admin\RequestsController::class, 'index'], [$perm('requests')]);
    $r->get('/demandes/export.csv', [Admin\RequestsController::class, 'export'], [$perm('requests')]);
    $r->get('/demandes/{id:\d+}', [Admin\RequestsController::class, 'show'], [$perm('requests')]);
    $r->post('/demandes/{id:\d+}', [Admin\RequestsController::class, 'action'], [$perm('requests')]);
    $r->get('/messages', [Admin\MessagesController::class, 'index'], [$perm('messages')]);
    $r->get('/messages/{id:\d+}', [Admin\MessagesController::class, 'show'], [$perm('messages')]);
    $r->post('/messages/{id:\d+}', [Admin\MessagesController::class, 'action'], [$perm('messages')]);
    $r->get('/contacts', [Admin\MessagesController::class, 'contacts'], [$perm('messages')]);
    $r->post('/contacts/{id:\d+}', [Admin\MessagesController::class, 'contactAction'], [$perm('messages')]);
    $r->get('/avis', [Admin\ReviewsController::class, 'index'], [$perm('reviews')]);
    $r->post('/avis/{id:\d+}', [Admin\ReviewsController::class, 'action'], [$perm('reviews')]);

    $r->get('/emailing', [Admin\MailingController::class, 'index'], [$perm('mailing')]);
    $r->any('/emailing/nouvelle', [Admin\MailingController::class, 'edit'], [$perm('mailing')]);
    $r->any('/emailing/{id:\d+}', [Admin\MailingController::class, 'edit'], [$perm('mailing')]);
    $r->post('/emailing/{id:\d+}/action', [Admin\MailingController::class, 'action'], [$perm('mailing')]);
    $r->post('/emailing/audience', [Admin\MailingController::class, 'audience'], [$perm('mailing')]);
    $r->get('/emailing/modeles', [Admin\MailingController::class, 'templates'], [$perm('mailing')]);
    $r->any('/emailing/modeles/{key:[a-z_]+}', [Admin\MailingController::class, 'template'], [$perm('mailing')]);
    $r->any('/emailing/file', [Admin\MailingController::class, 'queue'], [$perm('mailing')]);
    $r->get('/prospects', [Admin\MailingController::class, 'prospects'], [$perm('mailing')]);
    $r->get('/prospects/export.csv', [Admin\MailingController::class, 'prospectsExport'], [$perm('mailing')]);

    $r->get('/blog', [Admin\ContentController::class, 'articles'], [$perm('content')]);
    $r->any('/blog/nouveau', [Admin\ContentController::class, 'article'], [$perm('content')]);
    $r->any('/blog/{id:\d+}', [Admin\ContentController::class, 'article'], [$perm('content')]);
    $r->get('/pages', [Admin\ContentController::class, 'pages'], [$perm('content')]);
    $r->any('/pages/nouvelle', [Admin\ContentController::class, 'pageEdit'], [$perm('content')]);
    $r->any('/pages/{id:\d+}', [Admin\ContentController::class, 'pageEdit'], [$perm('content')]);
    $r->any('/accueil', [Admin\ContentController::class, 'home'], [$perm('content')]);
    $r->any('/categories', [Admin\ContentController::class, 'categories'], [$perm('content')]);
    $r->any('/occasions', [Admin\ContentController::class, 'occasions'], [$perm('content')]);
    $r->post('/upload', [Admin\ContentController::class, 'upload'], [$perm('content')]);

    $r->any('/seo', [Admin\SeoController::class, 'index'], [$perm('seo')]);
    $r->any('/seo/redirections', [Admin\SeoController::class, 'redirects'], [$perm('seo')]);
    $r->any('/seo/pages-locales', [Admin\SeoController::class, 'landings'], [$perm('seo')]);
    $r->any('/seo/robots', [Admin\SeoController::class, 'robots'], [$perm('seo')]);

    $r->any('/reglages', [Admin\SettingsController::class, 'env'], [$perm('env')]);
    $r->any('/reglages/fonctionnement', [Admin\SettingsController::class, 'features'], [$perm('settings')]);
    $r->any('/reglages/notifications', [Admin\SettingsController::class, 'notifications'], [$perm('settings')]);
    $r->any('/publicite', [Admin\SettingsController::class, 'ads'], [$perm('settings')]);
    $r->any('/antispam', [Admin\SettingsController::class, 'antispam'], [$perm('settings')]);
    $r->any('/ia', [Admin\AiController::class, 'index'], [$perm('settings')]);
    $r->post('/ia/test', [Admin\AiController::class, 'test'], [$perm('settings')]);
    $r->post('/ia/lot', [Admin\AiController::class, 'batch'], [$perm('settings')]);
    $r->get('/ia/conversations', [Admin\AiController::class, 'chats'], [$perm('settings')]);

    $r->get('/journal', [Admin\SystemController::class, 'logs'], [$perm('system')]);
    $r->get('/maintenance', [Admin\SystemController::class, 'index'], [$perm('system')]);
    $r->post('/maintenance/action', [Admin\SystemController::class, 'action'], [$perm('system')]);
    $r->post('/maintenance/import', [Admin\SystemController::class, 'import'], [$perm('system')]);
    $r->get('/maintenance/sauvegarde/{name:[A-Za-z0-9_\-.]+}', [Admin\SystemController::class, 'download'], [$perm('system')]);
    $r->get('/archives', [Admin\SystemController::class, 'archives'], [$perm('system')]);
    $r->get('/archives/{table:[a-z_]+}', [Admin\SystemController::class, 'archive'], [$perm('system')]);

    $r->get('/utilisateurs', [Admin\UsersController::class, 'index'], [$perm('users')]);
    $r->any('/utilisateurs/nouveau', [Admin\UsersController::class, 'edit'], [$perm('users')]);
    $r->any('/utilisateurs/{id:\d+}', [Admin\UsersController::class, 'edit'], [$perm('users')]);
    $r->post('/push/subscribe', [Admin\DashboardController::class, 'pushSubscribe']);
});

// ------------------------------------- pages SEO dynamiques (en dernier)
$router->get('/{a:[a-z0-9\-]+}/', [Front\ListingController::class, 'one']);
$router->get('/{a:[a-z0-9\-]+}/{b:[a-z0-9\-]+}/', [Front\ListingController::class, 'two']);
$router->get('/{a:[a-z0-9\-]+}/{b:[a-z0-9\-]+}/{c:[a-z0-9\-]+}/', [Front\ListingController::class, 'three']);
$router->get('/{a:[a-z0-9\-]+}/{b:[a-z0-9\-]+}/{c:[a-z0-9\-]+}/{d:[a-z0-9\-]+}/', [Front\ProController::class, 'show']);
