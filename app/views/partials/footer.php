<?php
/** Pied de page : logo, présentation, colonnes de liens, mentions. */

use App\Config;
use App\Content;
use App\I18n;
use App\Offices;
use App\Router;
use App\Text;

$lang = I18n::lang();
$basePath = Config::basePath();
$brandName = (string) ($settings['site']['name'] ?? 'Le Signal');
$logo = (string) ($settings['site']['logoLight'] ?? '/assets/img/lesignal-light.svg');
$sites = array_values(array_filter((array) ($settings['sites'] ?? []), static fn ($s): bool => \is_array($s) && ($s['enabled'] ?? true)));
$phone = (string) ($settings['contact']['phone'] ?? '');

$bookLinks = [];
foreach (\array_slice(Offices::decorateAll(Offices::filter(Offices::published(), ['status' => 'available']), $lang), 0, 3) as $office) {
    $bookLinks[] = ['label' => $office['name'], 'url' => $office['url']];
}
?>
<footer class="footer">
  <div class="shell footer__inner">
    <div class="footer__brand">
      <img class="footer__logo" src="<?= Text::e($basePath . $logo) ?>" alt="<?= Text::e($brandName . ' — ' . (string) ($settings['site']['tagline'] ?? '')) ?>" width="280" height="66" loading="lazy">
      <p class="footer__about"><?= Text::e(I18n::t('footer.about')) ?></p>
      <?php $socials = array_filter((array) ($settings['social'] ?? []), static fn ($s): bool => \is_array($s) && (string) ($s['url'] ?? '') !== ''); ?>
      <?php if ($socials !== []): ?>
      <div class="footer__social">
        <?php foreach ($socials as $social): ?>
          <a class="footer__socialLink" href="<?= Text::url((string) $social['url']) ?>" rel="noopener me" target="_blank" title="<?= Text::e((string) ($social['title'] ?? '')) ?>">
            <?= Text::e((string) ($social['label'] ?? '·')) ?>
            <span class="sr-only"><?= Text::e((string) ($social['title'] ?? '')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="footer__colTitle"><?= Text::e(I18n::t('footer.colBrand')) ?></div>
      <div class="footer__links">
        <a class="footer__link" href="<?= Text::e(Router::url('spaces', $lang)) ?>"><?= Text::e(I18n::t('footer.spaces')) ?></a>
        <a class="footer__link" href="<?= Text::e(Router::url('offices', $lang)) ?>"><?= Text::e(I18n::t('footer.offices')) ?></a>
        <a class="footer__link" href="<?= Text::e(Router::url('offices', $lang, [], ['type' => 'private'])) ?>"><?= Text::e(I18n::t('filter.private')) ?></a>
        <a class="footer__link" href="<?= Text::e(Router::url('offices', $lang, [], ['type' => 'openspace'])) ?>"><?= Text::e(I18n::t('filter.openspace')) ?></a>
        <a class="footer__link" href="<?= Text::e(Router::availableOffices($lang)) ?>"><?= Text::e(I18n::t('footer.availability')) ?></a>
        <?php if (Content::publishedPosts() !== []): ?>
          <a class="footer__link" href="<?= Text::e(Router::url('news', $lang)) ?>"><?= Text::e(I18n::t('footer.news')) ?></a>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="footer__colTitle"><?= Text::e(I18n::t('footer.colBook')) ?></div>
      <div class="footer__links">
        <?php if ($phone !== ''): ?>
          <a class="footer__link footer__link--strong" href="tel:<?= Text::e(preg_replace('/[^0-9+]/', '', $phone) ?? '') ?>"><?= Text::e($phone) ?></a>
        <?php endif; ?>
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
          <a class="footer__link" href="<?= Text::e(Router::url('contact', $lang)) ?>#plan">
            <?= Text::e((string) ($site['address'] ?? '')) ?><br><?= Text::e(trim((string) ($site['zip'] ?? '') . ' ' . (string) ($site['city'] ?? ''))) ?>
          </a>
        <?php endforeach; ?>
        <?php if (!empty($settings['contact']['email'])): ?>
          <a class="footer__link" href="mailto:<?= Text::e((string) $settings['contact']['email']) ?>"><?= Text::e((string) $settings['contact']['email']) ?></a>
        <?php endif; ?>
        <a class="footer__link" href="<?= Text::e(Router::url('contact', $lang)) ?>"><?= Text::e(I18n::t('footer.writeUs')) ?></a>
      </div>
    </div>
  </div>

  <div class="shell footer__bottom">
    <span>© <?= date('Y') ?> <?= Text::e(mb_strtoupper($brandName)) ?> — <?= Text::e(I18n::t('footer.rights')) ?></span>
    <span class="footer__legal">
      <a href="<?= Text::e(Router::url('legal', $lang)) ?>"><?= Text::e(I18n::t('nav.legal')) ?></a>
      <a href="<?= Text::e(Router::url('privacy', $lang)) ?>"><?= Text::e(I18n::t('nav.privacy')) ?></a>
      <button type="button" class="footer__cookies" data-consent-open><?= Text::e(I18n::t('consent.link')) ?></button>
    </span>
  </div>
</footer>
