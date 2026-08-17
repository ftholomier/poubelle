<?php
/**
 * Liste des hébergements à éditer.
 * @var array $items
 */
?>
<div class="bo-liste">
  <?php foreach ($items as $h): ?>
    <div class="bo-ligne">
      <img class="bo-ligne__vignette" src="<?= asset(str_replace('.jpg', '-mini.jpg', $h['images_avant'][0] ?? $h['image'])) ?>" alt="">
      <div class="bo-ligne__corps">
        <h2><?= e($h['nom']) ?></h2>
        <p>À partir de <?= e($h['prix_a_partir_de']) ?> € / nuit — <?= e($h['capacite']) ?> personnes</p>
      </div>
      <div class="bo-ligne__liens">
        <a href="<?= url('/admin/hebergements/' . rawurlencode($h['slug'])) ?>">Textes →</a>
        <a href="<?= url('/admin/hebergements/' . rawurlencode($h['slug']) . '/photos') ?>">Photos →</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
