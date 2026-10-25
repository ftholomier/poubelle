<?php
/** @var array $c  @var array $s */
?>
<section class="legal">
  <h1 class="legal__title"><?= t($c['title']) ?></h1>
  <?php foreach ($c['sections'] as $sec): ?>
    <h2 id="<?= e($sec['id'] ?? slugify($sec['title'])) ?>"><?= t($sec['title']) ?></h2>
    <p><?= tp($sec['text'] ?? '') ?><?php if (trim((string) ($sec['todo'] ?? '')) !== ''): ?> <span class="accent"><?= t($sec['todo']) ?></span><?php endif; ?></p>
  <?php endforeach; ?>
</section>
