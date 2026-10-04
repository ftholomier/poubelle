<?php
/** Carte d'une action (accueil, rubrique Nos actions). Variables : $a */
use App\Vitrine\Content;
use App\Vitrine\Host;
?>
<a class="card vaction" href="<?= e(Host::url('/nos-actions/' . $a['slug'] . '/')) ?>" data-reveal>
  <div class="card__media" style="--ratio:3/2">
    <?php if (Content::hasImage($a['image'] ?? null)): ?>
      <img src="<?= e(img($a['image'], 800)) ?>" srcset="<?= e(srcset($a['image'], [480, 800, 1200])) ?>" sizes="(max-width: 700px) 100vw, 33vw" alt="" loading="lazy">
    <?php else: ?><div class="ph"><?= icon_photo() ?></div><?php endif; ?>
    <span class="vaction__icon" aria-hidden="true"><?= e($a['icon'] ?? '★') ?></span>
    <?= \App\Core\View::partial('vitrine/partials/verify', ['item' => $a]) ?>
  </div>
  <div class="card__body">
    <h3 class="card__title"><?= e($a['title']) ?></h3>
    <p class="vaction__ex"><?= e($a['excerpt'] ?? '') ?></p>
    <span class="vlink">En savoir plus →</span>
  </div>
</a>
