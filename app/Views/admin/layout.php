<?php
$nav = $nav ?? '';
$user = $user ?? [];
$pendingDrafts = count(array_filter(Store::read('applications'), static fn ($a) => ($a['status'] ?? '') === 'brouillon'));
$newApps = count(array_filter(Store::read('applications'), static fn ($a) => ($a['stage'] ?? '') === 'nouveau' && ($a['status'] ?? '') !== 'brouillon'));
$newLeads = count(Store::read('leads'));
$items = [
    ['dashboard',    'Tableau de bord', url('admin'),                'chart',  null],
    ['applications', 'Candidatures',    url('admin/candidatures'),   'user',   $newApps],
    ['leads',        'Messages',        url('admin/messages'),       'mail',   $newLeads],
];
$items2 = [
    ['content',  'Contenu du site', url('admin/contenu'),      'sparkle'],
    ['posts',    'Actualités',      url('admin/actualites'),   'book'],
    ['settings', 'Réglages',        url('admin/reglages'),     'tools'],
    ['users',    'Utilisateurs',    url('admin/utilisateurs'), 'lock'],
    ['mails',    'E-mails envoyés', url('admin/emails'),       'file'],
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title ?? 'Back-office') ?> — Suisse Immo</title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="side__brand" href="<?= e(url('admin')) ?>">
      <?php partial('logo', ['class' => 'side__logo']); ?>
      <small>Back-office</small>
    </a>

    <nav>
      <h5>Pilotage</h5>
      <?php foreach ($items as [$key, $label, $href, $ic, $count]): ?>
        <a class="item <?= $nav === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>">
          <?= icon($ic) ?> <?= e($label) ?>
          <?php if ($count): ?><span class="badge"><?= (int) $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>

      <h5>Site</h5>
      <?php foreach ($items2 as [$key, $label, $href, $ic]): ?>
        <a class="item <?= $nav === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>"><?= icon($ic) ?> <?= e($label) ?></a>
      <?php endforeach; ?>

      <h5>Raccourcis</h5>
      <a class="item" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= icon('arrow-up-right') ?> Voir le site</a>
      <a class="item" href="<?= e(url('admin/candidatures/export')) ?>"><?= icon('download') ?> Export CSV</a>
    </nav>

    <div class="side__foot">
      <div><?= e($user['name'] ?: $user['email'] ?? '') ?></div>
      <a href="<?= e(url('admin/logout')) ?>" style="color:#ff8290">Se déconnecter</a>
      <?php if ($pendingDrafts): ?>
        <div style="margin-top:10px;font-size:.78rem"><?= (int) $pendingDrafts ?> candidature<?= $pendingDrafts > 1 ? 's' : '' ?> abandonnée<?= $pendingDrafts > 1 ? 's' : '' ?> à relancer</div>
      <?php endif; ?>
    </div>
  </aside>

  <main class="main">
    <?php foreach (Session::flash() as $f): ?>
      <div class="flash flash--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content_for_layout ?>
  </main>
</div>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
