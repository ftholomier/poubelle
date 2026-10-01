<?php
/**
 * Contact : « Réservez votre bureau sans plus attendre », téléphone, adresse,
 * formulaire (nom, téléphone, email, message, consentement au rappel), plan.
 * Un seul lieu : une seule carte, affichée directement.
 */

use App\Config;
use App\Content;
use App\Csrf;
use App\I18n;
use App\Text;
use App\View;

$lang = I18n::lang();
$sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));
$site = $sites[0] ?? [];
$address = trim((string) ($site['address'] ?? '') . ', ' . (string) ($site['zip'] ?? '') . ' ' . (string) ($site['city'] ?? ''), ' ,');
$phone = (string) ($settings['contact']['phone'] ?? '');
$tel = (string) preg_replace('/[^0-9+]/', '', $phone);
$email = (string) ($settings['contact']['email'] ?? '');
$needs = Content::list($page, 'needs');
$firstNeed = (string) ($needs[0] ?? '');
$embed = View::mapEmbed($address);
?>
<section class="shell section--first contact-page">
  <div class="contact-grid">
    <div data-reveal>
      <div class="kicker kicker--signal"><?= Text::e(Content::text($page, 'kicker')) ?></div>
      <h1 class="contact-title">
        <?= Text::e(Content::text($page, 'title')) ?>
        <mark><?= Text::e(Content::text($page, 'highlight')) ?></mark>
        <?= Text::e(Content::text($page, 'titleEnd')) ?>
      </h1>
      <p class="contact-lead"><?= Text::e(Content::text($page, 'text')) ?></p>

      <div class="contact-cards">
        <?php if ($tel !== ''): ?>
        <a class="contact-card contact-card--phone" href="tel:<?= Text::e($tel) ?>" data-track="contact_phone">
          <span class="contact-card__icon contact-card__icon--phone" aria-hidden="true"></span>
          <span class="contact-card__label"><?= Text::e(I18n::t('office.phone')) ?></span>
          <span class="contact-card__value"><?= Text::e($phone) ?></span>
        </a>
        <?php endif; ?>
        <?php if ($address !== ''): ?>
        <a class="contact-card" href="#plan">
          <span class="contact-card__icon contact-card__icon--pin" aria-hidden="true"></span>
          <span class="contact-card__label"><?= Text::e(I18n::t('office.address')) ?></span>
          <span class="contact-card__value"><?= Text::e((string) ($site['address'] ?? '')) ?><br><?= Text::e(trim((string) ($site['zip'] ?? '') . ' ' . (string) ($site['city'] ?? ''))) ?></span>
          <span class="contact-card__more"><?= Text::e(I18n::t('contact.mapNote')) ?></span>
        </a>
        <?php endif; ?>
      </div>

      <div class="hours">
        <div class="hours__label"><?= Text::e(I18n::t('contact.hoursLabel')) ?></div>
        <div class="hours__value"><?= Text::e(Content::i18n((array) ($settings['contact'] ?? []), 'hours', $lang)) ?></div>
        <?php if ($email !== ''): ?>
          <div class="hours__value"><a href="mailto:<?= Text::e($email) ?>"><?= Text::e($email) ?></a></div>
        <?php endif; ?>
      </div>
    </div>

    <form class="form" data-reveal data-delay="120" method="post" action="<?= Text::e(Config::basePath()) ?>/api/contact.php"
          data-ajax-form data-event="contact_submit"
          data-success="<?= Text::e(I18n::t('form.sentContact')) ?>" data-failure="<?= Text::e(I18n::t('form.error')) ?>">
      <?= Csrf::field('contact') ?>
      <input type="hidden" name="lang" value="<?= Text::e($lang) ?>">
      <?php if ($needs !== []): ?>
        <input type="hidden" name="need" value="<?= Text::e($firstNeed) ?>" data-needs-input data-default="<?= Text::e($firstNeed) ?>">
      <?php endif; ?>
      <?= App\Spam::fields('contact') ?>

      <div class="kicker kicker--signal"><?= Text::e(Content::text($page, 'formKicker')) ?></div>
      <h2 class="form__title">
        <?= Text::e(Content::text($page, 'formTitle')) ?>
        <span class="form__titleHighlight"><?= Text::e(Content::text($page, 'formHighlight')) ?></span>
      </h2>

      <label class="sr-only" for="c-name"><?= Text::e(I18n::t('form.name')) ?></label>
      <input class="field" id="c-name" type="text" name="name" required maxlength="120" autocomplete="name" placeholder="<?= Text::e(I18n::t('form.name')) ?>">

      <label class="sr-only" for="c-phone"><?= Text::e(I18n::t('form.phone')) ?></label>
      <input class="field" id="c-phone" type="tel" name="phone" required maxlength="40" autocomplete="tel" placeholder="<?= Text::e(I18n::t('form.phone')) ?>">

      <label class="sr-only" for="c-email"><?= Text::e(I18n::t('form.emailSimple')) ?></label>
      <input class="field" id="c-email" type="email" name="email" required maxlength="160" autocomplete="email" placeholder="<?= Text::e(I18n::t('form.emailSimple')) ?>">

      <?php if ($needs !== []): ?>
      <div class="needs" data-needs role="group" aria-label="<?= Text::e(I18n::t('form.message')) ?>">
        <?php foreach ($needs as $i => $need): ?>
          <button class="need<?= $i === 0 ? ' is-active' : '' ?>" type="button" data-need="<?= Text::e((string) $need) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>"><?= Text::e((string) $need) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <label class="sr-only" for="c-message"><?= Text::e(I18n::t('form.message')) ?></label>
      <textarea class="field" id="c-message" name="message" rows="4" required maxlength="4000" placeholder="<?= Text::e(I18n::t('form.message')) ?>"></textarea>

      <label class="check">
        <input type="checkbox" name="consent" value="1" required>
        <span><?= Text::e(I18n::t('form.consentCheck')) ?></span>
      </label>

      <button class="btn btn--ink btn--block btn--square" type="submit"><?= Text::e(I18n::t('form.submitContact')) ?></button>
      <div class="alert" data-form-alert hidden role="status"></div>
      <div class="form__consent"><?= Text::e(I18n::t('form.consent')) ?></div>
    </form>
  </div>

  <?php if ($embed !== ''): ?>
  <div class="map map--lg is-loaded" id="plan" data-reveal>
    <iframe class="map__frame" src="<?= Text::e($embed) ?>" loading="lazy"
            title="<?= Text::e(I18n::t('contact.mapNote') . ' — ' . (string) ($site['name'] ?? '')) ?>"
            referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
    <?php if (!empty($site['mapUrl'])): ?>
      <a class="map__open" href="<?= Text::url((string) $site['mapUrl']) ?>" target="_blank" rel="noopener"><?= Text::e(I18n::t('contact.mapNote')) ?> ↗</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<div class="shell section">
  <?= View::partial('partials/audio', ['kicker' => Content::text(Content::page('home', $lang), 'audio.kicker')]) ?>
</div>
