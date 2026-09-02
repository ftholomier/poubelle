<?php

use App\Admin\AdminController;
use App\Admin\Auth;
use App\View;

/** @var array<string,mixed> $site */
/** @var string $content */

$theme = $site['theme'] ?? [];
$loggedIn = Auth::isLoggedIn();
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/admin';

$menu = [
    '/admin'        => 'Tableau de bord',
    '/admin/pages'  => 'Pages & sections',
    '/admin/formes' => 'Atelier de formes',
    '/admin/theme'  => 'Couleur du site',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Back-office — <?= View::e($site['name'] ?? '') ?></title>
<?php /* La carte doit précéder tout module : elle indique au navigateur
             quelle version de chaque fichier charger. Sans elle, un point
             d'entrée à jour peut importer des dépendances restées en cache. */ ?>
    <script type="importmap"><?= View::importMap() ?></script>
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/admin.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%23111'/><circle cx='16' cy='16' r='7' fill='<?= View::e(rawurlencode($theme['accent'] ?? '#7b01f7')) ?>'/></svg>">
    <style>
        :root {
            --accent: <?= View::e($theme['accent'] ?? '#7b01f7') ?>;
            --accent-2: <?= View::e($theme['accent2'] ?? '#c001f7') ?>;
            --accent-3: <?= View::e($theme['accent3'] ?? '#25d5ff') ?>;
        }
    </style>
</head>
<body class="admin<?= $loggedIn ? '' : ' admin--bare' ?>">
<?php if ($loggedIn): ?>
    <header class="admin__bar">
        <span class="admin__brand">
            <span class="admin__dot" aria-hidden="true"></span>
            <?= View::e($site['name'] ?? '') ?> · back-office
        </span>

        <nav class="admin__nav" aria-label="Sections du back-office">
            <?php foreach ($menu as $href => $label): ?>
                <a
                    href="<?= View::e($href) ?>"
                    class="admin__link<?= $current === $href ? ' is-current' : '' ?>"
                    <?= $current === $href ? 'aria-current="page"' : '' ?>>
                    <?= View::e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin__right">
            <span class="admin__whoami" title="Compte connecté"><?= View::e(Auth::email()) ?></span>
            <a class="admin__link" href="/" target="_blank" rel="noopener">Voir le site ↗</a>
            <form method="post" action="/admin/deconnexion" class="admin__logout">
                <input type="hidden" name="csrf" value="<?= View::e(Auth::csrfToken()) ?>">
                <button type="submit" class="admin__signout">Déconnexion</button>
            </form>
        </div>
    </header>
<?php endif; ?>

<main class="admin__main">
    <?php $flash = $loggedIn ? AdminController::takeFlash() : null; ?>
    <?php if ($flash !== null): ?>
        <p class="notice notice--<?= $flash['level'] === 'error' ? 'error' : 'ok' ?>"
           role="<?= $flash['level'] === 'error' ? 'alert' : 'status' ?>">
            <?= View::e($flash['message']) ?>
        </p>
    <?php endif; ?>

    <?= $content ?>
</main>
</body>
</html>
