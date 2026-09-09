<?php
/**
 * Barre CTA collante, présente sur toutes les pages.
 * Le halo (animation ioioHalo) est porté par le bouton choisi au back-office.
 */

use App\I18n;
use App\Router;
use App\Text;

if (empty($settings['sticky']['enabled'])) {
    return;
}
$lang = I18n::lang();
$halo = (string) ($settings['sticky']['halo'] ?? 'reserve');
?>
<div class="sticky-cta">
  <div class="sticky-cta__pill">
    <span class="sticky-cta__slot<?= $halo === 'contact' ? ' sticky-cta__slot--halo' : '' ?>">
      <span class="sticky-cta__halo" aria-hidden="true"></span>
      <a class="sticky-cta__btn sticky-cta__btn--ghost" href="<?= Text::e(Router::url('contact', $lang)) ?>" data-track="cta_sticky_contact">
        <span class="sticky-cta__long"><?= Text::e(I18n::t('sticky.contact')) ?></span>
        <span class="sticky-cta__short"><?= Text::e(I18n::t('sticky.contactShort')) ?></span>
      </a>
    </span>
    <span class="sticky-cta__slot<?= $halo === 'reserve' ? ' sticky-cta__slot--halo' : '' ?>">
      <span class="sticky-cta__halo" aria-hidden="true"></span>
      <a class="sticky-cta__btn sticky-cta__btn--solid" href="<?= Text::e(Router::url('offices', $lang)) ?>" data-track="cta_sticky_reserve">
        <span class="sticky-cta__long"><?= Text::e(I18n::t('sticky.reserve')) ?></span>
        <span class="sticky-cta__short"><?= Text::e(I18n::t('sticky.reserveShort')) ?></span>
      </a>
    </span>
  </div>
</div>
