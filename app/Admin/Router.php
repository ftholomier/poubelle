<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router as CoreRouter;
use App\Core\Session;

/**
 * Back-office (/admin) : connexion, contrôle d'accès à deux niveaux, jeton CSRF
 * sur toutes les requêtes POST, puis distribution vers les écrans.
 */
final class Router
{
    public static function handle(Request $req): Response
    {
        Session::start();
        $res = self::dispatch($req);
        $res->headers['X-Frame-Options'] = 'DENY';
        $res->headers['X-Robots-Tag'] = 'noindex, nofollow';
        $res->headers['Referrer-Policy'] = 'same-origin';
        $res->headers['Cache-Control'] ??= 'no-store, private';
        $res->headers['X-Content-Type-Options'] = 'nosniff';
        return $res;
    }

    private static function dispatch(Request $req): Response
    {
        $path = rtrim($req->path, '/') ?: '/admin';
        $post = $req->method === 'POST';

        // Écrans accessibles sans être connecté
        if ($post && !self::csrfOk($req) && !str_starts_with($path, '/admin/api/')) {
            return Response::html(Base::page('admin/message', ['title' => 'Session expirée', 'text' => 'Votre session a expiré : rechargez la page puis recommencez.', 'back' => '/admin'], ['bare' => true, 'title' => 'Session expirée']), 419);
        }
        $public = new CoreRouter();
        $public->any('/admin/connexion', fn ($q) => Account::login($q));
        $public->any('/admin/premier-acces', fn ($q) => Account::setup($q));
        $public->any('/admin/invitation/{token}', fn ($q, $token) => Account::invitation($q, $token));
        $public->any('/admin/mot-de-passe-oublie', fn ($q) => Account::forgot($q));
        $res = $public->dispatch(new Request($req->method, $path, $req->query, $req->post, $req->files, $req->server, $req->body));
        if ($res instanceof Response) {
            return $res;
        }

        $user = Auth::user();
        if (!$user) {
            if (str_starts_with($path, '/admin/api/') || $req->wantsJson()) {
                return Response::json(['error' => 'Session expirée : reconnectez-vous.'], 401);
            }
            return Response::redirect('/admin/connexion?r=' . rawurlencode($req->server['REQUEST_URI'] ?? '/admin'));
        }
        if ($post && str_starts_with($path, '/admin/api/') && !self::csrfOk($req)) {
            return Response::json(['error' => 'Session expirée : rechargez la page.'], 419);
        }

        $r = new CoreRouter();
        $r->post('/admin/deconnexion', fn ($q) => Account::logout($q));
        $r->any('/admin/profil', fn ($q) => Account::profile($q));

        // Aide (guide d'utilisation)
        $r->get('/admin/aide', fn ($q) => Help::index($q));
        $r->get('/admin/aide/imprimer', fn ($q) => Help::printable($q));
        $r->get('/admin/aide/memo', fn ($q) => Help::memo($q));
        $r->get('/admin/aide/fichier/{name}', fn ($q, $name) => Help::file($q, $name));
        $r->get('/admin/aide/{slug}', fn ($q, $slug) => Help::chapter($q, $slug));

        // Pilotage
        $r->get('/admin', fn ($q) => Dashboard::index($q));
        $r->get('/admin/qualite', fn ($q) => Dashboard::quality($q));
        $r->get('/admin/journal', fn ($q) => Dashboard::journal($q));
        $r->get('/admin/audience', fn ($q) => Dashboard::audience($q));

        // Fiches
        foreach (Fiches::LISTS as $slug => $conf) {
            $r->get('/admin/' . $slug, fn ($q) => Fiches::list($q, $slug));
        }
        $r->get('/admin/corbeille', fn ($q) => Fiches::trashList($q));
        $r->post('/admin/fiches/lot', fn ($q) => Fiches::bulk($q));
        $r->get('/admin/fiche/nouvelle/{type}', fn ($q, $type) => Fiches::create($q, $type));
        $r->get('/admin/fiche/{id}', fn ($q, $id) => ctype_digit($id) ? Fiches::edit($q, (int) $id) : null);
        $r->post('/admin/fiche/{id}/enregistrer', fn ($q, $id) => ctype_digit($id) ? Fiches::save($q, (int) $id) : null);
        $r->post('/admin/fiche/{id}/corbeille', fn ($q, $id) => ctype_digit($id) ? Fiches::trash($q, (int) $id) : null);
        $r->post('/admin/fiche/{id}/sortir-corbeille', fn ($q, $id) => ctype_digit($id) ? Fiches::untrash($q, (int) $id) : null);
        $r->post('/admin/fiche/{id}/supprimer', fn ($q, $id) => ctype_digit($id) ? Fiches::destroy($q, (int) $id) : null);
        $r->get('/admin/fiche/{id}/version/{n}', fn ($q, $id, $n) => ctype_digit($id) && ctype_digit($n) ? Fiches::version($q, (int) $id, (int) $n) : null);
        $r->post('/admin/fiche/{id}/version/{n}/restaurer', fn ($q, $id, $n) => ctype_digit($id) && ctype_digit($n) ? Fiches::restore($q, (int) $id, (int) $n) : null);
        $r->post('/admin/fiche/{id}/traduire', fn ($q, $id) => ctype_digit($id) ? Fiches::translate($q, (int) $id) : null);
        $r->any('/admin/fiche/{id}/apercu', fn ($q, $id) => ctype_digit($id) ? Fiches::preview($q, (int) $id) : null);

        // Médiathèque
        $r->get('/admin/medias', fn ($q) => Medias::index($q));
        $r->post('/admin/medias/envoi', fn ($q) => Medias::upload($q));
        $r->post('/admin/medias/enregistrer', fn ($q) => Medias::save($q));
        $r->post('/admin/medias/supprimer', fn ($q) => Medias::delete($q));
        $r->get('/admin/medias/apercu', fn ($q) => Medias::source($q));

        // Référentiels, éditorial, rubriques
        $r->get('/admin/referentiels', fn ($q) => Referentials::index($q));
        $r->post('/admin/referentiels/enregistrer', fn ($q) => Referentials::save($q));
        $r->get('/admin/accueil', fn ($q) => Editorial::home($q));
        $r->post('/admin/accueil', fn ($q) => Editorial::homeSave($q));
        $r->get('/admin/moments', fn ($q) => Editorial::moments($q));
        $r->post('/admin/moments/ordre', fn ($q) => Editorial::momentsOrder($q));
        $r->get('/admin/rubriques', fn ($q) => Editorial::categories($q));
        $r->post('/admin/rubriques', fn ($q) => Editorial::categoriesSave($q));
        $r->get('/admin/redirections', fn ($q) => Editorial::redirects($q));
        $r->post('/admin/redirections', fn ($q) => Editorial::redirectsSave($q));

        // Collections (quiz, frise, maillots, épopées, lieux, partenaires…)
        $r->get('/admin/collection/{name}', fn ($q, $name) => Collections::edit($q, $name));
        $r->post('/admin/collection/{name}', fn ($q, $name) => Collections::save($q, $name));
        $r->get('/admin/interactif', fn ($q) => Collections::hub($q));
        $r->get('/admin/onze', fn ($q) => Collections::onze($q));
        $r->post('/admin/onze', fn ($q) => Collections::onzeSave($q));
        $r->get('/admin/album', fn ($q) => Collections::album($q));
        $r->post('/admin/album', fn ($q) => Collections::albumSave($q));

        // Communauté
        $r->get('/admin/contributions', fn ($q) => Community::contributions($q));
        $r->get('/admin/contributions/{ticket}', fn ($q, $ticket) => Community::contribution($q, $ticket));
        $r->post('/admin/contributions/{ticket}', fn ($q, $ticket) => Community::contributionAction($q, $ticket));
        $r->get('/admin/contributions/{ticket}/fichier/{n}', fn ($q, $ticket, $n) => Community::contributionFile($q, $ticket, (int) $n));
        $r->get('/admin/messages', fn ($q) => Community::messages($q));
        $r->get('/admin/messages/{id}', fn ($q, $id) => Community::message($q, $id));
        $r->post('/admin/messages/{id}', fn ($q, $id) => Community::messageAction($q, $id));
        $r->get('/admin/newsletter', fn ($q) => Community::newsletter($q));
        $r->post('/admin/newsletter', fn ($q) => Community::newsletterAction($q));
        $r->get('/admin/newsletter/apercu', fn ($q) => Community::newsletterPreview($q));
        $r->get('/admin/dons', fn ($q) => Donations::index($q));
        $r->post('/admin/dons', fn ($q) => Donations::action($q));
        $r->get('/admin/dons/export.csv', fn ($q) => Donations::export($q));
        $r->get('/admin/dons/{id}', fn ($q, $id) => Donations::show($q, $id));
        $r->post('/admin/dons/{id}', fn ($q, $id) => Donations::update($q, $id));
        $r->get('/admin/dons/recu/{num}', fn ($q, $num) => Donations::receipt($q, $num));

        // Système
        $r->get('/admin/traductions', fn ($q) => System::translations($q));
        $r->post('/admin/traductions', fn ($q) => System::translationsSave($q));
        $r->get('/admin/assistant', fn ($q) => System::assistant($q));
        $r->post('/admin/assistant', fn ($q) => System::assistantAction($q));
        $r->get('/admin/assistant/export.csv', fn ($q) => System::assistantExport($q));
        $r->get('/admin/audio', fn ($q) => Audio::index($q));
        $r->post('/admin/audio', fn ($q) => Audio::action($q));
        $r->get('/admin/couts-ia', fn ($q) => Costs::index($q));
        $r->post('/admin/couts-ia/tarifs', fn ($q) => Costs::savePrices($q));
        $r->post('/admin/couts-ia/tarifs/defaut', fn ($q) => Costs::resetPrices($q));
        $r->post('/admin/couts-ia/rembourse', fn ($q) => Costs::reimburse($q));
        $r->get('/admin/couts-ia/releve/{ym}', fn ($q, $ym) => Costs::statement($q, $ym));
        $r->get('/admin/couts-ia/detail/{ym}', fn ($q, $ym) => Costs::csv($q, $ym));
        $r->get('/admin/utilisateurs', fn ($q) => System::users($q));
        $r->post('/admin/utilisateurs', fn ($q) => System::usersAction($q));
        $r->get('/admin/reglages', fn ($q) => System::settings($q));
        $r->get('/admin/page-attente', fn ($q) => System::waiting($q));
        $r->post('/admin/reglages', fn ($q) => System::settingsSave($q));
        $r->get('/admin/sauvegardes', fn ($q) => System::backups($q));
        $r->post('/admin/sauvegardes', fn ($q) => System::backupsAction($q));
        $r->get('/admin/sauvegardes/{file}', fn ($q, $file) => System::backupDownload($q, $file));
        $r->get('/admin/taches', fn ($q) => System::tasks($q));
        $r->post('/admin/taches', fn ($q) => System::tasksRun($q));

        // API internes (sélecteurs, recherche globale)
        $r->get('/admin/api/recherche', fn ($q) => Api::search($q));
        $r->get('/admin/api/ac', fn ($q) => Api::autocomplete($q));
        $r->get('/admin/api/medias', fn ($q) => Api::media($q));
        $r->post('/admin/api/modeles', fn ($q) => Api::models($q));
        $r->post('/admin/api/traduire', fn ($q) => Api::translate($q));
        $r->post('/admin/api/correcteur', fn ($q) => Api::proofread($q));
        $r->post('/admin/api/correcteur/ignorer', fn ($q) => Api::proofIgnore($q));
        $r->post('/admin/api/correcteur/dictionnaire', fn ($q) => Api::proofWord($q));
        $r->get('/admin/api/couts', fn ($q) => Costs::api($q));
        $r->post('/admin/api/verrou', fn ($q) => Api::lock($q));
        $r->post('/admin/api/audio', fn ($q) => Audio::api($q));

        $res = $r->dispatch(new Request($req->method, $path, $req->query, $req->post, $req->files, $req->server, $req->body));
        if ($res instanceof Response) {
            return $res;
        }
        return Response::html(Base::page('admin/message', ['title' => 'Page introuvable', 'text' => 'Cette page du back-office n’existe pas.', 'back' => '/admin'], ['title' => 'Page introuvable']), 404);
    }

    private static function csrfOk(Request $req): bool
    {
        $t = (string) ($req->post['_csrf'] ?? $req->header('X-CSRF') ?? '');
        return Session::checkCsrf($t);
    }
}
