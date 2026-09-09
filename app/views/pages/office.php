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
// La grande vue prend la première photo assez définie pour l'emplacement (680 px) ;
// les autres passent en vignettes, où leur définition suffit.
$main = '';
foreach ($photos as $photo) {
    if (App\Media::dimensions($photo)['width'] >= 700) {
        $main = $photo;
        break;
    }
}
$main = $main !== '' ? $main : (string) ($photos[0] ?? '');
$thumbs = \array_slice(array_values(array_filter($photos, static fn (string $p): bool => $p !== $main)), 0, 3);
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

  <div class="fiche" data-gallery>
    <div data-reveal>
      <div class="fiche__main" style="background:<?= Text::e((string) $office['color']) ?>">
        <?= View::image($main, (string) $office['name'], ['placeholder' => (string) $office['name'], 'eager' => true, 'id' => 'main', 'minWidth' => 700, 'sizes' => '(max-width: 1080px) 100vw, 680px']) ?>
      </div>

      <?php if ($thumbs !== []): ?>
      <div class="fiche__thumbs">
        <?php foreach ($thumbs as $i => $photo): ?>
          <button class="fiche__thumb" type="button" data-gallery-thumb
                  data-src="<?= Text::e(Config::basePath() . (string) $photo) ?>"
                  data-alt="<?= Text::e(App\Media::alt((string) $photo, $lang, (string) $office['name'])) ?>"
                  aria-label="<?= Text::e(I18n::t('office.gallery')) ?> <?= $i + 2 ?>">
            <?= View::image((string) $photo, (string) $office['name'], ['minWidth' => 190, 'sizes' => '200px']) ?>
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ((string) $office['description'] !== ''): ?>
        <p class="fiche__desc"><?= Text::e((string) $office['description']) ?></p>
      <?php endif; ?>

      <?php $features = Offices::mergeFeatures((array) $office['features'], $included); if ($features !== []): ?>
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
          <span class="aside-book__priceValue"><?= Text::e((string) $office['priceLabel']) ?></span>
          <span class="aside-book__priceNote"><?= Text::e(I18n::t('office.perMonth')) ?></span>
        </div>
        <?php if (($office['status'] ?? '') === 'soon' && (string) $office['availableFrom'] !== ''): ?>
          <div class="aside-book__meta"><?= Text::e(I18n::t('office.availableFrom', ['date' => I18n::date((string) $office['availableFrom'], IntlDateFormatter::LONG)])) ?></div>
        <?php endif; ?>
      </div>

      <form class="form form--aside" method="post" action="<?= Text::e(Config::basePath()) ?>/api/reserve.php"
            data-ajax-form data-event="reserve_submit"
            data-success="<?= Text::e(I18n::t('form.sentReserve')) ?>" data-failure="<?= Text::e(I18n::t('form.error')) ?>">
        <?= Csrf::field('reserve') ?>
        <input type="hidden" name="ts" value="<?= time() ?>">
        <input type="hidden" name="officeId" value="<?= Text::e((string) $office['id']) ?>">
        <input type="hidden" name="lang" value="<?= Text::e($lang) ?>">
        <input class="honey" type="text" name="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

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
