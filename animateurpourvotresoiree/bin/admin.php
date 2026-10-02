<?php
declare(strict_types=1);

/*
 * Gestion des administrateurs en ligne de commande (secours) :
 *   php bin/admin.php create            crée un super-administrateur (questions interactives)
 *   php bin/admin.php reset email@x.fr  nouveau mot de passe + désactivation de la 2FA
 *   php bin/admin.php list              liste les comptes
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI uniquement\n");
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Crypto;
use App\Core\Str;
use App\Services\Store;

$cmd = $argv[1] ?? 'help';
$ask = static function (string $q, bool $hidden = false): string {
    echo $q;
    if ($hidden && DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $v = trim((string) fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $v;
    }
    return trim((string) fgets(STDIN));
};
$askPassword = static function () use ($ask): string {
    for (;;) {
        $pw = $ask('Mot de passe : ', true);
        if (($err = Auth::passwordError($pw)) !== null) {
            echo "  ✗ $err\n";
            continue;
        }
        if ($ask('Confirmation : ', true) !== $pw) {
            echo "  ✗ Les mots de passe ne correspondent pas.\n";
            continue;
        }
        return $pw;
    }
};

switch ($cmd) {
    case 'create':
        $name = $ask('Nom : ');
        $email = Str::email($ask('Email : '));
        if (!Str::emailValid($email)) {
            fwrite(STDERR, "Email invalide\n");
            exit(1);
        }
        foreach (Store::admins()->iterate() as $a) {
            if (strtolower($a['email']) === $email) {
                fwrite(STDERR, "Ce compte existe déjà (utilisez « reset »).\n");
                exit(1);
            }
        }
        $pw = $askPassword();
        Store::admins()->insert(['name' => $name, 'email' => $email, 'role' => 'superadmin', 'status' => 'active', 'password_hash' => Crypto::hashPassword($pw), 'session_version' => 0, 'totp_enabled' => false]);
        App\Core\Logger::security('Administrateur créé en ligne de commande', ['admin' => $email], 'info');
        echo "✓ Super-administrateur créé.\n";
        break;
    case 'reset':
        $email = Str::email($argv[2] ?? $ask('Email : '));
        foreach (Store::admins()->iterate() as $id => $a) {
            if (strtolower($a['email']) === $email) {
                $full = Store::admins()->get((int) $id);
                $pw = $askPassword();
                Store::admins()->update((int) $id, ['password_hash' => Crypto::hashPassword($pw), 'totp_enabled' => false, 'totp_secret' => null, 'recovery_codes' => [], 'locked_until' => null, 'failed_logins' => 0, 'status' => 'active', 'session_version' => (int) ($full['session_version'] ?? 0) + 1]);
                App\Core\Logger::security('Accès administrateur réinitialisé en ligne de commande', ['admin' => $email]);
                echo "✓ Mot de passe changé, 2FA désactivée, sessions fermées.\n";
                exit(0);
            }
        }
        fwrite(STDERR, "Compte introuvable\n");
        exit(1);
    case 'list':
        foreach (Store::admins()->iterate() as $id => $a) {
            printf("#%d  %-32s %-12s %-8s 2FA:%s\n", $id, $a['email'], $a['role'], $a['status'], $a['totp'] ? 'oui' : 'non');
        }
        break;
    default:
        echo "Usage : php bin/admin.php create | reset <email> | list\n";
}
