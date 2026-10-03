<?php
/**
 * Page d'attente (réglages > Page d'attente) : logo, texte WYSIWYG, compte à rebours optionnel.
 * Page autonome. Variables : $logo, $title, $text, $countdown, $countdownDate, $countdownLabel, $social
 */

use App\Core\Settings;

$links = array_filter([
    'Facebook' => Settings::get('social.facebook'),
    'Instagram' => Settings::get('social.instagram'),
    'X' => Settings::get('social.x'),
    'YouTube' => Settings::get('social.youtube'),
]);
$site = (string) Settings::get('general.site_name', 'Sochaux Rétro');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?: $site) ?> | <?= e($site) ?></title>
<meta name="robots" content="noindex">
<meta name="description" content="<?= e(mb_substr(trim(strip_tags($text)), 0, 160)) ?>">
<meta property="og:title" content="<?= e($title ?: $site) ?>">
<meta property="og:image" content="<?= e(base_url()) ?>/assets/img/partage-defaut.png">
<meta name="theme-color" content="#0E1F4D">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body>
<main class="waiting">
  <div class="waiting__inner">
    <?php if (!empty($logo) && \App\Data\Media::get($logo)): ?>
      <img src="<?= e(img($logo, 480)) ?>" alt="<?= e($site) ?>" style="max-width:240px;max-height:200px;width:auto;height:auto">
    <?php else: ?>
      <img src="/assets/img/logo-sochaux-retro.png" alt="<?= e($site) ?>" width="133" height="150">
    <?php endif; ?>
    <?php if ($title !== ''): ?><h1 class="h-xl" style="color:var(--cream)"><?= e($title) ?></h1><?php endif; ?>
    <?php if (trim(strip_tags($text)) !== ''): ?><div class="prose"><?= safe_html($text) ?></div><?php endif; ?>
    <?php if ($countdown && $countdownDate !== '' && strtotime($countdownDate) > time()): ?>
      <div class="stack" style="width:100%;gap:14px;align-items:center">
        <?php if ($countdownLabel !== ''): ?><span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e($countdownLabel) ?></span><?php endif; ?>
        <?= countdown_html($countdownDate) ?>
      </div>
    <?php endif; ?>
    <?php if ($social && $links): ?>
      <div class="social social--waiting">
        <?php foreach ($links as $label => $href): ?><a href="<?= e($href) ?>" rel="noopener" target="_blank"><?= e($label) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <a class="waiting__login" href="/admin/connexion"><?= e(t('Accès équipe')) ?></a>
  </div>
</main>
<script src="<?= asset('js/site.js') ?>" defer></script>
</body>
</html>
