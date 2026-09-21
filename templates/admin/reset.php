<?php
/** Choix d'un nouveau mot de passe. @var string $token @var string $error @var bool $done @var bool $valid */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="auth-stack">
  <div class="auth-card">
    <a class="logo logo-sm" href="/">intermittent<span class="tld">.fr</span></a>
    <h1><?= e(I18n::t('admin.new_password')) ?></h1>

    <?php if ($done): ?>
      <div class="notice notice-ok" role="status"><?= e(I18n::t('admin.reset_done')) ?></div>
      <a class="btn btn-coral" href="/admin"><?= e(I18n::t('admin.login')) ?></a>
    <?php else: ?>
      <?php if ($error !== ''): ?><div class="notice notice-err" role="alert"><?= e($error) ?></div><?php endif; ?>
      <?php if ($valid): ?>
        <p><?= e(I18n::t('admin.new_password_note')) ?></p>
        <form method="post" novalidate>
          <?= Csrf::field('admin-reset') ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <label class="field">
            <span class="label"><?= e(I18n::t('admin.new_password')) ?></span>
            <input class="input" type="password" name="password" required minlength="10"
                   autocomplete="new-password" autofocus>
          </label>
          <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= e(I18n::t('admin.reset_submit')) ?></button>
        </form>
      <?php else: ?>
        <a class="btn btn-coral" href="/admin/mot-de-passe-oublie"><?= e(I18n::t('admin.forgot_send')) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
