<?php
declare(strict_types=1);

/**
 * Installation initiale : copie les données de démarrage dans /data
 * et crée un compte administrateur. Idempotent.
 */
final class Installer
{
    public static function run(): void
    {
        foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        }
        // Interdit l'accès direct si /data se retrouve exposé par erreur.
        @file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\nDeny from all\n");

        foreach (['content', 'settings', 'posts'] as $name) {
            $target = DATA_DIR . '/' . $name . '.json';
            if (!is_file($target)) {
                @copy(APP_DIR . '/seed/' . $name . '.json', $target);
            }
        }
        foreach (['applications', 'leads', 'events', 'ratelimit'] as $name) {
            $target = DATA_DIR . '/' . $name . '.json';
            if (!is_file($target)) {
                @file_put_contents($target, "[]");
            }
        }

        // Sel unique par installation (empreintes visiteurs).
        $settings = Store::read('settings');
        if (empty($settings['security']['salt'])) {
            $settings['security']['salt'] = bin2hex(random_bytes(16));
            Store::write('settings', $settings);
        }

        if (!is_file(DATA_DIR . '/users.json')) {
            $password = getenv('ADMIN_PASSWORD') ?: 'SuisseImmo2026!';
            Store::write('users', [[
                'id' => Store::uid('usr-'),
                'name' => 'Administrateur',
                'email' => getenv('ADMIN_EMAIL') ?: 'admin@suisse-immo.fr',
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'admin',
                'active' => true,
                'must_change_password' => true,
                'created_at' => date('c'),
            ]]);
            @file_put_contents(DATA_DIR . '/PREMIERE-CONNEXION.txt',
                "Compte administrateur créé le " . date('d/m/Y H:i') . "\n" .
                "Identifiant : " . (getenv('ADMIN_EMAIL') ?: 'admin@suisse-immo.fr') . "\n" .
                "Mot de passe : " . $password . "\n\n" .
                "Changez-le dès la première connexion depuis Back-office > Utilisateurs, puis supprimez ce fichier.\n");
        }
    }
}
