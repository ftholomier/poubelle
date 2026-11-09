<?php
$pg = Content::page('realisations');
$items = Content::realisations();
View::render('partials/band', ['eyebrow' => $pg['eyebrow'] ?? '', 'h1' => $pg['title'], 'lead' => $pg['lead'] ?? '']);
?>
<div class="wrap" style="padding-top:34px">
  <?php if (!empty($pg['content'])): ?><div class="prose" style="max-width:860px;margin-bottom:28px"><?= View::html($pg['content']) ?></div><?php endif; ?>
  <div class="grid-gal" data-lightbox>
    <?php foreach ($items as $i => $g): $cap = trim((string) ($g['title'] ?? '')) !== ''; ?>
      <a class="gtile<?= $cap ? ' gtile--cap' : '' ?>" href="<?= e($g['src']) ?>" data-reveal="zoom" data-reveal-delay="<?= ($i % 6) * 70 ?>">
        <img src="<?= e($g['src']) ?>" alt="<?= e($cap ? $g['title'] : $pg['title']) ?>" loading="lazy">
        <div class="gtile__shade"></div>
        <?php if ($cap): ?>
          <div class="gtile__cap"><div class="gtile__t"><?= e($g['title']) ?></div><?php if (!empty($g['place'])): ?><div class="gtile__p"><?= e($g['place']) ?></div><?php endif; ?></div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="more">
    <?= View::btn(($pg['button'] ?? 'Demander un devis') . ' →', Content::path('devis'), 'btn-y btn-lg') ?>
  </div>
</div>
