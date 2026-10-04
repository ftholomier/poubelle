<?php
/** Page d'attente du site de l'association (site fermé). Variables : $title, $text, $museumOpen */
use App\Vitrine\Host;
use App\Vitrine\Site;

$social = Site::social();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(Site::name()) ?> · <?= e($title) ?></title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<link rel="stylesheet" href="<?= asset('css/vitrine.css') ?>">
</head>
<body class="vt">
<main class="waiting vwaiting">
  <div class="waiting__inner">
    <img src="/assets/img/logo-sochaux-retro.png" alt="<?= e(Site::name()) ?>" width="133" height="150">
    <span class="eyebrow eyebrow--yellow eyebrow--lg">Association <?= e(Site::name()) ?></span>
    <h1 class="h-xl"><?= e($title) ?></h1>
    <?php if ($text): ?><div class="prose"><?= $text ?></div><?php endif; ?>
    <?php if ($museumOpen): ?><a class="btn btn--yellow" href="<?= e(Host::museum('/')) ?>">Visiter le musée en ligne ↗</a><?php endif; ?>
    <?php if ($social): ?>
      <div class="vsocial vsocial--light" aria-label="Réseaux sociaux">
        <?php foreach ($social as $k => [$label, $href]): ?><a href="<?= e($href) ?>" rel="noopener" target="_blank" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= \App\Core\View::partial('vitrine/partials/icon', ['name' => $k]) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p class="vwaiting__legal"><a href="/mentions-legales/">Mentions légales</a> · <a href="/confidentialite/">Confidentialité</a></p>
  </div>
</main>
</body>
</html>
