<?php
/** Contact : coordonnées des deux lieux + formulaire + plan. */

use App\Config;
use App\Content;
use App\Csrf;
use App\I18n;
use App\Text;

$lang = I18n::lang();
$sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));
$needs = Content::list($page, 'needs');
$firstNeed = (string) ($needs[0] ?? '');
?>
<section class="shell section--first" style="padding-top:60px">
  <div class="contact-grid">
    <div data-reveal>
      <div class="kicker"><?= Text::e(Content::text($page, 'kicker')) ?></div>
      <h1 class="contact-title"><?= Text::e(Content::text($page, 'title')) ?></h1>
      <p class="contact-lead"><?= Text::e(Content::text($page, 'text')) ?></p>

      <div class="address-list">
        <?php foreach ($sites as $site): ?>
          <div class="address" style="background:<?= Text::e((string) ($site['color'] ?? '#FFD100')) ?>">
            <div class="address__name"><?= Text::e((string) ($site['name'] ?? '')) ?></div>
            <div class="address__lines"><?= Text::e((string) ($site['address'] ?? '') . ', ' . (string) ($site['zip'] ?? '') . ' ' . (string) ($site['city'] ?? '')) ?></div>
            <div class="address__note"><?= Text::e(Content::i18n($site, 'note', $lang)) ?></div>
            <?php if (!empty($site['mapUrl'])): ?>
              <a class="address__link link-underline link-underline--sm" href="<?= Text::url((string) $site['mapUrl']) ?>" target="_blank" rel="noopener"><?= Text::e(I18n::t('contact.mapNote')) ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="hours">
          <div class="hours__label"><?= Text::e(I18n::t('contact.hoursLabel')) ?></div>
          <div class="hours__value"><?= Text::e(Content::i18n((array) ($settings['contact'] ?? []), 'hours', $lang)) ?></div>
          <?php if (!empty($settings['contact']['email'])): ?>
            <div class="hours__value"><a href="mailto:<?= Text::e((string) $settings['contact']['email']) ?>"><?= Text::e((string) $settings['contact']['email']) ?></a></div>
          <?php endif; ?>
          <?php if (!empty($settings['contact']['phone'])): ?>
            <div class="hours__value"><a href="tel:<?= Text::e(preg_replace('/[^0-9+]/', '', (string) $settings['contact']['phone']) ?? '') ?>"><?= Text::e((string) $settings['contact']['phone']) ?></a></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <form class="form" data-reveal data-delay="120" method="post" action="<?= Text::e(Config::basePath()) ?>/api/contact.php"
          data-ajax-form data-event="contact_submit"
          data-success="<?= Text::e(I18n::t('form.sentContact')) ?>" data-failure="<?= Text::e(I18n::t('form.error')) ?>">
      <?= Csrf::field('contact') ?>
      <input type="hidden" name="ts" value="<?= time() ?>">
      <input type="hidden" name="lang" value="<?= Text::e($lang) ?>">
      <input type="hidden" name="need" value="<?= Text::e($firstNeed) ?>" data-needs-input data-default="<?= Text::e($firstNeed) ?>">
      <input class="honey" type="text" name="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="form__title"><?= Text::e(I18n::t('form.contactTitle')) ?></div>

      <label class="sr-only" for="c-name"><?= Text::e(I18n::t('form.name')) ?></label>
      <input class="field" id="c-name" type="text" name="name" required maxlength="120" autocomplete="name" placeholder="<?= Text::e(I18n::t('form.name')) ?>">

      <label class="sr-only" for="c-email"><?= Text::e(I18n::t('form.emailSimple')) ?></label>
      <input class="field" id="c-email" type="email" name="email" required maxlength="160" autocomplete="email" placeholder="<?= Text::e(I18n::t('form.emailSimple')) ?>">

      <label class="sr-only" for="c-phone"><?= Text::e(I18n::t('form.phone')) ?></label>
      <input class="field" id="c-phone" type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="<?= Text::e(I18n::t('form.phone')) ?>">

      <?php if ($needs !== []): ?>
      <div class="needs" data-needs role="group" aria-label="<?= Text::e(I18n::t('form.message')) ?>">
        <?php foreach ($needs as $i => $need): ?>
          <button class="need<?= $i === 0 ? ' is-active' : '' ?>" type="button" data-need="<?= Text::e((string) $need) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= Text::e((string) $need) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <label class="sr-only" for="c-message"><?= Text::e(I18n::t('form.message')) ?></label>
      <textarea class="field" id="c-message" name="message" rows="5" maxlength="4000" placeholder="<?= Text::e(I18n::t('form.message')) ?>"></textarea>

      <button class="btn btn--ink btn--block btn--square" type="submit"><?= Text::e(I18n::t('form.submitContact')) ?></button>
      <div class="alert" data-form-alert hidden role="status"></div>
      <div class="form__consent"><?= Text::e(I18n::t('form.consent')) ?></div>
    </form>
  </div>

  <?php $mapUrl = (string) ($sites[0]['mapUrl'] ?? ''); ?>
  <a class="map map--lg" data-reveal href="<?= Text::url($mapUrl !== '' ? $mapUrl : '#') ?>" <?= $mapUrl !== '' ? 'target="_blank" rel="noopener"' : '' ?> aria-label="<?= Text::e(I18n::t('contact.mapNote')) ?>">
    <span class="map__road-h"></span>
    <span class="map__road-v"></span>
    <?php foreach (\array_slice($sites, 0, 2) as $i => $site): ?>
      <span class="map__tag map__tag--<?= $i + 1 ?>" style="background:<?= Text::e((string) ($site['color'] ?? '#FFD100')) ?>"><?= Text::e((string) ($site['shortName'] ?? '')) ?></span>
    <?php endforeach; ?>
    <span class="map__note"><?= Text::e(I18n::t('contact.mapNote')) ?></span>
  </a>
</section>
