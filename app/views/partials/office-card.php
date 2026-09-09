<?php
/**
 * Carte d'un bureau du catalogue.
 * @var array  $office  bureau décoré (Offices::decorate)
 * @var string $variant 'featured' (accueil, fond crème) ou 'list' (page bureaux, fond blanc)
 */

use App\I18n;
use App\Text;
use App\View;

$variant = $variant ?? 'list';
$isList = $variant === 'list';
$delay = (int) ($delay ?? 0);
?>
<article class="office-card<?= $isList ? ' office-card--white' : '' ?>" data-reveal<?= $delay > 0 ? ' data-delay="' . $delay . '"' : '' ?>>
  <div class="office-card__media<?= $isList ? ' office-card__media--tall' : '' ?>" style="background:<?= Text::e((string) $office['color']) ?>">
    <?= View::image((string) $office['cover'], (string) $office['name'], ['placeholder' => (string) $office['name']]) ?>
  </div>
  <div class="office-card__body<?= $isList ? ' office-card__body--lg' : '' ?>">
    <div class="office-card__row">
      <span class="status-pill" style="background:<?= Text::e((string) $office['statusColor']) ?>"><?= Text::e((string) $office['statusLabel']) ?></span>
      <span class="meta-note"><?= Text::e($isList ? (string) $office['typeLabel'] : (string) $office['siteLabel'] . ' · ' . (string) $office['area']) ?></span>
    </div>
    <h3 class="office-card__title<?= $isList ? ' office-card__title--lg' : '' ?>">
      <a href="<?= Text::e((string) $office['url']) ?>"><?= Text::e((string) $office['name']) ?></a>
    </h3>
    <?php if ($isList): ?>
      <div class="office-card__sub"><?= Text::e((string) $office['siteLabel'] . ' · ' . (string) $office['area']) ?></div>
    <?php endif; ?>
    <div class="office-card__price">
      <span class="office-card__priceValue"><?= Text::e((string) $office['priceLabel']) ?></span>
      <span class="office-card__priceNote"><?= Text::e($isList ? I18n::t('office.perMonthShort') : I18n::t('office.perMonth')) ?></span>
    </div>
    <?php if (($office['status'] ?? '') === 'soon' && ($office['availableFrom'] ?? '') !== ''): ?>
      <div class="office-card__sub"><?= Text::e(I18n::t('office.availableFrom', ['date' => I18n::date((string) $office['availableFrom'], IntlDateFormatter::MEDIUM)])) ?></div>
    <?php endif; ?>
    <a class="btn <?= $isList ? 'btn--ink office-card__cta' : 'btn--outline-yellow office-card__cta' ?>"
       style="<?= $isList ? 'background-image:linear-gradient(var(--teal),var(--teal))' : '' ?>"
       href="<?= Text::e((string) $office['url']) ?>"><?= Text::e((string) $office['cta']) ?></a>
  </div>
</article>
