<?php /** Écran hors coque (connexion, invitation), avec un match en fond pour le plaisir (admin/connexion.js). Variables : $content, $meta */ ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($meta['title'] ?? 'Connexion') . ' · Back-office Sochaux Rétro') ?></title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('admin/admin.css') ?>">
</head>
<body>
<main class="auth">
  <canvas class="auth__pitch" data-pitch aria-hidden="true"></canvas>
  <div class="auth__board" data-board aria-hidden="true"><span>Sochaux</span><b data-home>0</b><i>–</i><b data-away>0</b><span>Visiteurs</span><em data-min>0’</em></div>
  <div class="auth__box">
    <div class="auth__brand"><img src="/assets/img/logo-sochaux-retro.png" alt=""><span><b>Sochaux rétro</b><small>Back-office du musée</small></span></div>
    <?= $content ?>
  </div>
</main>
<script src="<?= asset('admin/connexion.js') ?>" defer></script>
</body>
</html>
