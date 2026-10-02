<?php
/** « Ce contenu peut vous intéresser ». Variables : $related [kind, title, href, image] */
if (empty($related)) {
    return;
}
?>
<section class="wrap section related">
  <h2 class="h-section"><?= e(t('Ce contenu peut vous intéresser')) ?></h2>
  <div class="related__grid">
    <?php foreach ($related as $r): ?>
      <a class="card" href="<?= e($r['href']) ?>">
        <div class="card__media" style="--ratio:16/10">
          <?php if (!empty($r['image'])): ?>
            <img src="<?= e(img($r['image'], 640)) ?>" srcset="<?= e(srcset($r['image'], [480, 640, 800])) ?>" sizes="(max-width: 700px) 100vw, 33vw" alt="" loading="lazy" decoding="async">
          <?php else: ?><div class="ph"><?= icon_photo() ?></div><?php endif; ?>
        </div>
        <div class="card__body">
          <span class="card__kicker"><?= e($r['kind']) ?></span>
          <span class="card__title"><?= e($r['title']) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
