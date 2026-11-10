<?php

declare(strict_types=1);

/**
 * Back-office (/admin) : pages HTML + API JSON (/admin/api/…).
 * Toutes les modifications passent par l'API (fetch + jeton CSRF), enregistrées en JSON.
 */
final class Admin
{
    /**
     * Menu latéral : [slug, libellé, rôle requis] groupés par rubrique. Réservés aux administrateurs :
     * toute la rubrique « Réglages », « Avis clients » (avis Google automatiques, clé API) et « Conversion »
     * (barre CTA, bot, popup de sortie, vocal, consignes Gemini).
     */
    public const NAV = [
        'Suivi' => [['', 'Tableau de bord'], ['demandes', 'Demandes'], ['assistant/conversations', 'Conversations du bot']],
        'Contenus' => [['accueil', 'Page d\'accueil'], ['pages', 'Pages'], ['produits', 'Produits'], ['photos', 'Photos produits'], ['realisations', 'Réalisations'], ['actus', 'Actualités'], ['avis', 'Avis clients', 'admin'], ['medias', 'Médiathèque']],
        'Site' => [['menu', 'Menu'], ['coordonnees', 'Coordonnées'], ['conversion', 'Conversion', 'admin']],
        'Réglages' => [['assistant', 'Assistant IA', 'admin'], ['facebook', 'Facebook', 'admin'], ['emails', 'E-mails', 'admin'], ['utilisateurs', 'Utilisateurs', 'admin'], ['mises-a-jour', 'Mises à jour', 'admin'], ['sauvegarde', 'Sauvegarde', 'admin']],
    ];

    public static function route(string $path, string $method): void
    {
        security_headers(true);
        $sub = trim(substr($path, 6), '/'); // après « /admin »
        if (str_starts_with($sub, 'api/')) {
            self::api(substr($sub, 4), $method);
            return;
        }
        if ($method !== 'GET' && $method !== 'HEAD') {
            Http::error('Méthode non autorisée', 405);
        }
        // Pages accessibles sans être connecté
        if (!Auth::hasUsers() && $sub !== 'installation') {
            Http::redirect('/admin/installation');
        }
        switch ($sub) {
            case 'installation':
                if (Auth::hasUsers()) {
                    Http::redirect('/admin');
                }
                Auth::setupKey();
                self::bare('admin/setup', 'Installation');
                return;
            case 'connexion':
                if (Auth::user()) {
                    Http::redirect('/admin');
                }
                self::bare('admin/login', 'Connexion');
                return;
            case 'deconnexion':
                Auth::logout();
                Http::redirect('/admin/connexion');
            case 'mot-de-passe-oublie':
                self::bare('admin/forgot', 'Mot de passe oublié');
                return;
            case 'nouveau-mot-de-passe':
                self::bare('admin/reset', 'Nouveau mot de passe', ['user' => Auth::checkResetToken((string) ($_GET['t'] ?? ''))]);
                return;
        }
        $u = Auth::user();
        if (!$u) {
            Http::redirect('/admin/connexion');
        }
        if (maintenance()) {
            self::page('admin/notice', 'Mise à jour en cours', ['message' => 'Une mise à jour est en cours d\'installation. Réessayez dans une minute.']);
            return;
        }
        $parts = explode('/', $sub);
        // Rubrique « Réglages » (assistant IA, Facebook, e-mails, comptes, mises à jour, sauvegarde), « Avis clients »
        // (avis Google, clé API) et « Conversion » : administrateurs uniquement. Les conversations du bot (Suivi) restent ouvertes aux éditeurs.
        $adminOnly = ['avis', 'conversion', 'assistant', 'facebook', 'emails', 'utilisateurs', 'mises-a-jour', 'sauvegarde', 'sauvegarde.json'];
        $isSettings = in_array($parts[0], $adminOnly, true) && !($parts[0] === 'assistant' && ($parts[1] ?? '') === 'conversations');
        if ($isSettings && !Auth::isAdmin($u)) {
            self::page('admin/notice', 'Accès réservé', ['message' => 'Cette rubrique est réservée aux administrateurs.']);
            return;
        }
        switch ($parts[0]) {
            case '':
                self::page('admin/dashboard', 'Tableau de bord');
                return;
            case 'demandes':
                self::page('admin/leads', 'Demandes', ['leads' => Leads::all()]);
                return;
            case 'demandes.csv':
                self::leadsCsv();
                return;
            case 'vocal':
                self::voiceFile((string) ($parts[1] ?? ''));
                return;
            case 'accueil':
                self::formPage('Page d\'accueil', 'home.save', AdminSchemas::home(), Content::page('home'), 'Textes, chiffres et sections de la page d\'accueil.', '/');
                return;
            case 'pages':
                if (isset($parts[1])) {
                    $key = $parts[1];
                    if (!isset(Content::pages()[$key]) || $key === 'home') {
                        Http::redirect('/admin/pages');
                    }
                    self::formPage('Page « ' . (Content::page($key)['title'] ?? $key) . ' »', 'page.save', AdminSchemas::page($key), Content::page($key), '', Content::path($key), ['key' => $key]);
                    return;
                }
                self::page('admin/pages', 'Pages');
                return;
            case 'produits':
                if (isset($parts[1])) {
                    $p = $parts[1] === 'nouveau' ? ['id' => '', 'visible' => true, 'order' => 99] : Content::product($parts[1]);
                    if (!$p) {
                        Http::redirect('/admin/produits');
                    }
                    self::formPage($p['id'] === '' ? 'Nouveau produit' : 'Produit « ' . $p['title'] . ' »', 'product.save', AdminSchemas::product(), $p, '', $p['path'] ?? '', ['id' => $p['id']], $p['id'] !== '' ? 'product.delete' : '');
                    return;
                }
                self::page('admin/products', 'Produits');
                return;
            case 'photos':
                $g = Store::get('gallery');
                self::formPage('Photos produits', 'gallery.save', AdminSchemas::gallery(), $g, 'Galerie affichée sur la page « Nos produits » et sur chaque page produit, filtrée par gamme.', Content::path('produits'));
                return;
            case 'realisations':
                self::formPage('Réalisations', 'realisations.save', AdminSchemas::realisations(), ['items' => Store::get('realisations')], 'Photos de chantiers de la page « Nos réalisations ». Titre et lieu sont facultatifs.', Content::path('realisations'));
                return;
            case 'actus':
                if (isset($parts[1])) {
                    $p = $parts[1] === 'nouveau' ? ['id' => '', 'date' => date('Y-m-d'), 'visible' => true, 'cat' => Content::categories()[0] ?? '', 'images' => [], 'source' => 'site'] : Content::post($parts[1]);
                    if (!$p) {
                        Http::redirect('/admin/actus');
                    }
                    self::formPage($p['id'] === '' ? 'Nouvelle actualité' : 'Actualité', 'post.save', AdminSchemas::post(), $p, ($p['source'] ?? '') === 'facebook' ? 'Publication importée de Facebook : elle s\'affiche mélangée aux actus du site, sans distinction.' : '', $p['path'] ?? '', ['id' => $p['id']], $p['id'] !== '' ? 'post.delete' : '');
                    return;
                }
                self::page('admin/posts', 'Actualités');
                return;
            case 'facebook':
                self::formPage('Facebook', 'facebook.save', AdminSchemas::facebook(), Settings::all(), 'Synchronisation de la page Facebook avec les actualités du site.', '', [], '', 'admin/facebook_extra');
                return;
            case 'avis':
                self::formPage('Avis clients', 'reviews.save', AdminSchemas::reviews(), ['items' => Store::get('reviews'), 'rating_value' => Content::site()['rating_value'] ?? '', 'rating_count' => Content::site()['rating_count'] ?? '', 'rating_source' => Content::site()['rating_source'] ?? 'avis Google', 'reviews_url' => Content::site()['reviews_url'] ?? ''], 'Avis saisis à la main (repris de renovest.fr). Avec la fiche Google connectée ci-dessus, ils complètent les avis Google ou sont remplacés par eux, selon le mode choisi.', Content::path('avis'), [], '', '', 'admin/google_reviews');
                return;
            case 'medias':
                self::page('admin/media', 'Médiathèque');
                return;
            case 'menu':
                self::formPage('Menu', 'menu.save', AdminSchemas::menu(), ['items' => Store::get('menu')], 'Libellés, ordre et visibilité des entrées du menu principal. Les produits forment le sous-menu de « Nos produits ».', '/');
                return;
            case 'coordonnees':
                self::formPage('Coordonnées', 'site.save', AdminSchemas::site(), Content::site(), 'Téléphones, agences, réseaux sociaux et zone d\'intervention (en-tête, pied de page, contact, assistant).', Content::path('contact'));
                return;
            case 'conversion':
                self::formPage('Conversion', 'ui.save', AdminSchemas::ui(), ['voice' => Voice::cfg()] + Content::ui(), 'Bandeau supérieur, barre CTA, bulle d\'accroche, assistant, popup de sortie, formulaire de devis, curseur.', '/');
                return;
            case 'assistant':
                if (($parts[1] ?? '') === 'conversations') {
                    self::page('admin/botlog', 'Conversations du bot');
                    return;
                }
                $vals = Settings::all();
                self::formPage('Assistant IA', 'bot.save', AdminSchemas::bot(), $vals, 'Réponses du bot branchées sur Gemini (Google). Sans clé, le bot utilise les réponses rapides de la rubrique Conversion.', '', [], '', 'admin/bot_extra');
                return;
            case 'emails':
                self::formPage('E-mails', 'mail.save', AdminSchemas::mail(), Settings::all(), 'Destinataires des demandes et envoi des e-mails.', '', [], '', 'admin/mail_extra');
                return;
            case 'utilisateurs':
                self::page('admin/users', 'Utilisateurs');
                return;
            case 'mises-a-jour':
                $check = Updater::hasToken() ? Updater::check() : (Updater::state()['check'] ?? null);
                self::page('admin/updates', 'Mises à jour', ['check' => $check]);
                return;
            case 'sauvegarde':
                self::page('admin/backup', 'Sauvegarde');
                return;
            case 'sauvegarde.json':
                self::backupDownload();
                return;
            case 'compte':
                self::page('admin/account', 'Mon compte');
                return;
        }
        http_response_code(404);
        self::page('admin/notice', 'Page introuvable', ['message' => 'Cette page du back-office n\'existe pas.']);
    }

    // ------------------------------------------------------------------ rendu

    public static function page(string $tpl, string $title, array $vars = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $content = View::fetch($tpl, $vars + ['title' => $title]);
        View::render('admin/layout', ['content' => $content, 'title' => $title, 'user' => Auth::user()]);
    }

    private static function bare(string $tpl, string $title, array $vars = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $content = View::fetch($tpl, $vars + ['title' => $title]);
        View::render('admin/bare', ['content' => $content, 'title' => $title]);
    }

    private static function formPage(string $title, string $action, array $schema, array $values, string $intro = '', string $view = '', array $hidden = [], string $deleteAction = '', string $extra = '', string $before = ''): void
    {
        self::page('admin/form', $title, compact('action', 'schema', 'values', 'intro', 'view', 'hidden', 'deleteAction', 'extra', 'before'));
    }

    public static function navActive(string $slug): bool
    {
        $cur = trim(substr(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)), 6), '/');
        if ($slug === '') {
            return $cur === '';
        }
        if ($slug === 'assistant') {
            return $cur === 'assistant';
        }
        return $cur === $slug || str_starts_with($cur, $slug . '/');
    }

    // ------------------------------------------------------------------ API JSON

    private static function api(string $action, string $method): void
    {
        if ($method !== 'POST') {
            Http::error('Méthode non autorisée', 405);
        }
        $in = $_POST;
        $data = is_array($in['data'] ?? null) ? $in['data'] : [];

        // Actions sans session
        switch ($action) {
            case 'login':
                self::apiLogin($in);
                return;
            case 'setup':
                self::apiSetup($in);
                return;
            case 'forgot':
                self::apiForgot($in);
                return;
            case 'reset':
                self::apiReset($in);
                return;
        }
        $u = Auth::user();
        if (!$u) {
            Http::error('Session expirée : reconnectez-vous.', 401, ['redirect' => '/admin/connexion']);
        }
        Auth::checkCsrf();
        if (maintenance() && !str_starts_with($action, 'update.')) {
            Http::error('Mise à jour en cours : réessayez dans une minute.', 503);
        }
        $adminOnly = ['user.save', 'user.delete', 'update.check', 'update.apply', 'update.rollback', 'update.settings', 'backup.restore', 'content.reset',
            'bot.save', 'bot.models', 'bot.test', 'facebook.save', 'mail.save', 'mail.test',
            'ui.save', 'reviews.save', 'google.save', 'google.search', 'google.select', 'google.refresh', 'google.disconnect'];
        if (in_array($action, $adminOnly, true) && !Auth::isAdmin($u)) {
            Http::error('Action réservée aux administrateurs.', 403);
        }
        $by = (string) ($u['name'] ?: $u['email']);

        switch ($action) {
            // ---------------------------------------------------------- demandes
            case 'lead.update':
                $id = (string) ($in['id'] ?? '');
                $lead = Leads::find($id);
                if (!$lead) {
                    Http::error('Demande introuvable', 404);
                }
                $changes = [];
                if (isset($in['status']) && isset(Leads::STATUS[$in['status']])) {
                    $changes['status'] = $in['status'];
                }
                if (trim((string) ($in['note'] ?? '')) !== '') {
                    $changes['notes'] = array_merge($lead['notes'] ?? [], [['at' => date('c'), 'by' => $by, 'text' => Sanitizer::text($in['note'], 2000)]]);
                }
                Leads::update($id, $changes);
                Http::json(['ok' => true, 'message' => 'Demande mise à jour.', 'reload' => true]);
            case 'lead.delete':
                Leads::delete((string) ($in['id'] ?? ''));
                Http::json(['ok' => true, 'message' => 'Demande supprimée.', 'redirect' => '/admin/demandes']);

            // ---------------------------------------------------------- contenus
            case 'home.save':
                $pages = Store::get('pages');
                $pages['home'] = Form::collect(AdminSchemas::home(), $data, $pages['home']);
                Store::put('pages', $pages);
                Http::json(['ok' => true, 'message' => 'Page d\'accueil enregistrée.']);
            case 'page.save':
                $key = (string) ($in['key'] ?? '');
                $pages = Store::get('pages');
                if (!isset($pages[$key]) || $key === 'home') {
                    Http::error('Page inconnue', 404);
                }
                $new = Form::collect(AdminSchemas::page($key), $data, $pages[$key]);
                self::checkPath($new['path'] ?? '/', 'page:' . $key);
                $pages[$key] = $new;
                Store::put('pages', $pages);
                Http::json(['ok' => true, 'message' => 'Page enregistrée.']);
            case 'product.save':
                self::saveProduct((string) ($in['id'] ?? ''), $data);
                return;
            case 'product.delete':
                $id = (string) ($in['id'] ?? '');
                Store::update('products', fn($all) => array_values(array_filter($all, fn($p) => $p['id'] !== $id)));
                Http::json(['ok' => true, 'message' => 'Produit supprimé.', 'redirect' => '/admin/produits']);
            case 'product.order':
                $ids = array_values(array_filter((array) ($in['ids'] ?? []), 'is_string'));
                Store::update('products', function ($all) use ($ids) {
                    foreach ($all as &$p) {
                        $i = array_search($p['id'], $ids, true);
                        $p['order'] = $i === false ? 99 : $i;
                    }
                    return $all;
                });
                Http::json(['ok' => true, 'message' => 'Ordre enregistré.']);
            case 'gallery.save':
                $g = Store::get('gallery');
                $g = Form::collect(AdminSchemas::gallery(), $data, $g);
                Store::put('gallery', $g);
                Http::json(['ok' => true, 'message' => 'Galerie enregistrée (' . count($g['items']) . ' photos).']);
            case 'realisations.save':
                $r = Form::collect(AdminSchemas::realisations(), $data, []);
                Store::put('realisations', $r['items'] ?? []);
                Http::json(['ok' => true, 'message' => 'Réalisations enregistrées (' . count($r['items'] ?? []) . ' photos).']);
            case 'post.save':
                self::savePost((string) ($in['id'] ?? ''), $data, $by);
                return;
            case 'post.delete':
                $id = (string) ($in['id'] ?? '');
                Store::update('posts', function ($all) use ($id) {
                    foreach ($all as $p) {
                        if ($p['id'] === $id && ($p['source'] ?? '') === 'facebook') {
                            // Publication Facebook supprimée : elle ne sera pas réimportée
                            Store::update('fb_ignored', fn($ig) => array_values(array_unique(array_merge($ig, [(string) ($p['fb_id'] ?? '')]))));
                        }
                    }
                    return array_values(array_filter($all, fn($p) => $p['id'] !== $id));
                });
                Http::json(['ok' => true, 'message' => 'Actualité supprimée.', 'redirect' => '/admin/actus']);
            case 'reviews.save':
                $r = Form::collect(AdminSchemas::reviews(), $data, []);
                Store::put('reviews', $r['items'] ?? []);
                Store::update('site', fn($s) => array_replace($s, ['rating_value' => $r['rating_value'] ?? '', 'rating_count' => $r['rating_count'] ?? '', 'rating_source' => $r['rating_source'] ?? 'avis Google', 'reviews_url' => $r['reviews_url'] ?? '']));
                Http::json(['ok' => true, 'message' => 'Avis enregistrés.']);
            case 'menu.save':
                $m = Form::collect(AdminSchemas::menu(), $data, []);
                $items = [];
                foreach ($m['items'] ?? [] as $it) {
                    if (isset(Content::pages()[$it['key']])) {
                        $items[] = ['key' => $it['key'], 'label' => $it['label'] ?: Content::page($it['key'])['title'], 'visible' => !empty($it['visible'])] + ($it['key'] === 'produits' ? ['children' => 'products'] : []);
                    }
                }
                Store::put('menu', $items);
                Http::json(['ok' => true, 'message' => 'Menu enregistré.']);
            case 'site.save':
                Store::put('site', Form::collect(AdminSchemas::site(), $data, Content::site()));
                Http::json(['ok' => true, 'message' => 'Coordonnées enregistrées.']);
            case 'ui.save':
                Store::put('ui', Form::collect(AdminSchemas::ui(), $data, Content::ui()));
                Http::json(['ok' => true, 'message' => 'Réglages de conversion enregistrés.']);
            case 'content.reset':
                $name = (string) ($in['name'] ?? '');
                if (!in_array($name, ['site', 'pages', 'products', 'gallery', 'realisations', 'posts', 'reviews', 'menu', 'ui', 'devis'], true) || !Store::reset($name)) {
                    Http::error('Contenu inconnu', 404);
                }
                Http::json(['ok' => true, 'message' => 'Contenu « ' . $name . ' » remis à sa version d\'origine.', 'reload' => true]);

            // ---------------------------------------------------------- médias
            case 'media.list':
                Http::json(['ok' => true, 'items' => Media::list((string) ($in['folder'] ?? 'tout'))]);
            case 'media.upload':
                $files = Media::normalizeFiles($_FILES['files'] ?? $_FILES['file'] ?? []);
                if (!$files) {
                    Http::error('Aucun fichier reçu (taille maximale du serveur : ' . ini_get('upload_max_filesize') . ').', 422);
                }
                $out = [];
                $errors = [];
                foreach ($files as $f) {
                    try {
                        $out[] = Media::upload($f);
                    } catch (Throwable $e) {
                        $errors[] = $f['name'] . ' : ' . $e->getMessage();
                    }
                }
                if (!$out) {
                    Http::error(implode(' ; ', $errors), 422);
                }
                Http::json(['ok' => true, 'items' => $out, 'message' => count($out) . ' fichier(s) ajouté(s).' . ($errors ? ' Refusés : ' . implode(' ; ', $errors) : '')]);
            case 'media.delete':
                Media::delete((string) ($in['path'] ?? ''));
                Http::json(['ok' => true, 'message' => 'Fichier supprimé.']);

            // ---------------------------------------------------------- réglages
            case 'bot.save':
                Settings::set(Form::collect(AdminSchemas::bot(), $data, []));
                self::secrets($data, ['gemini_key']);
                if (Settings::hasSecret('gemini_key')) {
                    try {
                        Gemini::models(Settings::secret('gemini_key'));
                    } catch (Throwable $e) {
                        Http::json(['ok' => true, 'message' => 'Assistant enregistré, mais la clé n\'a pas pu être vérifiée : ' . $e->getMessage(), 'reload' => true]);
                    }
                }
                Http::json(['ok' => true, 'message' => 'Assistant enregistré.', 'reload' => true]);
            case 'bot.models':
                // Liste dynamique des modèles : clé en cours de saisie, sinon clé enregistrée
                $key = trim((string) ($in['key'] ?? '')) ?: Settings::secret('gemini_key');
                if ($key === '') {
                    Http::error('Saisissez d\'abord la clé API Gemini.', 422);
                }
                try {
                    $models = Gemini::models($key, !empty($in['force']));
                } catch (Throwable $e) {
                    Http::error($e->getMessage(), 422);
                }
                Http::json(['ok' => true, 'models' => $models, 'recommended' => Gemini::recommended($models), 'message' => count($models) . ' modèle(s) Gemini disponible(s) pour cette clé.']);
            case 'bot.test':
                $r = Bot::reply([['role' => 'user', 'text' => (string) ($in['q'] ?? 'Bonjour, que proposez-vous ?')]], 'test' . bin2hex(random_bytes(4)));
                if ($r['source'] === 'ia') {
                    Http::json(['ok' => true, 'message' => 'Réponse de Gemini (' . Bot::model() . ') : ' . $r['text']]);
                }
                Http::json(['ok' => true, 'message' => (Bot::aiReady() ? 'Gemini n\'a pas répondu' . (!empty($r['error']) ? ' (' . $r['error'] . ')' : '') . '. ' : 'Pas de clé Gemini : ') . 'réponse automatique : ' . $r['text']]);
            case 'botlog.clear':
                Store::put('botlog', []);
                Http::json(['ok' => true, 'message' => 'Journal vidé.', 'reload' => true]);
            case 'facebook.save':
                Settings::set(Form::collect(AdminSchemas::facebook(), $data, []));
                self::secrets($data, ['fb_token']);
                Http::json(['ok' => true, 'message' => 'Réglages Facebook enregistrés.', 'reload' => true]);
            case 'facebook.sync':
                try {
                    $r = Facebook::sync();
                } catch (Throwable $e) {
                    Http::error($e->getMessage(), 422);
                }
                Http::json(['ok' => true, 'message' => $r['added'] . ' publication(s) importée(s), ' . $r['updated'] . ' mise(s) à jour.', 'reload' => true]);
            case 'google.save':
                self::secrets($data, ['google_places_key']);
                $mode = in_array($data['google_reviews_mode'] ?? '', ['mix', 'google', 'manual'], true) ? $data['google_reviews_mode'] : 'mix';
                Settings::set(['google_reviews_mode' => $mode, 'google_min_rating' => max(1, min(5, (int) ($data['google_min_rating'] ?? 4)))]);
                Http::json(['ok' => true, 'message' => 'Réglages des avis Google enregistrés.', 'reload' => true]);
            case 'google.search':
                $key = trim((string) ($in['key'] ?? '')) ?: GoogleReviews::key();
                if ($key === '') {
                    Http::error('Saisissez d\'abord la clé API Google.', 422);
                }
                $r = GoogleReviews::search((string) ($in['q'] ?? ''), $key);
                if (isset($r['error'])) {
                    Http::error($r['error'], 422);
                }
                Http::json(['ok' => true, 'query' => $r['query'] ?? '', 'results' => $r['results']]);
            case 'google.select':
                $pid = (string) ($in['place_id'] ?? '');
                if (!preg_match('/^[A-Za-z0-9_-]{10,300}$/', $pid)) {
                    Http::error('Fiche Google invalide.', 422);
                }
                if (trim((string) ($in['key'] ?? '')) !== '') {
                    Settings::setSecret('google_places_key', trim((string) $in['key']));
                }
                Settings::set(['google_place_id' => $pid]);
                $d = GoogleReviews::refresh(true);
                if (empty($d['place_id'])) {
                    Http::error($d['error'] ?? 'Lecture de la fiche impossible.', 422);
                }
                Http::json(['ok' => true, 'message' => 'Fiche « ' . $d['name'] . ' » connectée : ' . ($d['rating'] !== null ? number_format((float) $d['rating'], 1, ',', '') . '/5, ' : '') . $d['count'] . ' avis sur Google, ' . count($d['reviews']) . ' récupéré(s).', 'reload' => true]);
            case 'google.refresh':
                $d = GoogleReviews::refresh(true);
                if (!empty($d['error'])) {
                    Http::error($d['error'], 422);
                }
                Http::json(['ok' => true, 'message' => 'Avis Google mis à jour : ' . count($d['reviews'] ?? []) . ' avis gardés, ' . (int) ($d['count'] ?? 0) . ' sur Google.', 'reload' => true]);
            case 'google.disconnect':
                Settings::set(['google_place_id' => '']);
                Store::put('google_reviews', []);
                Http::json(['ok' => true, 'message' => 'Fiche Google déconnectée : les avis saisis à la main sont affichés.', 'reload' => true]);
            case 'mail.save':
                Settings::set(Form::collect(AdminSchemas::mail(), $data, []));
                self::secrets($data, ['smtp_pass']);
                Http::json(['ok' => true, 'message' => 'Réglages e-mail enregistrés.', 'reload' => true]);
            case 'mail.test':
                $r = Mailer::send((string) Settings::get('notify_email', ''), 'Test d\'envoi — site ' . Content::site()['name'], "Ceci est un e-mail de test envoyé depuis le back-office.\n");
                $r === true ? Http::json(['ok' => true, 'message' => 'E-mail de test envoyé à ' . Settings::get('notify_email') . '.']) : Http::error('Échec : ' . $r, 422);

            // ---------------------------------------------------------- comptes
            case 'user.save':
                self::saveUser($in, $u);
                return;
            case 'user.delete':
                $id = (string) ($in['id'] ?? '');
                if ($id === $u['id']) {
                    Http::error('Vous ne pouvez pas supprimer votre propre compte.', 422);
                }
                Auth::deleteUser($id);
                Http::json(['ok' => true, 'message' => 'Compte supprimé.', 'reload' => true]);
            case 'account.save':
                self::saveAccount($in, $u);
                return;

            // ---------------------------------------------------------- mises à jour
            case 'update.check':
                $c = Updater::check(true);
                if ($c['error']) {
                    Http::error('Vérification impossible : ' . $c['error'], 422);
                }
                Http::json(['ok' => true, 'message' => Updater::available() !== null ? 'Une mise à jour est disponible.' : 'Le site est à jour.', 'reload' => true]);
            case 'update.apply':
                session_write_close();
                try {
                    $r = Updater::apply($by);
                } catch (Throwable $e) {
                    error_log('[mise à jour] ' . $e->getMessage());
                    Http::error($e->getMessage(), 500);
                }
                Http::json(['ok' => true, 'message' => 'Mise à jour installée : ' . count($r['updated']) . ' fichier(s) remplacé(s), ' . count($r['deleted']) . ' supprimé(s).', 'reload' => true]);
            case 'update.rollback':
                session_write_close();
                try {
                    $r = Updater::rollback((string) ($in['backup'] ?? ''), $by);
                } catch (Throwable $e) {
                    Http::error($e->getMessage(), 500);
                }
                Http::json(['ok' => true, 'message' => $r['restored'] . ' fichier(s) remis en place.', 'reload' => true]);
            case 'update.settings':
                $repo = trim((string) ($data['update_repo'] ?? ''));
                $branch = trim((string) ($data['update_branch'] ?? ''));
                if ($repo !== '' && !preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
                    Http::error('Dépôt invalide : « propriétaire/nom ».', 422);
                }
                if ($branch !== '' && (!preg_match('#^[\w.-]+(/[\w.-]+)*$#', $branch) || str_contains($branch, '..'))) {
                    Http::error('Branche invalide.', 422);
                }
                Settings::set(['update_repo' => $repo, 'update_branch' => $branch]);
                self::secrets($data, ['github_token']);
                Http::json(['ok' => true, 'message' => 'Réglages enregistrés.', 'reload' => true]);

            // ---------------------------------------------------------- sauvegarde
            case 'backup.restore':
                self::backupRestore();
                return;
        }
        Http::error('Action inconnue', 404);
    }

    /** Champs secrets d'un formulaire : enregistrés à part, jamais renvoyés au navigateur. */
    private static function secrets(array $data, array $keys): void
    {
        foreach ($keys as $k) {
            if (!empty($data['_clear'][$k])) {
                Settings::setSecret($k, '');
            } elseif (trim((string) ($data[$k] ?? '')) !== '') {
                Settings::setSecret($k, trim((string) $data[$k]));
            }
        }
    }

    private static function checkPath(string $path, string $self): void
    {
        foreach (Content::routes() as $p => [$type, $key]) {
            if ($p === Content::norm($path) && $type . ':' . $key !== $self) {
                Http::error('Cette adresse est déjà utilisée par une autre page.', 422);
            }
        }
        if (preg_match('#^/(admin|api|media|uploads|assets)(/|$)#', $path)) {
            Http::error('Adresse réservée par le site.', 422);
        }
    }

    private static function saveProduct(string $id, array $data): void
    {
        $all = Store::get('products');
        $cur = [];
        foreach ($all as $p) {
            if ($p['id'] === $id) {
                $cur = $p;
            }
        }
        $new = Form::collect(AdminSchemas::product(), $data, $cur);
        if (trim((string) ($new['title'] ?? '')) === '') {
            Http::error('Le titre est obligatoire.', 422);
        }
        if ($id === '') {
            $new['id'] = slugify($new['title']);
            foreach ($all as $p) {
                if ($p['id'] === $new['id']) {
                    $new['id'] .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
                }
            }
            $new['order'] = count($all);
            $new['gallery_cat'] = $new['gallery_cat'] ?? $new['id'];
        }
        if (($new['path'] ?? '/') === '/' || ($new['path'] ?? '') === '') {
            $new['path'] = Content::uniquePath($new['title'], $new['id']);
        }
        self::checkPath($new['path'], 'product:' . $new['id']);
        $found = false;
        foreach ($all as &$p) {
            if ($p['id'] === $new['id']) {
                $p = $new;
                $found = true;
            }
        }
        unset($p);
        if (!$found) {
            $all[] = $new;
            // Nouvelle gamme : filtre ajouté à la galerie photos
            Store::update('gallery', function ($g) use ($new) {
                $ids = array_column($g['filters'] ?? [], 'id');
                if (!in_array($new['gallery_cat'], $ids, true)) {
                    $g['filters'][] = ['id' => $new['gallery_cat'], 'label' => $new['title']];
                }
                return $g;
            });
        }
        Store::put('products', $all);
        Http::json(['ok' => true, 'message' => 'Produit enregistré.', 'redirect' => $id === '' ? '/admin/produits/' . $new['id'] : null]);
    }

    private static function savePost(string $id, array $data, string $by): void
    {
        $all = Store::get('posts');
        $cur = [];
        foreach ($all as $p) {
            if ($p['id'] === $id) {
                $cur = $p;
            }
        }
        $new = Form::collect(AdminSchemas::post(), $data, $cur);
        if (trim((string) ($new['title'] ?? '')) === '') {
            Http::error('Le titre est obligatoire.', 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($new['date'] ?? ''))) {
            $new['date'] = date('Y-m-d');
        }
        $isNew = $id === '';
        if ($isNew) {
            $new['id'] = 'p-' . date('ymd') . '-' . bin2hex(random_bytes(3));
            $new['source'] = 'site';
            $new['created_by'] = $by;
        }
        if (empty($new['path']) || $new['path'] === '/') {
            $new['path'] = Content::uniquePath($new['title'], $new['id']);
        }
        self::checkPath($new['path'], 'post:' . $new['id']);
        $new['updated'] = date('c');
        $publish = !empty($data['_publish_fb']);
        $found = false;
        foreach ($all as &$p) {
            if ($p['id'] === $new['id']) {
                $p = $new;
                $found = true;
            }
        }
        unset($p);
        if (!$found) {
            $all[] = $new;
        }
        Store::put('posts', $all);
        $msg = 'Actualité enregistrée.';
        if ($publish && empty($new['fb_id'])) {
            try {
                $fbId = Facebook::publish($new);
                Store::update('posts', function ($all) use ($new, $fbId) {
                    foreach ($all as &$p) {
                        if ($p['id'] === $new['id']) {
                            $p['fb_id'] = $fbId;
                            $p['fb_published'] = date('c');
                        }
                    }
                    return $all;
                });
                $msg .= ' Publiée aussi sur Facebook.';
            } catch (Throwable $e) {
                $msg .= ' Publication Facebook impossible : ' . $e->getMessage();
            }
        }
        Http::json(['ok' => true, 'message' => $msg, 'redirect' => $isNew ? '/admin/actus/' . $new['id'] : null]);
    }

    private static function saveUser(array $in, array $me): void
    {
        $id = (string) ($in['id'] ?? '');
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        $name = Sanitizer::line($in['name'] ?? '', 80);
        $role = isset(Auth::ROLES[$in['role'] ?? '']) ? $in['role'] : 'editeur';
        $pw = (string) ($in['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Http::error('Adresse e-mail invalide.', 422);
        }
        $other = Auth::findByEmail($email);
        if ($other && $other['id'] !== $id) {
            Http::error('Un compte utilise déjà cette adresse.', 422);
        }
        if ($id === '') {
            if (($err = Auth::passwordError($pw)) !== null) {
                Http::error($err, 422);
            }
            Auth::create($email, $name, $pw, $role);
            Http::json(['ok' => true, 'message' => 'Compte créé.', 'reload' => true]);
        }
        $changes = ['email' => $email, 'name' => $name];
        if ($id !== $me['id']) {
            $changes['role'] = $role;
        }
        if ($pw !== '') {
            if (($err = Auth::passwordError($pw)) !== null) {
                Http::error($err, 422);
            }
            $changes['password'] = $pw;
        }
        Auth::updateUser($id, $changes);
        Http::json(['ok' => true, 'message' => 'Compte mis à jour.', 'reload' => true]);
    }

    private static function saveAccount(array $in, array $u): void
    {
        $full = Auth::find($u['id']);
        if (!password_verify((string) ($in['current'] ?? ''), (string) $full['hash'])) {
            Http::error('Mot de passe actuel incorrect.', 422);
        }
        $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Http::error('Adresse e-mail invalide.', 422);
        }
        $other = Auth::findByEmail($email);
        if ($other && $other['id'] !== $u['id']) {
            Http::error('Un compte utilise déjà cette adresse.', 422);
        }
        $changes = ['email' => $email, 'name' => Sanitizer::line($in['name'] ?? '', 80)];
        $pw = (string) ($in['password'] ?? '');
        if ($pw !== '') {
            if (($err = Auth::passwordError($pw)) !== null) {
                Http::error($err, 422);
            }
            $changes['password'] = $pw;
        }
        Auth::updateUser($u['id'], $changes);
        if ($pw !== '') {
            Auth::login(Auth::find($u['id'])); // les autres sessions sont fermées
        }
        Http::json(['ok' => true, 'message' => 'Compte mis à jour.' . ($pw !== '' ? ' Les autres sessions ont été fermées.' : '')]);
    }

    // ------------------------------------------------------------------ connexion

    private static function apiLogin(array $in): void
    {
        if (!Http::rateLimit('login', 8, 900)) {
            Http::error('Trop de tentatives : réessayez dans 15 minutes.', 429);
        }
        $u = Auth::findByEmail((string) ($in['email'] ?? ''));
        if (!$u || !password_verify((string) ($in['password'] ?? ''), (string) $u['hash'])) {
            usleep(400000);
            Http::error('Identifiants incorrects.', 422);
        }
        if (password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
            Auth::updateUser($u['id'], ['hash' => password_hash((string) $in['password'], PASSWORD_DEFAULT)]);
        }
        Auth::login($u);
        Http::json(['ok' => true, 'redirect' => '/admin']);
    }

    private static function apiSetup(array $in): void
    {
        if (Auth::hasUsers()) {
            Http::error('Le compte administrateur existe déjà.', 409);
        }
        if (!Http::rateLimit('setup', 10, 900)) {
            Http::error('Trop de tentatives.', 429);
        }
        if (!hash_equals(Auth::setupKey(), trim((string) ($in['key'] ?? '')))) {
            Http::error('Clé d\'installation incorrecte.', 422);
        }
        $email = (string) ($in['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Http::error('Adresse e-mail invalide.', 422);
        }
        $pw = (string) ($in['password'] ?? '');
        if (($err = Auth::passwordError($pw)) !== null) {
            Http::error($err, 422);
        }
        $u = Auth::create($email, Sanitizer::line($in['name'] ?? '', 80), $pw, 'admin');
        @unlink(cfg('storage') . '/data/setup-key.txt');
        Auth::login($u);
        Http::json(['ok' => true, 'redirect' => '/admin']);
    }

    private static function apiForgot(array $in): void
    {
        if (!Http::rateLimit('forgot', 4, 3600)) {
            Http::error('Trop de demandes : réessayez plus tard.', 429);
        }
        $u = Auth::findByEmail((string) ($in['email'] ?? ''));
        if ($u) {
            $link = Http::abs('/admin/nouveau-mot-de-passe') . '?t=' . Auth::resetToken($u);
            Mailer::send($u['email'], 'Nouveau mot de passe — back-office ' . Content::site()['name'], "Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 1 heure) :\n$link\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.\n");
        }
        Http::json(['ok' => true, 'message' => 'Si un compte existe pour cette adresse, un e-mail vient d\'être envoyé.']);
    }

    private static function apiReset(array $in): void
    {
        $u = Auth::checkResetToken((string) ($in['t'] ?? ''));
        if (!$u) {
            Http::error('Lien expiré ou déjà utilisé.', 422);
        }
        $pw = (string) ($in['password'] ?? '');
        if (($err = Auth::passwordError($pw)) !== null) {
            Http::error($err, 422);
        }
        Auth::updateUser($u['id'], ['password' => $pw]);
        Auth::login(Auth::find($u['id']));
        Http::json(['ok' => true, 'redirect' => '/admin']);
    }

    /** Écoute d'un message vocal (fichier hors du dossier public, réservé au back-office). */
    private static function voiceFile(string $name): void
    {
        $f = Voice::file($name);
        if ($f === null) {
            http_response_code(404);
            exit;
        }
        $size = (int) filesize($f);
        $start = 0;
        $end = $size - 1;
        header('Content-Type: ' . Voice::mime($name));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=3600');
        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] !== '' ? min((int) $m[2], $size - 1) : $size - 1;
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));
        $fp = fopen($f, 'rb');
        fseek($fp, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($fp)) {
            $chunk = fread($fp, min(65536, $left));
            echo $chunk;
            $left -= strlen((string) $chunk);
        }
        fclose($fp);
        exit;
    }

    // ------------------------------------------------------------------ exports

    private static function leadsCsv(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="demandes-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Type', 'Statut', 'Nom', 'Téléphone', 'E-mail', 'Produits', 'Éléments', 'Échéance', 'Code postal', 'Message', 'Page'], ';');
        // Pas de formule exécutée à l'ouverture dans un tableur (=, +, -, @)
        $cell = fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;
        foreach (Leads::all() as $l) {
            fputcsv($out, array_map($cell, [date('d/m/Y H:i', strtotime($l['at'])), Leads::TYPES[$l['type']] ?? $l['type'], Leads::STATUS[$l['status']] ?? $l['status'],
                $l['nom'] ?? '', $l['tel'] ?? '', $l['email'] ?? '', implode(', ', (array) ($l['types'] ?? [])), $l['qty'] ?? '', $l['delai'] ?? '', $l['cp'] ?? '', $l['msg'] ?? '', $l['page'] ?? '']), ';');
        }
        exit;
    }

    public const BACKUP = ['site', 'pages', 'products', 'gallery', 'realisations', 'posts', 'reviews', 'menu', 'ui', 'devis', 'leads', 'settings'];

    private static function backupDownload(): void
    {
        $data = ['_app' => 'renovest', '_date' => date('c')];
        foreach (self::BACKUP as $n) {
            $data[$n] = Store::get($n, []);
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="renovest-contenu-' . date('Y-m-d-His') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function backupRestore(): void
    {
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > 30 * 1024 * 1024) {
            Http::error('Fichier manquant ou trop volumineux.', 422);
        }
        $j = json_decode((string) file_get_contents($f['tmp_name']), true);
        if (!is_array($j) || ($j['_app'] ?? '') !== 'renovest') {
            Http::error('Ce fichier n\'est pas une sauvegarde du site.', 422);
        }
        $n = 0;
        foreach (self::BACKUP as $name) {
            if (isset($j[$name]) && is_array($j[$name])) {
                Store::put($name, $j[$name]);
                $n++;
            }
        }
        Http::json(['ok' => true, 'message' => $n . ' ensemble(s) de contenu restauré(s).', 'reload' => true]);
    }
}
