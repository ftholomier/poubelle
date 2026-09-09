<?php
/**
 * Bandeau de consentement : tout accepter, tout refuser, paramètres.
 * Rendu côté serveur seulement si le visiteur n'a pas encore choisi ;
 * le panneau de réglages reste dans la page pour être rouvert depuis le pied.
 */

use App\Consent;
use App\I18n;
use App\Text;

if (($settings['consent']['enabled'] ?? true) === false) {
    return;
}
$state = Consent::state();
$inventory = Consent::inventory();
?>
<div class="consent" data-consent<?= $state['decided'] ? ' hidden' : '' ?> role="dialog" aria-live="polite"
     aria-labelledby="consent-title" aria-describedby="consent-text">
  <div class="consent__card">
    <div class="consent__body">
      <h2 class="consent__title" id="consent-title"><?= Text::e(I18n::t('consent.title')) ?></h2>
      <p class="consent__text" id="consent-text"><?= Text::e(I18n::t('consent.text')) ?></p>
    </div>
    <div class="consent__actions">
      <button class="btn btn--ink btn--md btn--square" type="button" data-consent-accept><?= Text::e(I18n::t('consent.acceptAll')) ?></button>
      <button class="btn btn--outline btn--md btn--square" type="button" data-consent-refuse><?= Text::e(I18n::t('consent.refuseAll')) ?></button>
      <button class="link-underline link-underline--sm" type="button" data-consent-open><?= Text::e(I18n::t('consent.settings')) ?></button>
    </div>
  </div>
</div>

<div class="consent-panel" data-consent-panel hidden role="dialog" aria-modal="true" aria-labelledby="consent-panel-title">
  <div class="consent-panel__card">
    <div class="consent-panel__head">
      <button class="exit__close" type="button" data-consent-close aria-label="<?= Text::e(I18n::t('consent.close')) ?>">×</button>
      <div class="exit__kicker"><?= Text::e(I18n::t('consent.link')) ?></div>
      <h2 class="consent-panel__title" id="consent-panel-title"><?= Text::e(I18n::t('consent.panelTitle')) ?></h2>
    </div>

    <div class="consent-panel__body">
      <p class="consent__text"><?= Text::e(I18n::t('consent.panelText')) ?></p>

      <div class="consent-cat">
        <div class="consent-cat__head">
          <span class="consent-cat__name"><?= Text::e(I18n::t('consent.necessary')) ?></span>
          <span class="consent-cat__always"><?= Text::e(I18n::t('consent.always')) ?></span>
        </div>
        <p class="consent-cat__text"><?= Text::e(I18n::t('consent.necessaryText')) ?></p>
        <div class="consent-cat__cookies"><?= Text::e(I18n::t('consent.cookiesUsed')) ?> <code><?= Text::e(implode(', ', $inventory['necessary'])) ?></code></div>
      </div>

      <?php foreach (App\Consent::CATEGORIES as $category): ?>
        <div class="consent-cat">
          <div class="consent-cat__head">
            <span class="consent-cat__name"><?= Text::e(I18n::t('consent.' . $category)) ?></span>
            <label class="switch">
              <input type="checkbox" data-consent-toggle="<?= Text::e($category) ?>" <?= $state[$category] ? 'checked' : '' ?>>
              <span class="switch__track" aria-hidden="true"><span class="switch__knob"></span></span>
              <span class="sr-only"><?= Text::e(I18n::t('consent.' . $category)) ?></span>
            </label>
          </div>
          <p class="consent-cat__text"><?= Text::e(I18n::t('consent.' . $category . 'Text')) ?></p>
          <div class="consent-cat__cookies"><?= Text::e(I18n::t('consent.cookiesUsed')) ?> <code><?= Text::e(implode(', ', $inventory[$category])) ?></code></div>
        </div>
      <?php endforeach; ?>

      <p class="consent-cat__text" style="margin-top:6px"><?= Text::e(I18n::t('consent.mapsNotice')) ?></p>

      <div class="consent-panel__actions">
        <button class="btn btn--ink btn--md btn--square" type="button" data-consent-save><?= Text::e(I18n::t('consent.save')) ?></button>
        <button class="btn btn--outline btn--md btn--square" type="button" data-consent-accept><?= Text::e(I18n::t('consent.acceptAll')) ?></button>
        <button class="btn btn--outline btn--md btn--square" type="button" data-consent-refuse><?= Text::e(I18n::t('consent.refuseAll')) ?></button>
      </div>
      <div class="alert alert--ok" data-consent-saved hidden role="status"><?= Text::e(I18n::t('consent.saved')) ?></div>
    </div>
  </div>
</div>
