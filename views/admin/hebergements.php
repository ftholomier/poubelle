<?php
/**
 * Liste des hébergements à éditer.
 * @var array $items
 */
?>
<div class="bo-liste">
  <?php foreach ($items as $h): ?>
    <a class="bo-ligne" href="<?= url('/admin/hebergements/' . rawurlencode($h['slug'])) ?>">
      <img class="bo-ligne__vignette" src="<?= asset(str_replace('.jpg', '-mini.jpg', $h['image'])) ?>" alt="">
      <div class="bo-ligne__corps">
        <h2><?= e($h['nom']) ?></h2>
        <p>À partir de <?= e($h['prix_a_partir_de']) ?> € / nuit — <?= e($h['capacite']) ?> personnes</p>
      </div>
      <span>Modifier →</span>
    </a>
  <?php endforeach; ?>
</div>
