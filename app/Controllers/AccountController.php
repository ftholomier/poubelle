<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Account;
use App\Services\Auth;
use App\Services\I18n;
use App\Services\SpamGuard;

/**
 * Espace membre : connexion, inscription, confirmation, mot de passe oublié,
 * mon compte. Pages hors index, jamais en cache partagé.
 */
final class AccountController extends Controller
{
    public function login(Request $request, array $params): Response
    {
        $return = Account::safeReturn((string) ($request->get('retour') ?? $request->input('retour', '')));
        if (Auth::check()) {
            return Response::redirect($return, 303);
        }
        $error = '';
        if ($request->isPost()) {
            if (!Csrf::check($request)) {
                $error = I18n::t('form.err_csrf');
            } else {
                $result = Auth::login((string) $request->input('email', ''), (string) ($request->post['password'] ?? ''), $request->ip());
                if ($result['ok']) {
                    return Response::redirect($return, 303);
                }
                $error = $result['error'];
            }
        }
        return $this->view('login', ['error' => $error, 'return' => $return,
            'email' => (string) $request->input('email', '')], I18n::t('account.login_title'));
    }

    public function register(Request $request, array $params): Response
    {
        $errors = [];
        $done = false;
        $old = $request->post;
        if ($request->isPost()) {
            $name = trim((string) $request->input('name', ''));
            $email = trim((string) $request->input('email', ''));
            $password = (string) ($request->post['password'] ?? '');
            if (!Csrf::check($request)) {
                $errors['_form'] = I18n::t('form.err_csrf');
            } elseif (SpamGuard::inspect($request, ['title' => $name, 'summary' => ''], 'account')['action'] === 'reject') {
                $errors['_form'] = I18n::t('form.error');
            } else {
                if ($name === '' || mb_strlen($name) > 120) { $errors['name'] = I18n::t('form.err_required'); }
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = I18n::t('form.err_email'); }
                if (mb_strlen($password) < 10) { $errors['password'] = I18n::t('account.err_password'); }
                if (($request->post['terms'] ?? '') === '') { $errors['terms'] = I18n::t('form.err_required'); }
                if ($errors === []) {
                    if (Account::register($name, $email, $password, (string) $request->input('role', 'employer'), $request->ip())) {
                        $done = true;
                    } else {
                        $errors['_form'] = I18n::t('form.err_rate');
                    }
                }
            }
        }
        return $this->view('register', ['errors' => $errors, 'done' => $done, 'old' => $old], I18n::t('account.register_title'));
    }

    public function confirm(Request $request, array $params): Response
    {
        $ok = Account::confirm((string) $request->get('jeton', ''));
        return $this->view('confirm', ['ok' => $ok], I18n::t('account.confirm_title'));
    }

    public function forgot(Request $request, array $params): Response
    {
        $done = false;
        $error = '';
        if ($request->isPost()) {
            if (!Csrf::check($request)) {
                $error = I18n::t('form.err_csrf');
            } else {
                Account::forgot((string) $request->input('email', ''), $request->ip());
                $done = true;   // même message que l'adresse existe ou non
            }
        }
        return $this->view('forgot', ['done' => $done, 'error' => $error], I18n::t('account.forgot_title'));
    }

    public function reset(Request $request, array $params): Response
    {
        $token = (string) ($request->get('jeton') ?? $request->input('jeton', ''));
        $error = '';
        $done = false;
        if ($request->isPost()) {
            if (!Csrf::check($request)) {
                $error = I18n::t('form.err_csrf');
            } elseif (Account::reset($token, (string) ($request->post['password'] ?? ''))) {
                $done = true;
            } else {
                $error = I18n::t('account.err_password');
            }
        }
        return $this->view('reset', ['token' => $token, 'valid' => $done || Account::checkReset($token),
            'done' => $done, 'error' => $error], I18n::t('account.reset_title'));
    }

    public function account(Request $request, array $params): Response
    {
        if (!Auth::check()) {
            return Response::redirect(I18n::url('/connexion') . '?retour=' . rawurlencode(I18n::url('/mon-compte')), 303);
        }
        if ($request->isPost() && Csrf::check($request)) {
            $action = (string) $request->input('action', '');
            if ($action === 'logout') {
                Auth::logout();
                return Response::redirect(I18n::url('/'), 303);
            }
            if ($action === 'delete' && Account::delete((string) Auth::user()['id'])) {
                Auth::logout();
                return Response::redirect(I18n::url('/'), 303);
            }
        }
        return $this->view('account', ['user' => Auth::user()], I18n::t('nav.account'));
    }

    private function view(string $mode, array $data, string $title): Response
    {
        return $this->page('pages/account', $data + ['mode' => $mode], [
            'title'  => $title,
            'path'   => I18n::stripPrefix((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)),
            'robots' => 'noindex, nofollow',
        ])->withHeader('Cache-Control', 'private, no-store')->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
