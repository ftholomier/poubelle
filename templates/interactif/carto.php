<?php
/** Carte interactive (maquette « Carto ») : stades, origines, épopées, lieux. */
?>
<section class="carto" data-carto data-api="/api/carte" data-lang="<?= e(\App\Services\I18n::lang()) ?>">
  <div class="carto__top">
    <div class="carto__brand"><b><?= e(t('La carto du musée')) ?></b><i><?= e(t('Stades, origines, épopées et lieux')) ?></i></div>
    <nav class="carto__tabs" role="tablist" aria-label="<?= e(t('Vues de la carte')) ?>">
      <button class="ctab" role="tab" data-tab="matchs" aria-selected="true"><?= e(t('Les stades')) ?></button>
      <button class="ctab" role="tab" data-tab="lions" aria-selected="false"><?= e(t('Les origines')) ?></button>
      <button class="ctab" role="tab" data-tab="epopees" aria-selected="false"><?= e(t('Les épopées')) ?></button>
      <button class="ctab" role="tab" data-tab="lieux" aria-selected="false"><?= e(t('Les lieux')) ?></button>
    </nav>
  </div>
  <div class="carto__app">
    <aside class="cpanel" id="cpanel" aria-live="polite"></aside>
    <div class="cmap">
      <div id="cmap" role="region" aria-label="<?= e(t('Carte')) ?>"></div>
      <div class="clegend" id="clegend"></div>
      <div class="czoom" id="czoom"><button type="button" data-z="in" aria-label="<?= e(t('Zoomer')) ?>">+</button><button type="button" data-z="out" aria-label="<?= e(t('Dézoomer')) ?>">−</button><button type="button" data-z="reset" aria-label="<?= e(t('Recentrer')) ?>">⟲</button></div>
      <div class="cdrawer" id="cdrawer"></div>
      <div class="cstory" id="cstory"></div>
      <noscript><p class="cnojs"><?= e(t('La carte nécessite JavaScript.')) ?></p></noscript>
    </div>
  </div>
</section>
