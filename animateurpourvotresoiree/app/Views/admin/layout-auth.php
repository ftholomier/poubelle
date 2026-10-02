<?php
use App\Core\Security;
use App\Core\Session;
use App\Services\Settings;

/** @var string $content @var string $title */
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Administration') ?> — <?= e(Settings::siteName()) ?></title>
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<meta name="theme-color" content="#1c1233">
</head>
<body class="admin-auth">
<main class="auth-box">
  <div class="logo"><span class="logo-mark" aria-hidden="true">A</span><span class="logo-word">animateur<em>pour</em>votresoirée</span></div>
  <p class="mono muted small">Back-office</p>
  <?php foreach (Session::active() ? Session::flashes() : [] as $f): ?><div class="alert alert-<?= e($f['type']) ?> mb-2"><?= e($f['msg']) ?></div><?php endforeach; ?>
  <?= $content ?>
</main>
<script src="<?= asset('js/app.js') ?>" defer<?= Security::attr() ?>></script>
</body>
</html>
