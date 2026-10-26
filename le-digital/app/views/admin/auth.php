<?php /** @var string $mode setup|login @var string $error @var string $csrf */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $mode === 'setup' ? 'Installation' : 'Connexion' ?> · Back-office LE-DIGITAL.com</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('admin/admin.css') ?>">
</head>
<body class="auth">
<main class="auth__card">
  <img src="/assets/img/logo.svg" alt="LE-DIGITAL.com" width="146" height="34" class="auth__logo">
  <?php if ($mode === 'setup'): ?>
    <h1>Bienvenue Fred 👋</h1>
    <p class="auth__p">Crée ton compte administrateur. C’est la seule fois que cette page s’affiche.</p>
  <?php else: ?>
    <h1>On se reconnecte ?</h1>
    <p class="auth__p">Accès réservé au back-office du site.</p>
  <?php endif; ?>
  <?php if ($error !== ''): ?><p class="auth__err" role="alert"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?php if ($mode === 'setup'): ?>
      <label>Ton prénom<input name="name" autocomplete="given-name" value="Frédéric"></label>
    <?php endif; ?>
    <label>E-mail<input type="email" name="email" required autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <label>Mot de passe<input type="password" name="password" required minlength="<?= $mode === 'setup' ? 10 : 1 ?>" autocomplete="<?= $mode === 'setup' ? 'new-password' : 'current-password' ?>"></label>
    <?php if ($mode === 'setup'): ?>
      <label>Confirme le mot de passe<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
    <?php endif; ?>
    <button class="btn btn--red btn--block" type="submit"><?= $mode === 'setup' ? 'Créer mon compte' : 'Se connecter' ?></button>
  </form>
  <a class="auth__back" href="/">← Retour au site</a>
</main>
</body>
</html>
