<?php
/**
 * Accueil du Signal : hero + bandeau défilant + le lieu (« Une visite ? ») +
 * bureaux disponibles + espaces de travail + l'expérience Le Signal + audio
 * + avis + bande de contact + FAQ. Ossature et animations du socle ; textes
 * repris du site le-signal.com, éditables au back-office.
 */

use App\Content;
use App\I18n;
use App\Offices;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$hero = (array) Content::get($page, 'hero', []);
$available = Offices::availableCount();
$minPrice = Offices::minPrice();
// Le diaporama occupe ~560 px de large : on écarte tout visuel qui y serait agrandi.
$slides = array_values(array_filter(
    Content::list($hero, 'slides'),
    static fn ($src): bool => \is_string($src) && App\Media::dimensions($src)['width'] >= 900
));
// Ordre tiré au sort à chaque visite : deux passages sur l'accueil ne montrent
// pas la même photo d'ouverture, et toutes les pièces finissent par être vues.
shuffle($slides);
$sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));
$site = $sites[0] ?? [];
// Quatre disponibilités au plus : une rangée complète de la mosaïque.
$featured = \array_slice(Offices::decorateAll(Offices::filter(Offices::published(), ['status' => 'available']), $lang), 0, 4);
$marquee = array_values(array_filter((array) ($settings['marquee'] ?? []), 'is_array'));
$faqItems = Content::list($page, 'faq.items');
$phone = (string) ($settings['contact']['phone'] ?? '');
$tel = preg_replace('/[^0-9+]/', '', $phone) ?? '';

/** Un bouton d'accueil peut viser une page filtrée : « status » se saisit au back-office. */
$ctaUrl = static fn (array $cta, string $default): string => Router::url(
    (string) (($cta['route'] ?? '') !== '' ? $cta['route'] : $default),
    $lang,
    [],
    ['status' => (string) ($cta['status'] ?? '')]
);
$ctaPrimary = (array) ($hero['ctaPrimary'] ?? []);
$ctaSecondary = (array) ($hero['ctaSecondary'] ?? []);
?>

<section class="hero">
  <div class="hero__bubble hero__bubble--yellow" data-parallax="0.12" aria-hidden="true"></div>
  <div class="hero__bubble hero__bubble--mist" data-parallax="-0.08" aria-hidden="true"></div>
  <div class="hero__waves" aria-hidden="true"><span></span><span></span><span></span></div>

  <div class="shell hero__inner">
    <div>
      <div class="hero__badge hero__badge--live" data-reveal>
        <span class="signal signal--live" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="hero__badgeText"><?= View::fill(
            Text::e(Content::text($hero, 'badge')),
            ['count' => '<strong class="hero__badgeCount" data-count-to="' . $available . '">' . $available . '</strong>']
        ) ?></span>
      </div>

      <h1 class="hero__title" data-reveal data-delay="80">
        <?= Text::e(Content::text($hero, 'title1')) ?><br>
        <?= Text::e(Content::text($hero, 'title2')) ?>
        <span class="hero__highlight"><?= Text::e(Content::text($hero, 'highlight')) ?></span>
      </h1>

      <p class="hero__text" data-reveal data-delay="160"><?= Text::e(Content::text($hero, 'text')) ?></p>

      <div class="hero__actions" data-reveal data-delay="240">
        <a class="btn btn--ink btn--lift" href="<?= Text::e($ctaUrl($ctaPrimary, 'offices')) ?>" data-track="hero_primary">
          <span><?= Text::e(Content::text($hero, 'ctaPrimary.label')) ?></span>
          <span class="btn--arrow" aria-hidden="true">→</span>
        </a>
        <a class="btn btn--outline" href="<?= Text::e($ctaUrl($ctaSecondary, 'contact')) ?>" data-track="hero_secondary">
          <?= Text::e(Content::text($hero, 'ctaSecondary.label')) ?>
        </a>
      </div>

      <div class="hero__stats" data-reveal data-delay="320">
        <?php foreach (Content::list($hero, 'stats') as $stat): ?>
          <div>
            <div class="hero__statValue"><?= Text::e((string) ($stat['value'] ?? '')) ?></div>
            <div class="hero__statLabel"><?= Text::e((string) ($stat['label'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="hero__media" data-reveal data-delay="120">
      <div class="slideshow" data-slideshow>
        <?php foreach ($slides as $i => $slide): ?>
          <div class="slideshow__slide<?= $i === 0 ? ' is-active' : '' ?>" data-slide
               style="background-image:<?= View::bgUrl((string) $slide) ?>"
               role="img" aria-label="<?= Text::e(App\Media::alt((string) $slide, $lang, (string) ($settings['site']['name'] ?? ''))) ?>"></div>
        <?php endforeach; ?>
        <?php if (\count($slides) > 1): ?>
        <div class="slideshow__dots" role="tablist" aria-label="<?= Text::e(I18n::t('album.title')) ?>">
          <?php foreach ($slides as $i => $slide): ?>
            <button class="slideshow__dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" role="tab"
                    data-slide-dot aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    aria-label="Photo <?= $i + 1 ?>"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <span class="slideshow__tail" aria-hidden="true"></span>

      <?php if ($minPrice !== null): ?>
      <div class="float-card float-card--price" data-float>
        <div class="float-card__label"><?= Text::e(Content::text($hero, 'cardPrice.label', I18n::t('office.from'))) ?></div>
        <div class="float-card__price"><?= Text::e(I18n::price($minPrice)) ?> <small><?= Text::e(Content::text($hero, 'cardPrice.note', I18n::t('office.perMonthShort'))) ?></small></div>
      </div>
      <?php endif; ?>

      <div class="float-card float-card--claim" data-float>
        <span class="float-card__kicker"><?= Text::e(Content::text($hero, 'cardBadge.kicker')) ?></span>
        <span class="float-card__claim"><?= Text::e(Content::text($hero, 'cardBadge.line1')) ?><br><?= Text::e(Content::text($hero, 'cardBadge.line2')) ?></span>
      </div>
    </div>
  </div>
</section>

<?php if ($marquee !== []): ?>
<section class="marquee" aria-hidden="true">
  <div class="marquee__track">
    <?php for ($pass = 0; $pass < 2; $pass++): ?>
      <?php foreach ($marquee as $item): ?>
        <span class="marquee__item">
          <span style="color:<?= Text::e((string) ($item['color'] ?? '#FFFFFF')) ?>"><?= Text::e(Content::i18n($item, 'label', $lang)) ?></span>
          <span class="marquee__bullet"><span></span><span></span><span></span></span>
        </span>
      <?php endforeach; ?>
    <?php endfor; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($site !== []):
    $place = (array) Content::get($page, 'place', []);
    $chips = $lang !== 'fr' && !empty($site['i18n'][$lang]['chips']) ? (array) $site['i18n'][$lang]['chips'] : (array) ($site['chips'] ?? []);
    $placePhoto = Content::text($place, 'photo', (string) ($site['photo'] ?? '')); ?>
<section class="shell section">
  <div class="place" data-reveal>
    <div class="place__media">
      <?= View::image($placePhoto, '', ['placeholder' => (string) ($site['name'] ?? ''), 'minWidth' => 700, 'sizes' => '(max-width: 880px) 100vw, 560px']) ?>
      <span class="place__address">
        <span class="place__pin" aria-hidden="true"></span>
        <?= Text::e((string) ($site['address'] ?? '')) ?>, <?= Text::e(trim((string) ($site['zip'] ?? '') . ' ' . (string) ($site['city'] ?? ''))) ?>
      </span>
    </div>
    <div class="place__body">
      <div class="kicker kicker--signal"><?= Text::e(Content::text($place, 'kicker')) ?></div>
      <h2 class="place__title"><?= Text::e(Content::text($place, 'title')) ?></h2>
      <p class="place__text"><?= Text::e(Content::text($place, 'text')) ?></p>
      <div class="chips">
        <?php foreach (array_filter($chips) as $chip): ?>
          <span class="chip"><?= Text::e((string) $chip) ?></span>
        <?php endforeach; ?>
        <?php if ($available > 0): ?>
          <span class="chip chip--go"><?= Text::e(Offices::availabilityLabel($available)) ?></span>
        <?php endif; ?>
      </div>
      <div class="place__actions">
        <a class="btn btn--ink btn--md" href="<?= Text::e(Router::url('contact', $lang)) ?>" data-track="place_contact"><?= Text::e(Content::text($place, 'cta')) ?></a>
        <a class="btn btn--outline btn--md" href="<?= Text::e(Router::url('spaces', $lang)) ?>"><?= Text::e(Content::text($place, 'ctaSecondary', Content::i18n($site, 'cta', $lang))) ?></a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="shell section--tight section" id="dispos">
  <div class="section-head" data-reveal>
    <div>
      <div class="kicker kicker--signal"><?= Text::e(Content::text($page, 'availability.kicker')) ?></div>
      <h2 class="section-title"><?= Text::e(Content::text($page, 'availability.title')) ?></h2>
    </div>
    <a class="link-underline" href="<?= Text::e(Router::availableOffices($lang)) ?>"><?= Text::e(Offices::availabilityLabel($available)) ?> →</a>
  </div>

  <?php if ($featured === []): ?>
    <p class="empty-note"><?= Text::e(I18n::t('office.none')) ?></p>
  <?php else: ?>
    <?php // Une ou deux disponibilités : on les met en avant en pleine largeur
          // plutôt que de les tasser dans une grille prévue pour quatre cartes.
          $spotlight = \count($featured) <= 2; ?>
    <div class="grid grid--offices<?= $spotlight ? ' grid--offices-spot' : '' ?>">
      <?php foreach ($featured as $i => $office): ?>
        <?= View::partial('partials/office-card', ['office' => $office, 'variant' => 'featured', 'spotlight' => $spotlight, 'delay' => $i * 90]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php $spaceItems = Content::list($page, 'spaces.items'); if ($spaceItems !== []): ?>
<section class="shell section">
  <div class="section-head" data-reveal>
    <div>
      <div class="kicker kicker--signal"><?= Text::e(Content::text($page, 'spaces.kicker')) ?></div>
      <h2 class="section-title"><?= Text::e(Content::text($page, 'spaces.title')) ?></h2>
    </div>
  </div>
  <div class="grid grid--amenities grid--amenities-3">
    <?php foreach ($spaceItems as $i => $item): ?>
      <div class="amenity" data-reveal data-delay="<?= $i * 110 ?>">
        <span class="signal signal--<?= $i + 1 ?>" aria-hidden="true"><span></span><span></span><span></span></span>
        <h3 class="amenity__title"><?= Text::e((string) ($item['title'] ?? '')) ?></h3>
        <p class="amenity__text"><?= Text::e((string) ($item['text'] ?? '')) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php $steps = Content::list($page, 'steps.items'); if ($steps !== []): ?>
<section class="shell section">
  <h2 class="section-title section-title--center" data-reveal style="margin:0"><?= Text::e(Content::text($page, 'steps.title')) ?></h2>
  <div class="grid grid--steps">
    <?php foreach ($steps as $i => $step): ?>
      <div class="step" data-reveal data-delay="<?= $i * 110 ?>" style="background:<?= Text::e((string) ($step['color'] ?? '#FFFFFF')) ?>">
        <div class="step__n"><?= Text::e((string) ($step['n'] ?? (string) ($i + 1))) ?></div>
        <h3 class="step__title"><?= Text::e((string) ($step['title'] ?? '')) ?></h3>
        <p class="step__text"><?= Text::e((string) ($step['text'] ?? '')) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="shell section">
  <?= View::partial('partials/audio', ['settings' => $settings, 'kicker' => Content::text($page, 'audio.kicker')]) ?>
</div>

<?php if (!empty($settings['reviews']['enabled']) && $reviews['reviews'] !== []):
    $badge = Content::i18n((array) ($settings['reviews'] ?? []), 'badge', $lang);
    if ($badge === '') {
        // Sans pastille saisie, on la compose à partir des avis réellement affichés.
        $badge = I18n::t('reviews.badge', ['count' => (int) $reviews['count'], 'rating' => str_replace('.', $lang === 'fr' ? ',' : '.', (string) round((float) $reviews['rating'], 1))]);
    } ?>
<section class="shell section">
  <div class="reviews-head" data-reveal>
    <div class="reviews-badge">
      <span class="reviews-badge__stars">★★★★★</span>
      <span class="reviews-badge__text"><?= Text::e($badge) ?></span>
    </div>
    <h2 class="reviews-title"><?= Text::e(Content::text($page, 'reviews.title')) ?></h2>
  </div>
  <?php $palette = ['#FFCC00', '#3DDC97', '#F2F3F5', '#FFFFFF'];
        $list = $reviews['reviews'];
        $many = \count($list) > 1; ?>
  <div class="reviews-carousel" data-reveal<?= $many ? ' data-carousel' : '' ?>>
    <div class="reviews-track" data-carousel-track<?= $many ? ' tabindex="0" role="region" aria-label="' . Text::e(I18n::t('reviews.carousel')) . '"' : '' ?>>
      <?php foreach ($list as $i => $review): $name = (string) ($review['name'] ?? ''); ?>
        <blockquote class="review" data-carousel-item>
          <div class="review__stars"><?= str_repeat('★', max(1, min(5, (int) ($review['rating'] ?? 5)))) ?></div>
          <p class="review__text"><?= Text::e((string) ($review['text'] ?? '')) ?></p>
          <footer class="review__footer">
            <span class="review__avatar" style="background:<?= Text::e($palette[$i % \count($palette)]) ?>"><?= Text::e((string) ($review['initials'] ?? Text::initials($name))) ?></span>
            <span>
              <span class="review__name"><?= Text::e($name) ?></span>
              <span class="review__source"><?= Text::e(I18n::t('reviews.source')) ?></span>
            </span>
          </footer>
        </blockquote>
      <?php endforeach; ?>
    </div>

    <?php if ($many): ?>
      <button class="reviews-arrow reviews-arrow--prev" type="button" data-carousel-prev
              aria-label="<?= Text::e(I18n::t('reviews.prev')) ?>">‹</button>
      <button class="reviews-arrow reviews-arrow--next" type="button" data-carousel-next
              aria-label="<?= Text::e(I18n::t('reviews.next')) ?>">›</button>
      <div class="reviews-dots" data-carousel-dots role="tablist" aria-label="<?= Text::e(I18n::t('reviews.carousel')) ?>">
        <?php foreach ($list as $i => $review): ?>
          <button class="reviews-dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" role="tab"
                  data-carousel-dot aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                  aria-label="<?= Text::e(I18n::t('reviews.goTo', ['n' => $i + 1])) ?>"></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php if (!empty($reviews['url'])): ?>
    <p style="margin-top:20px"><a class="link-underline link-underline--sm" href="<?= Text::url((string) $reviews['url']) ?>" target="_blank" rel="noopener"><?= Text::e(I18n::t('reviews.seeAll')) ?></a></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="shell section">
  <div class="band" data-reveal>
    <div class="band__bubble band__bubble--1" aria-hidden="true"></div>
    <div class="band__bubble band__bubble--2" aria-hidden="true"></div>
    <div class="band__inner">
      <div class="kicker kicker--signal kicker--light"><?= Text::e(Content::text($page, 'band.kicker')) ?></div>
      <h2 class="band__title"><?= Text::e(View::fill(Content::text($page, 'band.title'), ['count' => $available])) ?></h2>
      <p class="band__text"><?= Text::e(Content::text($page, 'band.text')) ?></p>
      <div class="band__actions">
        <a class="btn btn--yellow-paper btn--lift" href="<?= Text::e(Router::url('contact', $lang)) ?>" data-track="band_contact"><?= Text::e(Content::text($page, 'band.cta1')) ?></a>
        <?php if ($tel !== ''): ?>
          <a class="btn btn--outline-paper" href="tel:<?= Text::e($tel) ?>" data-track="band_phone">
            <span class="btn__phone" aria-hidden="true"></span><?= Text::e(Content::text($page, 'band.cta2', $phone)) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($faqItems !== []): ?>
<section class="shell shell--narrow section" style="padding-bottom:40px">
  <h2 class="section-title" data-reveal style="margin:0 0 28px"><?= Text::e(Content::text($page, 'faq.title')) ?></h2>
  <?php foreach ($faqItems as $i => $item): ?>
    <div class="faq<?= $i === 0 ? ' is-open' : '' ?>" data-faq data-reveal>
      <button class="faq__q" type="button" data-faq-toggle aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="faq-<?= $i ?>">
        <span><?= Text::e((string) ($item['q'] ?? '')) ?></span>
        <span class="faq__sign" aria-hidden="true">+</span>
      </button>
      <div class="faq__a" id="faq-<?= $i ?>">
        <p><?= Text::e((string) ($item['a'] ?? '')) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
