<?php
/** Mise en page de l'espace imprimeur. Variables : $title, $content (HTML), $flash, $logged */
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Espace imprimeur · Sochaux Rétro</title>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('admin/admin.css') ?>">
<link rel="stylesheet" href="<?= asset('admin/boutique.css') ?>">
<style>
  body { background: var(--paper, #f3eddf); }
  .pr-top { background: #0e1f4d; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 16px; }
  .pr-top b { font-family: var(--display); letter-spacing: .04em; text-transform: uppercase; font-size: 18px; }
  .pr-top a { color: #fdc729; margin-left: auto; }
  .pr-main { max-width: 1240px; margin: 0 auto; padding: 20px 16px 60px; }
  .pr-main h1 { font-family: var(--display); text-transform: uppercase; margin: 0 0 14px; }
</style>
</head>
<body>
<header class="pr-top"><b>Sochaux Rétro · Espace imprimeur</b><?php if ($logged): ?><a href="/imprimeur/deconnexion">Se déconnecter</a><?php endif; ?></header>
<main class="pr-main">
  <h1><?= e($title) ?></h1>
  <?php foreach ($flash as $f): ?><div class="alert alert--<?= $f['type'] === 'ok' ? 'ok' : 'info' ?>" style="margin:0 0 12px"><?= e($f['message']) ?></div><?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
