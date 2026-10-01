<?php
/**
 * Le Signal : présentation du lieu, offre de location, équipements, résumé
 * des bureaux privés (calculé depuis le catalogue), album photo, audio.
 * Un seul lieu : tout le texte vient de la page « spaces » ; adresse et accès
 * restent ceux des réglages, pour ne les saisir qu'une fois.
 */

use App\Config;
use App\Content;
use App\I18n;
use App\Media;
use App\Offices;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$offer = (array) ($page['offer'] ?? []);
$album = (array) ($page['album'] ?? []);
$private = (array) ($page['private'] ?? []);
$phone = (string) ($settings['contact']['phone'] ?? '');

// Résumé des bureaux privés : nombre, surfaces et prix réels du catalogue.
$privates = Offices::filter(Offices::published(), ['type' => 'private']);
$areas = array_values(array_filter(array_map(static fn (array $o): int => (int) preg_replace('/\D.*$/', '', (string) ($o['area'] ?? '')), $privates)));
$prices = array_values(array_filter(array_map(static fn (array $o): int => (int) ($o['price'] ?? 0), $privates)));
$privateLine = $privates === [] ? '' : View::fill(Content::text($private, 'line'), [
    'count' => \count($privates),
    'minArea' => $areas === [] ? '' : min($areas),
    'maxArea' => $areas === [] ? '' : max($areas),
    'min' => $prices === [] ? '' : min($prices),
    'max' => $prices === [] ? '' : max($prices),
]);

// Album : la liste choisie au back-office, sinon toutes les photos du lieu.
$photos = array_values(array_filter((array) ($album['photos'] ?? []), static fn ($p): bool => \is_string($p) && $p !== '' && is_file(Config::publicPath(ltrim($p, '/')))));
if ($photos === []) {
    $photos = array_map(static fn (array $m): string => (string) $m['path'], Media::bySite(Config::SITES[0]));
}
?>

<section class="place-hero">
  <div class="shell place-hero__inner">
    <div class="place-hero__text" data-reveal>
      <div class="kicker kicker--signal"><?= Text::e(Content::text($page, 'kicker')) ?></div>
      <h1 class="place-hero__title"><?= Text::e(Content::text($page, 'title')) ?></h1>
      <p class="place-hero__lead"><?= Text::e(Content::text($page, 'lead')) ?></p>
      <p class="place-hero__body"><?= Text::e(Content::text($page, 'text')) ?></p>
      <div class="place-hero__actions">
        <a class="btn btn--ink btn--lift" href="<?= Text::e(Router::url('contact', $lang)) ?>" data-track="spaces_contact"><?= Text::e(Content::text($page, 'cta')) ?></a>
      </div>
    </div>
    <div class="place-hero__photo" data-reveal data-delay="120">
      <?= View::image(Content::text($page, 'photo'), '', ['eager' => true, 'minWidth' => 700, 'sizes' => '(max-width: 900px) 100vw, 560px']) ?>
      <span class="place-hero__tail" aria-hidden="true"></span>
    </div>
  </div>
</section>

<section class="shell section">
  <div class="offer">
    <div class="offer__head" data-reveal>
      <div class="kicker kicker--signal"><?= Text::e(Content::text($offer, 'kicker')) ?></div>
      <h2 class="section-title"><?= Text::e(Content::text($offer, 'title')) ?></h2>
      <p class="offer__sub"><?= Text::e(Content::text($offer, 'subtitle')) ?></p>
      <p class="offer__line"><?= Text::e(Content::text($offer, 'line')) ?></p>
    </div>
    <div class="offer__body" data-reveal data-delay="100">
      <p class="offer__strong"><?= Text::e(Content::text($offer, 'text')) ?></p>
      <p><?= Text::e(Content::text($offer, 'text2')) ?></p>
      <h3 class="offer__parking"><?= Text::e(Content::text($offer, 'parking')) ?></h3>
      <?php $access = Content::list($offer, 'access'); if ($access !== []): ?>
        <div class="chips" aria-label="<?= Text::e(Content::text($offer, 'accessLabel')) ?>">
          <?php foreach ($access as $item): ?>
            <span class="chip chip--lg"><?= Text::e((string) $item) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php $amenities = Content::list($page, 'amenities'); if ($amenities !== []): ?>
  <div class="grid grid--amenities">
    <?php foreach ($amenities as $i => $amenity): ?>
      <div class="amenity" data-reveal data-delay="<?= $i * 80 ?>">
        <span class="signal signal--<?= min($i + 1, 3) ?>" aria-hidden="true"><span></span><span></span><span></span></span>
        <h3 class="amenity__title"><?= Text::e((string) ($amenity['title'] ?? '')) ?></h3>
        <p class="amenity__text"><?= Text::e((string) ($amenity['text'] ?? '')) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="offer__cta" data-reveal>
    <a class="btn btn--yellow btn--lift" href="<?= Text::e(Router::url('contact', $lang)) ?>" data-track="spaces_visit"><?= Text::e(Content::text($offer, 'cta')) ?></a>
    <?php if ($phone !== ''): ?>
      <a class="btn btn--outline" href="tel:<?= Text::e((string) preg_replace('/[^0-9+]/', '', $phone)) ?>"><span class="btn__phone" aria-hidden="true"></span><?= Text::e($phone) ?></a>
    <?php endif; ?>
  </div>
</section>

<?php if ($privateLine !== ''): ?>
<section class="shell">
  <div class="summary" data-reveal>
    <div>
      <h2 class="summary__title"><?= Text::e(Content::text($private, 'title')) ?></h2>
      <p class="summary__line"><?= Text::e($privateLine) ?></p>
    </div>
    <a class="btn btn--yellow btn--lift" href="<?= Text::e(Router::url('offices', $lang, ['facet' => 'private'])) ?>"><?= Text::e(Content::text($private, 'cta')) ?> <span class="btn--arrow" aria-hidden="true">→</span></a>
  </div>
</section>
<?php endif; ?>

<?php if ($photos !== []): ?>
<section class="shell section" id="album">
  <div class="album" data-album>
    <div class="album__head">
      <div>
        <div class="kicker kicker--signal"><?= Text::e(Content::text($album, 'kicker')) ?></div>
        <h2 class="section-title"><?= Text::e(Content::text($album, 'title')) ?></h2>
        <p class="album__intro"><?= Text::e(Content::text($album, 'text')) ?></p>
      </div>
      <span class="album__count"><?= \count($photos) ?> <?= Text::e(I18n::t('album.photos')) ?></span>
    </div>
    <div class="album__grid">
      <?php foreach ($photos as $index => $path):
          $alt = Media::alt($path, $lang); ?>
        <button class="album__item" type="button"
                data-album-open="<?= $index ?>"
                data-full="<?= Text::e(Config::basePath() . $path) ?>"
                data-caption="<?= Text::e($alt) ?>"
                aria-label="<?= Text::e($alt) ?>">
          <?= View::image($path, $alt, ['class' => 'album__img', 'sizes' => '(max-width: 620px) 45vw, 220px']) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="shell section">
  <?= View::partial('partials/audio', ['kicker' => Content::text($page, 'audio.kicker')]) ?>
</div>

<?= View::partial('partials/band') ?>
