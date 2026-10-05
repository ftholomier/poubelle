<?php
/** En-tête du site de l'association : logo, menu (sous-menus au survol), lien vers le musée, « Adhérer », menu mobile. */

use App\Vitrine\Host;
use App\Vitrine\Site;

$nav = Site::nav();
$active = $active ?? '';
?>
<header class="vh" data-vheader>
  <div class="vh__bar">
    <a class="vh__brand" href="<?= e(Host::url('/')) ?>" aria-label="<?= e(Site::name()) ?>, accueil">
      <img src="/assets/img/logo-sochaux-retro.png" alt="" width="53" height="60">
      <span><b><?= e(Site::name()) ?></b><small>L’association</small></span>
    </a>
    <nav class="vh__nav" aria-label="Menu principal">
      <ul>
        <?php foreach ($nav as [$key, $label, $href, $sub]): ?>
          <li class="vh__item<?= $sub ? ' has-sub' : '' ?><?= $key === $active ? ' is-active' : '' ?>">
            <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($sub): ?><span class="vh__caret" aria-hidden="true">▾</span><?php endif; ?></a>
            <?php if ($sub): ?>
              <div class="vh__sub">
                <?php foreach ($sub as [$l, $h, $ext]): ?>
                  <a href="<?= e($h) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e($l) ?><?= $ext ? ' <span aria-hidden="true">↗</span>' : '' ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="vh__cta">
      <a class="vh__museum" href="<?= e(Host::museum('/')) ?>" target="_blank" rel="noopener"><span class="vh__museum-k">Le musée</span><span class="vh__museum-v">en ligne</span> <span aria-hidden="true">↗</span></a>
      <?php if (\App\Shop\Orders::open()): ?><a class="btn btn--navy btn--sm vh__shop" href="<?= e(Host::museum('/boutique/')) ?>">La boutique</a><?php endif; ?>
      <a class="btn btn--yellow btn--sm vh__join" href="<?= e(Host::url('/nous-soutenir/adherer/')) ?>">Adhérer</a>
      <button type="button" class="vh__burger" data-vburger aria-controls="vmenu" aria-expanded="false"><span aria-hidden="true" data-vburger-icon>☰</span><span class="sr-only">Menu</span></button>
    </div>
  </div>
  <div class="vh__mobile" id="vmenu" data-vmobile hidden>
    <nav aria-label="Menu principal (mobile)">
      <?php foreach ($nav as [$key, $label, $href, $sub]): ?>
        <div class="vh__mgroup">
          <a class="vh__mtop<?= $key === $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
          <?php if ($sub): ?>
            <div class="vh__msub">
              <?php foreach ($sub as [$l, $h, $ext]): ?><a href="<?= e($h) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e($l) ?><?= $ext ? ' ↗' : '' ?></a><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="row gap-8" style="margin-top:18px">
        <a class="btn btn--yellow" href="<?= e(Host::url('/nous-soutenir/adherer/')) ?>">Adhérer</a>
        <?php if (\App\Shop\Orders::open()): ?><a class="btn btn--navy" href="<?= e(Host::museum('/boutique/')) ?>">La boutique</a><?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(Host::museum('/')) ?>" target="_blank" rel="noopener">Le musée en ligne ↗</a>
      </div>
    </nav>
  </div>
</header>
