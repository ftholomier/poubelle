<?php
/** Pied de page : présentation, trois colonnes de liens, avis Google, barre légale. */
use App\Core\Config;
use App\Services\I18n;
use App\Services\Reviews;
use App\Support\Icon;

$reviews = Reviews::get();
$socials = [
    'facebook'  => 'https://www.facebook.com/intermittent.fr',
    'instagram' => 'https://www.instagram.com/intermittent.fr',
    'linkedin'  => 'https://www.linkedin.com/company/intermittent-fr',
    'youtube'   => 'https://www.youtube.com/@intermittentfr',
];
$columns = [
    'footer.seekers' => [
        '/cv'            => I18n::t('nav.cv'),
        '/deposer-un-cv' => I18n::t('cta.post_cv'),
        '/offres'        => I18n::t('nav.jobs'),
    ],
    'footer.employers' => [
        '/deposer-une-annonce' => I18n::t('cta.post_job'),
        '/employeurs'          => I18n::t('nav.employers'),
        '/cv'                  => I18n::t('home.profiles'),
    ],
    'footer.site' => [
        '/ressources'      => I18n::t('nav.resources'),
        '/mentions-legales'=> I18n::t('footer.legal'),
        '/admin'           => I18n::t('nav.login'),
    ],
];
?>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <a class="logo logo-sm logo-light" href="<?= e(I18n::url('/')) ?>">intermittent<span class="tld">.fr</span></a>
        <p class="footer-about"><?= e(I18n::t('footer.about', (string) Config::get('site.since'))) ?></p>
        <h4><?= e(I18n::t('footer.follow')) ?></h4>
        <div class="socials">
          <?php foreach ($socials as $name => $href): ?>
            <a href="<?= e($href) ?>" rel="noopener noreferrer" target="_blank" aria-label="<?= e(ucfirst($name)) ?>">
              <?= Icon::svg($name, 18, 'currentColor', 2) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($columns as $titleKey => $links): ?>
      <div class="footer-col">
        <h4><?= e(I18n::t($titleKey)) ?></h4>
        <ul>
          <?php foreach ($links as $href => $label): ?>
            <li><a href="<?= e(str_starts_with($href, '/admin') ? $href : I18n::url($href)) ?>"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>

      <?php if ($reviews !== null && $reviews['count'] > 0): ?>
      <div class="footer-col reviews">
        <h4><?= e(I18n::t('footer.reviews')) ?></h4>
        <div class="score"><?= e(number_format($reviews['rating'], 1, ',', ' ')) ?></div>
        <div class="stars" aria-label="<?= e($reviews['rating']) ?>/5">
          <?php for ($i = 0; $i < 5; $i++): ?><?= Icon::star(15) ?><?php endfor; ?>
        </div>
        <?php foreach ($reviews['reviews'] as $review): ?>
          <div class="review">
            <span class="tile tile-40" style="background:<?= e(tile_color($review['author'])) ?>;color:<?= e(on_color(tile_color($review['author']))) ?>;width:32px;height:32px;font-size:13px;border-radius:10px">
              <?= e(initials($review['author'], 1)) ?>
            </span>
            <div>
              <div class="who"><?= e($review['author']) ?></div>
              <div class="txt"><?= e($review['text']) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if ($reviews['url'] !== ''): ?>
          <a class="all" href="<?= e($reviews['url']) ?>" rel="noopener noreferrer" target="_blank">
            <?= e(I18n::t('footer.reviews_all', number_format($reviews['count'], 0, ',', ' '))) ?>
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="footer-legal">
      <nav aria-label="<?= e(I18n::t('footer.legal')) ?>">
        <a href="<?= e(I18n::url('/mentions-legales')) ?>"><?= e(I18n::t('footer.legal')) ?></a>
        <a href="<?= e(I18n::url('/mentions-legales')) ?>#cgu"><?= e(I18n::t('footer.terms')) ?></a>
        <a href="<?= e(I18n::url('/mentions-legales')) ?>#cookies"><?= e(I18n::t('footer.cookies')) ?></a>
        <a href="<?= e(I18n::url('/mentions-legales')) ?>#rgpd"><?= e(I18n::t('footer.gdpr')) ?></a>
      </nav>
      <span><?= e(I18n::t('footer.free')) ?></span>
    </div>
  </div>
</footer>
