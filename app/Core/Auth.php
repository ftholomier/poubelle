<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Comptes du back-office (storage/users.json, hors /public et hors dépôt).
 *
 * Deux niveaux :
 *  - admin : tout ;
 *  - user  : tout sauf utilisateurs, réglages, suppression définitive, restauration de versions.
 */
final class Auth
{
    /** Empreinte Argon2id factice (mêmes paramètres que les vraies) : même durée de vérification pour un compte inconnu. */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$MjVLejNYQ0gzVzk4aDEvOA$bOH79MHUVu2kAO80P/xQ/PanfGsyFL2q7L0/D1X5ZRs';

    // Comptes communs à tous les musées (fichier du musée historique) ; chaque compte liste ses
    // musées et son rôle dans chacun (« clubs » : id du club → rôle, « * » = tous les musées).
    private const FILE = APP_ROOT . '/storage/users.json';
    private const SSO_DIR = APP_ROOT . '/storage/sso';
    public const ROLES = ['admin' => 'Administrateur', 'user' => 'Utilisateur'];

    /** Droits refusés au niveau « utilisateur ». */
    private const ADMIN_ONLY = ['users', 'settings', 'destroy', 'restore', 'backup_restore'];

    private static ?array $current = null;
    private static bool $loaded = false;

    /** Déconnexion automatique après 30 minutes sans activité. */
    public const IDLE = 1800;

    /** Compte dont la session vient d'être fermée pour inactivité (noté au journal par le back-office). */
    public static ?array $expired = null;

    /** @return array<string,array> par id */
    public static function users(): array
    {
        $out = [];
        foreach (self::allUsers() as $id => $u) {
            if (($role = self::roleIn($u, CLUB_ID)) !== null) {
                $out[$id] = ['role' => $role] + $u;
                $out[$id]['role'] = $role;
            }
        }
        return $out;
    }

    /** Tous les comptes, tous musées confondus (rôle d'origine). @return array<string,array> */
    public static function allUsers(): array
    {
        return JsonStore::read(self::FILE, []) ?? [];
    }

    /** Rôle du compte dans un musée (null : pas d'accès). Ancien compte sans « clubs » : musée historique. */
    public static function roleIn(array $u, string $club): ?string
    {
        $clubs = $u['clubs'] ?? [Club::DEFAULT => $u['role'] ?? 'user'];
        $r = $clubs[$club] ?? $clubs['*'] ?? null;
        return isset(self::ROLES[$r]) ? $r : null;
    }

    /** Musées accessibles au compte : id → rôle. @return array<string,string> */
    public static function clubsOf(array $u): array
    {
        $out = [];
        foreach (array_keys(Club::all()) as $id) {
            if (($r = self::roleIn($u, $id)) !== null) {
                $out[$id] = $r;
            }
        }
        return $out;
    }

    /** Super-administrateur : accès à tous les musées (« clubs » : « * »). */
    public static function isSuper(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && isset(self::allUsers()[$u['id']]['clubs']['*']);
    }

    /**
     * Bascule vers un autre musée (autre domaine, donc autre session) : jeton à usage unique,
     * valable 60 secondes, posé dans le dossier commun. Renvoie l'adresse d'arrivée, ou null.
     */
    public static function switchUrl(string $club): ?string
    {
        $u = self::user();
        $profile = Club::all()[$club] ?? null;
        if (!$u || !$profile || self::roleIn(self::allUsers()[$u['id']] ?? [], $club) === null) {
            return null;
        }
        is_dir(self::SSO_DIR) || mkdir(self::SSO_DIR, 0700, true);
        foreach (glob(self::SSO_DIR . '/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - 120) {
                @unlink($f);
            }
        }
        $token = bin2hex(random_bytes(24));
        file_put_contents(self::SSO_DIR . '/' . hash('sha256', $token) . '.json', json_encode(['uid' => $u['id'], 'club' => $club, 'exp' => time() + 60, 'ufp' => self::fingerprint($u)]), LOCK_EX);
        return rtrim((string) ($profile['base_url'] ?? ''), '/') . '/admin/jeton/' . $token;
    }

    /** Arrivée par jeton de bascule : connecte le compte sur ce musée si tout concorde. */
    public static function consumeSwitch(string $token): bool
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return false;
        }
        $f = self::SSO_DIR . '/' . hash('sha256', $token) . '.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        @unlink($f);
        $u = is_array($d) ? (self::users()[(string) ($d['uid'] ?? '')] ?? null) : null;
        if (!$u || ($d['club'] ?? '') !== CLUB_ID || (int) ($d['exp'] ?? 0) < time() || ($u['status'] ?? '') !== 'active'
            || !hash_equals((string) ($d['ufp'] ?? ''), self::fingerprint($u))) {
            return false;
        }
        self::login($u);
        return true;
    }

    private static function saveUsers(array $users): void
    {
        JsonStore::write(self::FILE, $users);
        @chmod(self::FILE, 0640);
    }

    public static function find(string $id): ?array
    {
        return self::users()[$id] ?? null;
    }

    public static function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        foreach (self::users() as $u) {
            if ($u['email'] === $email) {
                return $u;
            }
        }
        return null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$current;
        }
        self::$loaded = true;
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE['sr_session'])) {
            return null; // pas de session ouverte : visiteur anonyme
        }
        $id = Session::get('uid');
        $fp = Session::get('ufp');
        if (!$id) {
            return null;
        }
        $u = self::find((string) $id);
        if (!$u || ($u['status'] ?? '') !== 'active' || $fp !== self::fingerprint($u)) {
            Session::forget('uid');
            return null;
        }
        // Sans activité depuis plus de 30 minutes : session fermée, la page de connexion le dit. Le
        // navigateur ferme la session à l'heure dite ; le serveur, avec une marge (il n'apprend
        // l'activité vue par le navigateur que toutes les 4 minutes), si la page n'est plus ouverte.
        $now = time();
        $seen = (int) Session::get('seen', 0);
        if ($seen > 0 && $now - $seen > self::idleLimit() + self::idleGrace()) {
            self::$expired = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email']];
            self::logout();
            Session::set('idle_out', 1);
            return null;
        }
        if (!self::passive() && $now - $seen >= 20) {
            Session::set('seen', $now);
        }
        return self::$current = $u;
    }

    /** Délai d'inactivité, en secondes (essais : BO_IDLE_SECONDS, avec le serveur de développement de PHP seulement). */
    public static function idleLimit(): int
    {
        $t = PHP_SAPI === 'cli-server' ? (int) getenv('BO_IDLE_SECONDS') : 0;
        return $t > 0 ? $t : self::IDLE;
    }

    /** Marge du serveur au-delà du délai (5 minutes ; l'activité lui est signalée au plus toutes les 4 minutes). */
    public static function idleGrace(): int
    {
        return min(300, intdiv(self::idleLimit(), 4) + 10);
    }

    /**
     * Appel automatique du navigateur (verrou d'une fiche, rafraîchissement d'un écran) : il ne
     * prolonge pas la session ; seules les actions de la personne le font.
     */
    public static function passive(): bool
    {
        return ($_SERVER['HTTP_X_BO_BACKGROUND'] ?? '') === '1' || ($_POST['_bg'] ?? '') === '1';
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return $u['role'] === 'admin' || !in_array($perm, self::ADMIN_ONLY, true);
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    /** Version publique (sans secrets) pour les journaux. */
    public static function actor(): ?array
    {
        $u = self::user();
        return $u ? ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email']] : null;
    }

    private static function fingerprint(array $u): string
    {
        // Change si le mot de passe change : toutes les sessions ouvertes sont alors fermées.
        return substr(hash('sha256', $u['id'] . '|' . ($u['password'] ?? '')), 0, 24);
    }

    public static function attempt(string $email, string $password, string $ip): array
    {
        $mail = mb_strtolower(trim($email));
        $key = $mail . '|' . $ip;
        // Trois compteurs : e-mail + adresse IP, adresse IP seule, et compte seul (essais
        // répartis sur de nombreuses adresses). Les adresses IPv6 comptent par bloc /64.
        if (!RateLimiter::hit('login', $key, 8, 900) || !RateLimiter::hit('login-ip', $ip, 30, 900) || !RateLimiter::hit('login-account', $mail, 30, 3600)) {
            return ['ok' => false, 'error' => 'Trop de tentatives. Réessayez plus tard ou utilisez « Mot de passe oublié ».'];
        }
        $u = self::findByEmail($email);
        // Temps constant : une seule vérification, avec une empreinte factice si le compte n'existe pas.
        $ok = password_verify($password, (string) ($u['password'] ?? '') ?: self::DUMMY_HASH);
        if (!$u || !$ok || ($u['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'E-mail ou mot de passe incorrect.'];
        }
        if (password_needs_rehash($u['password'], PASSWORD_ARGON2ID)) {
            self::update($u['id'], ['password' => password_hash($password, PASSWORD_ARGON2ID)]);
            $u = self::find($u['id']);
        }
        // Seuls les échecs comptent : une connexion réussie remet les compteurs du compte à zéro.
        RateLimiter::clear('login', $key);
        RateLimiter::clear('login-account', $mail);
        self::login($u);
        return ['ok' => true];
    }

    public static function login(array $u): void
    {
        Session::start();
        session_regenerate_id(true);
        unset($_SESSION['_csrf']); // nouveau jeton de formulaire pour la session connectée
        Session::set('uid', $u['id']);
        Session::set('ufp', self::fingerprint($u));
        Session::set('seen', time());
        self::update($u['id'], ['last_login' => date('c')]);
        self::$current = self::find($u['id']);
        self::$loaded = true;
    }

    public static function logout(): void
    {
        Session::start();
        $_SESSION = [];
        session_regenerate_id(true);
        self::$current = null;
    }

    public static function createUser(string $email, string $name, string $role, ?string $password = null): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Adresse e-mail invalide.');
        }
        // Compte déjà ouvert sur un autre musée : on lui donne simplement accès à celui-ci.
        foreach (self::allUsers() as $other) {
            if ($other['email'] === $email) {
                if (self::roleIn($other, CLUB_ID) !== null) {
                    throw new RuntimeException('Un compte existe déjà avec cette adresse.');
                }
                $clubs = ($other['clubs'] ?? [Club::DEFAULT => $other['role'] ?? 'user']) + [CLUB_ID => isset(self::ROLES[$role]) ? $role : 'user'];
                self::update($other['id'], ['clubs' => $clubs]);
                return self::users()[$other['id']];
            }
        }
        if ($password !== null && mb_strlen($password) < 10) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 10 caractères.');
        }
        $u = [
            'id' => bin2hex(random_bytes(8)),
            'email' => $email,
            'name' => trim($name) ?: $email,
            'role' => isset(self::ROLES[$role]) ? $role : 'user',
            'clubs' => [CLUB_ID => isset(self::ROLES[$role]) ? $role : 'user'],
            'password' => $password !== null ? password_hash($password, PASSWORD_ARGON2ID) : null,
            'status' => $password !== null ? 'active' : 'invited',
            'invite' => null,
            'created_at' => date('c'),
            'last_login' => null,
        ];
        JsonStore::update(self::FILE, function ($all) use ($u) {
            $all = $all ?: [];
            $all[$u['id']] = $u;
            return $all;
        }, []);
        return $u;
    }

    public static function update(string $id, array $changes): void
    {
        JsonStore::update(self::FILE, function ($all) use ($id, $changes) {
            if (isset($all[$id])) {
                // Le rôle changé depuis un musée ne vaut que pour ce musée.
                if (isset($changes['role'])) {
                    $u = $all[$id];
                    $clubs = $u['clubs'] ?? [Club::DEFAULT => $u['role'] ?? 'user'];
                    if (!isset($clubs['*'])) {
                        $clubs[CLUB_ID] = $changes['role'];
                    }
                    $changes['clubs'] = $clubs;
                    if (CLUB_ID !== Club::DEFAULT) {
                        unset($changes['role']);
                    }
                }
                $all[$id] = array_merge($all[$id], $changes);
            }
            return $all;
        }, []);
    }

    /** Retire le compte de ce musée ; il n'est supprimé que s'il n'a plus accès à aucun musée. */
    public static function delete(string $id): void
    {
        JsonStore::update(self::FILE, function ($all) use ($id) {
            if (!isset($all[$id])) {
                return $all;
            }
            $clubs = $all[$id]['clubs'] ?? [Club::DEFAULT => $all[$id]['role'] ?? 'user'];
            unset($clubs[CLUB_ID]);
            if ($clubs) {
                $all[$id]['clubs'] = $clubs;
            } else {
                unset($all[$id]);
            }
            return $all;
        }, []);
    }

    /** Invitation : renvoie le jeton à envoyer par e-mail (valable 7 jours). Seul son hash est stocké. */
    public static function invite(string $id): string
    {
        $token = bin2hex(random_bytes(24));
        $status = self::find($id)['status'] ?? 'invited';
        // Un compte désactivé le reste : seul « Réactiver » le rouvre.
        self::update($id, ['invite' => ['hash' => hash('sha256', $token), 'expires' => time() + 7 * 86400], 'status' => in_array($status, ['active', 'disabled'], true) ? $status : 'invited']);
        return $token;
    }

    public static function findByInvite(string $token): ?array
    {
        $h = hash('sha256', $token);
        foreach (self::users() as $u) {
            if (!empty($u['invite']['hash']) && hash_equals($u['invite']['hash'], $h) && $u['invite']['expires'] > time() && ($u['status'] ?? '') !== 'disabled') {
                return $u;
            }
        }
        return null;
    }

    public static function setPassword(string $id, string $password): void
    {
        if (mb_strlen($password) < 10) {
            throw new RuntimeException('Le mot de passe doit contenir au moins 10 caractères.');
        }
        self::update($id, ['password' => password_hash($password, PASSWORD_ARGON2ID), 'status' => 'active', 'invite' => null]);
        // Nouveau mot de passe (lien « Mot de passe oublié ») : le compte n'est plus bloqué par les essais.
        if ($u = self::find($id)) {
            RateLimiter::clear('login-account', mb_strtolower(trim((string) $u['email'])));
        }
    }

    public static function countAdmins(): int
    {
        return count(array_filter(self::users(), fn ($u) => $u['role'] === 'admin' && $u['status'] === 'active'));
    }
}
