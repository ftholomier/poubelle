<?php /** @var array|null $page @var array $items */ ?>
<article class="page">
  <h1><?= e($page['titre'] ?? 'Hébergements') ?></h1>
  <ul class="liste-cartes">
    <?php foreach ($items as $i): ?>
      <li>
        <a href="<?= url('/hebergements/' . $i['slug']) ?>"><h2><?= e($i['nom']) ?></h2></a>
        <p><?= e($i['resume'] ?? '') ?></p>
        <?php if (!empty($i['prix_a_partir_de'])): ?>
          <p>À partir de <?= e($i['prix_a_partir_de']) ?> € la nuit</p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</article>
