<?php
/** Mot de passe oublié. @var bool $done @var string $error */
use App\Core\Csrf;
use App\Services\I18n;
?>
<div class="auth-stack">
  <div class="auth-card">
    <a class="logo logo-sm" href="/">intermittent<span class="tld">.fr</span></a>
    <h1><?= e(I18n::t('admin.forgot_title')) ?></h1>
    <p><?= e(I18n::t('admin.forgot_body')) ?></p>

    <?php if ($error !== ''): ?><div class="notice notice-err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($done): ?>
      <div class="notice notice-ok" role="status"><?= e(I18n::t('admin.forgot_done')) ?></div>
    <?php else: ?>
      <form method="post" novalidate>
        <?= Csrf::field('admin-forgot') ?>
        <label class="field">
          <span class="label"><?= e(I18n::t('admin.email')) ?></span>
          <input class="input" type="email" name="email" required autocomplete="username" autofocus>
        </label>
        <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= e(I18n::t('admin.forgot_send')) ?></button>
      </form>
    <?php endif; ?>

    <p style="margin:20px 0 0"><a href="/admin" style="font-size:13.5px">← <?= e(I18n::t('admin.login')) ?></a></p>
  </div>

</div>
