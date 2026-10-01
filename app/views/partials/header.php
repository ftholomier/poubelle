<?php
/** En-tête collant : logo, navigation, CTA, barre de progression de lecture. */

use App\Content;
use App\I18n;
use App\Router;
use App\Text;
use App\View;

$lang = I18n::lang();
$navItems = [
    ['route' => 'home', 'key' => 'nav.home'],
    ['route' => 'spaces', 'key' => 'nav.spaces'],
    ['route' => 'offices', 'key' => 'nav.offices'],
];
// L'actu n'apparaît dans le menu qu'à partir du premier article publié :
// un lien vers une page vide décevrait plus qu'il ne servirait.
if (Content::publishedPosts() !== []) {
    $navItems[] = ['route' => 'news', 'key' => 'nav.news'];
}
$navItems[] = ['route' => 'contact', 'key' => 'nav.contact'];

$current = $route ?? 'home';
$brandName = (string) ($settings['site']['name'] ?? 'Le Signal');
$tagline = (string) ($settings['site']['tagline'] ?? '');
?>
<header class="header" data-header>
  <div class="shell header__inner">
    <a class="brand" href="<?= Text::e(Router::url('home', $lang)) ?>" aria-label="<?= Text::e($brandName . ' — ' . I18n::t('nav.home')) ?>">
      <?= View::svg('lesignal-lockup.svg', 'brand__logo') ?>
      <?php if ($tagline !== ''): ?><span class="brand__tagline" aria-hidden="true"><?= Text::e($tagline) ?></span><?php endif; ?>
    </a>

    <button class="nav__toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="menu-principal" aria-label="Menu">
      <span class="nav__bars" aria-hidden="true"><span></span><span></span><span></span></span>
    </button>

    <nav class="nav" id="menu-principal" data-nav aria-label="Navigation principale">
      <?php foreach ($navItems as $item):
          $isActive = $current === $item['route']
              || ($item['route'] === 'offices' && $current === 'office')
              || ($item['route'] === 'news' && $current === 'post'); ?>
        <a class="nav__link<?= $isActive ? ' is-active' : '' ?>"
           href="<?= Text::e(Router::url($item['route'], $lang)) ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>><?= Text::e(I18n::t($item['key'])) ?></a>
      <?php endforeach; ?>
      <a class="nav__cta" href="<?= Text::e(Router::availableOffices($lang)) ?>" data-track="nav_reserve"><?= Text::e(I18n::t('nav.reserve')) ?></a>
    </nav>
  </div>
  <div class="header__progress" data-progress></div>
</header>
