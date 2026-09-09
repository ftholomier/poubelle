<?php
declare(strict_types=1);

/**
 * Back-office du iOiO.
 * Un seul point d'entrée : ?screen=… pour l'affichage, POST + action=… pour
 * les écritures. Chaque écriture passe par Store (atomique + versionnée).
 */

require __DIR__ . '/../../app/bootstrap.php';

use App\Admin;
use App\Ai\Docs;
use App\Ai\Gemini;
use App\Ai\Indexer;
use App\Auth;
use App\Config;
use App\Content;
use App\Csrf;
use App\I18n;
use App\Media;
use App\Offices;
use App\Requests;
use App\Reviews;
use App\Router;
use App\Session;
use App\Store;
use App\Text;
use App\Translator;
use App\View;

Session::start();
I18n::setLang(Config::DEFAULT_LANG);
header('X-Robots-Tag: noindex, nofollow');

$screen = (string) ($_GET['screen'] ?? '');
$action = (string) ($_POST['action'] ?? '');
$isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';

/** Redirige vers un écran du back-office puis arrête le script. */
$redirect = static function (string $screen = '', array $query = []): never {
    header('Location: ' . Router::adminUrl($screen, $query));
    exit;
};

$render = static function (string $view, array $data = []) use ($screen): never {
    echo View::admin($view, array_replace(['screen' => $screen], $data));
    exit;
};

// -------------------------------------------------------------- installation

if (!Auth::isInstalled()) {
    if ($isPost && $action === 'install') {
        if (!Csrf::check((string) ($_POST['csrf'] ?? ''), 'install')) {
            $render('install', ['error' => 'Session expirée : rechargez la page.']);
        }
        $result = Auth::createUser(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            'admin',
            (string) ($_POST['name'] ?? '')
        );
        if (!$result['ok']) {
            $render('install', ['error' => $result['error']]);
        }
        if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password2'] ?? '')) {
            Auth::deleteUser((string) ($_POST['email'] ?? ''));
            $render('install', ['error' => 'Les deux mots de passe ne correspondent pas.']);
        }
        Media::importExisting('install');
        Indexer::rebuild();
        Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        Session::flash('Compte créé. Bienvenue dans le back-office du iOiO.');
        $redirect('dash');
    }
    $render('install');
}

// ------------------------------------------------------------ authentification

$user = Auth::user();

if ($isPost && $action === 'login') {
    if (!Csrf::check((string) ($_POST['csrf'] ?? ''), 'login')) {
        $render('login', ['error' => 'Session expirée : rechargez la page.']);
    }
    $result = Auth::attempt(
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        !empty($_POST['remember'])
    );
    if (!$result['ok']) {
        $render('login', ['error' => $result['error'] ?? 'Identifiants incorrects.']);
    }
    $next = (string) ($_GET['next'] ?? '');
    if ($next !== '' && str_starts_with($next, Config::basePath() . '/admin')) {
        header('Location: ' . $next);
        exit;
    }
    $redirect('dash');
}

if ($isPost && $action === 'forgot') {
    if (Csrf::check((string) ($_POST['csrf'] ?? ''), 'forgot')) {
        Auth::startPasswordReset((string) ($_POST['email'] ?? ''));
    }
    // Réponse identique que le compte existe ou non.
    $render('login', ['mode' => 'forgot', 'sent' => true]);
}

if ($isPost && $action === 'reset') {
    $email = (string) ($_POST['email'] ?? '');
    $token = (string) ($_POST['token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (!Csrf::check((string) ($_POST['csrf'] ?? ''), 'reset')) {
        $render('login', ['mode' => 'reset', 'email' => $email, 'token' => $token, 'error' => 'Session expirée : rechargez la page.']);
    }
    if ($password !== (string) ($_POST['password2'] ?? '')) {
        $render('login', ['mode' => 'reset', 'email' => $email, 'token' => $token, 'error' => 'Les deux mots de passe ne correspondent pas.']);
    }
    if (!Auth::finishPasswordReset($email, $token, $password)) {
        $render('login', ['mode' => 'reset', 'email' => $email, 'token' => $token, 'error' => 'Lien expiré ou mot de passe trop court (10 caractères minimum).']);
    }
    Session::flash('Mot de passe mis à jour. Connectez-vous.');
    $redirect();
}

if ($user === null) {
    if ($screen === 'reset' && !$isPost) {
        $render('login', ['mode' => 'reset', 'email' => (string) ($_GET['email'] ?? ''), 'token' => (string) ($_GET['token'] ?? '')]);
    }
    $render('login', ['mode' => $screen === 'forgot' ? 'forgot' : 'login']);
}

$email = (string) $user['email'];

// ------------------------------------------------------------------ écritures

if ($isPost) {
    if (!Csrf::check((string) ($_POST['csrf'] ?? ''), 'admin')) {
        Session::flash('Session expirée : votre navigateur a été déconnecté. Réessayez.', 'error');
        $redirect($screen);
    }

    switch ($action) {
        case 'logout':
            Auth::logout();
            $redirect();

        // ------------------------------------------------------------- pages
        case 'page-save':
            $slug = (string) ($_POST['slug'] ?? 'home');
            $lang = (string) ($_POST['lang'] ?? Config::DEFAULT_LANG);
            $lang = \in_array($lang, Config::LANGS, true) ? $lang : Config::DEFAULT_LANG;
            $lockKey = 'page-' . $slug . '-' . $lang;
            $owner = Store::editLockOwner($lockKey);
            if ($owner !== null && $owner !== $email) {
                Session::flash('Page verrouillée : ' . $owner . ' est en train de l’éditer.', 'error');
                $redirect('pages', ['slug' => $slug, 'lang' => $lang]);
            }
            $file = 'pages/' . $slug . '.' . $lang . '.json';
            $page = Store::read($file);
            if ($page === []) {
                $page = ['_schema' => Config::SCHEMA, 'slug' => $slug, 'lang' => $lang, 'status' => 'draft'];
            }
            $page = Admin::applySchema($page, Admin::pageSchema($slug), (array) ($_POST['f'] ?? []));
            $page['status'] = ($_POST['publish'] ?? '') !== '' ? 'published' : (string) ($_POST['status'] ?? 'draft');
            $page['nav'] = trim((string) ($_POST['nav'] ?? ($page['nav'] ?? Admin::PAGES[$slug] ?? $slug)));
            Store::write($file, $page, $email);
            Store::acquireEditLock($lockKey, $email);
            Indexer::rebuild();
            Session::flash($page['status'] === 'published' ? 'Page publiée en ligne.' : 'Brouillon enregistré.');
            $redirect('pages', ['slug' => $slug, 'lang' => $lang]);

        case 'page-translate':
            $slug = (string) ($_POST['slug'] ?? 'home');
            $target = (string) ($_POST['target'] ?? 'en');
            if (!\in_array($target, Config::LANGS, true) || $target === Config::DEFAULT_LANG) {
                Session::flash('Langue cible invalide.', 'error');
                $redirect('pages', ['slug' => $slug]);
            }
            $source = Store::read('pages/' . $slug . '.' . Config::DEFAULT_LANG . '.json');
            $result = Translator::translateTree($source, $target, Admin::translatableKeys());
            if (!($result['ok'] ?? false)) {
                Session::flash((string) ($result['error'] ?? 'Traduction impossible.'), 'error');
                $redirect('pages', ['slug' => $slug, 'lang' => $target]);
            }
            $tree = $result['tree'];
            $tree['lang'] = $target;
            $tree['status'] = 'draft'; // relecture obligatoire avant publication
            Store::write('pages/' . $slug . '.' . $target . '.json', $tree, $email);
            Session::flash('Traduction écrite en brouillon : relisez-la puis publiez.');
            $redirect('pages', ['slug' => $slug, 'lang' => $target]);

        case 'page-restore':
            $slug = (string) ($_POST['slug'] ?? '');
            $lang = (string) ($_POST['lang'] ?? Config::DEFAULT_LANG);
            $ok = Store::restore('pages/' . $slug . '.' . $lang . '.json', (string) ($_POST['version'] ?? ''), $email);
            Indexer::rebuild();
            Session::flash($ok ? 'Version restaurée.' : 'Version introuvable.', $ok ? 'ok' : 'error');
            $redirect('pages', ['slug' => $slug, 'lang' => $lang]);

        case 'page-lock':
            $slug = (string) ($_POST['slug'] ?? '');
            $lang = (string) ($_POST['lang'] ?? Config::DEFAULT_LANG);
            Store::releaseEditLock('page-' . $slug . '-' . $lang, $email);
            $redirect('pages', ['slug' => $slug, 'lang' => $lang]);

        // ----------------------------------------------------------- bureaux
        case 'office-status':
            $id = (string) ($_POST['id'] ?? '');
            $offices = Offices::all();
            foreach ($offices as $i => $office) {
                if ((string) ($office['id'] ?? '') === $id) {
                    $current = array_search((string) ($office['status'] ?? 'available'), Config::STATUSES, true);
                    $offices[$i]['status'] = Config::STATUSES[(((int) $current) + 1) % \count(Config::STATUSES)];
                }
            }
            Offices::save($offices, $email);
            Indexer::rebuild();
            $redirect('offices');

        case 'office-toggle':
            $id = (string) ($_POST['id'] ?? '');
            $offices = Offices::all();
            foreach ($offices as $i => $office) {
                if ((string) ($office['id'] ?? '') === $id) {
                    $offices[$i]['enabled'] = !($office['enabled'] ?? true);
                }
            }
            Offices::save($offices, $email);
            Indexer::rebuild();
            $redirect('offices');

        case 'office-save':
            $id = (string) ($_POST['id'] ?? '');
            $offices = Offices::all();
            $input = (array) ($_POST['o'] ?? []);
            $photos = array_values(array_filter(array_map('trim', explode(',', (string) ($input['photos'] ?? '')))));
            $data = [
                'site' => \in_array((string) ($input['site'] ?? ''), Config::SITES, true) ? (string) $input['site'] : Config::SITES[0],
                'name' => trim(mb_substr((string) ($input['name'] ?? ''), 0, 120)),
                'type' => \in_array((string) ($input['type'] ?? ''), Config::TYPES, true) ? (string) $input['type'] : 'private',
                'area' => trim(mb_substr((string) ($input['area'] ?? ''), 0, 40)),
                'price' => max(0, (int) ($input['price'] ?? 0)),
                'currency' => 'EUR',
                'period' => 'month',
                'vat' => 'excl',
                'status' => \in_array((string) ($input['status'] ?? ''), Config::STATUSES, true) ? (string) $input['status'] : 'available',
                'availableFrom' => trim((string) ($input['availableFrom'] ?? '')),
                'enabled' => !empty($input['enabled']),
                'featured' => !empty($input['featured']),
                'order' => (int) ($input['order'] ?? 999),
                'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($input['color'] ?? '')) === 1 ? (string) $input['color'] : '#FFD100',
                'description' => trim(mb_substr((string) ($input['description'] ?? ''), 0, 2000)),
                'features' => array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) ($input['features'] ?? '')))))),
                'photos' => $photos,
                'i18n' => ['en' => [
                    'name' => trim((string) ($input['en_name'] ?? '')),
                    'area' => trim((string) ($input['en_area'] ?? '')),
                    'description' => trim((string) ($input['en_description'] ?? '')),
                    'features' => array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) ($input['en_features'] ?? '')))))),
                ]],
            ];
            if ($data['name'] === '') {
                Session::flash('Le nom du bureau est obligatoire.', 'error');
                $redirect('offices', $id === '' ? ['new' => 1] : ['edit' => $id]);
            }

            $found = false;
            foreach ($offices as $i => $office) {
                if ((string) ($office['id'] ?? '') === $id && $id !== '') {
                    $offices[$i] = array_replace($office, $data);
                    $found = true;
                }
            }
            if (!$found) {
                $data['id'] = Offices::makeId($data['site'], $data['name'], $offices);
                $data['order'] = $data['order'] === 999 ? \count($offices) + 1 : $data['order'];
                $offices[] = $data;
            }
            Offices::save($offices, $email);
            Indexer::rebuild();
            Session::flash($found ? 'Bureau mis à jour.' : 'Bureau ajouté au catalogue.');
            $redirect('offices');

        case 'office-delete':
            $id = (string) ($_POST['id'] ?? '');
            Offices::save(
                array_values(array_filter(Offices::all(), static fn (array $o): bool => (string) ($o['id'] ?? '') !== $id)),
                $email
            );
            Indexer::rebuild();
            Session::flash('Bureau supprimé.');
            $redirect('offices');

        case 'office-move':
            $id = (string) ($_POST['id'] ?? '');
            $direction = (string) ($_POST['direction'] ?? 'up') === 'down' ? 1 : -1;
            $offices = Offices::all();
            foreach ($offices as $i => $office) {
                if ((string) ($office['id'] ?? '') !== $id) {
                    continue;
                }
                $target = $i + $direction;
                if ($target >= 0 && $target < \count($offices)) {
                    [$offices[$i], $offices[$target]] = [$offices[$target], $offices[$i]];
                    foreach ($offices as $j => $o) {
                        $offices[$j]['order'] = $j + 1;
                    }
                }
                break;
            }
            Offices::save($offices, $email);
            $redirect('offices');

        // ------------------------------------------------------------ photos
        case 'media-upload':
            $files = $_FILES['photos'] ?? null;
            $added = 0;
            $errors = [];
            foreach ((array) ($files['name'] ?? []) as $i => $name) {
                $result = Media::store([
                    'name' => $name,
                    'type' => $files['type'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$i] ?? 0,
                ], $email, (string) ($_POST['alt'] ?? ''));
                if ($result['ok']) {
                    $added++;
                } else {
                    $errors[] = (string) ($result['error'] ?? '');
                }
            }
            Session::flash(
                $added > 0 ? $added . ' photo(s) ajoutée(s).' . ($errors ? ' Erreurs : ' . implode(' ', array_unique($errors)) : '') : implode(' ', array_unique($errors) ?: ['Aucune photo ajoutée.']),
                $added > 0 ? 'ok' : 'error'
            );
            $redirect('media');

        case 'media-update':
            Media::update((string) ($_POST['path'] ?? ''), [
                'alt' => trim(mb_substr((string) ($_POST['alt'] ?? ''), 0, 200)),
                'caption' => trim(mb_substr((string) ($_POST['caption'] ?? ''), 0, 300)),
                'i18n' => ['en' => [
                    'alt' => trim(mb_substr((string) ($_POST['alt_en'] ?? ''), 0, 200)),
                    'caption' => trim(mb_substr((string) ($_POST['caption_en'] ?? ''), 0, 300)),
                ]],
            ], $email);
            Session::flash('Photo mise à jour.');
            $redirect('media');

        case 'media-delete':
            Media::delete((string) ($_POST['path'] ?? ''), $email);
            Session::flash('Photo supprimée.');
            $redirect('media');

        // ------------------------------------------------------------ l'actu
        case 'post-save':
            $data = Store::read('posts.json');
            $posts = \is_array($data['posts'] ?? null) ? $data['posts'] : [];
            $input = (array) ($_POST['p'] ?? []);
            $slug = Text::slug((string) ($input['slug'] ?? '')) ?: Text::slug((string) ($input['title'] ?? '')) ?: ('article-' . date('Ymd-His'));
            $original = (string) ($_POST['original'] ?? '');
            $post = [
                'slug' => $slug,
                'status' => ($input['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
                'date' => trim((string) ($input['date'] ?? date('Y-m-d'))),
                'title' => trim(mb_substr((string) ($input['title'] ?? ''), 0, 160)),
                'excerpt' => trim(mb_substr((string) ($input['excerpt'] ?? ''), 0, 400)),
                'body' => Text::sanitizeHtml((string) ($input['body'] ?? '')),
                'image' => trim((string) ($input['image'] ?? '')),
                'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($input['color'] ?? '')) === 1 ? (string) $input['color'] : '#FFD100',
                'i18n' => ['en' => [
                    'title' => trim((string) ($input['en_title'] ?? '')),
                    'excerpt' => trim((string) ($input['en_excerpt'] ?? '')),
                    'body' => Text::sanitizeHtml((string) ($input['en_body'] ?? '')),
                ]],
            ];
            if ($post['title'] === '') {
                Session::flash('Le titre est obligatoire.', 'error');
                $redirect('posts');
            }
            $found = false;
            foreach ($posts as $i => $existing) {
                if ((string) ($existing['slug'] ?? '') === $original && $original !== '') {
                    $posts[$i] = array_replace($existing, $post);
                    $found = true;
                }
            }
            if (!$found) {
                $posts[] = $post;
            }
            Store::write('posts.json', ['_schema' => Config::SCHEMA, 'posts' => $posts], $email);
            Indexer::rebuild();
            Session::flash($found ? 'Article mis à jour.' : 'Article créé.');
            $redirect('posts');

        case 'post-delete':
            $data = Store::read('posts.json');
            $posts = array_values(array_filter(
                \is_array($data['posts'] ?? null) ? $data['posts'] : [],
                static fn (array $p): bool => (string) ($p['slug'] ?? '') !== (string) ($_POST['slug'] ?? '')
            ));
            Store::write('posts.json', ['_schema' => Config::SCHEMA, 'posts' => $posts], $email);
            Indexer::rebuild();
            Session::flash('Article supprimé.');
            $redirect('posts');

        // ---------------------------------------------------------- demandes
        case 'request-status':
            Requests::setStatus((string) ($_POST['ref'] ?? ''), (string) ($_POST['status'] ?? 'new'), $email);
            $redirect('requests');

        case 'request-delete':
            Requests::delete((string) ($_POST['ref'] ?? ''), $email);
            Session::flash('Demande supprimée.');
            $redirect('requests');

        // ------------------------------------------------------ assistant IA
        case 'doc-upload':
            $result = Docs::store($_FILES['doc'] ?? [], $email, (string) ($_POST['lang'] ?? Config::DEFAULT_LANG), !empty($_POST['public']));
            if ($result['ok']) {
                Indexer::rebuild();
            }
            Session::flash($result['ok'] ? 'Document ajouté et indexé.' : (string) ($result['error'] ?? 'Envoi impossible.'), $result['ok'] ? 'ok' : 'error');
            $redirect('docs');

        case 'doc-delete':
            Docs::delete((string) ($_POST['id'] ?? ''), $email);
            Indexer::rebuild();
            Session::flash('Document supprimé et retiré de l’index.');
            $redirect('docs');

        case 'ai-reindex':
            $result = Indexer::rebuild();
            Session::flash('Index reconstruit : ' . $result['chunks'] . ' extraits sur ' . $result['sources'] . ' sources.');
            $redirect('docs');

        case 'ai-prompt':
            $settings = Content::settings();
            $settings['ai']['systemPrompt'] = trim(mb_substr((string) ($_POST['prompt'] ?? ''), 0, 4000));
            $settings['ai']['enabled'] = !empty($_POST['enabled']);
            foreach (Config::LANGS as $code) {
                $suggestions = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) ($_POST['suggestions'][$code] ?? ''))))));
                $settings['ai']['suggestions'][$code] = \array_slice($suggestions, 0, 3);
            }
            Store::write('settings.json', $settings, $email);
            Session::flash('Assistant mis à jour.');
            $redirect('docs');

        case 'ai-misses-clear':
            Gemini::clearMisses();
            Session::flash('Journal des questions sans réponse vidé.');
            $redirect('docs');

        // ---------------------------------------------------------- réglages
        case 'settings-save':
            $settings = Content::settings();
            $input = (array) ($_POST['s'] ?? []);
            $settings['site']['name'] = trim((string) ($input['name'] ?? $settings['site']['name'] ?? ''));
            $settings['site']['tagline'] = trim((string) ($input['tagline'] ?? ''));
            $settings['contact']['email'] = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
            $settings['contact']['phone'] = trim((string) ($input['phone'] ?? ''));
            $settings['contact']['hours'] = trim((string) ($input['hours'] ?? ''));
            $settings['contact']['autoReply'] = !empty($input['autoReply']);
            $settings['seo']['titleSuffix'] = trim((string) ($input['titleSuffix'] ?? ''));
            $settings['seo']['description'] = trim((string) ($input['seoDescription'] ?? ''));
            $settings['top']['line1'] = trim((string) ($input['topLine1'] ?? ''));
            $settings['top']['line2'] = trim((string) ($input['topLine2'] ?? ''));
            $settings['sticky']['enabled'] = !empty($input['stickyEnabled']);
            $settings['sticky']['halo'] = (string) ($input['stickyHalo'] ?? 'reserve') === 'contact' ? 'contact' : 'reserve';
            $settings['exit']['enabled'] = !empty($input['exitEnabled']);
            $settings['exit']['inactivitySeconds'] = max(10, (int) ($input['exitInactivity'] ?? 45));
            $settings['consent']['enabled'] = !empty($input['consentEnabled']);
            $settings['reviews']['enabled'] = !empty($input['reviewsEnabled']);
            $settings['reviews']['badge'] = trim((string) ($input['reviewsBadge'] ?? ''));
            $settings['analytics']['provider'] = \in_array((string) ($input['analyticsProvider'] ?? 'none'), ['none', 'plausible', 'matomo'], true) ? (string) $input['analyticsProvider'] : 'none';
            $settings['analytics']['domain'] = trim((string) ($input['analyticsDomain'] ?? ''));
            $settings['analytics']['src'] = trim((string) ($input['analyticsSrc'] ?? ''));

            foreach ((array) ($input['sites'] ?? []) as $i => $site) {
                if (!isset($settings['sites'][$i])) {
                    continue;
                }
                $settings['sites'][$i] = array_replace($settings['sites'][$i], [
                    'name' => trim((string) ($site['name'] ?? '')),
                    'shortName' => trim((string) ($site['shortName'] ?? '')),
                    'tag' => trim((string) ($site['tag'] ?? '')),
                    'area' => trim((string) ($site['area'] ?? '')),
                    'address' => trim((string) ($site['address'] ?? '')),
                    'zip' => trim((string) ($site['zip'] ?? '')),
                    'city' => trim((string) ($site['city'] ?? '')),
                    'note' => trim((string) ($site['note'] ?? '')),
                    'description' => trim((string) ($site['description'] ?? '')),
                    'chips' => array_values(array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) ($site['chips'] ?? '')))))),
                    'cta' => trim((string) ($site['cta'] ?? '')),
                    'mapUrl' => trim((string) ($site['mapUrl'] ?? '')),
                    'photo' => trim((string) ($site['photo'] ?? '')),
                    'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($site['color'] ?? '')) === 1 ? (string) $site['color'] : ($settings['sites'][$i]['color'] ?? '#FFD100'),
                    'enabled' => !empty($site['enabled']),
                ]);
            }
            Store::write('settings.json', $settings, $email);
            Session::flash('Réglages enregistrés.');
            $redirect('settings');

        case 'keys-save':
            Admin::saveKeys((array) ($_POST['k'] ?? []), $email);
            Session::flash('Clés API enregistrées (stockées hors racine web, en 0600).');
            $redirect('settings', ['tab' => 'keys']);

        case 'reviews-refresh':
            $result = Reviews::refresh();
            Session::flash($result['ok'] ? 'Avis Google rafraîchis (' . ($result['count'] ?? 0) . ').' : (string) ($result['error'] ?? ''), $result['ok'] ? 'ok' : 'error');
            $redirect('settings', ['tab' => 'keys']);

        case 'user-add':
            $result = Auth::createUser((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['role'] ?? 'editor'));
            Session::flash($result['ok'] ? 'Compte créé.' : (string) ($result['error'] ?? ''), $result['ok'] ? 'ok' : 'error');
            $redirect('settings', ['tab' => 'users']);

        case 'user-delete':
            $target = (string) ($_POST['email'] ?? '');
            if ($target === $email) {
                Session::flash('Vous ne pouvez pas supprimer votre propre compte.', 'error');
            } else {
                Session::flash(Auth::deleteUser($target) ? 'Compte supprimé.' : 'Suppression impossible.', 'ok');
            }
            $redirect('settings', ['tab' => 'users']);

        case 'user-password':
            $ok = (string) ($_POST['password'] ?? '') === (string) ($_POST['password2'] ?? '')
                && Auth::updatePassword($email, (string) ($_POST['password'] ?? ''));
            Session::flash($ok ? 'Mot de passe modifié.' : 'Mots de passe différents ou trop courts (10 caractères minimum).', $ok ? 'ok' : 'error');
            $redirect('settings', ['tab' => 'users']);

        default:
            $redirect($screen);
    }
}

// ------------------------------------------------------------------- affichage

$screen = \array_key_exists($screen, Admin::MENU) ? $screen : 'dash';
$data = ['user' => $user, 'flash' => Session::flash(), 'screen' => $screen, 'settings' => Content::settings()];

switch ($screen) {
    case 'pages':
        $slug = (string) ($_GET['slug'] ?? 'home');
        $slug = \array_key_exists($slug, Admin::PAGES) ? $slug : 'home';
        $lang = (string) ($_GET['lang'] ?? Config::DEFAULT_LANG);
        $lang = \in_array($lang, Config::LANGS, true) ? $lang : Config::DEFAULT_LANG;
        $file = 'pages/' . $slug . '.' . $lang . '.json';
        $lockKey = 'page-' . $slug . '-' . $lang;
        $lockOwner = Store::editLockOwner($lockKey);
        if ($lockOwner === null) {
            Store::acquireEditLock($lockKey, $email);
            $lockOwner = $email;
        }
        $data += [
            'slug' => $slug,
            'lang' => $lang,
            'page' => Store::read($file),
            'schema' => Admin::pageSchema($slug),
            'versions' => Store::versions($file),
            'lockOwner' => $lockOwner,
            'media' => Media::all(),
        ];
        break;

    case 'offices':
        $data += [
            'offices' => Offices::all(),
            'editing' => (string) ($_GET['edit'] ?? ''),
            'creating' => isset($_GET['new']),
            'media' => Media::all(),
        ];
        break;

    case 'media':
        $data += ['media' => Media::all()];
        break;

    case 'posts':
        $data += [
            'posts' => Content::posts(),
            'editing' => (string) ($_GET['edit'] ?? ''),
            'creating' => isset($_GET['new']),
            'media' => Media::all(),
        ];
        break;

    case 'requests':
        $data += ['requests' => Requests::all()];
        break;

    case 'docs':
        $data += [
            'docs' => Docs::all(),
            'stats' => Indexer::stats(),
            'misses' => Gemini::misses(12),
            'settings' => Content::settings(),
        ];
        break;

    case 'settings':
        $data += [
            'settings' => Content::settings(),
            'tab' => (string) ($_GET['tab'] ?? 'site'),
            'users' => Auth::users(),
            'media' => Media::all(),
        ];
        break;

    default:
        $offices = Offices::all();
        $data += [
            'offices' => $offices,
            'requests' => \array_slice(Requests::all(), 0, 6),
            'stats' => Indexer::stats(),
            'pagesCount' => \count(glob(Config::contentPath('pages/*.json')) ?: []),
            'settings' => Content::settings(),
        ];
}

echo View::admin($screen, $data);
