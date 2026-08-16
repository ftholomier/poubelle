<?php /** @var array|null $page @var array $medias */ ?>
<article class="page">
  <h1><?= e($page['titre'] ?? 'Galerie') ?></h1>
  <div class="galerie">
    <?php foreach ($medias as $m): ?>
      <figure><img src="<?= asset($m['src']) ?>" alt="<?= e($m['alt'] ?? '') ?>" loading="lazy"></figure>
    <?php endforeach; ?>
  </div>
</article>
