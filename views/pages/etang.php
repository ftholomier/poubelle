<?php /** @var array $item */ ?>
<article class="page">
  <h1><?= e($item['nom']) ?></h1>
  <p><?= e($item['resume'] ?? '') ?></p>
  <p><a href="<?= url('/peche') ?>">Retour à la pêche</a></p>
</article>
