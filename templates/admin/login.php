<?php
/** Connexion au back-office. @var string $error */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="auth-stack">
  <div class="auth-card">
    <a class="logo logo-sm" href="/">intermittent<span class="tld">.fr</span></a>
    <h1><?= e(I18n::t('admin.title')) ?></h1>
    <p>Réservé à l'équipe éditoriale.</p>

    <?php if ($error !== ''): ?>
      <div class="notice notice-err" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= Csrf::field('admin-login') ?>
      <label class="field">
        <span class="label"><?= e(I18n::t('admin.email')) ?></span>
        <input class="input" type="email" name="email" required autocomplete="username" autofocus>
      </label>
      <label class="field" style="margin-top:14px">
        <span class="label"><?= e(I18n::t('admin.password')) ?></span>
        <input class="input" type="password" name="password" required autocomplete="current-password">
      </label>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:20px;flex-wrap:wrap">
        <a href="/admin/mot-de-passe-oublie" style="font-size:13.5px"><?= e(I18n::t('admin.forgot')) ?></a>
        <button type="submit" class="btn btn-coral"><?= e(I18n::t('admin.login')) ?></button>
      </div>
    </form>
  </div>

  <div class="auth-note">
    <h2><?= e(I18n::t('admin.forgot_title')) ?></h2>
    <p><?= e(I18n::t('admin.security')) ?></p>
  </div>
</div>
