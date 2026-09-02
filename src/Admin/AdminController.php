<?php

declare(strict_types=1);

namespace App\Admin;

use App\Content;
use App\Http\Response;
use App\Http\Router;
use App\Theme\Palette;
use App\View;

/**
 * Back-office : tout ce qui n'a pas à être public.
 *
 * On y règle la couleur dominante du site, on y compose les dessins en
 * particules et on les affecte à la section de son choix, page par page.
 */
final class AdminController
{
    public static function register(Router $router): void
    {
        $router
            ->get('/admin', [self::class, 'dashboard'])
            ->get('/admin/connexion', [self::class, 'loginForm'])
            ->post('/admin/connexion', [self::class, 'login'])
            ->post('/admin/deconnexion', [self::class, 'logout'])
            ->get('/admin/pages', [self::class, 'pages'])
            ->post('/admin/pages', [self::class, 'createPage'])
            ->post('/admin/pages/ordre', [self::class, 'reorderPages'])
            ->get('/admin/page/{slug}', [self::class, 'editPage'])
            ->post('/admin/page/{slug}', [self::class, 'savePage'])
            ->post('/admin/page/{slug}/supprimer', [self::class, 'deletePage'])
            ->post('/admin/page/{slug}/section', [self::class, 'addSection'])
            ->get('/admin/page/{slug}/section/{id}', [self::class, 'editSection'])
            ->post('/admin/page/{slug}/section/{id}', [self::class, 'saveSection'])
            ->post('/admin/page/{slug}/section/{id}/deplacer', [self::class, 'moveSection'])
            ->post('/admin/page/{slug}/section/{id}/supprimer', [self::class, 'deleteSection'])
            ->get('/admin/formes', [self::class, 'shapeStudio'])
            ->post('/admin/formes', [self::class, 'saveShape'])
            ->get('/admin/palette', [self::class, 'palette'])
            ->get('/admin/theme', [self::class, 'themeForm'])
            ->post('/admin/theme', [self::class, 'saveTheme']);
    }

    // ------------------------------------------------------------ Connexion

    public static function loginForm(): void
    {
        if (Auth::isLoggedIn()) {
            self::redirect('/admin');
            return;
        }

        self::renderBare('admin/login', [
            'configured' => Auth::isConfigured(),
            'csrf'       => Auth::csrfToken(),
            'error'      => null,
            'email'      => '',
        ]);
    }

    public static function login(): void
    {
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
            self::renderBare('admin/login', [
                'configured' => Auth::isConfigured(),
                'csrf'       => Auth::csrfToken(),
                'error'      => 'Session expirée, veuillez réessayer.',
                'email'      => (string) ($_POST['email'] ?? ''),
            ]);
            return;
        }

        $result = Auth::attempt(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        if ($result['ok']) {
            self::redirect('/admin');
            return;
        }

        // Un échec de connexion mérite un code de réponse honnête.
        http_response_code(401);
        self::renderBare('admin/login', [
            'configured' => Auth::isConfigured(),
            'csrf'       => Auth::csrfToken(),
            'error'      => $result['message'],
            // L'adresse saisie est conservée : seul le mot de passe est à retaper.
            'email'      => (string) ($_POST['email'] ?? ''),
        ]);
    }

    public static function logout(): void
    {
        if (Auth::checkCsrf($_POST['csrf'] ?? null)) {
            Auth::logout();
        }
        self::redirect('/admin/connexion');
    }

    // -------------------------------------------------------------- Écrans

    public static function dashboard(): void
    {
        if (!self::guard()) {
            return;
        }

        $pages = Content::pages();
        $sectionCount = array_sum(array_map(static fn(array $p): int => count($p['sections']), $pages));

        self::renderBare('admin/dashboard', [
            'pages'        => $pages,
            'sectionCount' => $sectionCount,
            'site'         => Content::site(),
            'csrf'         => Auth::csrfToken(),
        ]);
    }

    public static function pages(): void
    {
        if (!self::guard()) {
            return;
        }

        self::renderBare('admin/pages', [
            'pages' => Content::pages(),
            'kinds' => SectionSchema::all(),
            'site'  => Content::site(),
            'csrf'  => Auth::csrfToken(),
        ]);
    }

    // ------------------------------------------------------ Pages et sections

    public static function createPage(): void
    {
        self::mutate('/admin/pages', function (): string {
            $slug = ContentWriter::createPage([
                'title'       => $_POST['title'] ?? '',
                'navLabel'    => $_POST['navLabel'] ?? '',
                'order'       => $_POST['order'] ?? 99,
                'inNav'       => isset($_POST['inNav']),
                'description' => $_POST['description'] ?? '',
                'kind'        => $_POST['kind'] ?? 'hero',
            ]);

            self::flash("Page « {$slug} » créée.");

            return '/admin/page/' . rawurlencode($slug);
        });
    }

    public static function reorderPages(): void
    {
        self::mutate('/admin/pages', function (): string {
            // Le formulaire envoie un numéro par page ; on trie dessus puis on
            // renumérote proprement de 1 à n, sans trous ni ex æquo.
            $ranks = is_array($_POST['rang'] ?? null) ? $_POST['rang'] : [];
            $ranks = array_map('intval', $ranks);
            asort($ranks);

            ContentWriter::reorderPages(array_map('strval', array_keys($ranks)));
            self::flash('Ordre du menu enregistré.');

            return '/admin/pages';
        });
    }

    /**
     * @param array<string,string> $params
     */
    public static function editPage(array $params): void
    {
        if (!self::guard()) {
            return;
        }

        $page = Content::isValidSlug($params['slug'] ?? '') ? Content::page($params['slug']) : null;
        if ($page === null) {
            self::flash('Page inconnue.', 'error');
            self::redirect('/admin/pages');
            return;
        }

        self::renderBare('admin/page', [
            'page'   => $page,
            'kinds'  => SectionSchema::all(),
            'isHome' => $page['slug'] === Content::HOME,
            'csrf'   => Auth::csrfToken(),
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public static function savePage(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        self::mutate('/admin/page/' . rawurlencode($slug), function () use ($slug): string {
            ContentWriter::updatePage($slug, [
                'title'       => $_POST['title'] ?? '',
                'navLabel'    => $_POST['navLabel'] ?? '',
                'order'       => $_POST['order'] ?? 99,
                'inNav'       => isset($_POST['inNav']),
                'description' => $_POST['description'] ?? '',
            ]);
            self::flash('Réglages de la page enregistrés.');

            return '/admin/page/' . rawurlencode($slug);
        });
    }

    /**
     * @param array<string,string> $params
     */
    public static function deletePage(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        self::mutate('/admin/page/' . rawurlencode($slug), function () use ($slug): string {
            ContentWriter::deletePage($slug);
            self::flash("Page « {$slug} » supprimée. Une copie reste dans var/backups/.");

            return '/admin/pages';
        });
    }

    /**
     * @param array<string,string> $params
     */
    public static function addSection(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        self::mutate('/admin/page/' . rawurlencode($slug), function () use ($slug): string {
            $id = ContentWriter::addSection($slug, (string) ($_POST['kind'] ?? ''), (string) ($_POST['id'] ?? ''));
            self::flash("Section « {$id} » ajoutée.");

            return '/admin/page/' . rawurlencode($slug) . '/section/' . rawurlencode($id);
        });
    }

    /**
     * @param array<string,string> $params
     */
    public static function editSection(array $params): void
    {
        if (!self::guard()) {
            return;
        }

        $slug = (string) ($params['slug'] ?? '');
        $section = Content::isValidSlug($slug) ? Content::section($slug, (string) ($params['id'] ?? '')) : null;
        if ($section === null) {
            self::flash('Section inconnue.', 'error');
            self::redirect('/admin/pages');
            return;
        }

        $kind = (string) ($section['kind'] ?? 'statement');
        $schema = SectionSchema::forKind($kind);
        if ($schema === null) {
            self::flash("Le type « {$kind} » n'a pas de formulaire d'édition.", 'error');
            self::redirect('/admin/page/' . rawurlencode($slug));
            return;
        }

        self::renderBare('admin/section', [
            'page'    => Content::page($slug),
            'section' => $section,
            'schema'  => $schema,
            'csrf'    => Auth::csrfToken(),
        ]);
    }

    /**
     * @param array<string,string> $params
     */
    public static function saveSection(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $id = (string) ($params['id'] ?? '');
        $back = '/admin/page/' . rawurlencode($slug) . '/section/' . rawurlencode($id);

        self::mutate($back, function () use ($slug, $id, $back): string {
            ContentWriter::updateSection($slug, $id, $_POST['champ'] ?? []);
            self::flash('Contenu enregistré.');

            return $back;
        });
    }

    /**
     * @param array<string,string> $params
     */
    public static function moveSection(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $back = '/admin/page/' . rawurlencode($slug);

        self::mutate($back, function () use ($slug, $params, $back): string {
            ContentWriter::moveSection(
                $slug,
                (string) ($params['id'] ?? ''),
                ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down'
            );

            return $back;
        });
    }

    /**
     * @param array<string,string> $params
     */
    public static function deleteSection(array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $back = '/admin/page/' . rawurlencode($slug);

        self::mutate($back, function () use ($slug, $params, $back): string {
            $id = (string) ($params['id'] ?? '');
            ContentWriter::deleteSection($slug, $id);
            self::flash("Section « {$id} » supprimée.");

            return $back;
        });
    }

    public static function shapeStudio(): void
    {
        if (!self::guard()) {
            return;
        }

        // La cible éventuelle est passée en paramètre : « régler la forme de
        // telle section » ouvre directement l'atelier sur cette section.
        $target = null;
        $page = (string) ($_GET['page'] ?? '');
        $section = (string) ($_GET['section'] ?? '');
        if ($page !== '' && $section !== '' && Content::section($page, $section) !== null) {
            $target = ['page' => $page, 'section' => $section];
        }

        self::renderBare('admin/shapes', [
            'pages'  => Content::pages(),
            'site'   => Content::site(),
            'target' => $target,
            'csrf'   => Auth::csrfToken(),
        ]);
    }

    /**
     * Enregistrement d'une forme, appelé depuis l'atelier.
     */
    public static function saveShape(): void
    {
        if (!Auth::isLoggedIn()) {
            Response::error('Session expirée.', 401);
            return;
        }

        $payload = self::jsonBody();
        if (!Auth::checkCsrf($payload['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
            Response::error('Jeton de sécurité invalide.', 419);
            return;
        }

        try {
            ContentWriter::saveSectionShape(
                (string) ($payload['page'] ?? ''),
                (string) ($payload['section'] ?? ''),
                is_array($payload['shape'] ?? null) ? $payload['shape'] : []
            );
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 422, $e->getMessage());
            return;
        }

        Response::json([
            'ok'      => true,
            'message' => sprintf(
                'Forme enregistrée sur %s / %s.',
                $payload['page'],
                $payload['section']
            ),
        ]);
    }

    /**
     * Palette dérivée à la volée, pour l'aperçu en direct de l'éditeur.
     *
     * L'aperçu interroge PHP plutôt que de refaire le calcul en JavaScript :
     * une seule implémentation de la dérivation, donc aucun risque d'écart
     * entre ce qui est prévisualisé et ce qui sera réellement affiché.
     */
    public static function palette(): void
    {
        if (!Auth::isLoggedIn()) {
            Response::error('Session expirée.', 401);
            return;
        }

        try {
            $palette = Palette::build([
                'dominant' => (string) ($_GET['dominant'] ?? '#7b01f7'),
                'harmony'  => (string) ($_GET['harmony'] ?? 'duo'),
            ]);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 422, $e->getMessage());
            return;
        }

        Response::json($palette);
    }

    public static function themeForm(): void
    {
        if (!self::guard()) {
            return;
        }

        self::renderBare('admin/theme', [
            'site'      => Content::site(),
            'harmonies' => Palette::HARMONIES,
            'csrf'      => Auth::csrfToken(),
            'saved'     => isset($_GET['ok']),
            'error'     => null,
        ]);
    }

    public static function saveTheme(): void
    {
        if (!self::guard()) {
            return;
        }

        $error = null;
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
            $error = 'Session expirée, veuillez réessayer.';
        } else {
            try {
                ContentWriter::saveTheme([
                    'dominant' => (string) ($_POST['dominant'] ?? ''),
                    'harmony'  => (string) ($_POST['harmony'] ?? 'duo'),
                ]);
                self::redirect('/admin/theme?ok=1');
                return;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        http_response_code(422);
        self::renderBare('admin/theme', [
            'site'      => Content::site(),
            'harmonies' => Palette::HARMONIES,
            'csrf'      => Auth::csrfToken(),
            'saved'     => false,
            'error'     => $error,
        ]);
    }

    // -------------------------------------------------------------- Outils

    /**
     * Enveloppe commune aux écritures : session ouverte, jeton valide, erreurs
     * transformées en message plutôt qu'en page blanche, et redirection finale.
     * Rediriger après une écriture évite qu'un rafraîchissement ne la rejoue.
     *
     * @param callable():string $work rend l'adresse où aller ensuite
     */
    private static function mutate(string $fallback, callable $work): void
    {
        if (!self::guard()) {
            return;
        }

        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
            self::flash('Session expirée, veuillez recommencer.', 'error');
            self::redirect($fallback);
            return;
        }

        try {
            $destination = $work();
        } catch (\Throwable $e) {
            self::flash($e->getMessage(), 'error');
            self::redirect($fallback);
            return;
        }

        self::redirect($destination);
    }

    /** Dépose un message affiché une seule fois, après la redirection. */
    public static function flash(string $message, string $level = 'ok'): void
    {
        Auth::startSession();
        $_SESSION['admin_flash'] = ['message' => $message, 'level' => $level];
    }

    /**
     * @return array{message: string, level: string}|null
     */
    public static function takeFlash(): ?array
    {
        Auth::startSession();
        $flash = $_SESSION['admin_flash'] ?? null;
        unset($_SESSION['admin_flash']);

        return is_array($flash) ? $flash : null;
    }

    /** Redirige vers la connexion si la session n'est pas ouverte. */
    private static function guard(): bool
    {
        if (Auth::isLoggedIn()) {
            return true;
        }
        self::redirect('/admin/connexion');

        return false;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function renderBare(string $template, array $data): void
    {
        // Le back-office n'a rien à faire dans un index de moteur de recherche.
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Referrer-Policy: same-origin');
        }
        View::render($template, $data + ['site' => Content::site()], 'admin/layout');
    }

    private static function redirect(string $path): void
    {
        if (!headers_sent()) {
            header('Location: ' . $path, true, 302);
        }
        echo '<!doctype html><meta charset="utf-8"><a href="' . View::e($path) . '">Continuer</a>';
    }

    /**
     * @return array<string,mixed>
     */
    private static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
