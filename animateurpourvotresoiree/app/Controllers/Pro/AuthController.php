<?php
declare(strict_types=1);

namespace App\Controllers\Pro;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Url;
use App\Services\Mail;
use App\Services\Notify;
use App\Services\Pros;
use App\Services\Settings;
use App\Services\Store;

/** Connexion des pros (ancien identifiant ou email), mot de passe oublié, confirmation d'email. */
final class AuthController extends Controller
{
    public function loginForm(): Response
    {
        if (Auth::pro()) {
            return $this->redirect('/espace-pro/');
        }
        Session::start();
        return $this->view('pro/login', ['meta' => ['title' => 'Connexion à votre espace pro', 'description' => 'Accédez à votre espace professionnel : fiche, photos, demandes de devis, messages et statistiques.', 'robots' => 'noindex, follow', 'ads' => false]]);
    }

    public function login(): Response
    {
        $identifier = Request::str('identifier', 160);
        $password = (string) Request::raw('password', '');
        $res = Auth::attemptPro($identifier, $password);
        if ($res !== 'ok') {
            Session::flash('error', $res === 'locked'
                ? 'Trop de tentatives : votre accès est temporairement bloqué. Réessayez dans quelques minutes ou réinitialisez votre mot de passe.'
                : 'Identifiant ou mot de passe incorrect.');
            Session::withInput(['identifier' => $identifier]);
            return $this->redirect('/connexion/');
        }
        $pro = Auth::pro();
        Logger::security('Connexion pro', ['pro' => $pro['id'] ?? 0], 'info');
        $to = (string) Session::get('intended', '');
        Session::forget('intended');
        if (!str_starts_with($to, '/espace-pro') || str_starts_with($to, '//')) {
            $to = '/espace-pro/';
        }
        if (!empty($pro['must_change_password'])) {
            Session::flash('warning', 'Bienvenue sur le nouveau site ! Pour votre sécurité, choisissez un nouveau mot de passe avant de continuer.');
            $to = '/espace-pro/compte/#securite';
        }
        return $this->redirect($to);
    }

    public function logout(): Response
    {
        Auth::logoutPro();
        Session::regenerate();
        Session::flash('success', 'Vous êtes déconnecté. À bientôt !');
        return $this->redirect('/');
    }

    // ------------------------------------------------------ mot de passe oublié

    public function forgotForm(): Response
    {
        Session::start();
        return $this->view('pro/forgot', ['meta' => ['title' => 'Mot de passe oublié', 'robots' => 'noindex, follow', 'ads' => false]]);
    }

    public function forgot(): Response
    {
        $identifier = Request::str('identifier', 160);
        if ($identifier !== '' && RateLimiter::attempt('pwreset-ip:' . Request::ip(), 8, 3600) && RateLimiter::attempt('pwreset:' . mb_strtolower($identifier), 3, 3600)) {
            $pro = Pros::findByLogin($identifier);
            if ($pro && Str::emailValid((string) ($pro['email'] ?? '')) && !in_array($pro['status'] ?? '', ['deleted', 'rejected'], true)) {
                $token = Crypto::sign(['p' => (int) $pro['id'], 'h' => self::fingerprint($pro)], 'pwreset', 3600);
                Mail::send((string) $pro['email'], 'pro_password_reset', ['prenom' => $pro['first_name'] ?: Pros::displayName($pro)], [
                    'bouton_url' => '/reinitialiser/' . $token . '/',
                    'bouton_label' => 'Choisir un nouveau mot de passe',
                ]);
                Logger::security('Réinitialisation de mot de passe demandée', ['pro' => (int) $pro['id']], 'info');
            }
        }
        // Même réponse dans tous les cas : impossible de deviner si un compte existe.
        Session::flash('success', 'Si un compte correspond, un email avec un lien de réinitialisation vient d\'être envoyé à l\'adresse enregistrée (pensez à regarder dans les indésirables).');
        return $this->redirect('/mot-de-passe-oublie/');
    }

    /** Empreinte du mot de passe actuel : le lien devient invalide dès qu'il a servi. */
    private static function fingerprint(array $pro): string
    {
        return substr(hash_hmac('sha256', (string) ($pro['password_hash'] ?? '') . '|' . (int) ($pro['session_version'] ?? 0), Crypto::key()), 0, 16);
    }

    private function resetPro(string $token): ?array
    {
        $p = Crypto::verify($token, 'pwreset');
        if (!$p) {
            return null;
        }
        $pro = Store::pros()->get((int) $p['p']);
        if (!$pro || in_array($pro['status'] ?? '', ['deleted', 'rejected'], true) || !hash_equals(self::fingerprint($pro), (string) ($p['h'] ?? ''))) {
            return null;
        }
        return $pro;
    }

    public function resetForm(string $token): Response
    {
        $pro = $this->resetPro($token);
        if (!$pro) {
            return $this->expired('Ce lien de réinitialisation a expiré ou a déjà été utilisé.', '/mot-de-passe-oublie/', 'Recevoir un nouveau lien');
        }
        Session::start();
        return $this->view('pro/reset', ['token' => $token, 'pro' => $pro, 'meta' => ['title' => 'Nouveau mot de passe', 'robots' => 'noindex, nofollow', 'ads' => false]]);
    }

    public function reset(string $token): Response
    {
        $pro = $this->resetPro($token);
        if (!$pro) {
            return $this->expired('Ce lien de réinitialisation a expiré ou a déjà été utilisé.', '/mot-de-passe-oublie/', 'Recevoir un nouveau lien');
        }
        $pw = (string) Request::raw('password', '');
        $error = Auth::passwordError($pw, [$pro['login'] ?? '', $pro['email'] ?? '']);
        if ($error === null && $pw !== (string) Request::raw('password2', '')) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        }
        if ($error !== null) {
            Session::flash('error', $error);
            return $this->redirect('/reinitialiser/' . $token . '/');
        }
        $pro = Store::pros()->update((int) $pro['id'], [
            'password_hash' => Crypto::hashPassword($pw),
            'must_change_password' => false,
            'password_changed_at' => date('c'),
            'session_version' => (int) ($pro['session_version'] ?? 0) + 1,
            'failed_logins' => 0,
            'locked_until' => null,
        ], false);
        Logger::security('Mot de passe pro réinitialisé', ['pro' => (int) $pro['id']], 'info');
        Auth::loginPro($pro);
        Session::flash('success', 'Votre nouveau mot de passe est enregistré. Bienvenue dans votre espace !');
        return $this->redirect('/espace-pro/');
    }

    // ------------------------------------------------------ confirmation d'email

    public function verifyEmail(string $token): Response
    {
        $p = Crypto::verify($token, 'verify-email');
        $pro = $p ? Store::pros()->get((int) $p['p']) : null;
        if (!$pro || in_array($pro['status'] ?? '', ['deleted', 'rejected'], true)) {
            return $this->expired('Ce lien de confirmation a expiré. Connectez-vous à votre espace pour en recevoir un nouveau.', '/connexion/', 'Me connecter');
        }
        $email = (string) $p['e'];
        $pending = mb_strtolower((string) ($pro['email_pending'] ?? ''));
        if ($pending !== '' && $pending === $email) {
            // changement d'adresse demandé depuis l'espace pro
            if (Pros::emailTaken($email, (int) $pro['id'])) {
                return $this->expired('Cette adresse email est déjà utilisée par un autre compte.', '/espace-pro/compte/', 'Mon compte');
            }
            $pro = Store::pros()->update((int) $pro['id'], ['email' => $email, 'email_pending' => null, 'email_verified' => true, 'email_verified_at' => date('c'), 'login' => str_contains((string) ($pro['login'] ?? ''), '@') ? $email : ($pro['login'] ?? $email)]);
            Pros::changed();
            Logger::audit('Email pro modifié', ['pro' => (int) $pro['id']]);
            return $this->done('Nouvelle adresse confirmée', 'Votre adresse email a bien été mise à jour. Elle sert désormais à vous connecter et à recevoir les demandes.');
        }
        if (mb_strtolower((string) ($pro['email'] ?? '')) !== $email) {
            return $this->expired('Ce lien ne correspond plus à l\'adresse de votre compte.', '/connexion/', 'Me connecter');
        }
        if (empty($pro['email_verified'])) {
            $pro = Store::pros()->update((int) $pro['id'], ['email_verified' => true, 'email_verified_at' => date('c')]);
            if (($pro['status'] ?? '') === 'pending') {
                $clean = ($pro['registration']['spam']['decision'] ?? 'clean') === 'clean';
                if (Settings::get('registration.auto_approve') && $clean) {
                    $pro = Pros::save((int) $pro['id'], ['status' => 'active', 'validated_at' => date('c'), 'validated_by' => 'auto']) ?? $pro;
                    Mail::send((string) $pro['email'], 'pro_validated', ['prenom' => $pro['first_name'] ?: Pros::displayName($pro), 'fiche' => Pros::displayName($pro)], ['bouton_url' => Url::pro($pro), 'bouton_label' => 'Voir ma fiche']);
                    Notify::admin('pro_registered', 'Nouveau pro publié : ' . Pros::displayName($pro), ($pro['city'] ?? '') . ' — validation automatique', Url::admin('pros/' . $pro['id']), 'success');
                } else {
                    Notify::admin('pro_registered', 'Inscription à valider : ' . Pros::displayName($pro), ($pro['city'] ?? '') . ' · ' . implode(', ', array_map([\App\Services\Categories::class, 'name'], (array) ($pro['categories'] ?? []))), Url::admin('pros/' . $pro['id']), 'info');
                }
            }
        }
        $active = ($pro['status'] ?? '') === 'active';
        return $this->done('Adresse email confirmée 🎉', $active
            ? 'Merci ! Votre fiche est en ligne. Complétez-la (photos, vidéo, tarifs) pour recevoir plus de demandes.'
            : 'Merci ! Notre équipe vérifie votre fiche et la publie au plus vite. Profitez-en pour la compléter : photos, description, vidéo…');
    }

    private function done(string $title, string $text): Response
    {
        return $this->view('front/thanks', ['title' => $title, 'text' => $text, 'pro' => null, 'cta' => ['/espace-pro/', 'Accéder à mon espace'], 'meta' => ['title' => $title, 'robots' => 'noindex, nofollow', 'ads' => false]]);
    }

    private function expired(string $text, string $url, string $label): Response
    {
        return $this->view('front/thanks', ['title' => 'Lien invalide', 'text' => $text, 'pro' => null, 'cta' => [$url, $label], 'meta' => ['title' => 'Lien invalide', 'robots' => 'noindex, nofollow', 'ads' => false]], 'front/layout', 410);
    }
}
