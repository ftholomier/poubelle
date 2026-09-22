<?php
/** En-tête collant : logo typographique, navigation, sélecteur de langue. */
use App\Services\I18n;
use App\Support\Icon;

$current = $path ?? '/';

// Une langue sans traduction servirait la version française sous une autre
// adresse : on ne la propose pas, et le sélecteur disparaît s'il ne reste que
// le français.
$languages = array_filter(
    I18n::languages(),
    static fn(array $meta, string $code) => $code === 'fr' || I18n::hasTranslations($code),
    ARRAY_FILTER_USE_BOTH,
);
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

    <nav class="site-nav" id="site-nav" aria-label="<?= e(I18n::t('nav.main')) ?>">
      <?php foreach ($links as $href => $label): ?>
        <a href="<?= e(I18n::url($href)) ?>"<?= str_starts_with($current, $href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php // Le lien de connexion n'apparaît qu'ici en petit écran ; sur grand
            // écran il vit dans la barre d'actions, d'où le masquage en CSS. ?>
      <a class="nav-login" href="/admin"><?= e(I18n::t('nav.login')) ?></a>
    </nav>

    <div class="header-actions">
      <button type="button" class="nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-nav"
              aria-label="Menu"><?= Icon::svg('menu', 20, '#17123A') ?></button>

      <?php if (count($languages) > 1): ?>
      <button type="button" class="lang-btn" data-lang-toggle aria-haspopup="true"
              aria-expanded="false" aria-controls="lang-menu">
        <?= Icon::svg('globe', 15, '#17123A', 2) ?><?= e(strtoupper(I18n::lang())) ?>
      </button>
      <div class="lang-menu" id="lang-menu" data-lang-menu hidden>
        <?php foreach ($languages as $code => $meta): ?>
          <a href="<?= e(I18n::url(I18n::stripPrefix($current), $code)) ?>"
             hreflang="<?= e($code) ?>"<?= $code === I18n::lang() ? ' aria-current="true"' : '' ?>>
            <?= e($meta['name']) ?><span class="code"><?= e(strtoupper($code)) ?></span>
          </a>
        <?php endforeach; ?>
        <p class="note"><?= e(I18n::t('nav.lang_note')) ?></p>
      </div>
      <?php endif; ?>

      <a class="btn btn-ghost btn-sm" href="/admin"><?= e(I18n::t('nav.login')) ?></a>
    </div>
  </div>
</header>
