<?php
/** Pied de page : marque, colonnes de liens, mentions. */

use App\Config;
use App\Content;
use App\I18n;
use App\Offices;
use App\Router;
use App\Text;

$lang = I18n::lang();
$basePath = Config::basePath();
$brandName = (string) ($settings['site']['name'] ?? 'Le iOiO');
$logo = (string) ($settings['site']['logo'] ?? '/assets/img/ioio-logo.png');
$sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));

$bookLinks = [];
foreach (\array_slice(Offices::decorateAll(Offices::filter(Offices::published(), ['status' => 'available']), $lang), 0, 3) as $office) {
    $bookLinks[] = ['label' => $office['name'], 'url' => $office['url']];
}
?>
<footer class="footer">
  <div class="shell footer__inner">
    <div>
      <img class="footer__logo" src="<?= Text::e($basePath . $logo) ?>" alt="<?= Text::e($brandName . ' — ' . (string) ($settings['site']['tagline'] ?? '')) ?>" width="154" height="154" loading="lazy">
      <p class="footer__about"><?= Text::e(I18n::t('footer.about')) ?></p>
      <div class="footer__social">
        <?php foreach ((array) ($settings['social'] ?? []) as $social):
            $url = (string) ($social['url'] ?? '');
            if ($url === '') { continue; } ?>
          <a class="footer__socialLink" href="<?= Text::url($url) ?>" rel="noopener me" target="_blank" title="<?= Text::e((string) ($social['title'] ?? '')) ?>">
            <?= Text::e((string) ($social['label'] ?? '·')) ?>
            <span class="sr-only"><?= Text::e((string) ($social['title'] ?? '')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="footer__colTitle"><?= Text::e(I18n::t('footer.colBrand')) ?></div>
      <div class="footer__links">
        <a class="footer__link" href="<?= Text::e(Router::url('spaces', $lang)) ?>"><?= Text::e(I18n::t('nav.spaces')) ?></a>
        <a class="footer__link" href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('nav.offices')) ?></a>
        <a class="footer__link" href="<?= Text::e(Router::url('offices', $lang)) ?>#bureaux"><?= Text::e(I18n::t('footer.availability')) ?></a>
        <a class="footer__link" href="<?= Text::e(Router::url('news', $lang)) ?>"><?= Text::e(I18n::t('nav.news')) ?></a>
      </div>
    </div>

    <div>
      <div class="footer__colTitle"><?= Text::e(I18n::t('footer.colBook')) ?></div>
      <div class="footer__links">
        <a class="footer__link" href="<?= Text::e(Router::url('contact', $lang)) ?>"><?= Text::e(I18n::t('footer.bookVisit')) ?></a>
        <?php foreach ($bookLinks as $link): ?>
          <a class="footer__link" href="<?= Text::e($link['url']) ?>"><?= Text::e($link['label']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="footer__colTitle"><?= Text::e(I18n::t('footer.colContact')) ?></div>
      <div class="footer__links">
        <?php foreach ($sites as $site): ?>
          <a class="footer__link" href="<?= Text::e(Router::url('contact', $lang)) ?>"><?= Text::e((string) ($site['address'] ?? '') . ', ' . (string) ($site['city'] ?? '')) ?></a>
        <?php endforeach; ?>
        <?php if (!empty($settings['contact']['email'])): ?>
          <a class="footer__link" href="mailto:<?= Text::e((string) $settings['contact']['email']) ?>"><?= Text::e((string) $settings['contact']['email']) ?></a>
        <?php endif; ?>
        <?php if (!empty($settings['contact']['phone'])): ?>
          <a class="footer__link" href="tel:<?= Text::e(preg_replace('/[^0-9+]/', '', (string) $settings['contact']['phone']) ?? '') ?>"><?= Text::e((string) $settings['contact']['phone']) ?></a>
        <?php endif; ?>
        <a class="footer__link" href="<?= Text::e(Router::url('contact', $lang)) ?>"><?= Text::e(I18n::t('footer.writeUs')) ?></a>
      </div>
    </div>
  </div>

  <div class="shell footer__bottom">
    <span>© <?= date('Y') ?> <?= Text::e($brandName) ?> — <?= Text::e(I18n::t('footer.rights')) ?></span>
    <span class="footer__legal">
      <a href="<?= Text::e(Router::url('legal', $lang)) ?>"><?= Text::e(I18n::t('nav.legal')) ?></a>
      <a href="<?= Text::e(Router::url('privacy', $lang)) ?>"><?= Text::e(I18n::t('nav.privacy')) ?></a>
    </span>
  </div>
</footer>
