<?php
/**
 * Rubrique en mosaïque (maquette « Mosaïque »).
 * Variables : voir App\Front\Mosaic::show()
 */
?>
<section class="mhead">
  <div class="wrap mhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>">
      <?php foreach ($crumbs as $c): ?><a href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a><span aria-hidden="true">/</span><?php endforeach; ?>
      <span aria-current="page"><?= e($label) ?></span>
    </nav>
    <div class="mhead__row">
      <div class="mhead__text">
        <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e($eyebrow) ?></span>
        <h1 class="mhead__title"><?= e($label) ?></h1>
        <?php if ($intro !== ''): ?><p class="mhead__intro"><?= e($intro) ?></p><?php endif; ?>
      </div>
      <div class="mhead__total"><b data-count><?= number_format($grandTotal ?: $total, 0, ',', ' ') ?></b><span><?= e($unit) ?></span></div>
    </div>
    <?php if (count($tabs) > 1): ?>
    <nav class="mhead__tabs" aria-label="<?= e(t('Sous-rubriques')) ?>">
      <?php foreach ($tabs as $tb): ?><a href="<?= e($tb['href']) ?>"<?= $tb['on'] ? ' class="is-on" aria-current="page"' : '' ?>><?= e($tb['label']) ?></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </div>
</section>

<div class="mtools" data-mtools>
  <div class="wrap mtools__inner">
    <div class="mtools__row">
      <form class="mtools__search" action="<?= e($base) ?>" method="get" role="search" data-msearch>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
        <?php foreach ($state as $k => $v): if ($v === null || $v === '' || $k === 'q') continue; ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <label class="sr-only" for="mq"><?= e(t('Rechercher dans la rubrique')) ?></label>
        <input id="mq" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($placeholder) ?>" autocomplete="off">
      </form>
      <div class="seg" role="group" aria-label="<?= e(t('Trier')) ?>">
        <?php foreach ($sorts as $so): ?><a href="<?= e($so['href']) ?>"<?= $so['on'] ? ' class="is-on" aria-current="true"' : '' ?> rel="nofollow"><?= e($so['label']) ?></a><?php endforeach; ?>
      </div>
      <div class="seg" role="group" aria-label="<?= e(t('Affichage')) ?>">
        <?php $sv = $state; $sv['vue'] = null; $sl = $state; $sl['vue'] = 'liste'; ?>
        <a href="<?= e($base . \App\Front\Mosaic::qs($sv)) ?>"<?= $view === 'grid' ? ' class="is-on"' : '' ?> rel="nofollow">▦ <?= e(t('Mosaïque')) ?></a>
        <a href="<?= e($base . \App\Front\Mosaic::qs($sl)) ?>"<?= $view === 'list' ? ' class="is-on"' : '' ?> rel="nofollow">☰ <?= e(t('Liste')) ?></a>
      </div>
    </div>
    <?php if ($chips): ?>
    <div class="mtools__chips">
      <?php foreach ($chips as $ch): if (!empty($ch['sep'])): ?><span class="mtools__sep" aria-hidden="true"></span><?php continue; endif; ?>
        <a class="mchip<?= $ch['on'] ? ' is-on' : '' ?>" href="<?= e($ch['href']) ?>"<?= $ch['on'] ? ' aria-current="true"' : '' ?>><?= e($ch['label']) ?><?php if (isset($ch['count'])): ?> <small><?= (int) $ch['count'] ?></small><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="wrap mbody">
  <div class="mbody__bar">
    <span class="mbody__count" data-mcount><?= e($countLabel) ?><?= !empty($panel['season']) ? ' · ' . e(t('Saison')) . ' ' . e($panel['season']) : '' ?></span>
    <?php if ($hasFilter): ?><a class="mbody__reset" href="<?= e($resetHref) ?>"><?= e(t('Réinitialiser les filtres')) ?></a><?php endif; ?>
  </div>

  <?php if (!empty($extras)): ?>
  <div class="mextras">
    <span class="dpanel__label"><?= e(count($extras) > 1 ? t('À lire aussi') : t('À lire aussi')) ?></span>
    <div class="mextras__list">
      <?php foreach ($extras as $x): ?>
        <a class="mextra" href="<?= e(url($x['path'])) ?>">
          <span class="mextra__img"><?php if ($x['image']): ?><img src="<?= e(img($x['image'], 160)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
          <span class="mextra__t"><?= e(\App\Front\Pages::shortTitle($x)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($panel): ?>
  <div class="dpanel">
    <div class="dpanel__group">
      <span class="dpanel__label"><?= e(t('Décennie')) ?><?php if ($panel['allDecades']): ?> · <a href="<?= e($panel['allDecades']) ?>"><?= e(t('toutes')) ?></a><?php endif; ?></span>
      <div class="dpanel__tiles">
        <?php foreach ($panel['tiles'] as $d): ?>
          <?php $dt = decade_label((int) $d['full'], true) . ' : ' . $d['count'] . ' ' . ($d['count'] > 1 ? t('matchs') : t('match')); ?>
          <?php if (!$d['count'] && !$d['on']): ?><span class="dtile is-empty" aria-disabled="true" title="<?= e($dt) ?>"><?= e($d['label']) ?></span><?php else: ?><a class="dtile<?= $d['on'] ? ' is-on' : '' ?><?= !$d['count'] ? ' is-empty' : '' ?>" href="<?= e($d['href']) ?>" title="<?= e($dt) ?>"><?= e($d['label']) ?></a><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($panel['decadeName']): ?>
    <div class="dpanel__group">
      <span class="dpanel__label"><?= e(t('Saison')) ?> · <?= e($panel['decadeName']) ?><?php if ($panel['seasonAll']): ?> · <a href="<?= e($panel['seasonAll']) ?>"><?= e(t('toutes')) ?></a><?php endif; ?></span>
      <div class="dpanel__seasons">
        <?php foreach ($panel['seasons'] as $se): ?>
          <a class="schip<?= $se['on'] ? ' is-on' : '' ?>" href="<?= e($se['href']) ?>"><?= e($se['label']) ?> <small>(<?= (int) $se['count'] ?>)</small></a>
        <?php endforeach; ?>
        <?php if (!$panel['seasons']): ?><span class="muted"><?= e(t('Aucun match fiché pour cette décennie.')) ?></span><?php endif; ?>
      </div>
      <?php if ($panel['seasonPage']): ?><a class="link-under dpanel__seasonpage" href="<?= e($panel['seasonPage']) ?>"><?= e(t('Voir la page de la saison {s}', ['s' => $panel['season']])) ?> →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($total === 0): ?>
    <div class="mempty">
      <span class="mempty__t"><?= e(t('Rien dans les réserves…')) ?></span>
      <span><?= e(t('Essayez un autre filtre, ou proposez-nous une archive.')) ?></span>
      <a class="btn btn--yellow btn--sm" href="<?= e(url('/contribuer/')) ?>"><?= e(t('Contribuer')) ?></a>
    </div>
  <?php else: ?>
    <div class="<?= $kind === 'matchs' ? ($view === 'list' ? 'mlist' : 'mgrid') : ($view === 'list' ? 'tlist' : 'tgrid') ?>" data-mitems>
      <?= $itemsHtml ?>
    </div>
    <nav class="mpager" aria-label="<?= e(t('Pagination')) ?>">
      <?php if ($prevHref): ?><a class="btn btn--ghost btn--sm" href="<?= e($prevHref) ?>" rel="prev">← <?= e(t('Page précédente')) ?></a><?php endif; ?>
      <?php if ($nextHref): ?><a class="btn btn--shadow mpager__more" href="<?= e($nextHref) ?>" rel="next" data-mmore><?= e(t('Afficher plus')) ?></a><?php endif; ?>
      <?php if ($pages > 1): ?><span class="mpager__info"><?= e(t('Page {p} sur {n}', ['p' => $pageNum, 'n' => $pages])) ?></span><?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
