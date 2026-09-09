<?php
declare(strict_types=1);

/**
 * Gestion des comptes en ligne de commande (dépannage, serveur sans accès web).
 *   php bin/user.php list
 *   php bin/user.php add prenom@ioio.fr "MotDePasseSolide" [admin|editor]
 *   php bin/user.php password prenom@ioio.fr "NouveauMotDePasse"
 *   php bin/user.php delete prenom@ioio.fr
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Auth;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Ce script s'exécute en ligne de commande uniquement.\n");
}

$command = $argv[1] ?? 'list';
$email = $argv[2] ?? '';
$password = $argv[3] ?? '';
$role = $argv[4] ?? 'editor';

switch ($command) {
    case 'add':
        $result = Auth::createUser($email, $password, $role);
        echo $result['ok'] ? "Compte créé : {$email} ({$role})\n" : 'Erreur : ' . $result['error'] . "\n";
        exit($result['ok'] ? 0 : 1);

    case 'password':
        $ok = Auth::updatePassword($email, $password);
        echo $ok ? "Mot de passe modifié pour {$email}\n" : "Échec : compte inconnu ou mot de passe trop court (10 caractères minimum).\n";
        exit($ok ? 0 : 1);

    case 'delete':
        $ok = Auth::deleteUser($email);
        echo $ok ? "Compte supprimé : {$email}\n" : "Échec : compte inconnu, ou c'est le dernier compte.\n";
        exit($ok ? 0 : 1);

    default:
        $users = Auth::users();
        if ($users === []) {
            echo "Aucun compte : ouvrez /admin pour lancer l'installation.\n";
            exit(0);
        }
        foreach ($users as $user) {
            printf(
                "%-32s %-8s créé %s%s\n",
                $user['email'],
                $user['role'] ?? 'editor',
                substr((string) ($user['createdAt'] ?? ''), 0, 10),
                !empty($user['lockedUntil']) ? '  [bloqué]' : ''
            );
        }
}
