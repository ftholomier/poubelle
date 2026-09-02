<?php

declare(strict_types=1);

/**
 * Définit le mot de passe du back-office.
 * Usage : php tools/admin-password.php
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Admin\Auth;

if (PHP_SAPI !== 'cli') {
    exit("Ce script ne s'exécute qu'en ligne de commande.\n");
}

echo "Mot de passe du back-office (10 caractères minimum).\n";

$read = static function (string $prompt): string {
    echo $prompt;
    // stty masque la saisie quand le terminal le permet.
    $silent = @shell_exec('stty -g 2>/dev/null');
    if ($silent !== null && trim((string) $silent) !== '') {
        shell_exec('stty -echo 2>/dev/null');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty ' . trim((string) $silent) . ' 2>/dev/null');
        echo "\n";
        return $value;
    }

    return trim((string) fgets(STDIN));
};

$password = $read('Nouveau mot de passe : ');
$confirm = $read('Confirmation          : ');

if ($password !== $confirm) {
    exit("Les deux saisies diffèrent. Rien n'a été modifié.\n");
}

try {
    Auth::storePassword($password);
} catch (Throwable $e) {
    exit('Échec : ' . $e->getMessage() . "\n");
}

echo "Mot de passe enregistré dans var/admin.json.\n";
echo "Le back-office est accessible sur /admin.\n";
