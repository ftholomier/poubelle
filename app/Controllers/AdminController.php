<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Domain\CvRepository;
use App\Domain\EmployerRepository;
use App\Domain\JobRepository;
use App\Domain\PageRepository;
use App\Domain\TradeRepository;
use App\Domain\UserRepository;
use App\Services\Ads;
use App\Services\AdSnippet;
use App\Services\Aggregator;
use App\Services\Auth;
use App\Services\ContentTranslator;
use App\Services\I18n;
use App\Services\JobLifecycle;
use App\Services\Knowledge;
use App\Services\Mailer;
use App\Services\Notifier;
use App\Services\Sanitizer;
use App\Services\Search;
use App\Services\Regie;
use App\Services\Secrets;
use App\Services\Seo;
use App\Services\SecretsTest;
use App\Services\Trades;
use App\Services\Translator;
use App\Services\Validator;
use App\Storage\Audit;
use App\Storage\Backup;
use App\Storage\Index;
use App\Storage\Lock;

/** Back-office. Tout est derrière authentification sauf connexion et récupération. */
final class AdminController extends Controller
{
    /* ----------------------------------------------------- authentification */

    public function login(Request $request, array $params): Response
    {
        if (Auth::isStaff()) {
            return Response::redirect('/admin/tableau-de-bord', 302);
        }

        $error = '';
        if ($request->isPost()) {
            if (!Csrf::check($request)) {
                $error = I18n::t('form.err_csrf');
            } else {
                $result = Auth::login(
                    (string) $request->input('email', ''),
                    (string) $request->input('password', ''),
                    $request->ip(),
                    true,   // back-office : rôles staff uniquement
                );
                if ($result['ok']) {
                    return Response::redirect('/admin/tableau-de-bord', 303);
                }
                $error = $result['error'];
            }
        }

        return $this->screen('admin/login', ['error' => $error], I18n::t('admin.title'), false);
    }

    public function forgot(Request $request, array $params): Response
    {
        $done = false;
        $error = '';

        if ($request->isPost()) {
            if (!Csrf::check($request)) {
                $error = I18n::t('form.err_csrf');
            } else {
                $email = (string) $request->input('email', '');
                $token = Auth::createResetToken($email, $request->ip());
                if ($token !== '') {
                    $link = rtrim((string) Config::get('site.url'), '/')
                          . '/admin/reinitialiser?token=' . $token;
                    Mailer::resetLink($email, $link);
                }
                // Même réponse dans tous les cas : on ne dit pas si le compte existe.
                $done = true;
            }
        }

        return $this->screen('admin/forgot', ['done' => $done, 'error' => $error],
            I18n::t('admin.forgot_title'), false);
    }

    public function reset(Request $request, array $params): Response
    {
        $token = (string) ($request->get('token') ?? $request->input('token', ''));
        $user = Auth::checkResetToken($token);
        $error = '';
        $done = false;

        if ($user === null) {
            $error = I18n::t('admin.reset_bad');
        } elseif ($request->isPost()) {
            if (!Csrf::check($request)) {
                $error = I18n::t('form.err_csrf');
            } else {
                $password = (string) $request->input('password', '');
                if (mb_strlen($password) < 10) {
                    $error = I18n::t('admin.new_password_note');
                } elseif (Auth::consumeResetToken($token, $password)) {
                    $done = true;
                } else {
                    $error = I18n::t('admin.reset_bad');
                }
            }
        }

        return $this->screen('admin/reset', [
            'token' => $token, 'error' => $error, 'done' => $done, 'valid' => $user !== null,
        ], I18n::t('admin.new_password'), false);
    }

    public function logout(Request $request, array $params): Response
    {
        if (Csrf::check($request)) {
            Auth::logout();
        }
        return Response::redirect('/admin', 303);
    }

    /* -------------------------------------------------------- tableau de bord */

    public function dashboard(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $jobFacets = Index::meta('jobs');
        $cvFacets = Index::meta('cv');

        $rows = [];
        foreach (PageRepository::all('fr') as $page) {
            $states = PageRepository::translationStates((string) $page['slug']);
            $fresh = count(array_filter($states, static fn(array $s) => $s['state'] === 'fresh'));
            $rows[] = [
                'title'  => $page['title'],
                'slug'   => $page['slug'],
                'type'   => 'Page',
                'langs'  => $fresh + 1,
                'total'  => count($states),
                'status' => $page['status'],
                'lock'   => Lock::inspect('page:fr:' . $page['slug']),
            ];
        }

        // Les compteurs annoncent ce qui est réellement en ligne, et ce qui
        // attend une décision : un chiffre gonflé par des annonces périmées
        // n'aide personne.
        $pending = count(array_filter(
            JobRepository::all(),
            static fn(array $j) => ($j['status'] ?? '') === 'pending',
        )) + count(array_filter(
            CvRepository::all(),
            static fn(array $c) => ($c['status'] ?? '') === 'pending',
        ));

        return $this->screen('admin/dashboard', [
            'kpi' => [
                'jobs'  => Search::liveJobCount(),
                'cv'    => (int) ($cvFacets['total'] ?? 0),
                'regie' => count(Audit::recent(200, 'regie.answered')),
                'ads'   => count(array_filter(Ads::slots(), static fn(array $s) => $s['enabled'])),
            ],
            'pending' => $pending,
            'rows'      => $rows,
            'journal'   => Audit::recent(12),
            'backups'   => array_slice(Backup::listAll(), 0, 5),
            'knowledge' => Knowledge::stats(),
        ], I18n::t('admin.dashboard'));
    }

    /* --------------------------------------------------------------- contenus */

    public function contents(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        if ($request->isPost() && Csrf::check($request) && $request->input('action') === 'create') {
            $title = trim((string) $request->input('title', ''));
            if ($title !== '') {
                $slug = slugify($title);
                PageRepository::save([
                    'id' => $slug, 'slug' => $slug, 'title' => $title,
                    'status' => 'draft', 'body' => '', 'excerpt' => '',
                ], 'fr');
                Audit::log('content.created', ['slug' => $slug], $this->userId());
                return Response::redirect('/admin/contenu/' . $slug, 303);
            }
        }

        return $this->screen('admin/contents', [
            'pages' => PageRepository::all('fr'),
        ], I18n::t('admin.contents'));
    }

    public function editor(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $slug = slugify((string) ($params['slug'] ?? ''));
        $page = PageRepository::find($slug, 'fr');
        if ($page === null) {
            return $this->screen('admin/404', [], I18n::t('error.404_title'));
        }

        $user = Auth::user();
        $resource = 'page:fr:' . $slug;
        $lock = Lock::acquire($resource, (int) $user['id'], (string) $user['name']);
        $notice = '';

        if ($request->isPost()) {
            if ($lock === null) {
                $held = Lock::inspect($resource);
                $notice = I18n::t('admin.lock_taken',
                    (string) ($held['user_name'] ?? '—'), (int) ($held['age_minutes'] ?? 0));
            } elseif (!Csrf::check($request)) {
                $notice = I18n::t('form.err_csrf');
            } else {
                // Instantané avant publication : toute version est restaurable.
                if ($request->input('action') === 'publish') {
                    Backup::snapshot('publication-' . $slug, $this->userId());
                }

                $page['title']  = trim((string) $request->input('title', $page['title']));
                $page['body']   = Sanitizer::html((string) ($request->post['body'] ?? ''));
                $page['excerpt']= str_excerpt(Sanitizer::text((string) $page['body']), 220);
                $page['status'] = $request->input('action') === 'publish' ? 'publish' : 'draft';
                $page['revision'] = (int) ($page['revision'] ?? 1) + 1;
                $page['seo'] = [
                    'title'       => trim((string) $request->input('seo_title', '')) ?: $page['title'],
                    'description' => trim((string) $request->input('seo_description', '')) ?: $page['excerpt'],
                ];
                if ($page['status'] === 'publish' && ($page['published_at'] ?? '') === '') {
                    $page['published_at'] = date('c');
                }

                PageRepository::save($page, 'fr');
                Index::rebuild('pages');
                Knowledge::rebuild();
                Audit::log('content.saved', ['slug' => $slug, 'status' => $page['status'],
                    'revision' => $page['revision']], $this->userId());
                $notice = I18n::t('admin.saved');
            }
        }

        return $this->screen('admin/editor', [
            'page'         => $page,
            'lock'         => $lock,
            'lockHolder'   => $lock === null ? Lock::inspect($resource) : null,
            'notice'       => $notice,
            'translations' => PageRepository::translationStates($slug),
            'documents'    => Knowledge::documents(),
        ], (string) $page['title']);
    }

    /* ----------------------------------------------------- listes de contenus */

    public function jobs(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }
        $this->handleRowAction($request, 'job');

        // Actions groupées sur l'historique repris de WordPress.
        if ($request->isPost() && Csrf::check($request)) {
            $bulk = (string) $request->input('bulk', '');
            if ($bulk === 'date') {
                $n = JobLifecycle::stampUndated();
                Session::flash('notice', $n . ' annonce(s) datée(s).');
                return Response::redirect('/admin/offres', 303);
            }
            if ($bulk === 'archive') {
                $n = JobLifecycle::archiveUndated();
                Session::flash('notice', $n . ' annonce(s) archivée(s).');
                return Response::redirect('/admin/offres', 303);
            }
        }

        $all = $this->sortByDate(JobRepository::all());
        $filter = (string) $request->get('etat', '');

        // Les annonces reprises de l'ancien site n'ont pas de date de fin :
        // on annonce clairement quand elles passeront en archive.
        $undated = count(array_filter(
            $all,
            static fn(array $j) => (string) ($j['status'] ?? '') === 'publish'
                && trim((string) ($j['expires_at'] ?? '')) === '',
        ));

        return $this->screen('admin/jobs', [
            'items'  => $filter === '' ? $all : array_values(array_filter(
                $all,
                static fn(array $j) => (string) ($j['status'] ?? '') === $filter,
            )),
            'counts'   => $this->countByStatus($all),
            'filter'   => $filter,
            'undated'  => $undated,
            'graceEnd' => JobLifecycle::startedAt()
                        + max(0, (int) Config::get('jobs.legacy_grace_days', 30)) * 86400,
        ], I18n::t('admin.jobs'));
    }

    public function cvs(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }
        $this->handleRowAction($request, 'cv');

        $all = $this->sortByDate(CvRepository::all());
        $filter = (string) $request->get('etat', '');

        return $this->screen('admin/cvs', [
            'items'  => $filter === '' ? $all : array_values(array_filter(
                $all,
                static fn(array $c) => (string) ($c['status'] ?? '') === $filter,
            )),
            'counts' => $this->countByStatus($all),
            'filter' => $filter,
        ], I18n::t('admin.cvs'));
    }

    public function employers(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        return $this->screen('admin/employers', [
            'items' => Index::load('employers'),
        ], I18n::t('admin.employers'));
    }

    /* ----------------------------------------------------- documents de l'IA */

    public function documents(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $notice = '';
        if ($request->isPost() && Csrf::check($request)) {
            $action = (string) $request->input('action', '');

            if ($action === 'remove') {
                Knowledge::removeDocument((string) $request->input('id', ''));
                Audit::log('ai.document_removed', ['id' => $request->input('id')], $this->userId());
                $notice = I18n::t('admin.saved');
            } elseif ($action === 'add') {
                $title = trim((string) $request->input('title', ''));
                $text = trim((string) ($request->post['text'] ?? ''));
                if ($title !== '' && $text !== '') {
                    Knowledge::addDocument($title, $text, (string) $request->input('kind', 'md'));
                    Audit::log('ai.document_added', ['title' => $title], $this->userId());
                    $notice = I18n::t('admin.saved');
                }
            } elseif ($action === 'reindex') {
                Knowledge::rebuild();
                $notice = I18n::t('admin.saved');
            }
        }

        return $this->screen('admin/documents', [
            'documents' => Knowledge::documents(),
            'stats'     => Knowledge::stats(),
            'notice'    => $notice,
        ], I18n::t('admin.documents'));
    }

    /* --------------------------------------------------------- traductions */

    public function translations(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $notice = '';
        $noticeOk = true;
        if ($request->isPost() && Csrf::check($request)) {
            if (!Translator::available()) {
                $notice = I18n::t('admin.translate_unavailable');
                $noticeOk = false;
            } else {
                $done = 0;
                foreach (array_keys(I18n::languages()) as $lang) {
                    if ($lang === 'fr') {
                        continue;
                    }
                    $done += I18n::refresh($lang);
                    foreach (PageRepository::published('fr') as $page) {
                        $states = PageRepository::translationStates((string) $page['slug']);
                        if (($states[$lang]['state'] ?? '') === 'fresh') {
                            continue;
                        }
                        $translated = Translator::translatePage($page, $lang);
                        if ($translated !== null) {
                            PageRepository::save($translated, $lang);
                            $done++;
                        }
                    }
                }

                // Les annonces et profils, plafonnés : un clic ne doit pas
                // déclencher des milliers d'appels facturés au caractère.
                $records = ContentTranslator::translateMissing(null, 150);

                Audit::log('i18n.refreshed',
                    ['pages' => $done, 'records' => $records['done'],
                     'failed' => $records['failed']], $this->userId());

                // Zéro traduit alors que tout est à faire : l'API a refusé.
                // Le dire, plutôt qu'annoncer « Enregistré » en vert.
                $error = Translator::lastError();
                if ($done === 0 && $records['done'] === 0 && $error !== '') {
                    $notice = 'Aucune traduction : l’API Google a refusé la requête. ' . $error;
                    $noticeOk = false;
                } else {
                    $notice = sprintf('%s %d élément(s) d’interface et de page, %d fiche(s) traduite(s).',
                        I18n::t('admin.saved'), $done, $records['done']);
                    if ($records['failed'] > 0) {
                        $notice .= sprintf(' %d échec(s)%s.', $records['failed'],
                            $error !== '' ? ' — ' . $error : '');
                        $noticeOk = false;
                    }
                    if ($records['remaining'] > 0) {
                        $notice .= sprintf(' Il reste %d fiche(s) à traduire : relancez pour continuer.',
                            $records['remaining']);
                    }
                }
            }
        }

        $matrix = [];
        foreach (PageRepository::all('fr') as $page) {
            $matrix[] = [
                'title'  => $page['title'],
                'slug'   => $page['slug'],
                'states' => PageRepository::translationStates((string) $page['slug']),
            ];
        }

        return $this->screen('admin/translations', [
            'matrix'    => $matrix,
            'records'   => ContentTranslator::stats(),
            'available' => Translator::available(),
            'notice'    => $notice,
            'noticeOk'  => $noticeOk,
        ], I18n::t('admin.translations'));
    }

    /* --------------------------------------------------------- sauvegardes */

    public function backups(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $notice = '';
        if ($request->isPost() && Csrf::check($request)) {
            $action = (string) $request->input('action', '');
            if ($action === 'create') {
                $name = Backup::snapshot('manuel', $this->userId());
                $notice = $name !== null ? I18n::t('admin.saved') : I18n::t('form.error');
            } elseif ($action === 'restore' && Auth::isAdmin()) {
                $notice = Backup::restore((string) $request->input('name', ''), $this->userId())
                    ? I18n::t('admin.saved') : I18n::t('form.error');
            } elseif ($action === 'delete' && Auth::isAdmin()) {
                Backup::delete((string) $request->input('name', ''));
                $notice = I18n::t('admin.saved');
            }
        }

        return $this->screen('admin/backups', [
            'backups' => Backup::listAll(),
            'journal' => Audit::recent(20),
            'notice'  => $notice,
        ], I18n::t('admin.backups'));
    }

    /* --------------------------------------------------------- utilisateurs */

    public function users(Request $request, array $params): Response
    {
        if (($guard = $this->guard(true)) !== null) {
            return $guard;
        }

        $notice = '';
        if ($request->isPost() && Csrf::check($request)) {
            $action = (string) $request->input('action', '');
            $target = UserRepository::find((string) $request->input('id', ''));

            if ($target !== null && $action === 'toggle' && (string) $target['id'] !== (string) $this->userId()) {
                $target['active'] = !($target['active'] ?? true);
                UserRepository::save($target);
                Audit::log('user.toggled', ['user' => $target['id'], 'active' => $target['active']], $this->userId());
                $notice = I18n::t('admin.saved');
            } elseif ($action === 'create') {
                $v = (new Validator($request->post))->email('new_email')->required('new_name')
                    ->oneOf('new_role', ['admin', 'employer', 'candidate'], 'candidate');
                if (!$v->fails() && UserRepository::findByEmail((string) $v->value('new_email')) === null) {
                    $id = UserRepository::nextId();
                    UserRepository::save([
                        'id' => $id, 'email' => $v->value('new_email'), 'login' => $v->value('new_email'),
                        'display_name' => $v->value('new_name'), 'role' => $v->value('new_role'),
                        'password' => '', 'must_reset' => true, 'active' => true,
                    ]);
                    UserRepository::reindex();
                    $token = Auth::createResetToken((string) $v->value('new_email'), $request->ip());
                    if ($token !== '') {
                        Mailer::resetLink((string) $v->value('new_email'),
                            rtrim((string) Config::get('site.url'), '/') . '/admin/reinitialiser?token=' . $token);
                    }
                    Audit::log('user.created', ['user' => $id], $this->userId());
                    $notice = I18n::t('admin.saved');
                }
            }
        }

        $users = UserRepository::all();
        usort($users, static fn(array $a, array $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $this->screen('admin/users', [
            'users'  => $users,
            'notice' => $notice,
        ], I18n::t('admin.users'));
    }

    /* ------------------------------------------------------------ publicité */

    public function ads(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $notice = '';
        $parsed = null;

        if ($request->isPost() && Csrf::check($request)) {
            if ((string) $request->input('action', '') === 'snippet') {
                $parsed = $this->applySnippet((string) $request->input('snippet', ''));
                $notice = AdSnippet::summary($parsed);
            } else {
                Ads::setConsentMode((string) $request->input('consent', 'google'));
                foreach (array_keys(Ads::slots()) as $name) {
                    Ads::setEnabled($name, $request->input('slot_' . $name) === '1');
                }
                Audit::log('ads.updated',
                    ['mode' => Ads::mode(), 'consent' => Ads::consentMode()], $this->userId());
                $notice = I18n::t('admin.saved');
            }
        }

        return $this->screen('admin/ads', [
            'slots'   => Ads::slots(),
            'client'  => Ads::client(),
            'mode'    => Ads::mode(),
            'snippet' => Ads::snippet(),
            'consent' => Ads::consentMode(),
            'parsed'  => $parsed,
            'notice'  => $notice,
        ], I18n::t('admin.ads'));
    }

    /**
     * Le code AdSense collé au back-office alimente les réglages existants :
     * l'identifiant éditeur, l'unité par défaut — donc tous les emplacements —
     * et le mode qui va avec. Rien du code collé n'est rendu dans les pages.
     *
     * @return array<string, mixed> la lecture, pour l'afficher à l'éditeur
     */
    private function applySnippet(string $snippet): array
    {
        $parsed = AdSnippet::parse($snippet);
        Ads::setSnippet($snippet);

        if ($parsed['client'] === '') {
            return $parsed;
        }

        Secrets::save([
            'adsense_client'        => $parsed['client'],
            'adsense_default_slot'  => $parsed['slot'],
            'adsense_infeed_layout' => $parsed['layout'],
        ], [], $this->userId());

        Audit::log('ads.snippet', [
            'mode' => $parsed['mode'],
            'unit' => $parsed['slot'] !== '' ? 'renseignée' : 'aucune',
        ], $this->userId());

        return $parsed;
    }

    /* --------------------------------------------------- offres externes */

    public function sources(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $notice = '';
        if ($request->isPost() && Csrf::check($request)) {
            if ($request->input('action') === 'clear') {
                $count = Aggregator::clearCache();
                Audit::log('sources.cache_cleared', ['files' => $count], $this->userId());
                $notice = I18n::t('admin.saved');
            } else {
                foreach (array_keys(Aggregator::sources()) as $key) {
                    Aggregator::setSourceEnabled($key, $request->input('source_' . $key) === '1');
                }
                Aggregator::saveSettings(
                    (string) $request->input('query', ''),
                    (string) $request->input('exclude', ''),
                    $request->input('filter') === '1',
                    (string) $request->input('rome', ''),
                );
                // Les mots-clés ayant changé, le cache ne vaut plus rien.
                Aggregator::clearCache();
                Audit::log('sources.updated',
                    ['filtre' => Aggregator::filterEnabled()], $this->userId());
                $notice = I18n::t('admin.saved');
            }
        }

        $rows = [];
        $cache = Aggregator::cacheStatus();
        foreach (Aggregator::sources() as $key => $source) {
            $rows[$key] = [
                'name'       => $source->name(),
                'configured' => $source->isConfigured(),
                'enabled'    => Aggregator::isSourceEnabled($key),
                'cached'     => (int) ($cache[$key]['jobs'] ?? 0),
                'age'        => (int) ($cache[$key]['age'] ?? -1),
            ];
        }

        return $this->screen('admin/sources', [
            'rows'     => $rows,
            'settings' => (array) Config::get('sources', []),
            'query'    => Aggregator::query(),
            'exclude'  => implode(', ', Aggregator::exclude()),
            'rome'     => implode(', ', Aggregator::romeCodes()),
            'filter'   => Aggregator::filterEnabled(),
            'active'   => Aggregator::isEnabled(),
            'notice'   => $notice,
        ], I18n::t('admin.sources'));
    }

    /* ------------------------------------------------------- clés d'API */

    public function settings(Request $request, array $params): Response
    {
        if (($guard = $this->guard(true)) !== null) {
            return $guard;
        }

        $notice = '';
        $errors = [];
        $test = null;

        if ($request->isPost() && Csrf::check($request)) {
            $action = (string) $request->input('action', 'save');
            // Le bouton de test porte son groupe dans sa propre valeur.
            $group = (string) $request->input('test', '');

            // Un champ laissé vide ne doit pas effacer la valeur en place :
            // le formulaire ne réaffiche jamais un secret.
            $values = [];
            foreach (array_keys(Secrets::flatten()) as $key) {
                if (array_key_exists($key, $request->post)) {
                    $values[$key] = (string) $request->post[$key];
                }
            }
            $slots = $request->post['adsense_slots'] ?? [];
            if (is_array($slots)) {
                $values['adsense_slots'] = $slots;
            }
            if ($action === 'generate') {
                $values['app_key'] = Secrets::generateKey();
            }
            $clear = array_values(array_filter(
                (array) ($request->post['clear'] ?? []),
                static fn($k) => is_string($k) && $k !== '',
            ));

            // Tous les boutons de cet écran — enregistrer, engendrer une clé,
            // tester — commencent par enregistrer la saisie. Le test comme la
            // génération la faisaient disparaître, ce qui donnait l'impression
            // qu'un réglage « ne se change pas ».
            if (Secrets::save($values, $clear, $this->userId())) {
                $notice = I18n::t('admin.saved');
            } else {
                $errors[] = I18n::t('admin.save_failed');
            }

            if ($group !== '' && $errors === []) {
                $test = ['group' => $group] + SecretsTest::run($group);
                Audit::log('secrets.tested', ['group' => $group, 'ok' => $test['ok']], $this->userId());
            }

            // La clé vient peut-être d'être saisie : on redemande la liste des
            // modèles plutôt que de la laisser vide jusqu'au lendemain.
            $refreshModels = $action === 'models';
        }

        // Liste des modèles de l'assistant, demandée à Google et mise en cache.
        $models = Regie::models($refreshModels ?? false);
        if ($models['error'] !== '') {
            $errors[] = $models['error'];
        }

        return $this->screen('admin/settings', [
            // Le groupe « Envoi des e-mails » a son propre écran : il n'apparaît
            // pas deux fois.
            'catalog' => array_filter(
                Secrets::CATALOG,
                static fn(array $group) => ($group['screen'] ?? 'settings') === 'settings',
            ),
            'slots'   => Ads::slots(),
            'notice'  => $notice,
            'errors'  => $errors,
            'test'    => $test,
            // Menus déroulants, par clé de réglage.
            'choices' => ['regie_model' => $models['models']],
            'choicesAt' => ['regie_model' => $models['at']],
        ], I18n::t('admin.settings'));
    }

    /* --------------------------------------------------------------- métiers */

    /**
     * Rubrique « Les métiers » : la liste des fiches, et la création d'un
     * métier qui n'existait pas dans les fiches d'origine.
     */
    public function trades(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $errors = [];
        if ($request->isPost() && Csrf::check($request) && $request->input('action') === 'create') {
            $name = trim((string) $request->input('name', ''));
            $family = (string) $request->input('family', '');
            if ($name === '') {
                $errors[] = 'Donnez un nom au métier.';
            } elseif (!isset(Trades::families()[$family])) {
                $errors[] = 'Choisissez une famille.';
            } else {
                $slug = TradeRepository::uniqueSlug($name);
                TradeRepository::save([
                    'id' => $slug, 'slug' => $slug, 'name' => $name, 'family' => $family,
                    // Brouillon : une fiche vide n'a rien à faire en ligne.
                    'status' => 'draft', 'keywords' => [$name],
                ]);
                Index::rebuild('trades');
                Audit::log('trade.created', ['slug' => $slug], $this->userId());
                return Response::redirect('/admin/metier/' . $slug, 303);
            }
        }

        $rows = Index::load('trades');
        $counts = Trades::jobCounts();
        $modified = [];
        foreach ($rows as $row) {
            $record = TradeRepository::find((string) $row['id']);
            $modified[(string) $row['id']] = $record !== null && TradeRepository::isModified($record);
        }

        return $this->screen('admin/trades', [
            'rows'     => $rows,
            'families' => Trades::families(),
            'counts'   => $counts,
            'modified' => $modified,
            'errors'   => $errors,
        ], I18n::t('admin.trades'));
    }

    /** Édition d'une fiche métier. */
    public function trade(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }

        $id = (string) preg_replace('/[^a-z0-9\-]/', '', (string) ($params['id'] ?? ''));
        $trade = TradeRepository::find($id);
        if ($trade === null) {
            return $this->screen('admin/404', [], I18n::t('error.404_title'));
        }

        $user = Auth::user();
        $resource = 'trade:' . $id;
        $lock = Lock::acquire($resource, (int) $user['id'], (string) $user['name']);
        $notice = '';
        $errors = [];

        if ($request->isPost()) {
            $action = (string) $request->input('action', 'publish');

            if ($lock === null) {
                $held = Lock::inspect($resource);
                $errors[] = I18n::t('admin.lock_taken',
                    (string) ($held['user_name'] ?? '—'), (int) ($held['age_minutes'] ?? 0));
            } elseif (!Csrf::check($request)) {
                $errors[] = I18n::t('form.err_csrf');
            } elseif ($action === 'delete') {
                if ((string) $request->input('confirm_delete', '') !== 'oui') {
                    $errors[] = 'Cochez la case de confirmation pour supprimer la fiche.';
                } else {
                    Backup::snapshot('metier-supprime-' . $id, $this->userId());
                    TradeRepository::delete($id);
                    Lock::release($resource, (int) $user['id']);
                    Index::rebuild('trades');
                    Knowledge::rebuild();
                    Audit::log('trade.deleted', ['slug' => $trade['slug']], $this->userId());
                    return Response::redirect('/admin/metiers', 303);
                }
            } elseif ($action === 'restore') {
                $restored = TradeRepository::restore($id);
                if ($restored === null) {
                    $errors[] = 'Ce métier n’a pas de texte d’origine à restaurer.';
                } else {
                    $trade = $restored;
                    Index::rebuild('trades');
                    Knowledge::rebuild();
                    Audit::log('trade.restored', ['slug' => $trade['slug']], $this->userId());
                    $notice = 'Texte d’origine restauré. L’adresse et l’état de publication sont conservés.';
                }
            } else {
                [$candidate, $errors] = $this->readTradeForm($request, $trade);
                if ($errors === []) {
                    $candidate['status'] = $action === 'draft' ? 'draft' : 'publish';
                    if ($candidate['status'] === 'publish' && trim((string) ($trade['published_at'] ?? '')) === '') {
                        $candidate['published_at'] = date('c');
                    }
                    if (TradeRepository::save($candidate)) {
                        $trade = TradeRepository::find($id) ?? $candidate;
                        Index::rebuild('trades');
                        Knowledge::rebuild();
                        Audit::log('trade.saved', ['slug' => $trade['slug'], 'status' => $trade['status']],
                            $this->userId());
                        $notice = $trade['status'] === 'publish'
                            ? 'Fiche enregistrée et publiée.'
                            : 'Fiche enregistrée en brouillon : elle n’est pas visible sur le site.';
                    } else {
                        $errors[] = I18n::t('admin.save_failed');
                    }
                } else {
                    // Les valeurs refusées restent dans le formulaire, à corriger.
                    $trade = $candidate;
                }
            }
        }

        return $this->screen('admin/trade', [
            'trade'      => $trade,
            'families'   => Trades::families(),
            'all'        => Index::load('trades'),
            'units'      => Trades::UNITS,
            'matches'    => count(Trades::matchingJobs($trade, 1000)),
            'profiles'   => count(Trades::matchingProfiles($trade, 1000)),
            'hasSeed'    => TradeRepository::seed($id) !== null,
            'modified'   => TradeRepository::isModified($trade),
            'lock'       => $lock,
            'lockHolder' => $lock === null ? Lock::inspect($resource) : null,
            'notice'     => $notice,
            'errors'     => $errors,
        ], (string) $trade['name']);
    }

    /**
     * Lit le formulaire d'une fiche métier.
     *
     * @return array{0: array<string, mixed>, 1: string[]} fiche candidate, erreurs
     */
    private function readTradeForm(Request $request, array $trade): array
    {
        $errors = [];
        $text = static fn(string $key): string => trim(Sanitizer::text((string) ($request->post[$key] ?? '')));
        // Une ligne par élément : c'est ainsi qu'on écrit une liste à la main.
        $lines = static fn(string $key): array => array_values(array_filter(array_map(
            'trim', preg_split('/\r\n|\r|\n/', Sanitizer::text((string) ($request->post[$key] ?? ''))) ?: [],
        ), 'strlen'));

        $candidate = $trade;
        $candidate['name']     = $text('name');
        $candidate['name_f']   = $text('name_f');
        $candidate['family']   = (string) ($request->post['family'] ?? '');
        $candidate['rome']     = strtoupper($text('rome'));
        $candidate['summary']  = $text('summary');
        $candidate['intro']    = $text('intro');
        $candidate['missions'] = $lines('missions');
        $candidate['day']      = $text('day');
        $candidate['skills']   = $lines('skills');
        $candidate['training'] = $text('training');
        $candidate['schools']  = $lines('schools');
        $candidate['statut']   = $text('statut');
        $candidate['career']   = $text('career');
        $candidate['search']   = $text('search');
        $candidate['brief'] = [
            'status'   => $text('brief_status'),
            'training' => $text('brief_training'),
            'sectors'  => $text('brief_sectors'),
        ];
        $candidate['pay'] = [
            'min'  => max(0, (int) ($request->post['pay_min'] ?? 0)),
            'max'  => max(0, (int) ($request->post['pay_max'] ?? 0)),
            'unit' => (string) ($request->post['pay_unit'] ?? 'jour'),
            'note' => $text('pay_note'),
        ];
        $candidate['seo'] = [
            'title'       => $text('seo_title'),
            'description' => $text('seo_description'),
        ];

        // Mots-clés : un par ligne ou séparés par des virgules.
        $keywords = preg_split('/[\r\n,;]+/', Sanitizer::text((string) ($request->post['keywords'] ?? ''))) ?: [];
        $candidate['keywords'] = array_values(array_unique(array_filter(array_map('trim', $keywords), 'strlen')));

        $faq = [];
        $questions = (array) ($request->post['faq_q'] ?? []);
        $answers = (array) ($request->post['faq_a'] ?? []);
        foreach ($questions as $i => $question) {
            $q = trim(Sanitizer::text((string) $question));
            $a = trim(Sanitizer::text((string) ($answers[$i] ?? '')));
            if ($q !== '' && $a !== '') {
                $faq[] = ['q' => $q, 'a' => $a];
            } elseif ($q !== '' || $a !== '') {
                $errors[] = 'Question fréquente n° ' . ((int) $i + 1) . ' : il faut la question et sa réponse.';
            }
        }
        $candidate['faq'] = $faq;

        $slugs = [];
        foreach (Index::load('trades') as $row) {
            $slugs[(string) $row['slug']] = (string) $row['id'];
        }
        $checked = array_values(array_filter(
            array_map('strval', (array) ($request->post['related'] ?? [])),
            static fn(string $slug) => isset($slugs[$slug]) && $slug !== $trade['slug'],
        ));
        // Les cases suivent l'ordre des familles, la fiche le sien — c'est lui
        // qui décide des voisins montrés en premier : on le garde, les voisins
        // cochés à l'instant viennent à la suite. Un voisin sans case, dont la
        // fiche n'existe pas encore, reste en place : il s'affichera à son arrivée.
        $related = [];
        foreach ((array) $trade['related'] as $slug) {
            $slug = (string) $slug;
            if (in_array($slug, $checked, true) || !isset($slugs[$slug])) {
                $related[] = $slug;
            }
        }
        foreach ($checked as $slug) {
            $related[] = $slug;
        }
        $candidate['related'] = array_values(array_unique($related));

        if ($candidate['name'] === '') {
            $errors[] = 'Le nom du métier est obligatoire.';
        }
        if (!isset(Trades::families()[$candidate['family']])) {
            $errors[] = 'Choisissez une famille.';
        }
        if (!isset(Trades::UNITS[$candidate['pay']['unit']])) {
            $errors[] = 'Unité de rémunération inconnue.';
        }
        if ($candidate['pay']['max'] > 0 && $candidate['pay']['min'] > $candidate['pay']['max']) {
            $errors[] = 'La rémunération minimale dépasse la maximale.';
        }
        if ($candidate['rome'] !== '' && preg_match('/^[A-N]\d{4}$/', $candidate['rome']) !== 1) {
            $errors[] = 'Code ROME attendu sous la forme L1508.';
        }

        // Nouvelle adresse : l'ancienne rejoint les adresses rattrapées, pour
        // que les liens déjà partagés et indexés continuent de mener ici.
        $wanted = slugify((string) ($request->post['slug'] ?? $trade['slug']));
        if ($wanted === '') {
            $errors[] = 'L’adresse de la fiche ne peut pas être vide.';
        } elseif ($wanted !== $trade['slug']) {
            if (isset($slugs[$wanted]) && $slugs[$wanted] !== $trade['id']) {
                $errors[] = 'Cette adresse est déjà celle d’une autre fiche.';
            } else {
                $former = array_values(array_unique(array_merge((array) $trade['former_slugs'], [(string) $trade['slug']])));
                $candidate['former_slugs'] = array_values(array_diff($former, [$wanted]));
                $candidate['slug'] = $wanted;
            }
        }

        return [$candidate, $errors];
    }

    /* --------------------------------------------------------- référencement */

    /**
     * Titres, descriptions et adresses de toutes les pages du site.
     *
     * Changer l'adresse d'une rubrique ou le slug d'une page laisse derrière
     * elle une redirection permanente : aucun lien ne se casse, et le
     * référencement acquis se reporte sur la nouvelle adresse.
     */
    public function seo(Request $request, array $params): Response
    {
        if (($guard = $this->guard(true)) !== null) {
            return $guard;
        }

        $notice = '';
        $errors = [];

        if ($request->isPost() && Csrf::check($request)) {
            $scope = (string) $request->input('scope', 'routes');

            if ($scope === 'routes') {
                $result = Seo::save(
                    (array) ($request->post['routes'] ?? []),
                    ['og_image' => (string) $request->input('og_image', '')],
                    $this->userId(),
                );
                $errors = $result['errors'];
                $notice = $result['ok'] && $errors === [] ? I18n::t('admin.saved') : '';
            } elseif ($scope === 'pages') {
                $notice = $this->savePagesSeo((array) ($request->post['pages'] ?? []), $errors);
            }
        }

        $pages = [];
        foreach (PageRepository::all('fr') as $page) {
            $pages[] = [
                'slug'        => (string) $page['slug'],
                'title'       => (string) $page['title'],
                'seo_title'   => (string) ($page['seo']['title'] ?? ''),
                'description' => (string) ($page['seo']['description'] ?? ''),
                'robots'      => (string) ($page['seo']['robots'] ?? ''),
                'status'      => (string) $page['status'],
            ];
        }

        return $this->screen('admin/seo', [
            'routes'   => Seo::ROUTES,
            'settings' => Seo::all()['routes'] ?? [],
            'pages'    => $pages,
            'ogImage'  => (string) (Seo::all()['og_image'] ?? ''),
            'notice'   => $notice,
            'errors'   => $errors,
        ], I18n::t('admin.seo'));
    }

    /**
     * Enregistre les métas des pages éditoriales. Un slug modifié déplace le
     * fichier et laisse une redirection derrière lui.
     *
     * @param array<string, array<string,string>> $input
     * @param string[]                            $errors
     */
    private function savePagesSeo(array $input, array &$errors): string
    {
        $changed = 0;

        foreach ($input as $slug => $fields) {
            $slug = slugify((string) $slug);
            $page = PageRepository::find($slug, 'fr');
            if ($page === null) {
                continue;
            }

            $page['title'] = mb_substr(trim((string) ($fields['title'] ?? $page['title'])), 0, 180) ?: $page['title'];
            $page['seo']['title'] = mb_substr(trim((string) ($fields['seo_title'] ?? '')), 0, 180);
            $page['seo']['description'] = mb_substr(trim((string) ($fields['description'] ?? '')), 0, 320);
            $page['seo']['robots'] = ($fields['robots'] ?? '') === 'noindex' ? 'noindex' : '';

            $wanted = slugify((string) ($fields['slug'] ?? $slug));
            if ($wanted !== '' && $wanted !== $slug) {
                if (PageRepository::find($wanted, 'fr') !== null) {
                    $errors[] = sprintf('« %s » : l’adresse /%s est déjà prise.', $page['title'], $wanted);
                } else {
                    $page['slug'] = $wanted;
                    $page['id'] = $wanted;
                    PageRepository::save($page, 'fr');
                    PageRepository::delete($slug, 'fr');
                    // Les traductions suivent le renommage.
                    foreach (array_keys((array) Config::get('i18n.languages', [])) as $lang) {
                        if ($lang === 'fr') {
                            continue;
                        }
                        $translated = PageRepository::find($slug, $lang);
                        if ($translated !== null && ($translated['fallback'] ?? false) === false) {
                            $translated['slug'] = $wanted;
                            $translated['id'] = $wanted;
                            PageRepository::save($translated, $lang);
                            PageRepository::delete($slug, $lang);
                        }
                    }
                    Seo::renamePage($slug, $wanted, $this->userId());
                    $changed++;
                    continue;
                }
            }

            PageRepository::save($page, 'fr');
            $changed++;
        }

        if ($changed > 0) {
            Index::rebuild('pages');
            Audit::log('seo.pages_updated', ['count' => $changed], $this->userId());
        }
        return $errors === [] ? I18n::t('admin.saved') : '';
    }

    /* ------------------------------------------------------ alertes e-mail */

    /**
     * Adresse d'alerte, choix des événements notifiés et transport des
     * e-mails. Le bouton « Tester » envoie un vrai message : c'est la seule
     * vérification qui prouve la chaîne complète.
     */
    public function alerts(Request $request, array $params): Response
    {
        if (($guard = $this->guard(true)) !== null) {
            return $guard;
        }

        $notice = '';
        $errors = [];
        $test = null;
        $posted = [];

        if ($request->isPost() && Csrf::check($request)) {
            // « test » reste accepté sous son ancien nom : un formulaire encore
            // affiché dans un onglet ouvert doit continuer de fonctionner.
            $action = (string) $request->input('test', '') !== ''
                ? 'test'
                : (string) $request->input('action', 'save');

            $values = [];
            foreach (array_keys((array) (Secrets::CATALOG['mail']['keys'] ?? [])) as $key) {
                if (array_key_exists($key, $request->post)) {
                    $values[$key] = (string) $request->post[$key];
                }
            }
            // Gardées de côté : en cas de refus, le formulaire redonne à
            // corriger ce qui vient d'être saisi, jamais l'état précédent.
            $posted = $values;
            $errors = $this->checkMailAddresses($values);

            if ($errors === []) {
                // Cases décochées : le navigateur ne les renvoie pas, on note
                // donc explicitement « off » pour chaque événement absent.
                $checked = (array) ($request->post['events'] ?? []);
                $events = [];
                foreach (array_keys(Notifier::EVENTS) as $event) {
                    $events[$event] = in_array($event, $checked, true) ? 'on' : 'off';
                }
                $values['alert_events'] = $events;

                $clear = array_values(array_filter(
                    (array) ($request->post['clear'] ?? []),
                    static fn($k) => is_string($k) && $k !== '',
                ));

                // Un enregistrement qui échoue — dossier en lecture seule,
                // disque plein — ne doit pas s'annoncer comme réussi : c'est
                // ce qui fait croire qu'une adresse « ne se change pas ».
                if (Secrets::save($values, $clear, $this->userId())) {
                    $notice = I18n::t('admin.saved');
                    $posted = [];
                } else {
                    $errors[] = I18n::t('admin.save_failed');
                }
            }

            // Le test part après l'enregistrement : il éprouve les réglages
            // qui viennent d'être saisis, pas ceux d'avant.
            if ($action === 'test' && $errors === []) {
                $test = ['group' => 'mail'] + SecretsTest::run('mail');
                Audit::log('secrets.tested', ['group' => 'mail', 'ok' => $test['ok']], $this->userId());
            }
        }

        return $this->screen('admin/alerts', [
            'group'      => Secrets::CATALOG['mail'],
            'events'     => Notifier::EVENTS,
            'recipients' => Notifier::recipients(),
            'transport'  => Mailer::transport(),
            'notice'     => $notice,
            'errors'     => $errors,
            'posted'     => $posted,
            'test'       => $test,
        ], I18n::t('admin.alerts'));
    }

    /**
     * Contrôle des adresses saisies sur l'écran des alertes.
     *
     * Une adresse mal formée n'aurait jamais reçu la moindre alerte : elle
     * aurait été enregistrée, réaffichée telle quelle, puis écartée en
     * silence au moment de l'envoi. Le formulaire la refuse.
     *
     * @param  array<string,string> $values
     * @return string[]
     */
    private function checkMailAddresses(array $values): array
    {
        $errors = [];

        foreach (Notifier::split((string) ($values['alert_email'] ?? '')) as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = I18n::t('admin.bad_email', $address);
            }
        }

        $from = trim((string) ($values['mail_from'] ?? ''));
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = I18n::t('admin.bad_email', $from);
        }

        return $errors;
    }

    /* ---------------------------------------------------------------- privé */

    /**
     * Le back-office n'est ouvert qu'aux rôles déclarés dans
     * `security.staff_roles`. Un compte repris de WordPress peut se connecter
     * au site, jamais à l'administration : sa session existe peut-être, elle ne
     * vaut pas autorisation.
     */
    private function guard(bool $adminOnly = false): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/admin', 302);
        }
        if (!Auth::isStaff()) {
            Audit::log('admin.forbidden', [
                'role' => Auth::user()['role'] ?? '',
                'path' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            ], $this->userId());
            Auth::logout();
            return Response::html(
                View::render('pages/error', [
                    'code'  => 403,
                    'title' => I18n::t('error.403_title'),
                    'body'  => I18n::t('error.403_body'),
                    'path'  => '/',
                ]),
                403,
            );
        }
        if ($adminOnly && !Auth::isAdmin()) {
            return Response::redirect('/admin/tableau-de-bord', 302);
        }
        return null;
    }

    /** @return array<string,int> combien de fiches par état, pour les onglets */
    private function countByStatus(array $items): array
    {
        $counts = [];
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'draft');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        return $counts;
    }

    private function userId(): int
    {
        return (int) (Auth::user()['id'] ?? 0);
    }

    /** Rend un écran du back-office (mise en page dédiée, sans barre CTA). */
    private function screen(string $template, array $data, string $title, bool $withNav = true): Response
    {
        Security::sendHeaders();
        return Response::html(View::render($template, $data + [
            'title'   => $title,
            'withNav' => $withNav,
            'user'    => Auth::user(),
            'path'    => '/',
        ], 'admin/layout'));
    }

    /** Publier / dépublier / supprimer une offre ou un CV depuis la liste. */
    private function handleRowAction(Request $request, string $type): void
    {
        if (!$request->isPost() || !Csrf::check($request)) {
            return;
        }
        $id = (string) $request->input('id', '');
        $action = (string) $request->input('action', '');
        $repo = $type === 'job' ? JobRepository::class : CvRepository::class;

        $record = $repo::find($id);
        if ($record === null) {
            return;
        }

        if ($action === 'publish' || $action === 'unpublish') {
            $record['status'] = $action === 'publish' ? 'publish' : 'draft';
            if ($type === 'cv') {
                $record['listed'] = $action === 'publish';
            }
            if ($action === 'publish' && ($record['published_at'] ?? '') === '') {
                $record['published_at'] = date('c');
            }
            // Publier une annonce lui rend une durée de vie entière : sans
            // cela, elle ressortirait déjà périmée.
            if ($action === 'publish' && $type === 'job') {
                $record['expires_at'] = JobLifecycle::expiresAt($record, time());
            }
            $repo::save($record);
        } elseif ($action === 'extend' && $type === 'job') {
            // Remettre une annonce expirée en ligne pour une période complète.
            $record['status'] = 'publish';
            $record['expires_at'] = JobLifecycle::expiresAt($record, time());
            $repo::save($record);
            Audit::log('job.extended', ['id' => $id, 'until' => $record['expires_at']], $this->userId());
        } elseif ($action === 'delete' && Auth::isAdmin()) {
            Backup::snapshot('suppression-' . $type, $this->userId());
            $repo::delete($id);
        } else {
            return;
        }

        Index::rebuild($type === 'job' ? 'jobs' : 'cv');
        Index::rebuild('employers');
        Knowledge::rebuild();
        Audit::log($type . '.' . $action, ['id' => $id], $this->userId());
    }

    private function sortByDate(array $items): array
    {
        usort($items, static fn(array $a, array $b) => strcmp(
            (string) ($b['published_at'] ?: $b['created_at']),
            (string) ($a['published_at'] ?: $a['created_at']),
        ));
        return $items;
    }
}
