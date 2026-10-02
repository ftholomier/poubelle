<?php
/** Accès au site protégé par mot de passe (pré-lancement). Variables : $error */
$site = (string) \App\Core\Settings::get('general.site_name', 'Sochaux Rétro');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('Accès réservé')) ?> | <?= e($site) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body>
<main class="waiting">
  <div class="waiting__inner" style="max-width:520px">
    <img src="/assets/img/logo-sochaux-retro.png" alt="<?= e($site) ?>" width="133" height="150">
    <h1 class="h-2" style="color:var(--cream)"><?= e(t('Accès réservé')) ?></h1>
    <p class="lead" style="margin:0 auto"><?= e(t('Le nouveau musée est en préparation. Saisissez le mot de passe qui vous a été communiqué.')) ?></p>
    <form method="post" class="gateform">
      <label class="sr-only" for="front_password"><?= e(t('Mot de passe')) ?></label>
      <input id="front_password" type="password" name="front_password" autocomplete="current-password" required autofocus>
      <button class="btn btn--yellow" type="submit"><?= e(t('Entrer')) ?></button>
    </form>
    <?php if ($error !== ''): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
  </div>
</main>
</body>
</html>
