<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/Store.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/ErrorHandler.php';
require __DIR__ . '/Security.php';
require __DIR__ . '/Icons.php';
require __DIR__ . '/Router.php';
require __DIR__ . '/ContentSchema.php';
require __DIR__ . '/Smtp.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/PasswordReset.php';
require __DIR__ . '/Analytics.php';
require __DIR__ . '/Housekeeping.php';
require __DIR__ . '/DocText.php';
require __DIR__ . '/Bot.php';
require __DIR__ . '/Controllers/SiteController.php';
require __DIR__ . '/Controllers/ApiController.php';
require __DIR__ . '/Controllers/AdminController.php';

// Toute erreur non rattrapée est journalisée et présentée proprement.
ErrorHandler::register();

// Première exécution : on installe les données de démarrage. Ensuite, on
// complète simplement les réglages apparus avec les nouvelles versions.
require __DIR__ . '/install.php';
if (!is_file(DATA_DIR . '/settings.json')) {
    Installer::run();
} else {
    Installer::upgrade();
    // Un compte administrateur utilisable doit toujours exister : c'est ce
    // qui rend possible la reprise en main par simple accès au fichier
    // (suppression de data/users.json, ou dépôt de NOUVEAU-COMPTE.txt).
    Installer::ensureAdmin();
}

// Purge des données échues : une fois par jour, déclenchée par le trafic
// (1 requête publique sur 200) pour ne pas dépendre d'une tâche planifiée.
if (PHP_SAPI !== 'cli' && random_int(1, 200) === 1) {
    Housekeeping::maybeRun();
}

// En-têtes de sécurité communs.
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');
    header_remove('X-Powered-By');

    // Politique de sécurité du contenu. Les scripts en ligne portent un
    // nonce ; aucune ressource externe n'est chargée (polices et icônes
    // sont servies par le site lui-même), d'où un 'self' partout. Les
    // styles restent en 'unsafe-inline' : la mise en page s'appuie sur des
    // attributs style="" dont la suppression changerait le rendu.
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "base-uri 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "form-action 'self'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self' 'nonce-" . csp_nonce() . "'"
    );

    // HSTS : uniquement lorsque la requête est déjà chiffrée, sinon on
    // condamnerait un site accessible en clair.
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Les pages HTML portent un jeton CSRF propre au visiteur : elles ne
    // doivent jamais être mises en cache par un intermédiaire partagé.
    // Les routes qui produisent autre chose (plan de site, manifeste,
    // robots.txt) remplacent cet en-tête par le leur.
    header('Cache-Control: private, no-cache, must-revalidate');
}
