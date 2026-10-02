<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Net;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;
use App\Services\Notify;
use App\Services\Store;

/** Connexion au back-office : identifiants, double authentification, création du premier compte. */
final class AuthController extends Controller
{
    private function guardIp(): void
    {
        $allow = (string) Env::get('ADMIN_IP_ALLOWLIST', '');
        if ($allow !== '' && !Net::ipInList(Request::ip(), $allow)) {
            throw new HttpException(404);
        }
    }

    private function auth(string $view, array $data = [], int $status = 200): Response
    {
        return $this->view('admin/' . $view, $data, 'admin/layout-auth', $status);
    }

    public function loginForm(): Response
    {
        $this->guardIp();
        if (Store::admins()->count() === 0) {
            return $this->redirect(Url::admin('setup'));
        }
        Session::start();
        if (Auth::admin()) {
            return $this->redirect(Url::admin());
        }
        return $this->auth('login', ['title' => 'Connexion']);
    }

    public function login(): Response
    {
        $this->guardIp();
        $email = Request::str('email', 160);
        $res = Auth::attemptAdmin($email, (string) Request::raw('password', ''));
        if ($res === '2fa') {
            return $this->redirect(Url::admin('2fa'));
        }
        if ($res !== 'ok') {
            Session::flash('error', $res === 'locked' ? 'Accès temporairement bloqué après plusieurs échecs. Réessayez dans quelques minutes.' : 'Identifiants incorrects.');
            Session::withInput(['email' => $email]);
            return $this->redirect(Url::admin('login'));
        }
        return $this->afterLogin();
    }

    private function afterLogin(): Response
    {
        $admin = Auth::admin();
        if ($admin && Env::bool('ADMIN_2FA_REQUIRED') && empty($admin['totp_enabled'])) {
            Session::flash('warning', 'La double authentification est obligatoire : activez-la pour continuer.');
            return $this->redirect(Url::admin('mon-compte#2fa'));
        }
        $to = (string) Session::get('admin_intended', '');
        Session::forget('admin_intended');
        if (!str_starts_with($to, Url::admin()) || str_starts_with($to, '//')) {
            $to = Url::admin();
        }
        return $this->redirect($to);
    }

    public function twoFactorForm(): Response
    {
        $this->guardIp();
        if (!Auth::pendingAdmin()) {
            return $this->redirect(Url::admin('login'));
        }
        return $this->auth('2fa', ['title' => 'Double authentification']);
    }

    public function twoFactor(): Response
    {
        $this->guardIp();
        if (!Auth::pendingAdmin()) {
            return $this->redirect(Url::admin('login'));
        }
        if (!Auth::verifyAdmin2fa(Request::str('code', 40))) {
            Session::flash('error', 'Code invalide ou expiré.');
            return $this->redirect(Url::admin('2fa'));
        }
        return $this->afterLogin();
    }

    public function logout(): Response
    {
        Auth::logoutAdmin();
        Session::regenerate();
        Session::flash('success', 'Vous êtes déconnecté.');
        return $this->redirect(Url::admin('login'));
    }

    /** Création du premier administrateur (protégée par le jeton SETUP_TOKEN du fichier .env). */
    public function setup(): Response
    {
        $this->guardIp();
        if (Store::admins()->count() > 0) {
            throw new HttpException(404);
        }
        Session::start();
        $expected = (string) Env::get('SETUP_TOKEN', '');
        $errors = [];
        if (Request::isPost()) {
            if (!Csrf::check()) {
                throw new HttpException(419, 'Session expirée, rechargez la page.');
            }
            if (!RateLimiter::attempt('setup:' . Request::ip(), 10, 3600)) {
                throw new HttpException(429);
            }
            $token = Request::str('token', 200);
            $name = Str::clean(Request::str('name', 80), false);
            $email = Str::email(Request::str('email', 160));
            $pw = (string) Request::raw('password', '');
            if ($expected === '' || !hash_equals($expected, $token)) {
                $errors['token'] = 'Jeton d\'installation incorrect.';
                Logger::security('Jeton d\'installation incorrect');
            }
            if (mb_strlen($name) < 2) {
                $errors['name'] = 'Indiquez votre nom.';
            }
            if (!Str::emailValid($email)) {
                $errors['email'] = 'Email invalide.';
            }
            if (($pe = Auth::passwordError($pw, [$email])) !== null) {
                $errors['password'] = $pe;
            } elseif ($pw !== (string) Request::raw('password2', '')) {
                $errors['password'] = 'Les deux mots de passe ne correspondent pas.';
            }
            if (!$errors) {
                $admin = Store::admins()->insert([
                    'name' => $name,
                    'email' => $email,
                    'role' => 'superadmin',
                    'status' => 'active',
                    'password_hash' => Crypto::hashPassword($pw),
                    'session_version' => 0,
                    'totp_enabled' => false,
                ]);
                Env::write(['SETUP_TOKEN' => '']);
                // contenus de départ : pages légales et articles d'origine du blog
                \App\Services\Pages::seedIfEmpty();
                \App\Services\Blog::seedIfEmpty();
                \App\Services\Push::ensureKeys();
                Logger::security('Premier administrateur créé', ['admin' => $email], 'info');
                Auth::attemptAdmin($email, $pw);
                Notify::admin('security', 'Back-office initialisé', 'Compte super-administrateur créé pour ' . $email, Url::admin('utilisateurs'), 'success');
                Session::flash('success', 'Bienvenue ' . $name . ' ! Pour sécuriser votre compte, activez maintenant la double authentification.');
                return $this->redirect(Url::admin('mon-compte#2fa'));
            }
        }
        return $this->auth('setup', ['title' => 'Installation', 'hasToken' => $expected !== '', 'errors' => $errors, 'prefillToken' => (string) Request::query('token', '')]);
    }
}
