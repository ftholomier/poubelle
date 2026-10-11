<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\UserRepository;
use App\Storage\Audit;
use App\Storage\Json;

/**
 * Comptes membres du site : inscription, confirmation de l'adresse,
 * mot de passe oublié, suppression.
 *
 * Un compte sert surtout à consulter l'annuaire des CV, réservé aux membres.
 * Les jetons envoyés par e-mail sont à usage unique ; seule leur empreinte est
 * écrite sur le disque. Aucune réponse ne dit si une adresse a déjà un compte.
 */
final class Account
{
    public const ROLES = ['employer', 'candidate'];
    private const CONFIRM_TTL = 86400;

    private static function tokenDir(): string
    {
        $dir = Config::path('data') . '/private/auth';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function base(): string
    {
        return rtrim((string) Config::get('site.url'), '/');
    }

    /**
     * Inscription. Adresse nouvelle : compte en attente et lien de
     * confirmation. Adresse connue : un e-mail l'indique à son titulaire, et la
     * page affiche le même message — l'existence d'un compte ne se devine pas.
     */
    public static function register(string $name, string $email, string $password, string $role, string $ip): bool
    {
        if (RateLimit::hit('register', $ip, 5, 3600) > 0) {
            return false;
        }
        $email = mb_strtolower(trim($email));
        $site = (string) Config::get('site.name');

        if (UserRepository::findByEmail($email) !== null) {
            Mailer::send($email, '[' . $site . '] Vous avez déjà un compte',
                "Bonjour,\n\nUne inscription vient d'être demandée avec cette adresse, qui a déjà un compte sur {$site}.\n\n"
                . "Pour vous connecter : " . self::base() . I18n::url('/connexion') . "\n"
                . "Mot de passe oublié : " . self::base() . I18n::url('/mot-de-passe-oublie') . "\n\n"
                . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n");
            Audit::log('account.register_existing', []);
            return true;
        }

        $now = date('c');
        $user = [
            'id'             => UserRepository::nextId(),
            'email'          => $email,
            'login'          => $email,
            'display_name'   => $name,
            'first_name'     => '',
            'last_name'      => '',
            'role'           => in_array($role, self::ROLES, true) ? $role : 'employer',
            'password'       => Auth::hash($password),
            'password_legacy'=> '',
            'must_reset'     => false,
            'company'        => [],
            'locale'         => I18n::lang(),
            'active'         => true,
            'email_verified' => false,
            'two_factor'     => [],
            'created_at'     => $now,
            'updated_at'     => $now,
            'last_login_at'  => '',
        ];
        UserRepository::save($user);
        UserRepository::reindex();

        $link = self::base() . I18n::url('/confirmer') . '?jeton=' . self::token((string) $user['id'], 'confirm', self::CONFIRM_TTL);
        Mailer::send($email, '[' . $site . '] Confirmez votre adresse',
            "Bonjour,\n\nPour activer votre compte sur {$site}, ouvrez ce lien dans les 24 heures :\n{$link}\n\n"
            . "Si vous n'êtes pas à l'origine de cette inscription, ignorez ce message : le compte ne sera pas activé.\n");
        Audit::log('account.registered', ['user' => $user['id'], 'role' => $user['role']], (int) $user['id']);
        Notifier::notify('user.new', 'Nouveau compte créé', ['Nom' => $name, 'Type' => $user['role']], '/admin/utilisateurs');
        return true;
    }

    /** Confirme l'adresse : le compte peut se connecter. */
    public static function confirm(string $token): bool
    {
        $user = self::consume($token, 'confirm');
        if ($user === null) {
            return false;
        }
        $user['email_verified'] = true;
        UserRepository::save($user);
        Audit::log('account.confirmed', ['user' => $user['id']], (int) $user['id']);
        return true;
    }

    /** Lien de réinitialisation, envoyé seulement si l'adresse a un compte actif. */
    public static function forgot(string $email, string $ip): void
    {
        if (RateLimit::hit('reset', $ip, 5, 3600) > 0) {
            return;
        }
        $user = UserRepository::findByEmail(mb_strtolower(trim($email)));
        if ($user === null || !($user['active'] ?? true)) {
            return;
        }
        $link = self::base() . I18n::url('/nouveau-mot-de-passe') . '?jeton='
              . self::token((string) $user['id'], 'reset', (int) Config::get('security.reset_ttl', 1800));
        Mailer::resetLink((string) $user['email'], $link);
    }

    public static function checkReset(string $token): bool
    {
        return self::peek($token, 'reset') !== null;
    }

    /** Nouveau mot de passe ; vaut aussi confirmation de l'adresse. */
    public static function reset(string $token, string $password): bool
    {
        if (mb_strlen($password) < 10) {
            return false;
        }
        $user = self::consume($token, 'reset');
        if ($user === null) {
            return false;
        }
        $user['password'] = Auth::hash($password);
        $user['password_legacy'] = '';
        $user['must_reset'] = false;
        $user['email_verified'] = true;
        UserRepository::save($user);
        Audit::log('auth.password_reset', ['user' => $user['id']], (int) $user['id']);
        return true;
    }

    /** Suppression par son titulaire. Les comptes du back-office ne s'effacent pas ici. */
    public static function delete(string $id): bool
    {
        $user = UserRepository::find($id);
        if ($user === null || Auth::isStaffRole((string) ($user['role'] ?? ''))) {
            return false;
        }
        UserRepository::delete($id);
        UserRepository::reindex();
        Audit::log('account.deleted', ['user' => $id]);
        return true;
    }

    /** Chemin de retour après connexion : interne au site, sinon l'annuaire. */
    public static function safeReturn(string $path): string
    {
        return preg_match('#^/(?!/)[A-Za-z0-9\-/_%.?=&]{0,300}$#', $path) === 1 ? $path : I18n::url('/cv');
    }

    private static function token(string $userId, string $purpose, int $ttl): string
    {
        $token = bin2hex(random_bytes(32));
        Json::write(self::tokenDir() . '/' . hash('sha256', $token) . '.json', [
            'user_id' => $userId, 'purpose' => $purpose, 'created_at' => time(),
            'expires_at' => time() + $ttl, 'used' => false,
        ]);
        return $token;
    }

    private static function peek(string $token, string $purpose): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $state = Json::read(self::tokenDir() . '/' . hash('sha256', $token) . '.json');
        if ($state === [] || !empty($state['used']) || (int) ($state['expires_at'] ?? 0) < time()
            || ($state['purpose'] ?? 'reset') !== $purpose) {
            return null;
        }
        return UserRepository::find((string) $state['user_id']);
    }

    private static function consume(string $token, string $purpose): ?array
    {
        $user = self::peek($token, $purpose);
        if ($user === null) {
            return null;
        }
        $file = self::tokenDir() . '/' . hash('sha256', $token) . '.json';
        $state = Json::read($file);
        $state['used'] = true;
        Json::write($file, $state);
        return $user;
    }
}
