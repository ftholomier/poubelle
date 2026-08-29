<?php
/**
 * Layout des écrans non connectés (connexion, première configuration).
 *
 * @var string $slot
 * @var array|null $page
 */
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page['titre'] ?? 'Administration') ?> — Mairie de Lachapelle-sous-Chaux</title>
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="bo bo--centre">
<main class="bo-carte-acces">
  <img class="bo-carte-acces__logo" src="<?= asset('assets/img/logo/logo-lachapelle-clair.svg') ?>" alt="Mairie de Lachapelle-sous-Chaux">
  <?= $slot ?>
</main>
</body>
</html>
