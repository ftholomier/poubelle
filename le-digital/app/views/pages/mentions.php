<?php
/** @var array $c  @var array $s */
?>
<section class="legal">
  <h1 class="legal__title"><?= t($c['title']) ?></h1>
  <?php foreach ($c['sections'] as $sec): if (trim((string) ($sec['text'] ?? '')) === '') { continue; } ?>
    <h2 id="<?= e($sec['id'] ?? slugify($sec['title'])) ?>"><?= t($sec['title']) ?></h2>
    <p><?= tp($sec['text']) ?></p>
  <?php endforeach; ?>
</section>
