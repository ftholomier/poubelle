<?php /** @var array|null $page @var array $items */ ?>
<article class="page">
  <h1><?= e($page['titre'] ?? 'La pêche') ?></h1>
  <ul class="liste-cartes">
    <?php foreach ($items as $i): ?>
      <li>
        <a href="<?= url('/peche/' . $i['slug']) ?>"><h2><?= e($i['nom']) ?></h2></a>
        <p><?= e($i['resume'] ?? '') ?></p>
      </li>
    <?php endforeach; ?>
  </ul>
</article>
