<?php
/** Page éditoriale simple (Présentation, Mentions légales…). @var array $pg @var string $key */
View::render('partials/band', ['eyebrow' => $pg['eyebrow'] ?? '', 'h1' => $pg['h1'] ?? $pg['title'], 'lead' => $pg['lead'] ?? '']);
?>
<div class="page-body">
  <div class="prose" data-reveal="up"><?= View::html($pg['content'] ?? '') ?></div>
  <?php if (!empty($pg['image'])): ?>
    <div class="art__hero" style="padding:0;margin-top:34px"><div><img src="<?= e($pg['image']) ?>" alt="<?= e($pg['title']) ?>" loading="lazy"></div></div>
  <?php endif; ?>
</div>
<?php if (($key ?? '') !== 'mentions'): ?><?php View::render('partials/cta_block') ?><?php endif; ?>
