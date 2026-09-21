<?php
/** Assistant IA « Régie ». Au-dessus de la barre CTA, jamais par-dessus. */
use App\Core\Config;
use App\Core\Csrf;
use App\Services\I18n;
use App\Support\Icon;

if (!Config::get('regie.enabled', true)) {
    return;
}
?>
<div class="regie" data-regie
     data-endpoint="/api/regie"
     data-lang="<?= e(I18n::lang()) ?>"
     data-csrf="<?= e(Csrf::token('regie')) ?>"
     data-offline="<?= e(I18n::t('regie.offline')) ?>">

  <button type="button" class="regie-launcher" data-regie-open aria-label="<?= e(I18n::t('regie.open')) ?>">
    <span class="regie-avatar"><?= Icon::svg('robot', 19, '#17123A', 2) ?></span>
    <?= e(I18n::t('regie.name')) ?>
  </button>

  <div class="regie-panel" data-regie-panel role="dialog" aria-label="<?= e(I18n::t('regie.name')) ?>" hidden>
    <div class="regie-head">
      <span class="regie-avatar"><?= Icon::svg('robot', 19, '#17123A', 2) ?></span>
      <span class="who">
        <span class="name"><?= e(I18n::t('regie.name')) ?></span>
        <span class="sub"><?= e(I18n::t('regie.subtitle')) ?></span>
      </span>
      <button type="button" class="regie-close" data-regie-close aria-label="<?= e(I18n::t('regie.close')) ?>">
        <?= Icon::svg('close', 17, '#fff', 2.2) ?>
      </button>
    </div>

    <div class="regie-thread" data-regie-thread>
      <div class="bubble bubble-bot"><?= e(I18n::t('regie.hello')) ?></div>
    </div>

    <div class="regie-suggestions">
      <?php foreach (['regie.suggest1', 'regie.suggest2', 'regie.suggest3'] as $key): ?>
        <button type="button" data-regie-suggest><?= e(I18n::t($key)) ?></button>
      <?php endforeach; ?>
    </div>

    <form class="regie-form" data-regie-form>
      <label class="visually-hidden" for="regie-input"><?= e(I18n::t('regie.placeholder')) ?></label>
      <input id="regie-input" type="text" autocomplete="off" placeholder="<?= e(I18n::t('regie.placeholder')) ?>">
      <button type="submit" class="regie-send" aria-label="<?= e(I18n::t('regie.send')) ?>">
        <?= Icon::svg('send', 17, '#fff', 2) ?>
      </button>
    </form>
  </div>
</div>
