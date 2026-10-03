<?php
/** Aide : guide complet sur une page, mis en forme pour l'impression et le PDF. Variables : $chapters */
use App\Admin\Help;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Guide du back-office · Sochaux Rétro</title>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('admin/aide.css') ?>">
</head>
<body class="aide-print">
<section class="pcover">
  <img src="/assets/img/logo-sochaux-retro.png" alt="" class="pcover__logo">
  <span class="pcover__kicker">Sochaux Rétro · le musée en ligne du FC Sochaux-Montbéliard</span>
  <h1>Guide d’utilisation<br>du back-office</h1>
  <p>Pour les historiens et les administrateurs du musée : saisir, illustrer et publier les contenus, comprendre ce que le site fait tout seul, et prendre la relève de l’ancien WordPress.</p>
  <span class="pcover__date">Édition du <?= e(date_fr(date('Y-m-d'))) ?></span>
</section>
<section class="ptoc">
  <h2>Sommaire</h2>
  <ol>
    <?php foreach ($chapters as $c): ?><li><a href="#<?= e($c['slug']) ?>"><b><?= e($c['title']) ?></b></a><span><?= e($c['summary']) ?></span></li><?php endforeach; ?>
  </ol>
</section>
<?php $n = 0; foreach ($chapters as $c): $n++; ?>
<section class="pchap" id="<?= e($c['slug']) ?>">
  <span class="pchap__n">Chapitre <?= sprintf('%02d', $n) ?></span>
  <h2><?= e($c['title']) ?></h2>
  <p class="aide-lead"><?= e($c['summary']) ?></p>
  <?php foreach ($c['sections'] as $s): ?>
    <div class="aide-sec" id="<?= e($c['slug'] . '-' . $s['id']) ?>">
      <h3><?= e($s['title']) ?></h3>
      <?= Help::render($s['html']) ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endforeach; ?>
</body>
</html>
