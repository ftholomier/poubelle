<?php
/** Carte d'actualité. Variables : $n, $big (bool) */
use App\Vitrine\Content;
use App\Vitrine\Host;
?>
<a class="card vnews-card<?= !empty($big) ? ' vnews-card--big' : '' ?>" href="<?= e(Host::url('/actualites/' . $n['slug'] . '/')) ?>" data-reveal>
  <div class="card__media" style="--ratio:16/10">
    <?php if (Content::hasImage($n['image'] ?? null)): ?>
      <img src="<?= e(img($n['image'], 800)) ?>" srcset="<?= e(srcset($n['image'], [480, 800, 1200])) ?>" sizes="(max-width: 700px) 100vw, 33vw" alt="" loading="lazy">
    <?php else: ?><div class="ph"><?= icon_photo() ?></div><?php endif; ?>
    <?= \App\Core\View::partial('vitrine/partials/verify', ['item' => $n]) ?>
  </div>
  <div class="card__body">
    <span class="card__kicker"><?= e(date_fr((string) ($n['date'] ?? ''))) ?></span>
    <h3 class="card__title"><?= e($n['title']) ?></h3>
    <?php if (!empty($n['excerpt'])): ?><p class="vnews-card__ex"><?= e($n['excerpt']) ?></p><?php endif; ?>
    <span class="vlink">Lire la suite →</span>
  </div>
</a>
