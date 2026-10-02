<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Totp;
use App\Core\Url;
use App\Services\Mail;
use App\Services\Settings;
use App\Services\Store;

/** Comptes administrateurs : rôles, double authentification, mon compte. */
final class UsersController extends AdminController
{
    public const ROLES = [
        'superadmin' => ['Super-administrateur', 'Tout, y compris la configuration (.env) et les utilisateurs'],
        'admin' => ['Administrateur', 'Tout sauf la configuration (.env) et les utilisateurs'],
        'moderator' => ['Modérateur', 'Pros, demandes, messages, avis'],
        'editor' => ['Rédacteur', 'Blog, pages, SEO'],
    ];

    public function index(): Response
    {
        $items = Store::admins()->find(null, static fn ($a, $b) => strcmp($a['name'], $b['name']), 200)['items'];
        return $this->page('users', ['items' => $items], 'Utilisateurs', 'users');
    }

    public function edit(?int $id = null): Response
    {
        $user = $id ? Store::admins()->get($id) : null;
        if ($id && !$user) {
            throw new HttpException(404);
        }
        if (Request::isPost()) {
            $action = (string) Request::input('action', 'save');
            if ($user && $action !== 'save') {
                return $this->userAction($user, $action);
            }
            $name = Str::clean(Request::str('name', 80), false);
            $email = Str::email(Request::str('email', 160));
            $role = array_key_exists((string) Request::input('role'), self::ROLES) ? (string) Request::input('role') : 'moderator';
            $status = Request::input('status') === 'disabled' ? 'disabled' : 'active';
            if (mb_strlen($name) < 2 || !Str::emailValid($email)) {
                return $this->done('Nom et email valides obligatoires.', $id ? 'utilisateurs/' . $id : 'utilisateurs/nouveau', 'error');
            }
            foreach (Store::admins()->iterate() as $aid => $a) {
                if ((int) $aid !== (int) $id && strtolower($a['email']) === $email) {
                    return $this->done('Un compte utilise déjà cet email.', $id ? 'utilisateurs/' . $id : 'utilisateurs/nouveau', 'error');
                }
            }
            if ($user && (int) $user['id'] === (int) $this->current()['id'] && ($role !== 'superadmin' || $status !== 'active')) {
                return $this->done('Vous ne pouvez pas retirer vos propres droits de super-administrateur.', 'utilisateurs/' . $id, 'error');
            }
            if ($user) {
                $changed = ($user['role'] ?? '') !== $role || ($user['status'] ?? '') !== $status;
                Store::admins()->update((int) $id, ['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status, 'session_version' => (int) ($user['session_version'] ?? 0) + ($changed ? 1 : 0)]);
                $this->audit('Compte administrateur modifié', ['compte' => $email, 'role' => $role, 'statut' => $status]);
                return $this->done('Compte enregistré.', 'utilisateurs/' . $id);
            }
            $temp = self::tempPassword();
            $new = Store::admins()->insert([
                'name' => $name, 'email' => $email, 'role' => $role, 'status' => $status,
                'password_hash' => Crypto::hashPassword($temp), 'must_change_password' => true, 'session_version' => 0, 'totp_enabled' => false,
            ]);
            $this->audit('Compte administrateur créé', ['compte' => $email, 'role' => $role]);
            Mail::send($email, 'admin_alert', ['titre' => 'Votre accès au back-office', 'corps' => 'Un compte « ' . self::ROLES[$role][0] . ' » vient d\'être créé pour vous sur ' . Settings::siteName() . '. Votre mot de passe provisoire vous sera communiqué par la personne qui a créé le compte. Il vous sera demandé de le changer à la première connexion.'], ['bouton_url' => Url::admin('login'), 'bouton_label' => 'Se connecter']);
            Session::flash('warning', 'Mot de passe provisoire à transmettre à ' . $name . ' (affiché une seule fois) : ' . $temp);
            return $this->redirect(Url::admin('utilisateurs/' . $new['id']));
        }
        $devices = $user ? array_map(static fn ($sid) => Store::pushSubs()->get($sid), Store::pushSubs()->ids('owner', 'admin:' . $user['id'])) : [];
        return $this->page('user', ['user' => $user, 'roles' => self::ROLES, 'devices' => array_filter($devices)], $user ? ($user['name'] ?: $user['email']) : 'Nouvel utilisateur', 'users');
    }

    private function userAction(array $user, string $action): Response
    {
        $id = (int) $user['id'];
        $self = $id === (int) $this->current()['id'];
        switch ($action) {
            case 'reset-password':
                $temp = self::tempPassword();
                Store::admins()->update($id, ['password_hash' => Crypto::hashPassword($temp), 'must_change_password' => true, 'session_version' => (int) ($user['session_version'] ?? 0) + 1]);
                $this->audit('Mot de passe administrateur réinitialisé', ['compte' => $user['email']]);
                Session::flash('warning', 'Nouveau mot de passe provisoire (affiché une seule fois) : ' . $temp);
                return $this->redirect(Url::admin('utilisateurs/' . $id));
            case 'disable-2fa':
                Store::admins()->update($id, ['totp_enabled' => false, 'totp_secret' => null, 'recovery_codes' => []]);
                $this->audit('2FA désactivée par un administrateur', ['compte' => $user['email']]);
                return $this->done('Double authentification désactivée pour ce compte.', 'utilisateurs/' . $id, 'warning');
            case 'logout':
                Store::admins()->update($id, ['session_version' => (int) ($user['session_version'] ?? 0) + 1]);
                $this->audit('Sessions administrateur révoquées', ['compte' => $user['email']]);
                return $this->done('Toutes les sessions de ce compte ont été fermées.', $self ? 'login' : 'utilisateurs/' . $id);
            case 'unlock':
                Store::admins()->update($id, ['locked_until' => null, 'failed_logins' => 0]);
                return $this->done('Compte déverrouillé.', 'utilisateurs/' . $id);
            case 'delete':
                if ($self) {
                    return $this->done('Vous ne pouvez pas supprimer votre propre compte.', 'utilisateurs/' . $id, 'error');
                }
                $supers = Store::admins()->count(static fn ($a) => $a['role'] === 'superadmin' && $a['status'] === 'active');
                if (($user['role'] ?? '') === 'superadmin' && $supers <= 1) {
                    return $this->done('Impossible : il doit rester au moins un super-administrateur.', 'utilisateurs/' . $id, 'error');
                }
                Store::admins()->delete($id);
                foreach (Store::pushSubs()->ids('owner', 'admin:' . $id) as $sid) {
                    Store::pushSubs()->delete($sid);
                }
                $this->audit('Compte administrateur supprimé', ['compte' => $user['email']]);
                return $this->done('Compte supprimé.', 'utilisateurs');
        }
        return $this->done('Action inconnue.', 'utilisateurs/' . $id, 'error');
    }

    private static function tempPassword(): string
    {
        return Str::random(6) . '-' . Str::random(6) . '-' . random_int(10, 99);
    }

    // ---------------------------------------------------------------- mon compte

    public function me(): Response
    {
        $me = $this->current();
        $id = (int) $me['id'];
        if (Request::isPost()) {
            $form = (string) Request::input('form', '');
            switch ($form) {
                case 'profile':
                    $name = Str::clean(Request::str('name', 80), false);
                    if (mb_strlen($name) >= 2) {
                        Store::admins()->update($id, ['name' => $name]);
                    }
                    return $this->done('Profil enregistré.', 'mon-compte');
                case 'password':
                    if (!Crypto::verifyPassword((string) Request::raw('current', ''), (string) $me['password_hash'])) {
                        return $this->done('Mot de passe actuel incorrect.', 'mon-compte#securite', 'error');
                    }
                    $pw = (string) Request::raw('password', '');
                    if (($err = Auth::passwordError($pw, [$me['email']])) !== null || $pw !== (string) Request::raw('password2', '')) {
                        return $this->done($err ?? 'Les deux mots de passe ne correspondent pas.', 'mon-compte#securite', 'error');
                    }
                    $sv = (int) ($me['session_version'] ?? 0) + 1;
                    Store::admins()->update($id, ['password_hash' => Crypto::hashPassword($pw), 'must_change_password' => false, 'session_version' => $sv, 'password_changed_at' => date('c')]);
                    Session::set('admin_sv', $sv);
                    Logger::security('Mot de passe administrateur modifié', ['admin' => $me['email']], 'info');
                    return $this->done('Mot de passe modifié. Vos autres sessions ont été fermées.', 'mon-compte');
                case '2fa-start':
                    Session::set('totp_pending', Totp::secret());
                    return $this->redirect(Url::admin('mon-compte#2fa'));
                case '2fa-confirm':
                    $secret = (string) Session::get('totp_pending', '');
                    if ($secret === '' || !Totp::verify($secret, Request::str('code', 10))) {
                        return $this->done('Code incorrect : vérifiez l\'heure de votre téléphone et réessayez.', 'mon-compte#2fa', 'error');
                    }
                    $rc = Totp::recoveryCodes(8);
                    Store::admins()->update($id, ['totp_enabled' => true, 'totp_secret' => Crypto::encrypt($secret), 'totp_last_step' => Totp::lastStep(), 'recovery_codes' => $rc['hashes']]);
                    Session::forget('totp_pending');
                    Session::set('recovery_once', $rc['codes']);
                    Logger::security('2FA activée', ['admin' => $me['email']], 'info');
                    return $this->done('Double authentification activée ✔ Conservez précieusement vos codes de secours.', 'mon-compte#2fa');
                case '2fa-disable':
                    if (!Crypto::verifyPassword((string) Request::raw('current', ''), (string) $me['password_hash'])) {
                        return $this->done('Mot de passe incorrect.', 'mon-compte#2fa', 'error');
                    }
                    if (\App\Core\Env::bool('ADMIN_2FA_REQUIRED')) {
                        return $this->done('La double authentification est obligatoire sur ce site.', 'mon-compte#2fa', 'error');
                    }
                    Store::admins()->update($id, ['totp_enabled' => false, 'totp_secret' => null, 'recovery_codes' => []]);
                    Logger::security('2FA désactivée', ['admin' => $me['email']]);
                    return $this->done('Double authentification désactivée.', 'mon-compte#2fa', 'warning');
                case 'recovery':
                    if (empty($me['totp_enabled'])) {
                        break;
                    }
                    $rc = Totp::recoveryCodes(8);
                    Store::admins()->update($id, ['recovery_codes' => $rc['hashes']]);
                    Session::set('recovery_once', $rc['codes']);
                    return $this->done('Nouveaux codes de secours générés (les anciens ne fonctionnent plus).', 'mon-compte#2fa');
                case 'device-delete':
                    $sid = Request::int('device');
                    $sub = Store::pushSubs()->get($sid);
                    if ($sub && ($sub['owner'] ?? '') === 'admin:' . $id) {
                        Store::pushSubs()->delete($sid);
                    }
                    return $this->done('Appareil retiré.', 'mon-compte#appareils');
            }
            return $this->redirect(Url::admin('mon-compte'));
        }
        \App\Services\Push::ensureKeys();
        $pending = (string) Session::get('totp_pending', '');
        $recovery = Session::get('recovery_once');
        Session::forget('recovery_once');
        $devices = array_filter(array_map(static fn ($sid) => Store::pushSubs()->get($sid), Store::pushSubs()->ids('owner', 'admin:' . $id)));
        return $this->page('me', [
            'me' => $me,
            'pending' => $pending,
            'otpUri' => $pending !== '' ? Totp::uri($pending, (string) $me['email'], Settings::siteName()) : '',
            'recovery' => is_array($recovery) ? $recovery : null,
            'devices' => $devices,
            'roles' => self::ROLES,
            'scripts' => [\App\Core\Url::asset('vendor/qrcode/qrcode.js')],
        ], 'Mon compte', '');
    }
}
