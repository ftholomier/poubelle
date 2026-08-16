<?php /** @var array $page */ ?>
<article class="page">
  <h1><?= e($page['titre'] ?? '') ?></h1>
  <?php foreach ($page['sections'] ?? [] as $s): ?>
    <section>
      <?php if (!empty($s['titre'])): ?><h2><?= e($s['titre']) ?></h2><?php endif; ?>
      <?php if (!empty($s['texte'])): ?><p><?= e($s['texte']) ?></p><?php endif; ?>
    </section>
  <?php endforeach; ?>
</article>
