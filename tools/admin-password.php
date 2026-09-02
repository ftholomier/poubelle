<?php

declare(strict_types=1);

/**
 * Crée ou remplace le compte du back-office.
 *
 * Usage :
 *   php tools/admin-password.php
 *   php tools/admin-password.php frederic@exemple.fr
 *
 * L'adresse peut être passée en argument ; le mot de passe, jamais, pour qu'il
 * ne reste pas dans l'historique du terminal.
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Admin\Auth;

if (PHP_SAPI !== 'cli') {
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

// stty permet d'éteindre l'écho du terminal. Sans lui — dans un tube, ou sur
// un terminal restreint — la saisie restera visible : on prévient une fois.
$sttyState = @shell_exec('stty -g 2>/dev/null');
$canHide = $sttyState !== null && trim((string) $sttyState) !== '';

/** Lit une ligne, en masquant la saisie quand le terminal le permet. */
$read = static function (string $prompt, bool $hidden = false) use ($canHide, $sttyState): string {
    echo $prompt;

    if (!$hidden || !$canHide) {
        return trim((string) fgets(STDIN));
    }

    shell_exec('stty -echo 2>/dev/null');
    $value = trim((string) fgets(STDIN));
    shell_exec('stty ' . trim((string) $sttyState) . ' 2>/dev/null');
    echo "\n";

    return $value;
};

$current = Auth::isConfigured() ? Auth::email() : '';
if ($current !== '') {
    echo "Compte actuel : {$current}\n";
}

$email = $argv[1] ?? '';
if ($email === '') {
    $invite = $current !== '' ? "Adresse électronique [{$current}] : " : 'Adresse électronique : ';
    $email = $read($invite);
    if ($email === '') {
        $email = $current;
    }
}

if ($email === '') {
    exit("Aucune adresse fournie. Rien n'a été modifié.\n");
}

echo "Mot de passe (10 caractères minimum).\n";
if (!$canHide) {
    echo "Attention : ce terminal ne permet pas de masquer la saisie.\n";
}
$password = $read('  Nouveau mot de passe : ', true);
$confirm = $read('  Confirmation          : ', true);

if ($password !== $confirm) {
    exit("Les deux saisies diffèrent. Rien n'a été modifié.\n");
}

try {
    Auth::storeCredentials($email, $password);
} catch (Throwable $e) {
    exit('Échec : ' . $e->getMessage() . "\n");
}

echo "\nCompte enregistré dans var/admin.json.\n";
echo "Identifiant : {$email}\n";
echo "Le back-office est accessible sur /admin.\n";
