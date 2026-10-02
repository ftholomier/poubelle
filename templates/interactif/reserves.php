<?php
/** Les réserves du musée (collections d'objets). Variables : $cols, $col, $items */
?>
<section class="mhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(32px,4vw,56px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><?php if ($col): ?><a href="<?= e(url('/reserves/')) ?>"><?= e(t('Les réserves')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t($col['name'])) ?></span><?php else: ?><span aria-current="page"><?= e(t('Les réserves')) ?></span><?php endif; ?></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Les réserves du musée')) ?></span>
    <h1 class="mhead__title"><?= e($col ? t($col['name']) : t('Les réserves')) ?></h1>
    <p class="mhead__intro"><?= e($col ? t($col['desc']) : t('Maillots, affiches, programmes, photos, presse, objets de supporters : les pièces des collections, photographiées et documentées.')) ?></p>
    <nav class="mhead__tabs">
      <a href="<?= e(url('/reserves/')) ?>"<?= !$col ? ' class="is-on"' : '' ?>><?= e(t('Tout')) ?></a>
      <?php foreach ($cols as $c): ?><a href="<?= e($c['href']) ?>"<?= $col && $col['slug'] === $c['slug'] ? ' class="is-on"' : '' ?>><?= e(t($c['name'])) ?></a><?php endforeach; ?>
    </nav>
  </div>
</section>
<div class="wrap mbody">
  <?php if (!$col): ?>
    <div class="tgrid">
      <?php foreach ($cols as $i => $c): ?>
        <a class="tile<?= $i === 0 ? ' tile--big' : '' ?>" style="grid-row:span <?= $i === 0 ? 2 : 1 ?>;grid-column:span <?= $i === 0 ? 2 : 1 ?>" href="<?= e($c['href']) ?>">
          <?php if ($c['image']): ?><img src="<?= e(img($c['image'], 800)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
          <span class="tile__txt"><span class="tile__tag"><?= (int) $c['count'] ?> <?= e($c['count'] > 1 ? t('pièces') : t('pièce')) ?></span><span class="tile__title"><?= e(t($c['name'])) ?></span><span class="tile__meta"><?= e(t($c['desc'])) ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif ($items): ?>
    <div class="tgrid"><?= \App\Core\View::partial('partials/mosaic-items', ['items' => $items, 'kind' => 'articles', 'view' => 'grid', 'offset' => 0, 'catSlug' => '']) ?></div>
  <?php else: ?>
    <div class="mempty">
      <span class="mempty__t"><?= e(t('Collection en cours de numérisation')) ?></span>
      <span><?= e(t('Vous possédez une pièce liée au FCSM ? Prêtez-la au musée le temps d’une photo.')) ?></span>
      <a class="btn btn--yellow btn--sm" href="<?= e(url('/contribuer/')) ?>"><?= e(t('Proposer une pièce')) ?></a>
    </div>
  <?php endif; ?>
</div>
