<?php
/**
 * Espace membre : une page, plusieurs écrans selon $mode
 * (login, register, confirm, forgot, reset, account).
 */
use App\Core\Csrf;
use App\Services\I18n;
use App\Services\SpamGuard;

$mode ??= 'login';
$t = static fn(string $k): string => e(I18n::t($k));
$return ??= '';
?>
<div class="container account-page" style="max-width:560px">
  <div class="card card-lg" style="margin:40px 0" data-reveal>

  <?php if ($mode === 'login'): ?>
    <h1 class="h1-sub" style="margin-bottom:14px"><?= $t('account.login_title') ?></h1>
    <p class="lede" style="margin-bottom:18px"><?= $t('account.login_lede') ?></p>
    <?php if ($error !== ''): ?><div class="notice notice-err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(I18n::url('/connexion')) ?>">
      <?= Csrf::field('account-login') ?>
      <input type="hidden" name="retour" value="<?= e($return) ?>">
      <label class="field"><span class="label"><?= $t('account.email') ?></span>
        <input class="input" type="email" name="email" value="<?= e($email) ?>" required autocomplete="email"></label>
      <label class="field" style="margin-top:14px"><span class="label"><?= $t('account.password') ?></span>
        <input class="input" type="password" name="password" required autocomplete="current-password"></label>
      <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= $t('nav.login') ?></button>
    </form>
    <p style="margin-top:18px;font-size:14px">
      <a href="<?= e(I18n::url('/mot-de-passe-oublie')) ?>"><?= $t('account.forgot_link') ?></a> ·
      <a href="<?= e(I18n::url('/inscription')) ?>"><?= $t('account.register_cta') ?></a>
    </p>

  <?php elseif ($mode === 'register'): ?>
    <?php if ($done): ?>
      <h1 class="h1-sub" style="margin-bottom:12px"><?= $t('account.register_done_title') ?></h1>
      <p class="lede"><?= $t('account.register_done') ?></p>
    <?php else: ?>
      <?php $err = static fn(string $k): string => (string) ($errors[$k] ?? ''); $val = static fn(string $k): string => e((string) ($old[$k] ?? '')); ?>
      <h1 class="h1-sub" style="margin-bottom:12px"><?= $t('account.register_title') ?></h1>
      <p class="lede" style="margin-bottom:18px"><?= $t('account.register_lede') ?></p>
      <?php if ($err('_form') !== ''): ?><div class="notice notice-err" role="alert"><?= e($err('_form')) ?></div><?php endif; ?>
      <form method="post" action="<?= e(I18n::url('/inscription')) ?>" novalidate>
        <?= Csrf::field('account-register') ?>
        <?= SpamGuard::fields() ?>
        <label class="field"><span class="label"><?= $t('account.name') ?></span>
          <input class="input" type="text" name="name" value="<?= $val('name') ?>" required autocomplete="name">
          <?php if ($err('name') !== ''): ?><span class="field-error"><?= e($err('name')) ?></span><?php endif; ?></label>
        <label class="field" style="margin-top:14px"><span class="label"><?= $t('account.email') ?></span>
          <input class="input" type="email" name="email" value="<?= $val('email') ?>" required autocomplete="email">
          <?php if ($err('email') !== ''): ?><span class="field-error"><?= e($err('email')) ?></span><?php endif; ?></label>
        <label class="field" style="margin-top:14px"><span class="label"><?= $t('account.password_new') ?></span>
          <input class="input" type="password" name="password" required minlength="10" autocomplete="new-password">
          <?php if ($err('password') !== ''): ?><span class="field-error"><?= e($err('password')) ?></span><?php endif; ?></label>
        <fieldset class="field" style="margin-top:14px;border:0;padding:0">
          <legend class="label"><?= $t('account.role') ?></legend>
          <label><input type="radio" name="role" value="employer"<?= ($old['role'] ?? 'employer') !== 'candidate' ? ' checked' : '' ?>> <?= $t('account.role_employer') ?></label><br>
          <label><input type="radio" name="role" value="candidate"<?= ($old['role'] ?? '') === 'candidate' ? ' checked' : '' ?>> <?= $t('account.role_candidate') ?></label>
        </fieldset>
        <label style="display:flex;gap:8px;margin-top:14px;font-size:14px">
          <input type="checkbox" name="terms" value="1"<?= ($old['terms'] ?? '') !== '' ? ' checked' : '' ?>>
          <span><?= $t('account.terms') ?> <a href="<?= e(I18n::url('/cgu')) ?>" target="_blank" rel="noopener"><?= $t('account.terms_link') ?></a></span>
        </label>
        <?php if ($err('terms') !== ''): ?><span class="field-error"><?= e($err('terms')) ?></span><?php endif; ?>
        <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= $t('account.register_cta') ?></button>
      </form>
      <p style="margin-top:18px;font-size:14px"><a href="<?= e(I18n::url('/connexion')) ?>"><?= $t('account.have_account') ?></a></p>
    <?php endif; ?>

  <?php elseif ($mode === 'confirm'): ?>
    <h1 class="h1-sub" style="margin-bottom:12px"><?= $t('account.confirm_title') ?></h1>
    <p class="lede"><?= $ok ? $t('account.confirm_ok') : $t('account.confirm_bad') ?></p>
    <a class="btn btn-coral" style="margin-top:18px" href="<?= e(I18n::url('/connexion')) ?>"><?= $t('nav.login') ?></a>

  <?php elseif ($mode === 'forgot'): ?>
    <h1 class="h1-sub" style="margin-bottom:12px"><?= $t('account.forgot_title') ?></h1>
    <?php if ($done): ?>
      <p class="lede"><?= $t('account.forgot_done') ?></p>
    <?php else: ?>
      <?php if ($error !== ''): ?><div class="notice notice-err" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(I18n::url('/mot-de-passe-oublie')) ?>">
        <?= Csrf::field('account-forgot') ?>
        <label class="field"><span class="label"><?= $t('account.email') ?></span>
          <input class="input" type="email" name="email" required autocomplete="email"></label>
        <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= $t('account.forgot_send') ?></button>
      </form>
    <?php endif; ?>

  <?php elseif ($mode === 'reset'): ?>
    <h1 class="h1-sub" style="margin-bottom:12px"><?= $t('account.reset_title') ?></h1>
    <?php if ($done): ?>
      <p class="lede"><?= $t('account.reset_done') ?></p>
      <a class="btn btn-coral" style="margin-top:18px" href="<?= e(I18n::url('/connexion')) ?>"><?= $t('nav.login') ?></a>
    <?php elseif (!$valid): ?>
      <p class="lede"><?= $t('account.confirm_bad') ?></p>
    <?php else: ?>
      <?php if ($error !== ''): ?><div class="notice notice-err" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="<?= e(I18n::url('/nouveau-mot-de-passe')) ?>">
        <?= Csrf::field('account-reset') ?>
        <input type="hidden" name="jeton" value="<?= e($token) ?>">
        <label class="field"><span class="label"><?= $t('account.password_new') ?></span>
          <input class="input" type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <button type="submit" class="btn btn-coral" style="margin-top:18px"><?= $t('account.reset_save') ?></button>
      </form>
    <?php endif; ?>

  <?php elseif ($mode === 'account'): ?>
    <h1 class="h1-sub" style="margin-bottom:12px"><?= $t('nav.account') ?></h1>
    <p class="lede"><?= e((string) $user['name']) ?> · <?= e((string) $user['email']) ?></p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
      <a class="btn btn-coral" href="<?= e(I18n::url('/cv')) ?>"><?= $t('nav.cv') ?></a>
      <a class="btn btn-ghost" href="<?= e(I18n::url('/mot-de-passe-oublie')) ?>"><?= $t('account.change_password') ?></a>
      <form method="post"><?= Csrf::field('account') ?>
        <button type="submit" name="action" value="logout" class="btn btn-ghost"><?= $t('nav.logout') ?></button></form>
    </div>
    <form method="post" style="margin-top:28px"><?= Csrf::field('account') ?>
      <button type="submit" name="action" value="delete" class="btn btn-ghost btn-sm"
              data-confirm="<?= $t('account.delete_confirm') ?>"><?= $t('account.delete') ?></button>
    </form>
  <?php endif; ?>

  </div>
</div>
