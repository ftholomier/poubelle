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
use App\Domain\UserRepository;
use App\Services\Ads;
use App\Services\AdSnippet;
use App\Services\Aggregator;
use App\Services\Auth;
use App\Services\ContentTranslator;
use App\Services\I18n;
use App\Services\Knowledge;
use App\Services\Mailer;
use App\Services\Sanitizer;
use App\Services\Secrets;
use App\Services\SecretsTest;
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
        if (Auth::check()) {
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

        return $this->screen('admin/dashboard', [
            'kpi' => [
                'jobs'  => (int) ($jobFacets['total'] ?? 0),
                'cv'    => (int) ($cvFacets['total'] ?? 0),
                'regie' => count(Audit::recent(200, 'regie.answered')),
                'ads'   => count(array_filter(Ads::slots(), static fn(array $s) => $s['enabled'])),
            ],
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

        return $this->screen('admin/jobs', [
            'items' => $this->sortByDate(JobRepository::all()),
        ], I18n::t('admin.jobs'));
    }

    public function cvs(Request $request, array $params): Response
    {
        if (($guard = $this->guard()) !== null) {
            return $guard;
        }
        $this->handleRowAction($request, 'cv');

        return $this->screen('admin/cvs', [
            'items' => $this->sortByDate(CvRepository::all()),
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
                Audit::log('sources.updated', [], $this->userId());
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
        $test = null;

        if ($request->isPost() && Csrf::check($request)) {
            $action = (string) $request->input('action', 'save');

            if ($action === 'test') {
                $group = (string) $request->input('group', '');
                $test = ['group' => $group] + SecretsTest::run($group);
                Audit::log('secrets.tested', ['group' => $group, 'ok' => $test['ok']], $this->userId());
            } elseif ($action === 'generate') {
                Secrets::save(['app_key' => Secrets::generateKey()], [], $this->userId());
                $notice = I18n::t('admin.saved');
            } else {
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
                $clear = array_values(array_filter(
                    (array) ($request->post['clear'] ?? []),
                    static fn($k) => is_string($k) && $k !== '',
                ));

                Secrets::save($values, $clear, $this->userId());
                $notice = I18n::t('admin.saved');
            }
        }

        return $this->screen('admin/settings', [
            'catalog' => Secrets::CATALOG,
            'slots'   => Ads::slots(),
            'notice'  => $notice,
            'test'    => $test,
        ], I18n::t('admin.settings'));
    }

    /* ---------------------------------------------------------------- privé */

    private function guard(bool $adminOnly = false): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/admin', 302);
        }
        if ($adminOnly && !Auth::isAdmin()) {
            return Response::redirect('/admin/tableau-de-bord', 302);
        }
        return null;
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
            $repo::save($record);
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
