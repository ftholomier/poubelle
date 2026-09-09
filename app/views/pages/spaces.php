<?php
/** Nos espaces : un bloc par lieu (Carnot, Granvelle) + équipements. */

use App\Content;
use App\I18n;
use App\Offices;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$siteById = [];
foreach ((array) ($settings['sites'] ?? []) as $entry) {
    if (\is_array($entry)) {
        $siteById[(string) ($entry['id'] ?? '')] = $entry;
    }
}
?>

<section class="space-hero">
  <div class="shell space-hero__inner">
    <div class="kicker"><?= Text::e(Content::text($page, 'kicker')) ?></div>
    <h1 class="space-hero__title"><?= Text::e(Content::text($page, 'title')) ?></h1>
    <p class="space-hero__text"><?= Text::e(Content::text($page, 'text')) ?></p>
  </div>
</section>

<?php foreach (Content::list($page, 'spaces') as $index => $space):
    $id = (string) ($space['id'] ?? '');
    $site = $siteById[$id] ?? [];
    if (($site['enabled'] ?? true) === false) { continue; }
    $photos = array_values(array_filter(array_merge(
        (array) ($space['photos'] ?? []),
        [(string) ($site['photo'] ?? '')]
    ), static fn ($p): bool => \is_string($p) && $p !== ''));
    $available = Offices::availableCount($id);
    $bg = (string) ($space['bg'] ?? ($index % 2 === 0 ? '#FFF8EA' : '#FFFFFF'));
    $color = (string) ($site['color'] ?? '#FFD100');
?>
<section class="space" id="<?= Text::e($id) ?>" style="background:<?= Text::e($bg) ?>">
  <div class="shell space__inner">
    <div class="space__grid">
      <div data-reveal>
        <span class="tag tag--lg" style="background:<?= Text::e($color) ?>"><?= Text::e(Content::i18n($space, 'tag', $lang)) ?></span>
        <h2 class="space__title"><?= Text::e((string) ($site['name'] ?? $id)) ?></h2>
        <div class="space__where"><?= Text::e((string) ($site['area'] ?? '') . ' · ' . (string) ($site['address'] ?? '')) ?></div>
        <p class="space__text"><?= Text::e(Content::i18n($space, 'text', $lang)) ?></p>

        <?php $access = Content::list($space, 'access'); if ($access !== []): ?>
        <div class="space__access">
          <div class="space__accessLabel"><?= Text::e(I18n::t('contact.accessLabel')) ?></div>
          <div class="chips">
            <?php foreach ($access as $item): ?>
              <span class="chip chip--lg"><?= Text::e((string) $item) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <a class="btn btn--ink space__cta btn--stacked" href="<?= Text::e(Router::availableOffices($lang, $id)) ?>">
          <span class="btn__main"><?= Text::e(Content::i18n($space, 'cta', $lang)) ?></span>
          <span class="btn__sub"><?= Text::e(Offices::availabilityLabel($available)) ?></span>
        </a>
      </div>

      <div class="space__gallery" data-reveal data-delay="120">
        <div class="space__shot space__shot--main">
          <?= View::image((string) ($photos[0] ?? ''), (string) ($site['name'] ?? ''), ['placeholder' => (string) ($site['name'] ?? ''), 'minWidth' => 700, 'sizes' => '(max-width: 880px) 100vw, 600px']) ?>
        </div>
        <div class="space__shot space__shot--small">
          <?= View::image((string) ($photos[1] ?? ''), '', ['placeholder' => (string) ($site['shortName'] ?? ''), 'minWidth' => 190, 'sizes' => '300px']) ?>
        </div>
        <div class="space__shot space__shot--small">
          <?= View::image((string) ($photos[2] ?? ''), '', ['placeholder' => (string) ($site['shortName'] ?? ''), 'minWidth' => 190, 'sizes' => '300px']) ?>
        </div>
      </div>
    </div>

    <?php $album = App\Media::bySite($id); if ($album !== []): ?>
    <div class="album" data-album>
      <div class="album__head">
        <h3 class="album__title"><?= Text::e(I18n::t('album.title', ['name' => (string) ($site['shortName'] ?? '')])) ?></h3>
        <span class="album__count"><?= \count($album) ?> <?= Text::e(I18n::t('album.photos')) ?></span>
      </div>
      <div class="album__grid">
        <?php foreach ($album as $index => $photo):
            $path = (string) $photo['path'];
            $alt = App\Media::alt($path, $lang); ?>
          <button class="album__item" type="button"
                  data-album-open="<?= $index ?>"
                  data-full="<?= Text::e(App\Config::basePath() . $path) ?>"
                  data-caption="<?= Text::e($alt) ?>"
                  aria-label="<?= Text::e($alt) ?>">
            <?= View::image($path, $alt, ['class' => 'album__img', 'sizes' => '(max-width: 620px) 45vw, 220px']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php $amenities = Content::list($space, 'amenities'); if ($amenities !== []): ?>
    <div class="grid grid--amenities">
      <?php foreach ($amenities as $amenity): ?>
        <div class="amenity" data-reveal>
          <div class="amenity__bar" style="background:<?= Text::e((string) ($amenity['color'] ?? $color)) ?>"></div>
          <h3 class="amenity__title"><?= Text::e((string) ($amenity['title'] ?? '')) ?></h3>
          <p class="amenity__text"><?= Text::e((string) ($amenity['text'] ?? '')) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endforeach; ?>
