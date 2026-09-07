<?php
declare(strict_types=1);

/**
 * Outil en ligne de commande : créer un compte administrateur ou en
 * redéfinir le mot de passe.
 *
 * Sert quand plus personne ne peut entrer dans le back-office — mot de
 * passe perdu et e-mail de réinitialisation qui n'arrive pas, par
 * exemple parce que l'hébergement n'envoie pas de courrier. Éditer
 * `data/users.json` à la main obligerait à calculer un hachage à part ;
 * ici tout est fait correctement, y compris la fermeture des sessions
 * ouvertes.
 *
 * Utilisation, depuis la racine du projet :
 *
 *   php outils/compte-admin.php --liste
 *   php outils/compte-admin.php --email=vous@exemple.fr --mot-de-passe='…'
 *   php outils/compte-admin.php --email=vous@exemple.fr --mot-de-passe='…' --changement-impose
 *
 * Si le compte existe, son mot de passe est remplacé ; sinon il est créé.
 * `--changement-impose` oblige à choisir un nouveau mot de passe dès la
 * première connexion : à utiliser quand le mot de passe transite par un
 * canal peu sûr (SMS, message instantané).
 */

// Ce fichier ne doit jamais être atteignable depuis le web. Il est déjà
// hors de la racine publique, mais un hébergeur mal configuré pourrait
// exposer le dépôt entier : le refus est explicite.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Cet outil ne s'utilise qu'en ligne de commande.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

/** @return array<string,string> options --cle=valeur de la ligne de commande */
function options(array $argv): array
{
    $out = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        $pos = strpos($arg, '=');
        if ($pos === false) {
            $out[$arg] = '1';
        } else {
            $out[substr($arg, 0, $pos)] = substr($arg, $pos + 1);
        }
    }
    return $out;
}

function sortie(string $message, int $code = 1): never
{
    fwrite($code === 0 ? STDOUT : STDERR, $message . "\n");
    exit($code);
}

$opt = options($argv);

// --- Liste des comptes -----------------------------------------------------
if (isset($opt['liste'])) {
    $comptes = Store::read('users');
    if ($comptes === []) {
        sortie('Aucun compte. Créez-en un avec --email et --mot-de-passe.', 0);
    }
    echo str_pad('ADRESSE', 38), str_pad('ÉTAT', 12), str_pad('CHANGEMENT IMPOSÉ', 20), "DERNIÈRE CONNEXION\n";
    foreach ($comptes as $c) {
        echo str_pad((string) ($c['email'] ?? '—'), 38),
             str_pad(($c['active'] ?? true) ? 'actif' : 'désactivé', 12),
             str_pad(!empty($c['must_change_password']) ? 'oui' : 'non', 20),
             (string) ($c['last_login'] ?? 'jamais'), "\n";
    }
    exit(0);
}

// --- Création ou mise à jour ------------------------------------------------
$email = strtolower(trim((string) ($opt['email'] ?? '')));
$motDePasse = (string) ($opt['mot-de-passe'] ?? '');

if ($email === '' || $motDePasse === '') {
    sortie(
        "Usage :\n"
        . "  php outils/compte-admin.php --liste\n"
        . "  php outils/compte-admin.php --email=vous@exemple.fr --mot-de-passe='…' [--changement-impose]\n"
    );
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sortie('Adresse e-mail invalide : ' . $email);
}

// Un mot de passe court n'est pas refusé — cet outil sert justement à
// débloquer une situation — mais il ne passe pas inaperçu.
$court = mb_strlen($motDePasse) < 12;

$existant = null;
foreach (Store::read('users') as $c) {
    if (strtolower((string) ($c['email'] ?? '')) === $email) {
        $existant = $c;
        break;
    }
}

$champs = [
    'password_hash' => password_hash($motDePasse, PASSWORD_DEFAULT),
    'must_change_password' => isset($opt['changement-impose']),
    'active' => true,
    // Ferme les sessions ouvertes avant ce changement.
    'password_changed_at' => date('c'),
    // Le hachage ne dit rien de la robustesse du mot de passe : on retient
    // ici qu'il était court, pour que le back-office puisse le rappeler
    // jusqu'à ce qu'il soit remplacé.
    'password_faible' => $court,
];

if ($existant !== null) {
    Store::update('users', (string) ($existant['id'] ?? ''), $champs);
    $action = 'Mot de passe remplacé';
} else {
    Store::push('users', $champs + [
        'name' => (string) ($opt['nom'] ?? 'Administrateur'),
        'email' => $email,
        'role' => 'admin',
    ]);
    $action = 'Compte créé';
}

// Les demandes de réinitialisation en cours n'ont plus lieu d'être.
if (is_file(Store::path('password-resets'))) {
    Store::write('password-resets', []);
}
@unlink(DATA_DIR . '/' . PasswordReset::FICHIER_REPLI);

echo $action . ' : ' . $email . "\n";
echo 'Connexion : ' . rtrim((string) settings('site.url', ''), '/') . url('admin/login') . "\n";
if ($champs['must_change_password']) {
    echo "Un nouveau mot de passe sera demandé dès la première connexion.\n";
}
if ($court) {
    echo "\n⚠  Ce mot de passe fait moins de 12 caractères. Le back-office donne accès à des\n"
       . "   candidatures nominatives : changez-le depuis Back-office → Utilisateurs dès que\n"
       . "   vous êtes entré. Une phrase de passe fait un excellent mot de passe.\n";
}
exit(0);
