<?php
/** Pop-up de sortie : une seule fois par session, email → /api/lead.php. */

use App\Config;
use App\Csrf;
use App\I18n;
use App\Offices;
use App\Text;

if (empty($settings['exit']['enabled'])) {
    return;
}
$minPrice = Offices::minPrice();
?>
<div class="exit" data-exit hidden role="dialog" aria-modal="true" aria-labelledby="exit-title">
  <div class="exit__card">
    <div class="exit__head">
      <button class="exit__close" type="button" data-exit-close aria-label="<?= Text::e(I18n::t('exit.close')) ?>">×</button>
      <div class="exit__kicker"><?= Text::e(I18n::t('exit.kicker')) ?></div>
      <h2 class="exit__title" id="exit-title"><?= Text::e(I18n::t('exit.title')) ?></h2>
    </div>
    <div class="exit__body">
      <p class="exit__text"><?= Text::e(I18n::t('exit.sub')) ?></p>
      <form class="exit__form" data-exit-form method="post" action="<?= Text::e(Config::basePath()) ?>/api/lead.php">
        <?= Csrf::field('lead') ?>
        <input type="hidden" name="source" value="exit-intent">
        <input type="hidden" name="lang" value="<?= Text::e(I18n::lang()) ?>">
        <?= App\Spam::fields('lead') ?>
        <label class="sr-only" for="exit-email"><?= Text::e(I18n::t('form.emailSimple')) ?></label>
        <input class="exit__input" id="exit-email" type="email" name="email" required
               placeholder="<?= Text::e(I18n::t('exit.placeholder')) ?>" autocomplete="email">
        <button class="exit__submit" type="submit"><?= Text::e(I18n::t('exit.cta')) ?></button>
        <div class="alert" data-form-alert hidden role="status"></div>
      </form>
      <div class="exit__proof">
        <span><?= Text::e(I18n::t('reviews.source')) ?> ★★★★★</span>
        <?php if ($minPrice !== null): ?>
          <span><?= Text::e(I18n::t('office.from')) ?> <?= Text::e(I18n::price($minPrice)) ?> <?= Text::e(I18n::t('office.perMonthShort')) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
