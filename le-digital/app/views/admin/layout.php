<?php
/** @var string $body @var string $title @var array $flash @var array $user @var string $csrf @var array $counts */
$path = LD\Http::path();
$nav = [
    ['/admin', 'Tableau de bord', 'M3 12l9-8 9 8M5 10v10h14V10'],
    ['/admin/demandes', 'Demandes', 'M4 4h16v12H5.17L4 17.17V4zM8 9h8M8 12h5', $counts['new'] ?? 0],
    ['/admin/rendez-vous', 'Rendez-vous', 'M7 3v3M17 3v3M4 8h16M5 5h14v15H5z'],
    ['/admin/contenus', 'Contenus des pages', 'M4 5h16M4 10h16M4 15h10M4 20h7'],
    ['/admin/avis', 'Avis clients', 'M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z'],
    ['/admin/blog', 'Blog', 'M5 4h11l3 3v13H5zM9 9h6M9 13h6M9 17h4'],
    ['/admin/medias', 'Médiathèque', 'M4 5h16v14H4zM4 15l5-5 4 4 3-3 4 4'],
    ['/admin/ia', 'IA & clés API', 'M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z'],
    ['/admin/mises-a-jour', 'Mises à jour', 'M21 12a9 9 0 11-2.6-6.4M21 4v5h-5', LD\Updater::available() ? 1 : 0],
    ['/admin/parametres', 'Paramètres', 'M12 9a3 3 0 100 6 3 3 0 000-6zM19.4 15a1.6 1.6 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.6 1.6 0 00-1.8-.3 1.6 1.6 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.6 1.6 0 00-1-1.5 1.6 1.6 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.6 1.6 0 00.3-1.8 1.6 1.6 0 00-1.5-1H3a2 2 0 110-4h.1a1.6 1.6 0 001.5-1 1.6 1.6 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.6 1.6 0 001.8.3H9a1.6 1.6 0 001-1.5V3a2 2 0 114 0v.1a1.6 1.6 0 001 1.5 1.6 1.6 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.6 1.6 0 00-.3 1.8V9a1.6 1.6 0 001.5 1H21a2 2 0 110 4h-.1a1.6 1.6 0 00-1.5 1z'],
];
$isActive = static fn(string $href) => $href === '/admin' ? $path === '/admin' : str_starts_with($path, $href);
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e($csrf) ?>">
<title><?= e($title ?: 'Back-office') ?> · LE-DIGITAL.com</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('admin/admin.css') ?>">
</head>
<body class="adm">
<aside class="side" id="side">
  <div class="side__top">
    <a class="side__logo" href="/admin"><img src="/assets/img/logo-blanc.svg" alt="LE-DIGITAL.com" width="128" height="30"><span>Back-office</span></a>
    <button class="side__close" type="button" data-side-toggle aria-label="Fermer le menu">×</button>
  </div>
  <nav class="side__nav">
    <?php foreach ($nav as $n): ?>
      <a href="<?= e($n[0]) ?>"<?= $isActive($n[0]) ? ' class="is-on" aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= $n[2] ?>"/></svg><span><?= e($n[1]) ?></span><?php if (!empty($n[3])): ?><b class="side__badge"><?= (int) $n[3] ?></b><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side__foot">
    <a href="/" target="_blank" rel="noopener">Voir le site ↗</a>
    <a href="/admin/compte"<?= $isActive('/admin/compte') ? ' class="is-on"' : '' ?>><?= e($user['name'] ?: $user['email']) ?></a>
    <form method="post" action="/admin/deconnexion"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button type="submit">Se déconnecter</button></form>
  </div>
</aside>
<div class="main">
  <header class="topbar">
    <button class="topbar__menu" type="button" data-side-toggle aria-label="Menu">☰</button>
    <h1 class="topbar__title"><?= e($title) ?></h1>
    <a class="topbar__site" href="/" target="_blank" rel="noopener">Voir le site ↗</a>
  </header>
  <?php foreach ($flash as $f): ?>
    <div class="flash flash--<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?><button type="button" aria-label="Fermer" data-dismiss>×</button></div>
  <?php endforeach; ?>
  <div class="content"><?= $body ?></div>
</div>
<div class="toast" id="toast" role="status" hidden></div>
<script src="<?= asset('admin/admin.js') ?>" defer></script>
</body>
</html>
