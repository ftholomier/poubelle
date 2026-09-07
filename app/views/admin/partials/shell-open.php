<?php /** @var array $settings @var array $user @var string $active @var int $newLeads @var array $flash @var string $title */ ?>
<!DOCTYPE html>
<html lang="fr">
<head><?= App\Core\View::render('admin/partials/head', ['settings' => $settings, 'title' => $title ?? 'Administration']) ?></head>
<body>
<div class="ad-shell">
    <?= App\Core\View::render('admin/partials/sidebar', compact('settings', 'user', 'active', 'newLeads')) ?>
    <main class="ad-main">
        <button type="button" class="ad-menu-toggle" data-sidebar-toggle><?= icon('menu', '', 18) ?> Menu</button>
        <?= App\Core\View::render('admin/partials/flash', ['flash' => $flash]) ?>
