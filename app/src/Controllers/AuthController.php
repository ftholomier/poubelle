<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Content\Settings;
use App\Core\Installer;
use App\Core\View;
use App\Http\Request;
use App\Http\Response;
use App\Mail\Mailer;
use App\Security\Auth;
use App\Security\Csrf;
use App\Security\Session;

/**
 * Connexion, déconnexion, mot de passe oublié, installation initiale.
 */
final class AuthController
{
    public static function login(Request $request): void
    {
        Session::start();
        Response::securityHeaders(true);

        if (Installer::needsSetup()) {
            Response::redirect('/admin/installation');
        }
        if (Auth::check()) {
            Response::redirect('/admin');
        }

        $error = null;
        if ($request->isPost()) {
            if (!Csrf::check($request->str('_token'), 'login')) {
                $error = 'Session expirée. Merci de réessayer.';
            } else {
                $result = Auth::attempt($request->str('email'), (string) $request->input('password', ''), $request->ip());
                if ($result['ok']) {
                    Csrf::rotate('login');
                    $target = (string) Session::get('_intended', '/admin');
                    Session::forget('_intended');
                    Response::redirect($target);
                }
                $error = match ($result['error'] ?? '') {
                    'too_many' => 'Trop de tentatives. Réessayez dans quelques minutes.',
                    default    => 'Identifiants incorrects.',
                };
            }
        }

        View::display('admin/login', [
            'error'    => $error,
            'email'    => $request->str('email'),
            'settings' => Settings::all(),
        ]);
    }

    public static function logout(Request $request): never
    {
        Session::start();
        if ($request->isPost() && Csrf::check($request->str('_token'), 'logout')) {
            Auth::logout();
        } elseif (!$request->isPost()) {
            Auth::logout();
        }
        Response::redirect('/admin/login');
    }

    public static function forgot(Request $request): void
    {
        Session::start();
        Response::securityHeaders(true);

        $sent  = false;
        $error = null;

        if ($request->isPost()) {
            if (!Csrf::check($request->str('_token'), 'forgot')) {
                $error = 'Session expirée. Merci de réessayer.';
            } elseif (!\App\Security\RateLimiter::hit('forgot', $request->ip(), 5, 900)) {
                $error = 'Trop de demandes. Réessayez dans quelques minutes.';
            } else {
                $reset = Auth::createResetToken($request->str('email'));
                if ($reset !== null) {
                    $link = $request->baseUrl() . '/admin/reinitialisation?token=' . urlencode($reset['token']);
                    Mailer::send(
                        (string) $reset['user']['email'],
                        'Réinitialisation de votre mot de passe',
                        '<div style="font-family:Arial,sans-serif;color:#0B1B33">'
                        . '<h2>Réinitialisation du mot de passe</h2>'
                        . '<p>Bonjour ' . htmlspecialchars((string) $reset['user']['name'], ENT_QUOTES) . ',</p>'
                        . '<p>Vous avez demandé la réinitialisation de votre mot de passe d’administration.</p>'
                        . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '" '
                        . 'style="display:inline-block;padding:12px 22px;background:#1B4F91;color:#fff;'
                        . 'border-radius:10px;text-decoration:none">Choisir un nouveau mot de passe</a></p>'
                        . '<p style="color:#5C6B80;font-size:13px">Ce lien expire dans une heure. '
                        . 'Si vous n’êtes pas à l’origine de cette demande, ignorez simplement ce message.</p></div>'
                    );
                }
                // Réponse identique dans tous les cas : pas d'énumération de comptes.
                $sent = true;
            }
        }

        View::display('admin/forgot', ['sent' => $sent, 'error' => $error, 'settings' => Settings::all()]);
    }

    public static function reset(Request $request): void
    {
        Session::start();
        Response::securityHeaders(true);

        $token = $request->str('token');
        $error = null;
        $done  = false;

        if ($request->isPost()) {
            if (!Csrf::check($request->str('_token'), 'reset')) {
                $error = 'Session expirée. Merci de réessayer.';
            } else {
                $password = (string) $request->input('password', '');
                $confirm  = (string) $request->input('password_confirm', '');
                if ($password !== $confirm) {
                    $error = 'Les deux mots de passe ne correspondent pas.';
                } else {
                    $result = Auth::resetPassword($token, $password);
                    if ($result['ok']) {
                        $done = true;
                        Csrf::rotate('reset');
                    } else {
                        $error = match ($result['error']) {
                            'invalid_token' => 'Ce lien n’est plus valable.',
                            'expired_token' => 'Ce lien a expiré. Refaites une demande.',
                            'too_short'     => 'Le mot de passe doit contenir au moins 10 caractères.',
                            'too_weak'      => 'Le mot de passe doit mêler lettres et chiffres.',
                            default         => 'Réinitialisation impossible.',
                        };
                    }
                }
            }
        }

        View::display('admin/reset', [
            'token'    => $token,
            'error'    => $error,
            'done'     => $done,
            'settings' => Settings::all(),
        ]);
    }

    /** Création du tout premier compte (uniquement si aucun n'existe). */
    public static function setup(Request $request): void
    {
        Session::start();
        Response::securityHeaders(true);

        if (!Installer::needsSetup()) {
            Response::redirect('/admin/login');
        }

        $error = null;
        if ($request->isPost()) {
            if (!Csrf::check($request->str('_token'), 'setup')) {
                $error = 'Session expirée. Merci de réessayer.';
            } else {
                $password = (string) $request->input('password', '');
                if ($password !== (string) $request->input('password_confirm', '')) {
                    $error = 'Les deux mots de passe ne correspondent pas.';
                } else {
                    $result = Auth::createUser(
                        $request->str('name'),
                        $request->str('email'),
                        $password,
                        Auth::ROLE_ADMIN
                    );
                    if ($result['ok']) {
                        \App\Content\Bootstrap::seedIfEmpty();
                        Auth::attempt($request->str('email'), $password, $request->ip());
                        Response::redirect('/admin');
                    }
                    $error = match ($result['error']) {
                        'invalid_email' => 'Adresse e-mail invalide.',
                        'email_taken'   => 'Cette adresse est déjà utilisée.',
                        'too_short'     => 'Le mot de passe doit contenir au moins 10 caractères.',
                        'too_weak'      => 'Le mot de passe doit mêler lettres et chiffres.',
                        default         => 'Création impossible.',
                    };
                }
            }
        }

        View::display('admin/setup', ['error' => $error, 'settings' => Settings::all()]);
    }
}
