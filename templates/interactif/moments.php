<?php
/** 100 ans, 100 moments. Variables : $moments */
$open = array_values(array_filter($moments, fn ($m) => $m['open']));
?>
<section class="mhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(32px,4vw,56px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/centenaire/')) ?>"><?= e(t('Centenaire')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('100 moments')) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Jusqu’au 14 juin 2028')) ?></span>
    <h1 class="mhead__title"><?= e(t('100 ans,')) ?><br><?= e(t('100 moments')) ?></h1>
  </div>
</section>
<div class="wrap mbody">
  <?= \App\Core\View::partial('interactif/moments-grid', ['moments' => $moments]) ?>
  <?php if ($open): ?>
  <div class="related__grid" style="margin-top:24px">
    <?php foreach (array_reverse($open) as $m): ?>
      <a class="card" href="<?= e($m['href']) ?>">
        <div class="card__media" style="--ratio:16/10"><?php if ($m['image']): ?><img src="<?= e(img($m['image'], 640)) ?>" alt="" loading="lazy"><?php else: ?><div class="ph"><?= icon_photo() ?></div><?php endif; ?></div>
        <div class="card__body"><span class="card__kicker"><?= e(t('Moment n° {n}', ['n' => pad2($m['n'])])) ?><?= $m['year'] ? ' · ' . (int) $m['year'] : '' ?></span><span class="card__title"><?= e($m['title']) ?></span></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="mempty"><span class="mempty__t"><?= e(t('Les premiers moments arrivent')) ?></span><span><?= e(t('Les historiens du musée préparent les récits : revenez bientôt !')) ?></span></div>
  <?php endif; ?>
</div>
