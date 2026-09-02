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
        ]);
    }

    public static function login(): void
    {
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
            self::renderBare('admin/login', [
                'configured' => Auth::isConfigured(),
                'csrf'       => Auth::csrfToken(),
                'error'      => 'Session expirée, veuillez réessayer.',
            ]);
            return;
        }

        $result = Auth::attempt((string) ($_POST['password'] ?? ''));
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
            'site'  => Content::site(),
            'csrf'  => Auth::csrfToken(),
        ]);
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
