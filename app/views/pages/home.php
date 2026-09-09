<?php
/**
 * Accueil : hero + bandeau défilant + espaces + disponibilités + étapes
 * + avis + bande de conversion + FAQ. Reprise fidèle du design de référence.
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
// pas la même photo d'ouverture, et tous les espaces finissent par être vus.
shuffle($slides);
$sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));
$featured = \array_slice(Offices::decorateAll(Offices::filter(Offices::published(), ['status' => 'available']), $lang), 0, 3);
$marquee = array_values(array_filter((array) ($settings['marquee'] ?? []), 'is_array'));
$faqItems = Content::list($hero === [] ? $page : $page, 'faq.items');
?>

<section class="hero">
  <div class="hero__blob hero__blob--yellow" data-parallax="0.16"></div>
  <div class="hero__blob hero__blob--teal" data-parallax="-0.13"></div>
  <div class="hero__thread hero__thread--1" aria-hidden="true"><span></span></div>
  <div class="hero__thread hero__thread--2" aria-hidden="true"><span></span></div>

  <div class="shell hero__inner">
    <div>
      <div class="hero__badge hero__badge--live" data-reveal>
        <span class="hero__badgeDot" aria-hidden="true"></span>
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

      <?php // Un bouton d'accueil peut viser une page filtrée : « status »
            // est saisissable au back-office à côté de la route.
            $ctaUrl = static fn (array $cta, string $default): string => Router::url(
                (string) Content::text($cta, 'route', $default),
                $lang,
                [],
                ['status' => (string) Content::text($cta, 'status', '')]
            );
            $ctaPrimary = (array) ($hero['ctaPrimary'] ?? []);
            $ctaSecondary = (array) ($hero['ctaSecondary'] ?? []); ?>
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
        <div class="slideshow__dots" role="tablist" aria-label="Photos des espaces">
          <?php foreach ($slides as $i => $slide): ?>
            <button class="slideshow__dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" role="tab"
                    data-slide-dot aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    aria-label="Photo <?= $i + 1 ?>"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

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
          <span style="color:<?= Text::e((string) ($item['color'] ?? '#FFF8EA')) ?>"><?= Text::e((string) ($item['label'] ?? '')) ?></span>
          <span class="marquee__bullet"></span>
        </span>
      <?php endforeach; ?>
    <?php endfor; ?>
  </div>
</section>
<?php endif; ?>

<section class="shell section" style="padding-bottom:20px">
  <div class="section-head" data-reveal>
    <div>
      <div class="kicker"><?= Text::e(Content::text($page, 'places.kicker')) ?></div>
      <h2 class="section-title"><?= Text::e(Content::text($page, 'places.title')) ?></h2>
    </div>
    <a class="btn btn--outline btn--md" href="<?= Text::e(Router::url('spaces', $lang)) ?>"><?= Text::e(Content::text($page, 'places.cta')) ?></a>
  </div>

  <div class="grid grid--places">
    <?php foreach ($sites as $i => $site):
        $siteAvailable = Offices::availableCount((string) $site['id']); ?>
      <article class="place-card" data-reveal data-delay="<?= $i * 120 ?>">
        <div class="place-card__media" style="background:<?= Text::e((string) ($site['color'] ?? '#FFD100')) ?>">
          <?= View::image((string) ($site['photo'] ?? ''), (string) ($site['name'] ?? ''), ['placeholder' => (string) ($site['name'] ?? ''), 'minWidth' => 700, 'sizes' => '(max-width: 880px) 100vw, 600px']) ?>
          <?php if (!empty($site['logo'])): ?>
            <span class="place-card__logo" style="background-image:<?= View::bgUrl((string) $site['logo']) ?>"></span>
          <?php endif; ?>
        </div>
        <div class="place-card__body">
          <div class="place-card__meta">
            <span class="tag" style="background:<?= Text::e((string) ($site['color'] ?? '#FFD100')) ?>"><?= Text::e(Content::i18n($site, 'tag', $lang)) ?></span>
            <span class="meta-note"><?= Text::e((string) ($site['area'] ?? '')) ?></span>
          </div>
          <h3 class="place-card__title"><?= Text::e((string) ($site['shortName'] ?? $site['name'])) ?></h3>
          <p class="place-card__text"><?= Text::e(Content::i18n($site, 'description', $lang) ?: Content::i18n($site, 'note', $lang)) ?></p>
          <div class="chips">
            <?php
            $chips = $lang !== 'fr' && !empty($site['i18n'][$lang]['chips']) ? (array) $site['i18n'][$lang]['chips'] : (array) ($site['chips'] ?? []);
            if ($chips === []) { $chips = [(string) ($site['address'] ?? ''), (string) ($site['area'] ?? '')]; }
            foreach (array_filter($chips) as $chip): ?>
              <span class="chip"><?= Text::e((string) $chip) ?></span>
            <?php endforeach; ?>
            <?php if ($siteAvailable > 0): ?>
              <span class="chip"><?= Text::e(Offices::availabilityLabel($siteAvailable)) ?></span>
            <?php endif; ?>
          </div>
          <a class="btn btn--ink btn--md place-card__cta" href="<?= Text::e(Router::url('offices', $lang)) ?>?site=<?= Text::e((string) $site['id']) ?>">
            <?= Text::e(Content::i18n($site, 'cta', $lang) ?: (string) ($site['shortName'] ?? '')) ?>
          </a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="shell section--tight section" id="dispos">
  <div class="section-head" data-reveal>
    <div>
      <div class="kicker"><?= Text::e(Content::text($page, 'availability.kicker')) ?></div>
      <h2 class="section-title"><?= Text::e(Content::text($page, 'availability.title')) ?></h2>
    </div>
    <a class="link-underline" href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('office.allOffices')) ?></a>
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

<section class="shell section">
  <h2 class="section-title section-title--center" data-reveal style="margin:0"><?= Text::e(Content::text($page, 'steps.title')) ?></h2>
  <div class="grid grid--steps">
    <?php foreach (Content::list($page, 'steps.items') as $i => $step): ?>
      <div class="step" data-reveal data-delay="<?= $i * 110 ?>" style="background:<?= Text::e((string) ($step['color'] ?? '#FFFFFF')) ?>">
        <div class="step__n"><?= Text::e((string) ($step['n'] ?? (string) ($i + 1))) ?></div>
        <h3 class="step__title"><?= Text::e((string) ($step['title'] ?? '')) ?></h3>
        <p class="step__text"><?= Text::e((string) ($step['text'] ?? '')) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!empty($settings['reviews']['enabled']) && $reviews['reviews'] !== []): ?>
<section class="shell section">
  <div class="reviews-head" data-reveal>
    <div class="reviews-badge">
      <span class="reviews-badge__stars">★★★★★</span>
      <span class="reviews-badge__text"><?= Text::e(Content::i18n((array) ($settings['reviews'] ?? []), 'badge', $lang)) ?></span>
    </div>
    <h2 class="reviews-title"><?= Text::e(Content::text($page, 'reviews.title')) ?></h2>
  </div>
  <div class="grid grid--reviews">
    <?php $palette = ['#FFD100', '#12B39A', '#EDE5D5', '#FFFFFF'];
    foreach ($reviews['reviews'] as $i => $review):
        $name = (string) ($review['name'] ?? ''); ?>
      <blockquote class="review" data-reveal data-delay="<?= $i * 90 ?>">
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
  <?php if (!empty($reviews['url'])): ?>
    <p style="margin-top:20px"><a class="link-underline link-underline--sm" href="<?= Text::url((string) $reviews['url']) ?>" target="_blank" rel="noopener"><?= Text::e(I18n::t('reviews.seeAll')) ?></a></p>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="shell section">
  <div class="band" data-reveal>
    <div class="band__blob band__blob--1"></div>
    <div class="band__blob band__blob--2"></div>
    <div class="band__inner">
      <h2 class="band__title"><?= Text::e(View::fill(Content::text($page, 'band.title'), ['count' => $available])) ?></h2>
      <p class="band__text"><?= Text::e(Content::text($page, 'band.text')) ?></p>
      <div class="band__actions">
        <a class="btn btn--yellow-cream btn--lift" href="<?= Text::e(Router::availableOffices($lang)) ?>" data-track="band_reserve"><?= Text::e(Content::text($page, 'band.cta1')) ?></a>
        <a class="btn btn--outline-cream" href="<?= Text::e(Router::url('contact', $lang)) ?>" data-track="band_contact"><?= Text::e(Content::text($page, 'band.cta2')) ?></a>
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
