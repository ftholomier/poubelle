<?php
declare(strict_types=1);

namespace App\Core;

/** Classe de base des contrôleurs. */
abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'front/layout', int $status = 200): Response
    {
        return Response::html(View::render($template, $data, $layout), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function back(string $fallback = '/'): Response
    {
        return Response::back($fallback);
    }

    protected function notFound(string $message = ''): never
    {
        throw new HttpException(404, $message);
    }

    protected function forbidden(string $message = 'Accès refusé'): never
    {
        throw new HttpException(403, $message);
    }

    protected function flash(string $type, string $msg): void
    {
        Session::flash($type, $msg);
    }

    /** Valide le jeton CSRF des formulaires de session (espace pro, back-office). */
    protected function requireCsrf(): void
    {
        if (!Csrf::check()) {
            Logger::security('Jeton CSRF invalide', ['path' => Request::path()]);
            throw new HttpException(419, 'Votre session a expiré. Rechargez la page et réessayez.');
        }
    }
}
