<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router as CoreRouter;
use App\Core\Session;
use App\Data\Activity;

/**
 * Back-office (/admin) : connexion, contrôle d'accès à deux niveaux, jeton CSRF
 * sur toutes les requêtes POST, puis distribution vers les écrans.
 */
final class Router
{
    /** Adresses réservées aux administrateurs (en plus des comptes et des réglages, contrôlés écran par écran). */
    private const ADMIN_ONLY = '#^/admin/(assistant|audio|couts-ia|sauvegardes|taches|api/couts|dons/recu|statistiques|association|boutique|notifications)(/|$)#';

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
            if (Auth::$expired) {
                Activity::log(Auth::$expired, 'a été déconnecté après ' . Account::idleMinutes() . ' minutes sans activité', null);
            }
            if (str_starts_with($path, '/admin/api/') || $req->wantsJson()) {
                return Response::json(Auth::$expired ? ['error' => 'Déconnecté après ' . Account::idleMinutes() . ' minutes sans activité : reconnectez-vous.', 'idle' => true] : ['error' => 'Session expirée : reconnectez-vous.'], 401);
            }
            return Response::redirect('/admin/connexion?r=' . rawurlencode($req->server['REQUEST_URI'] ?? '/admin'));
        }
        if ($post && str_starts_with($path, '/admin/api/') && !self::csrfOk($req)) {
            return Response::json(['error' => 'Session expirée : rechargez la page.'], 419);
        }
        // Écrans techniques (coûts de l'IA, assistant, traitement audio groupé, sauvegardes,
        // tâches) et reçus fiscaux : administrateurs seulement, comme les comptes et les réglages.
        if (!Auth::isAdmin() && preg_match(self::ADMIN_ONLY, $path)) {
            return str_starts_with($path, '/admin/api/') ? Response::json(['error' => 'Réservé aux administrateurs.'], 403) : (Base::denyUnlessAdmin() ?? Response::redirect('/admin'));
        }

        $r = new CoreRouter();
        $r->post('/admin/deconnexion', fn ($q) => Account::logout($q));
        // Inactivité : activité signalée par le navigateur (saisie sans enregistrement), déconnexion au bout du délai
        $r->post('/admin/api/actif', fn ($q) => Account::stillHere($q));
        $r->post('/admin/api/inactif', fn ($q) => Account::idleLogout($q));
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
        $r->post('/admin/qualite/controler', fn ($q) => Dashboard::control($q));
        $r->post('/admin/qualite/orthographe-tout', fn ($q) => Dashboard::proofAll($q));
        $r->get('/admin/journal', fn ($q) => Dashboard::journal($q));
        $r->get('/admin/audience', fn ($q) => Response::redirect('/admin/statistiques'));
        $r->get('/admin/statistiques', fn ($q) => Statistics::index($q));
        $r->get('/admin/statistiques/direct', fn ($q) => Statistics::live($q));
        $r->get('/admin/statistiques/rapport.pdf', fn ($q) => Statistics::pdf($q));
        $r->post('/admin/statistiques/remise-a-zero', fn ($q) => Statistics::reset($q));

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
        $r->post('/admin/fiche/{id}/qualite-zero', fn ($q, $id) => ctype_digit($id) ? Fiches::qualityReset($q, (int) $id) : null);
        $r->post('/admin/fiche/{id}/qualite-reafficher', fn ($q, $id) => ctype_digit($id) ? Fiches::qualityReopen($q, (int) $id) : null);
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
        $r->post('/admin/moments/date', fn ($q) => Editorial::momentsDate($q));
        $r->get('/admin/moments/idees', fn ($q) => MomentIdeas::index($q));
        $r->post('/admin/moments/idees', fn ($q) => MomentIdeas::action($q));
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
        $r->get('/admin/retro-direct', fn ($q) => Retro::index($q));
        $r->post('/admin/retro-direct', fn ($q) => Retro::action($q));
        $r->get('/admin/quiz-club-house', fn ($q) => QuizClub::index($q));
        $r->post('/admin/quiz-club-house', fn ($q) => QuizClub::action($q));
        $r->get('/admin/souvenirs', fn ($q) => Kit::index($q));
        $r->post('/admin/souvenirs', fn ($q) => Kit::save($q));
        // Boutique (administrateurs seulement : voir ADMIN_ONLY)
        $r->get('/admin/boutique', fn ($q) => Shop::index($q));
        $r->get('/admin/boutique/supports', fn ($q) => Shop::supports($q));
        $r->post('/admin/boutique/supports', fn ($q) => Shop::saveSupport($q));
        $r->post('/admin/boutique/rapprochement', fn ($q) => Shop::reconcile($q));
        $r->get('/admin/boutique/promos', fn ($q) => Shop::promos($q));
        $r->post('/admin/boutique/promos', fn ($q) => Shop::savePromo($q));
        $r->get('/admin/boutique/releves', fn ($q) => Shop::statements($q));
        $r->get('/admin/boutique/releves/pdf', fn ($q) => Shop::statementFile($q, 'pdf'));
        $r->get('/admin/boutique/releves/csv', fn ($q) => Shop::statementFile($q, 'csv'));
        $r->get('/admin/boutique/reglages', fn ($q) => Shop::settings($q));
        $r->post('/admin/boutique/reglages', fn ($q) => Shop::saveSettings($q));
        $r->get('/admin/boutique/commandes', fn ($q) => Shop::orders($q));
        $r->get('/admin/boutique/commandes/{id}/pdf/{n}', fn ($q, $id, $n) => Shop::orderPdf($q, $id, $n));
        $r->get('/admin/boutique/commandes/{id}', fn ($q, $id) => Shop::order($q, $id));
        $r->post('/admin/boutique/commandes/{id}', fn ($q, $id) => Shop::orderAction($q, $id));
        $r->get('/admin/boutique/textes', fn ($q) => Shop::texts($q));
        $r->post('/admin/boutique/textes', fn ($q) => Shop::textsAction($q));
        $r->get('/admin/boutique/modeles', fn ($q) => Shop::models($q));
        $r->post('/admin/boutique/modeles', fn ($q) => Shop::modelAction($q));
        $r->post('/admin/boutique/modeles/ordre', fn ($q) => Shop::modelOrder($q));
        $r->post('/admin/boutique/apercu', fn ($q) => Shop::preview($q));
        $r->get('/admin/boutique/modeles/{id}/pdf', fn ($q, $id) => Shop::pdf($q, $id));
        $r->get('/admin/boutique/modeles/{id}', fn ($q, $id) => Shop::editor($q, $id));
        $r->post('/admin/boutique/modeles/{id}', fn ($q, $id) => Shop::saveModel($q, $id));
        $r->get('/admin/murs-photos', fn ($q) => PhotoWalls::index($q));
        $r->post('/admin/murs-photos', fn ($q) => PhotoWalls::save($q));

        // Communauté
        $r->get('/admin/contributions', fn ($q) => Community::contributions($q));
        $r->get('/admin/contributions/{ticket}', fn ($q, $ticket) => Community::contribution($q, $ticket));
        $r->post('/admin/contributions/{ticket}', fn ($q, $ticket) => Community::contributionAction($q, $ticket));
        $r->get('/admin/contributions/{ticket}/fichier/{n}', fn ($q, $ticket, $n) => Community::contributionFile($q, $ticket, (int) $n));
        $r->get('/admin/messages', fn ($q) => Community::messages($q));
        $r->get('/admin/messages/{id}', fn ($q, $id) => Community::message($q, $id));
        $r->post('/admin/messages/{id}', fn ($q, $id) => Community::messageAction($q, $id));
        $r->get('/admin/notifications', fn ($q) => Push::index($q));
        $r->get('/admin/carnets', fn ($q) => Push::carnets($q));
        $r->post('/admin/notifications', fn ($q) => Push::action($q));
        $r->get('/admin/newsletter', fn ($q) => Community::newsletter($q));
        $r->post('/admin/newsletter', fn ($q) => Community::newsletterAction($q));
        $r->get('/admin/newsletter/apercu', fn ($q) => Community::newsletterPreview($q));
        $r->get('/admin/dons', fn ($q) => Donations::index($q));
        $r->post('/admin/dons', fn ($q) => Donations::action($q));
        $r->get('/admin/dons/export.csv', fn ($q) => Donations::export($q));
        $r->get('/admin/dons/{id}', fn ($q, $id) => Donations::show($q, $id));
        $r->post('/admin/dons/{id}', fn ($q, $id) => Donations::update($q, $id));
        $r->get('/admin/dons/recu/{num}', fn ($q, $num) => Donations::receipt($q, $num));

        // Site de l'association (administrateurs seulement : voir ADMIN_ONLY)
        $r->get('/admin/association', fn ($q) => Association::index($q));
        $r->post('/admin/association/ouverture', fn ($q) => Association::toggle($q));
        $r->get('/admin/association/attente', fn ($q) => Association::waitingEdit($q));
        $r->get('/admin/association/contenus', fn ($q) => Response::redirect('/admin/association/contenus/pages'));
        $r->get('/admin/association/contenus/{name}', fn ($q, $name) => Association::contents($q, $name));
        $r->post('/admin/association/contenus/{name}/depart', fn ($q, $name) => Association::reset($q, $name));
        $r->post('/admin/association/contenus/{name}', fn ($q, $name) => Association::contentsSave($q, $name));
        $r->get('/admin/association/page/{key}', fn ($q, $key) => Association::pageEdit($q, $key));
        $r->post('/admin/association/page/{key}/depart', fn ($q, $key) => Association::reset($q, 'page-' . $key));
        $r->post('/admin/association/page/{key}', fn ($q, $key) => Association::pageSave($q, $key));
        $r->post('/admin/association/documents/envoi', fn ($q) => Association::upload($q));
        $r->get('/admin/association/adhesions', fn ($q) => Association::memberships($q));
        $r->post('/admin/association/adhesions', fn ($q) => Association::membershipAdd($q));
        $r->get('/admin/association/adhesions/export.csv', fn ($q) => Association::membershipsExport($q));
        $r->get('/admin/association/adhesions/{id}', fn ($q, $id) => Association::membership($q, $id));
        $r->post('/admin/association/adhesions/{id}', fn ($q, $id) => Association::membershipAction($q, $id));
        $r->get('/admin/association/benevoles', fn ($q) => Association::volunteers($q));
        $r->post('/admin/association/benevoles', fn ($q) => Association::volunteersAction($q));
        $r->get('/admin/association/benevoles/export.csv', fn ($q) => Association::volunteersExport($q));
        $r->get('/admin/association/reglages', fn ($q) => Association::settings($q));

        // Système
        $r->get('/admin/traductions', fn ($q) => System::translations($q));
        $r->post('/admin/traductions', fn ($q) => System::translationsSave($q));
        $r->get('/admin/assistant', fn ($q) => System::assistant($q));
        $r->post('/admin/assistant', fn ($q) => System::assistantAction($q));
        $r->get('/admin/assistant/export.csv', fn ($q) => System::assistantExport($q));
        $r->get('/admin/trouvailles', fn ($q) => Finds::index($q));
        $r->post('/admin/trouvailles', fn ($q) => Finds::action($q));
        $r->get('/admin/archives', fn ($q) => Archives::index($q));
        $r->post('/admin/archives', fn ($q) => Archives::action($q));
        $r->get('/admin/livre', fn ($q) => Book::index($q));
        $r->get('/admin/maillots', fn ($q) => Jerseys::index($q));
        $r->post('/admin/maillots', fn ($q) => Jerseys::action($q));
        $r->post('/admin/livre', fn ($q) => Book::build($q));
        $r->get('/admin/reprise-1928-1969', fn ($q) => Heritage::index($q));
        $r->post('/admin/reprise-1928-1969', fn ($q) => Heritage::action($q));
        $r->get('/admin/import-feuilles', fn ($q) => Sheets::index($q));
        $r->post('/admin/import-feuilles', fn ($q) => Sheets::action($q));
        $r->get('/admin/mises-a-jour', fn ($q) => Updates::index($q));
        $r->post('/admin/mises-a-jour', fn ($q) => Updates::action($q));
        $r->get('/admin/audio', fn ($q) => Audio::index($q));
        $r->post('/admin/audio', fn ($q) => Audio::action($q));
        $r->get('/admin/audio/telecharger', fn ($q) => Audio::download($q));
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
        $r->post('/admin/reglages/vider-pages', fn ($q) => System::purgePages($q));
        $r->post('/admin/reglages/test-email', fn ($q) => System::testEmail($q));
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
        $r->post('/admin/api/recherche-web', fn ($q) => Api::webCheck($q));
        $r->get('/admin/api/couts', fn ($q) => Costs::api($q));
        $r->post('/admin/api/verrou', fn ($q) => Api::lock($q));
        $r->post('/admin/api/audio', fn ($q) => Audio::api($q));
        $r->post('/admin/api/favoris', fn ($q) => Favorites::api($q));

        $res = $r->dispatch(new Request($req->method, $path, $req->query, $req->post, $req->files, $req->server, $req->body));
        if ($res instanceof Response) {
            return $res;
        }
        return Response::html(Base::page('admin/message', ['title' => 'Page introuvable', 'text' => 'Cette page du back-office n’existe pas.', 'back' => '/admin'], ['title' => 'Page introuvable']), 404);
    }

    /** Écran réservé aux administrateurs (en plus de ceux qui le vérifient eux-mêmes : comptes, réglages…). */
    public static function adminOnly(string $path): bool
    {
        return (bool) preg_match(self::ADMIN_ONLY, $path);
    }

    private static function csrfOk(Request $req): bool
    {
        $t = (string) ($req->post['_csrf'] ?? $req->header('X-CSRF') ?? '');
        return Session::checkCsrf($t);
    }
}
