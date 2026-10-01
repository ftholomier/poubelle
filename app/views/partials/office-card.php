<?php
/**
 * Carte d'un bureau du catalogue.
 * @var array  $office  bureau décoré (Offices::decorate)
 * @var string $variant 'featured' (accueil, fond gris) ou 'list' (page bureaux, fond blanc)
 */

use App\I18n;
use App\Offices;
use App\Text;
use App\View;

$variant = $variant ?? 'list';
$isList = $variant === 'list';
$spotlight = (bool) ($spotlight ?? false);
$delay = (int) ($delay ?? 0);
// La photo occupe une colonne large en mise en avant, une vignette sinon.
$sizes = $spotlight
    ? '(max-width: 700px) 100vw, (max-width: 1080px) 46vw, 24vw'
    : '(max-width: 560px) 100vw, (max-width: 880px) 45vw, (max-width: 1180px) 30vw, 290px';
/** Assemble les fragments réellement renseignés. */
$meta = static fn (array $parts): string => implode(' · ', array_filter(array_map('trim', $parts)));
// Un seul lieu : son nom n'apprend rien au visiteur, on montre surface et capacité.
$where = Offices::multiSite() ? (string) $office['siteLabel'] : '';
$details = $meta([$where, (string) $office['area'], (string) ($office['capacity'] ?? '')]);
$photoCount = \count((array) $office['photos']);
?>
<article class="office-card<?= $isList ? ' office-card--white' : '' ?><?= $spotlight ? ' office-card--spot' : '' ?><?= ($office['status'] ?? '') === 'rented' ? ' office-card--rented' : '' ?>" data-reveal<?= $delay > 0 ? ' data-delay="' . $delay . '"' : '' ?>>
  <div class="office-card__media" style="background:<?= Text::e((string) $office['color']) ?>">
    <?= View::image((string) $office['cover'], (string) $office['name'], ['placeholder' => (string) $office['name'], 'minWidth' => 190, 'sizes' => $sizes]) ?>
    <?php if ((string) ($office['badge'] ?? '') !== ''): ?>
      <span class="office-card__badge"><?= Text::e((string) $office['badge']) ?></span>
    <?php endif; ?>
    <?php if ($photoCount > 1): ?>
      <span class="office-card__count"><?= $photoCount ?> photos</span>
    <?php endif; ?>
  </div>
  <div class="office-card__body<?= $isList ? ' office-card__body--lg' : '' ?>">
    <div class="office-card__row">
      <span class="status-pill" style="background:<?= Text::e((string) $office['statusColor']) ?>"><?= Text::e((string) $office['statusLabel']) ?></span>
      <span class="meta-note"><?= Text::e((string) $office['typeLabel']) ?></span>
    </div>
    <h3 class="office-card__title<?= $isList ? ' office-card__title--lg' : '' ?>">
      <a href="<?= Text::e((string) $office['url']) ?>"><?= Text::e((string) $office['name']) ?></a>
    </h3>
    <?php if ($details !== ''): ?>
      <div class="office-card__sub"><?= Text::e($details) ?></div>
    <?php endif; ?>
    <div class="office-card__price">
      <?php if (!empty($office['hasPromo'])): ?>
        <s class="price-old"><?= Text::e((string) $office['priceFullLabel']) ?></s>
      <?php endif; ?>
      <span class="office-card__priceValue"><?= Text::e((string) $office['priceLabel']) ?></span>
      <span class="office-card__priceNote"><?= Text::e($isList ? I18n::t('office.perMonthShort') : I18n::t('office.perMonth')) ?></span>
    </div>
    <?php if (($office['status'] ?? '') === 'soon' && ($office['availableFrom'] ?? '') !== ''): ?>
      <div class="office-card__sub"><?= Text::e(I18n::t('office.availableFrom', ['date' => I18n::date((string) $office['availableFrom'], IntlDateFormatter::MEDIUM)])) ?></div>
    <?php endif; ?>
    <a class="btn office-card__cta <?= $isList ? 'btn--ink btn--go-fill' : 'btn--outline-yellow' ?>"
       href="<?= Text::e((string) $office['url']) ?>"><?= Text::e((string) $office['cta']) ?></a>
  </div>
</article>
