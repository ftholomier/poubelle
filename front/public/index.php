<?php

declare(strict_types=1);

/*
 * Contrôleur frontal : seul fichier PHP exposé sur le web.
 * Tout le code (src/, views/, config/, content/, storage/) est hors de public/.
 */

// Serveur de développement PHP : laisser servir les fichiers statiques.
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_file($f) && !str_ends_with($f, '.php')) {
        return false;
    }
}

require __DIR__ . '/../src/bootstrap.php';

$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$norm = rtrim(rawurldecode($path), '/') ?: '/';

try {
    if ($norm === '/admin' || str_starts_with($norm, '/admin/')) {
        Admin::route($norm, $method);
        exit;
    }
    if (maintenance()) {
        Site::maintenancePage();
    }
    if ($norm === '/api' || str_starts_with($norm, '/api/')) {
        Api::handle($norm, $method);
        exit;
    }
    if ($norm === '/sitemap.xml' || $norm === '/sitemap_index.xml' || $norm === '/page-sitemap.xml' || $norm === '/post-sitemap.xml') {
        Site::sitemap();
    }
    if ($norm === '/robots.txt') {
        Site::robots();
    }
    Site::handle($path, $method);
} catch (Throwable $e) {
    error_log('[renovest] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Erreur</title>'
        . '<p style="font:16px system-ui;text-align:center;margin-top:20vh;color:#1c1c1c">Une erreur est survenue. Réessayez dans un instant ou appelez-nous au 03 81 95 22 17.</p>';
}
