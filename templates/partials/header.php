<?php
/** En-tête collant : logo typographique, navigation, sélecteur de langue. */
use App\Services\I18n;
use App\Support\Icon;

$current = $path ?? '/';
$links = [
    '/offres'     => I18n::t('nav.jobs'),
    '/cv'         => I18n::t('nav.cv'),
    '/employeurs' => I18n::t('nav.employers'),
    '/ressources' => I18n::t('nav.resources'),
];
?>
<header class="site-header">
  <div class="inner">
    <a class="logo" href="<?= e(I18n::url('/')) ?>" aria-label="intermittent.fr">intermittent<span class="tld">.fr</span></a>

    <nav class="site-nav" id="site-nav" aria-label="<?= e(I18n::t('nav.jobs')) ?>">
      <?php foreach ($links as $href => $label): ?>
        <a href="<?= e(I18n::url($href)) ?>"<?= str_starts_with($current, $href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="nav-login" href="/admin"><?= e(I18n::t('nav.login')) ?></a>
    </nav>

    <div class="header-actions">
      <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-nav"
              aria-label="Menu"><?= Icon::svg('menu', 20, '#17123A') ?></button>

      <button type="button" class="lang-btn" data-lang-toggle aria-haspopup="true" aria-controls="lang-menu">
        <?= Icon::svg('globe', 15, '#17123A', 2) ?><?= e(strtoupper(I18n::lang())) ?>
      </button>
      <div class="lang-menu" id="lang-menu" data-lang-menu hidden>
        <?php foreach (I18n::languages() as $code => $meta): ?>
          <a href="<?= e(I18n::url(I18n::stripPrefix($current), $code)) ?>"
             hreflang="<?= e($code) ?>"<?= $code === I18n::lang() ? ' aria-current="true"' : '' ?>>
            <?= e($meta['name']) ?><span class="code"><?= e(strtoupper($code)) ?></span>
          </a>
        <?php endforeach; ?>
        <p class="note"><?= e(I18n::t('nav.lang_note')) ?></p>
      </div>

      <a class="btn btn-ghost btn-sm" href="/admin"><?= e(I18n::t('nav.login')) ?></a>
    </div>
  </div>
</header>
