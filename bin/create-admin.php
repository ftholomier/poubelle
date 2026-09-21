#!/usr/bin/env php
<?php
/**
 * Crée ou promeut un compte administrateur.
 *
 *   php bin/create-admin.php --email=vous@exemple.fr [--name="Prénom Nom"] [--password=…]
 *
 * Sans --password, un mot de passe aléatoire est généré et affiché une seule
 * fois. Un compte existant est promu administrateur sans perdre ses données.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Domain\UserRepository;
use App\Services\Auth;
use App\Storage\Audit;

$options = getopt('', ['email:', 'name::', 'password::']);
if (!isset($options['email'])) {
    fwrite(STDERR, "Usage : php bin/create-admin.php --email=vous@exemple.fr [--name=…] [--password=…]\n");
    exit(1);
}

$email = strtolower(trim((string) $options['email']));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Adresse e-mail invalide.\n");
    exit(1);
}

$password = (string) ($options['password'] ?? '');
$generated = false;
if ($password === '') {
    // 18 caractères sans ambiguïté visuelle.
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#%+=';
    $password = '';
    for ($i = 0; $i < 18; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $generated = true;
} elseif (mb_strlen($password) < 10) {
    fwrite(STDERR, "Le mot de passe doit faire au moins 10 caractères.\n");
    exit(1);
}

$user = UserRepository::findByEmail($email);
$isNew = $user === null;

if ($isNew) {
    $user = [
        'id'           => UserRepository::nextId(),
        'email'        => $email,
        'login'        => $email,
        'display_name' => (string) ($options['name'] ?? strstr($email, '@', true)),
        'role'         => 'admin',
        'active'       => true,
    ];
} else {
    $user['role'] = 'admin';
    $user['active'] = true;
    if (isset($options['name'])) {
        $user['display_name'] = (string) $options['name'];
    }
}

$user['password'] = Auth::hash($password);
$user['password_legacy'] = '';
$user['must_reset'] = false;

UserRepository::save($user);
UserRepository::reindex();
Audit::log($isNew ? 'user.admin_created' : 'user.admin_promoted', ['user' => $user['id']]);

printf("%s : %s (id %s)\n", $isNew ? 'Compte créé' : 'Compte promu administrateur', $email, $user['id']);
if ($generated) {
    printf("Mot de passe : %s\n", $password);
    echo "Notez-le maintenant : il n'est pas réaffiché.\n";
}
echo "Connexion : /admin\n";
