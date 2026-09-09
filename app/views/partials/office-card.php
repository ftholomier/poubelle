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
$spotlight = (bool) ($spotlight ?? false);
$delay = (int) ($delay ?? 0);
// La photo occupe une colonne large en mise en avant, une vignette sinon.
$sizes = $spotlight
    ? '(max-width: 760px) 100vw, 46vw'
    : '(max-width: 560px) 100vw, (max-width: 880px) 45vw, (max-width: 1180px) 30vw, 290px';
/** Assemble les fragments réellement renseignés. */
$meta = static fn (array $parts): string => implode(' · ', array_filter(array_map('trim', $parts)));
?>
<article class="office-card<?= $isList ? ' office-card--white' : '' ?><?= $spotlight ? ' office-card--spot' : '' ?>" data-reveal<?= $delay > 0 ? ' data-delay="' . $delay . '"' : '' ?>>
  <div class="office-card__media" style="background:<?= Text::e((string) $office['color']) ?>">
    <?= View::image((string) $office['cover'], (string) $office['name'], ['placeholder' => (string) $office['name'], 'minWidth' => 190, 'sizes' => $sizes]) ?>
    <?php if (\count((array) $office['photos']) > 1): ?>
      <span class="office-card__count"><?= \count((array) $office['photos']) ?> photos</span>
    <?php endif; ?>
  </div>
  <div class="office-card__body<?= $isList ? ' office-card__body--lg' : '' ?>">
    <div class="office-card__row">
      <span class="status-pill" style="background:<?= Text::e((string) $office['statusColor']) ?>"><?= Text::e((string) $office['statusLabel']) ?></span>
      <span class="meta-note"><?= Text::e($isList ? (string) $office['typeLabel'] : $meta([(string) $office['siteLabel'], (string) $office['area']])) ?></span>
    </div>
    <h3 class="office-card__title<?= $isList ? ' office-card__title--lg' : '' ?>">
      <a href="<?= Text::e((string) $office['url']) ?>"><?= Text::e((string) $office['name']) ?></a>
    </h3>
    <?php if ($isList): ?>
      <div class="office-card__sub"><?= Text::e($meta([(string) $office['siteLabel'], (string) $office['area']])) ?></div>
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
