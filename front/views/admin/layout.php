<?php
/** @var string $content @var string $title @var array $user */
$site = Content::site();
$unread = Leads::unread();
$upd = Auth::isAdmin($user) && Updater::available() !== null;
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(Auth::csrf()) ?>">
<title><?= e($title) ?> · Back-office <?= e($site['name']) ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicons/favicon-32x32.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('/assets/css/admin.css')) ?>">
</head>
<body class="bo">
<aside class="bo-side" id="bo-side">
  <a class="bo-logo" href="/admin"><img src="/assets/img/logo.png" alt="<?= e($site['name']) ?>"><span>Back-office</span></a>
  <nav>
    <?php foreach (Admin::NAV as $group => $items):
      $items = array_values(array_filter($items, fn($it) => ($it[2] ?? '') !== 'admin' || Auth::isAdmin($user)));
      if (!$items) { continue; } // rubrique entièrement réservée (Réglages pour un éditeur) : titre masqué aussi ?>
      <div class="bo-nav__g"><?= e($group) ?></div>
      <?php foreach ($items as $it): ?>
        <a class="bo-nav__a<?= Admin::navActive($it[0]) ? ' is-on' : '' ?>" href="/admin<?= $it[0] !== '' ? '/' . e($it[0]) : '' ?>">
          <span><?= e($it[1]) ?></span>
          <?php if ($it[0] === 'demandes' && $unread): ?><b class="bo-badge"><?= $unread ?></b><?php endif; ?>
          <?php if ($it[0] === 'mises-a-jour' && $upd): ?><b class="bo-badge">1</b><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
</aside>
<div class="bo-main">
  <header class="bo-top">
    <button class="bo-burger" type="button" id="bo-burger" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="bo-top__t"><?= e($title) ?></div>
    <div class="bo-top__r">
      <a class="b b--l" href="/" target="_blank" rel="noopener">Voir le site ↗</a>
      <a class="bo-me" href="/admin/compte" title="Mon compte"><?= e(mb_strtoupper(mb_substr($user['name'] ?: $user['email'], 0, 1))) ?></a>
      <a class="bo-out" href="/admin/deconnexion">Déconnexion</a>
    </div>
  </header>
  <main class="bo-content">
    <?= $content ?>
  </main>
</div>
<div class="bo-toasts" id="bo-toasts" aria-live="polite"></div>
<script src="<?= e(asset('/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
