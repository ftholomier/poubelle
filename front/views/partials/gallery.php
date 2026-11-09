<?php
/** Galerie photos des produits, avec filtres par gamme. @var array $gallery @var string $current */
$items = $gallery['items'] ?? [];
if (!$items) {
    return;
}
$filters = $gallery['filters'] ?? [];
$counts = [];
foreach ($items as $it) {
    $counts[$it['cat']] = ($counts[$it['cat']] ?? 0) + 1;
}
$current = $current !== '' && isset($counts[$current]) ? $current : '';
?>
<section class="sec" data-gallery>
  <div class="sec__head" data-reveal="up">
    <div><?= View::eyebrow('', 'Photos') ?><h2 class="h2"><?= e($title ?? 'Nos produits en images') ?></h2></div>
  </div>
  <div class="chips" style="margin-top:26px" role="tablist">
    <button class="chip<?= $current === '' ? ' is-on' : '' ?>" type="button" data-filter="">Toutes<small><?= count($items) ?></small></button>
    <?php foreach ($filters as $f): if (empty($counts[$f['id']])) { continue; } ?>
      <button class="chip<?= $current === $f['id'] ? ' is-on' : '' ?>" type="button" data-filter="<?= e($f['id']) ?>"><?= e($f['label']) ?><small><?= (int) $counts[$f['id']] ?></small></button>
    <?php endforeach; ?>
  </div>
  <div class="grid-gal" style="margin-top:26px" data-lightbox>
    <?php foreach ($items as $i => $it): ?>
      <a class="gtile<?= $current !== '' && $it['cat'] !== $current ? ' is-out' : '' ?>" href="<?= e($it['src']) ?>" data-cat="<?= e($it['cat']) ?>">
        <img src="<?= e($it['src']) ?>" alt="<?= e($it['title'] ?? '') ?>" loading="lazy">
        <div class="gtile__shade"></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
