<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/Store.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/Security.php';
require __DIR__ . '/Icons.php';
require __DIR__ . '/Router.php';
require __DIR__ . '/ContentSchema.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/Analytics.php';
require __DIR__ . '/DocText.php';
require __DIR__ . '/Bot.php';
require __DIR__ . '/Controllers/SiteController.php';
require __DIR__ . '/Controllers/ApiController.php';
require __DIR__ . '/Controllers/AdminController.php';

// Première exécution : on installe les données de démarrage. Ensuite, on
// complète simplement les réglages apparus avec les nouvelles versions.
require __DIR__ . '/install.php';
if (!is_file(DATA_DIR . '/settings.json')) {
    Installer::run();
} else {
    Installer::upgrade();
}

// En-têtes de sécurité communs.
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header_remove('X-Powered-By');
}
