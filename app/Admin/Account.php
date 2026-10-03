<?php
declare(strict_types=1);

namespace App\Admin;

use App\Core\Auth;
use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Data\Activity;
use App\Services\Mailer;

/**
 * Comptes : connexion, premier accès (création du premier administrateur avec un
 * code lisible uniquement sur le serveur), invitations, mot de passe oublié, profil.
 */
final class Account extends Base
{
    private const SETUP = STORAGE_PATH . '/premier-acces.txt';

    public static function login(Request $req): Response
    {
        if (!Auth::users()) {
            return Response::redirect('/admin/premier-acces');
        }
        $back = self::safeReturn($req->str('r'));
        if (Auth::user()) {
            return Response::redirect($back);
        }
        $error = null;
        $email = '';
        if ($req->method === 'POST') {
            $email = trim((string) ($req->post['email'] ?? ''));
            $r = Auth::attempt($email, (string) ($req->post['password'] ?? ''), $req->ip());
            if ($r['ok']) {
                Activity::log(Auth::actor(), 's’est connecté', null);
                return Response::redirect($back);
            }
            $error = $r['error'];
        }
        return self::html('admin/auth/login', ['error' => $error, 'email' => $email, 'r' => $back], ['bare' => true, 'title' => 'Connexion']);
    }

    /** Création du premier compte administrateur (seulement si aucun compte n'existe). */
    public static function setup(Request $req): Response
    {
        if (Auth::users()) {
            return Response::redirect('/admin/connexion');
        }
        if (!is_file(self::SETUP)) {
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
            file_put_contents(self::SETUP, "Code de premier accès au back-office Sochaux Rétro : $code\nCe fichier est supprimé automatiquement après la création du premier compte.\n", LOCK_EX);
            @chmod(self::SETUP, 0600);
        }
        $error = null;
        $old = ['name' => '', 'email' => ''];
        if ($req->method === 'POST') {
            $old = ['name' => trim((string) ($req->post['name'] ?? '')), 'email' => trim((string) ($req->post['email'] ?? ''))];
            preg_match('/:\s*([A-F0-9]{12})/', (string) file_get_contents(self::SETUP), $m);
            $code = strtoupper(trim((string) ($req->post['code'] ?? '')));
            $pwd = (string) ($req->post['password'] ?? '');
            if (!RateLimiter::hit('setup', $req->ip(), 10, 3600)) {
                $error = 'Trop de tentatives : réessayez dans une heure.';
            } elseif (!isset($m[1]) || !hash_equals($m[1], $code)) {
                $error = 'Code de premier accès incorrect.';
            } elseif ($pwd !== (string) ($req->post['password2'] ?? '')) {
                $error = 'Les deux mots de passe ne correspondent pas.';
            } else {
                try {
                    $u = Auth::createUser($old['email'], $old['name'], 'admin', $pwd);
                    @unlink(self::SETUP);
                    Auth::login($u);
                    Activity::log(Auth::actor(), 'a créé le premier compte administrateur', null);
                    return self::back('/admin', 'Bienvenue ! Votre compte administrateur est créé.');
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }
        return self::html('admin/auth/setup', ['error' => $error, 'old' => $old], ['bare' => true, 'title' => 'Premier accès']);
    }

    /** Lien d'invitation ou de réinitialisation : choix du mot de passe. */
    public static function invitation(Request $req, string $token): Response
    {
        $u = preg_match('/^[a-f0-9]{48}$/', $token) ? Auth::findByInvite($token) : null;
        if (!$u) {
            return self::html('admin/message', ['title' => 'Lien expiré', 'text' => 'Ce lien n’est plus valable. Demandez une nouvelle invitation à un administrateur, ou utilisez « Mot de passe oublié ».', 'back' => '/admin/connexion'], ['bare' => true, 'title' => 'Lien expiré']);
        }
        $error = null;
        if ($req->method === 'POST') {
            $pwd = (string) ($req->post['password'] ?? '');
            if ($pwd !== (string) ($req->post['password2'] ?? '')) {
                $error = 'Les deux mots de passe ne correspondent pas.';
            } else {
                try {
                    if (!empty($req->post['name'])) {
                        Auth::update($u['id'], ['name' => mb_substr(trim((string) $req->post['name']), 0, 80)]);
                    }
                    Auth::setPassword($u['id'], $pwd);
                    Auth::login(Auth::find($u['id']));
                    Activity::log(Auth::actor(), $u['status'] === 'invited' ? 'a rejoint le back-office' : 'a changé son mot de passe', null);
                    return self::back('/admin', 'Mot de passe enregistré. Bienvenue !');
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
            }
        }
        return self::html('admin/auth/invitation', ['u' => $u, 'error' => $error], ['bare' => true, 'title' => $u['status'] === 'invited' ? 'Invitation' : 'Nouveau mot de passe']);
    }

    public static function forgot(Request $req): Response
    {
        $sent = false;
        if ($req->method === 'POST') {
            $email = trim((string) ($req->post['email'] ?? ''));
            if (RateLimiter::hit('forgot', $req->ip(), 5, 3600)) {
                $u = Auth::findByEmail($email);
                if ($u && $u['status'] === 'active') {
                    $token = Auth::invite($u['id']);
                    // Envoi après la réponse : même durée de réponse que le compte existe ou non.
                    register_shutdown_function(function () use ($u, $token) {
                        if (function_exists('fastcgi_finish_request')) {
                            fastcgi_finish_request();
                        } elseif (function_exists('litespeed_finish_request')) {
                            litespeed_finish_request();
                        }
                        self::mailLink($u, $token, 'reset');
                    });
                }
            }
            $sent = true; // même réponse que le compte existe ou non
        }
        return self::html('admin/auth/forgot', ['sent' => $sent], ['bare' => true, 'title' => 'Mot de passe oublié']);
    }

    public static function logout(Request $req): Response
    {
        Activity::log(Auth::actor(), 's’est déconnecté', null);
        Auth::logout();
        return Response::redirect('/admin/connexion');
    }

    public static function profile(Request $req): Response
    {
        $u = Auth::user();
        if ($req->method === 'POST') {
            $name = mb_substr(trim((string) ($req->post['name'] ?? '')), 0, 80);
            if ($name !== '' && $name !== $u['name']) {
                Auth::update($u['id'], ['name' => $name]);
            }
            $new = (string) ($req->post['password'] ?? '');
            if ($new !== '') {
                if (!password_verify((string) ($req->post['current'] ?? ''), (string) $u['password'])) {
                    return self::back('/admin/profil', null, 'Mot de passe actuel incorrect.');
                }
                if ($new !== (string) ($req->post['password2'] ?? '')) {
                    return self::back('/admin/profil', null, 'Les deux nouveaux mots de passe ne correspondent pas.');
                }
                try {
                    Auth::setPassword($u['id'], $new);
                    Auth::login(Auth::find($u['id']));
                } catch (\RuntimeException $e) {
                    return self::back('/admin/profil', null, $e->getMessage());
                }
            }
            return self::back('/admin/profil', 'Profil enregistré.');
        }
        return self::html('admin/auth/profile', ['u' => $u, 'activity' => array_slice(array_values(array_filter(\App\Data\Activity::recent(300), fn ($a) => ($a['uid'] ?? '') === $u['id'])), 0, 30)], ['title' => 'Mon profil', 'crumb' => 'Compte', 'nav' => 'profil']);
    }

    /** Envoie un lien d'invitation ou de réinitialisation. */
    public static function mailLink(array $u, string $token, string $kind = 'invite'): bool
    {
        // Jamais l'en-tête « Host » de la requête dans un lien envoyé par e-mail : sans adresse du
        // site réglée, le lien n'est pas envoyé (sinon un tiers pourrait le détourner vers son site).
        $base = rtrim((string) \App\Core\Settings::get('general.base_url', ''), '/');
        if ($base === '') {
            error_log('[compte] adresse du site non réglée (Réglages) : lien ' . $kind . ' non envoyé');
            return false;
        }
        $link = $base . '/admin/invitation/' . $token;
        $site = (string) \App\Core\Settings::get('general.site_name', 'Sochaux Rétro');
        if ($kind === 'invite') {
            $subject = "Invitation au back-office de $site";
            $html = '<p>Bonjour ' . e($u['name']) . ',</p><p>Vous êtes invité(e) à contribuer au back-office du musée en ligne ' . e($site) . '. Choisissez votre mot de passe pour activer votre compte :</p>';
        } else {
            $subject = "Réinitialisation de votre mot de passe · $site";
            $html = '<p>Bonjour ' . e($u['name']) . ',</p><p>Pour choisir un nouveau mot de passe, cliquez sur le lien ci-dessous :</p>';
        }
        $html .= '<p><a href="' . e($link) . '" style="display:inline-block;background:#0E1F4D;color:#F6C400;padding:12px 18px;text-decoration:none;font-weight:bold">' . ($kind === 'invite' ? 'Activer mon compte' : 'Choisir un mot de passe') . '</a></p><p style="font-size:13px;color:#3A4A75">Ce lien est valable 7 jours. Si vous n’êtes pas concerné(e), ignorez ce message.</p>';
        return Mailer::send($u['email'], $subject, $html);
    }

    private static function safeReturn(string $r): string
    {
        return preg_match('#^/admin(?:/[\w\-/?=&%.]*)?$#', $r) && !str_starts_with($r, '/admin/connexion') ? $r : '/admin';
    }
}
