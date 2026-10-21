<?php
/** Fiche bureau : galerie, ce qui est compris, plan, formulaire de réservation. */

use App\Config;
use App\Content;
use App\Csrf;
use App\I18n;
use App\Offices;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$photos = array_values(array_filter((array) $office['photos'], 'is_string'));
// Toutes les photos passent dans la galerie, sans plafond : une annonce qui en
// porte huit doit les montrer toutes. On ouvre sur la première assez définie
// pour l'emplacement (680 px), les autres suivent dans l'ordre du back-office.
$ouverture = 0;
foreach ($photos as $i => $photo) {
    if (App\Media::dimensions($photo)['width'] >= 700) {
        $ouverture = $i;
        break;
    }
}
if ($ouverture > 0) {
    $photos = array_merge([$photos[$ouverture]], array_values(array_filter(
        $photos,
        static fn (string $p, int $i): bool => $i !== $ouverture,
        ARRAY_FILTER_USE_BOTH
    )));
}
$site = null;
foreach ((array) ($settings['sites'] ?? []) as $entry) {
    if ((string) ($entry['id'] ?? '') === (string) $office['site']) {
        $site = $entry;
    }
}
$included = Content::list($page, 'included');
$isRented = ($office['status'] ?? '') === 'rented';
?>

<section class="shell" style="padding-top:34px">
  <div class="crumb">
    <a href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('office.crumb')) ?></a>
    <span>/</span>
    <span><?= Text::e((string) $office['name']) ?></span>
  </div>

  <div class="fiche" data-gallery<?= \count($photos) > 1 ? ' data-gallery-auto="5000"' : '' ?>>
    <div data-reveal>
      <?php // Chaque photo a son propre emplacement grand format : on montre
            // celle qui porte « is-on », le reste attend. Pas d'échange
            // d'attributs, donc chaque image garde son srcset et sa définition. ?>
      <div class="fiche__main" style="background:<?= Text::e((string) $office['color']) ?>"
           role="group" aria-roledescription="diaporama" aria-label="<?= Text::e(I18n::t('office.gallery')) ?>">
        <?php foreach ($photos as $i => $photo): ?>
          <div class="fiche__slide<?= $i === 0 ? ' is-on' : '' ?>" data-gallery-slide
               aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
            <?= View::image((string) $photo, (string) $office['name'], [
                'placeholder' => (string) $office['name'],
                'eager' => $i === 0,
                'minWidth' => 700,
                'sizes' => '(max-width: 1080px) 100vw, 680px',
            ]) ?>
          </div>
        <?php endforeach; ?>
        <?php if (\count($photos) > 1): ?>
          <button class="fiche__nav fiche__nav--prev" type="button" data-gallery-prev
                  aria-label="<?= Text::e(I18n::t('office.prevPhoto')) ?>">‹</button>
          <button class="fiche__nav fiche__nav--next" type="button" data-gallery-next
                  aria-label="<?= Text::e(I18n::t('office.nextPhoto')) ?>">›</button>
          <span class="fiche__counter" data-gallery-counter aria-hidden="true">1/<?= \count($photos) ?></span>
        <?php endif; ?>
      </div>

      <?php if (\count($photos) > 1): ?>
      <div class="fiche__thumbs">
        <?php foreach ($photos as $i => $photo): ?>
          <button class="fiche__thumb<?= $i === 0 ? ' is-active' : '' ?>" type="button" data-gallery-thumb
                  aria-label="<?= Text::e(I18n::t('office.gallery')) ?> <?= $i + 1 ?>"
                  aria-current="<?= $i === 0 ? 'true' : 'false' ?>">
            <?= View::image((string) $photo, (string) $office['name'], ['minWidth' => 190, 'sizes' => '200px']) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ((string) $office['description'] !== ''): ?>
        <?php // HTML assaini à l'enregistrement (Text::sanitizeHtml) : liste
              // blanche de balises, aucun script, aucun style en ligne. ?>
        <div class="fiche__desc rich"><?= (string) $office['description'] ?></div>
      <?php endif; ?>

      <?php $features = array_column(Offices::includedFor($office, $included, $settings, $lang), 'texte');
            if ($features !== []): ?>
      <h2 class="fiche__h2"><?= Text::e(I18n::t('office.included')) ?></h2>
      <div class="grid grid--included">
        <?php foreach ($features as $item): ?>
          <div class="included"><span class="included__check">✓</span><span><?= Text::e((string) $item) ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($site !== null): ?>
      <h2 class="fiche__h2 fiche__h2--map"><?= Text::e(I18n::t('office.where')) ?></h2>
      <?php $address = trim((string) ($site['address'] ?? '') . ', ' . (string) ($site['zip'] ?? '') . ' ' . (string) ($site['city'] ?? '')); ?>
      <?php $embed = View::mapEmbed($address); ?>
      <div class="map<?= $embed !== '' ? ' is-loaded' : '' ?>" data-map
           data-embed="<?= Text::e($embed) ?>" data-label="<?= Text::e((string) ($site['name'] ?? '')) ?>">
        <?php if ($embed !== ''): ?>
          <iframe class="map__frame" src="<?= Text::e($embed) ?>" loading="lazy"
                  title="<?= Text::e((string) ($site['name'] ?? '')) ?>"
                  referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        <?php else: ?>
          <span class="map__decor" aria-hidden="true"><span class="map__road-h"></span><span class="map__road-v"></span></span>
          <div class="map__cover"><span class="map__address"><?= Text::e((string) ($site['address'] ?? '')) ?></span></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <aside class="aside-book" data-reveal data-delay="100">
      <div class="aside-book__head" style="background:<?= Text::e((string) $office['color']) ?>">
        <span class="status-pill status-pill--lg" style="background:<?= Text::e((string) $office['statusColor']) ?>"><?= Text::e((string) $office['statusLabel']) ?></span>
        <h1 class="aside-book__title"><?= Text::e((string) $office['name']) ?></h1>
        <div class="aside-book__meta"><?= Text::e(implode(' · ', array_filter([(string) $office['typeLabel'], (string) $office['area'], (string) $office['siteLabel']]))) ?></div>
        <div class="aside-book__price">
          <?php if (!empty($office['hasPromo'])): ?>
            <s class="price-old price-old--lg"><?= Text::e((string) $office['priceFullLabel']) ?></s>
          <?php endif; ?>
          <span class="aside-book__priceValue"><?= Text::e((string) $office['priceLabel']) ?></span>
          <span class="aside-book__priceNote"><?= Text::e(I18n::t('office.perMonth')) ?></span>
        </div>
        <?php if ((string) ($office['priceNote'] ?? '') !== ''): ?>
          <div class="aside-book__note"><?= Text::e((string) $office['priceNote']) ?></div>
        <?php endif; ?>
        <?php
        // Quand il se libère : la date si elle est connue, la précision libre
        // ensuite. Chacune s'affiche sur sa ligne, elles ne se remplacent pas.
        $soon = ($office['status'] ?? '') === 'soon';
        if ($soon && (string) $office['availableFrom'] !== ''): ?>
          <div class="aside-book__meta"><?= Text::e(I18n::t('office.availableFrom', ['date' => I18n::date((string) $office['availableFrom'], IntlDateFormatter::LONG)])) ?></div>
        <?php endif; ?>
        <?php if ($soon && (string) ($office['soonNote'] ?? '') !== ''): ?>
          <div class="aside-book__meta"><?= Text::e((string) $office['soonNote']) ?></div>
        <?php endif; ?>
      </div>

      <form class="form form--aside" method="post" action="<?= Text::e(Config::basePath()) ?>/api/reserve.php"
            data-ajax-form data-event="reserve_submit"
            data-success="<?= Text::e(I18n::t('form.sentReserve')) ?>" data-failure="<?= Text::e(I18n::t('form.error')) ?>">
        <?= Csrf::field('reserve') ?>
        <input type="hidden" name="officeId" value="<?= Text::e((string) $office['id']) ?>">
        <input type="hidden" name="lang" value="<?= Text::e($lang) ?>">
        <?= App\Spam::fields('reserve') ?>

        <div class="form__kicker"><?= Text::e($isRented ? I18n::t('form.notifyTitle') : I18n::t('form.interestTitle')) ?></div>
        <label class="sr-only" for="r-name"><?= Text::e(I18n::t('form.name')) ?></label>
        <input class="field" id="r-name" type="text" name="name" required maxlength="120" autocomplete="name" placeholder="<?= Text::e(I18n::t('form.name')) ?>">
        <label class="sr-only" for="r-email"><?= Text::e(I18n::t('form.email')) ?></label>
        <input class="field" id="r-email" type="email" name="email" required maxlength="160" autocomplete="email" placeholder="<?= Text::e(I18n::t('form.email')) ?>">
        <label class="sr-only" for="r-phone"><?= Text::e(I18n::t('form.phone')) ?></label>
        <input class="field" id="r-phone" type="tel" name="phone" maxlength="40" autocomplete="tel" placeholder="<?= Text::e(I18n::t('form.phone')) ?>">
        <label class="sr-only" for="r-date"><?= Text::e(I18n::t('form.startDate')) ?></label>
        <input class="field" id="r-date" type="text" name="startDate" maxlength="60" placeholder="<?= Text::e(I18n::t('form.startDate')) ?>"
               onfocus="this.type='date'" onblur="if(!this.value){this.type='text'}">

        <button class="btn btn--yellow btn--block btn--square" type="submit" style="margin-top:6px">
          <?= Text::e($isRented ? I18n::t('office.notifyMe') : I18n::t('form.submitInterest')) ?>
        </button>
        <div class="form__note"><?= Text::e(I18n::t('form.note')) ?></div>
        <div class="alert" data-form-alert hidden role="status"></div>
        <div class="form__consent"><?= Text::e(I18n::t('form.consent')) ?></div>
      </form>
    </aside>
  </div>
</section>
