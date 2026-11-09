<?php /** Pages sans menu : connexion, installation, mot de passe. */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Back-office <?= e(Content::site()['name']) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicons/favicon-32x32.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
</head>
<body class="bo-bare">
<div class="bo-auth">
  <a class="bo-auth__logo" href="/"><img src="/assets/img/logo.png" alt="<?= e(Content::site()['name']) ?>"></a>
  <div class="bo-auth__card"><?= $content ?></div>
</div>
<div class="bo-toasts" id="bo-toasts" aria-live="polite"></div>
<script src="<?= e(asset('/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
