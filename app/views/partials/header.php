<?php
/** En-tête collant : logo, navigation, CTA, barre de progression de lecture. */

use App\Config;
use App\I18n;
use App\Router;
use App\Text;

$lang = I18n::lang();
$navItems = [
    ['route' => 'home', 'key' => 'nav.home'],
    ['route' => 'spaces', 'key' => 'nav.spaces'],
    ['route' => 'offices', 'key' => 'nav.offices'],
    ['route' => 'news', 'key' => 'nav.news'],
    ['route' => 'contact', 'key' => 'nav.contact'],
];
$current = $route ?? 'home';
$brandName = (string) ($settings['site']['name'] ?? 'Le iOiO');
$tagline = (string) ($settings['site']['tagline'] ?? '');
?>
<header class="header" data-header>
  <div class="shell header__inner">
    <a class="brand" href="<?= Text::e(Router::url('home', $lang)) ?>">
      <span class="brand__mark" aria-hidden="true"></span>
      <span>
        <span class="brand__name"><?= Text::e($brandName) ?></span>
        <?php if ($tagline !== ''): ?><span class="brand__tagline"><?= Text::e($tagline) ?></span><?php endif; ?>
      </span>
    </a>

    <button class="nav__toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="menu-principal" aria-label="Menu">☰</button>

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
