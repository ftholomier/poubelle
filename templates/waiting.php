<?php
/**
 * Page d'attente (réglages > Page d'attente) : logo, texte WYSIWYG, compte à rebours optionnel.
 * Page autonome. Variables : $logo, $title, $text, $countdown, $countdownDate, $countdownLabel, $social,
 * $teaser (vidéo affichée), $app (appli installable), $push (bouton « Prévenez-moi de l'ouverture »).
 * Aucun lien vers le back-office : l'équipe s'y rend par /admin.
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
<html lang="fr" data-app="<?= !empty($app) ? '1' : '0' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?: $site) ?> | <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="<?= e(mb_substr(trim(strip_tags($text)), 0, 160)) ?>">
<meta property="og:title" content="<?= e($title ?: $site) ?>">
<meta property="og:image" content="<?= e(base_url()) ?>/assets/img/partage-defaut.png">
<meta name="theme-color" content="#0E1F4D">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<?php if (!empty($app)): ?>
<link rel="apple-touch-icon" href="/assets/img/app/180.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Sochaux Rétro">
<?php endif; ?>
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
    <?php if (!empty($teaser)): ?>
      <video class="waiting__teaser" controls playsinline preload="none" poster="/video/teaser.jpg" width="1920" height="1080" aria-label="<?= e(t('Teaser vidéo du musée')) ?>">
        <source src="/video/teaser.mp4" type="video/mp4">
      </video>
    <?php endif; ?>
    <?php if ($countdown && $countdownDate !== '' && strtotime($countdownDate) > time()): ?>
      <div class="stack" style="width:100%;gap:14px;align-items:center">
        <?php if ($countdownLabel !== ''): ?><span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e($countdownLabel) ?></span><?php endif; ?>
        <?= countdown_html($countdownDate) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($push)):
        $share = '<svg class="appbar__share" viewBox="0 0 24 24" width="18" height="18" aria-label="' . e(t('Partager')) . '" role="img"><path d="M12 3v12M7.5 7.5 12 3l4.5 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 10H6a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1h-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
        $msgs = [
            'busy' => t('Un instant…'),
            'on' => t('C’est noté : vous serez prévenu de l’ouverture sur cet appareil.'),
            'later' => t('Votre navigateur attend votre accord : touchez à nouveau le bouton et choisissez « Autoriser ».'),
            'denied' => t('Les notifications sont bloquées pour ce site dans votre navigateur : autorisez-les dans ses réglages pour être prévenu.'),
            'error' => t('L’inscription n’a pas abouti. Réessayez dans un instant.'),
            'bye' => t('C’est fait : vous ne serez pas prévenu.'),
        ]; ?>
      <div class="waiting__push" data-wait-push data-msgs="<?= e(json_encode($msgs, JSON_UNESCAPED_UNICODE)) ?>" hidden>
        <p><?= e(t('Soyez prévenu le jour de l’ouverture : une notification sur votre téléphone ou votre ordinateur, sans donner ni nom ni e-mail.')) ?></p>
        <button type="button" class="btn btn--yellow" data-wait-on><?= e(t('Prévenez-moi de l’ouverture')) ?></button>
        <p data-wait-ios hidden><?= str_replace('{icon}', $share, e(t('Sur iPhone : touchez {icon} puis « Sur l’écran d’accueil », ouvrez Sochaux Rétro depuis l’écran d’accueil et touchez « Prévenez-moi de l’ouverture ».'))) ?></p>
        <p data-wait-state role="status" hidden></p>
        <button type="button" class="waiting__off" data-wait-off hidden><?= e(t('Ne plus être prévenu')) ?></button>
      </div>
    <?php endif; ?>
    <?php if ($social && $links): ?>
      <div class="social social--waiting">
        <?php foreach ($links as $label => $href): ?><a href="<?= e($href) ?>" rel="noopener" target="_blank"><?= e($label) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<script src="<?= asset('js/site.js') ?>" defer></script>
<?php if (!empty($push)): ?><script src="<?= asset('js/attente.js') ?>" defer></script><?php endif; ?>
</body>
</html>
