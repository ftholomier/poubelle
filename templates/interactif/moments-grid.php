<?php
/** Grille des 100 moments. Variables : $moments */
?>
<div class="mgrid100" data-moments>
  <?php foreach ($moments as $m): ?>
    <?php if ($m['open']): ?>
      <a class="mom is-open" href="<?= e($m['href']) ?>" title="<?= e($m['title']) ?>"><b><?= pad2($m['n']) ?></b><small><?= e(mb_strimwidth((string) $m['title'], 0, 34, '…')) ?></small></a>
    <?php elseif ($m['due']): ?>
      <span class="mom is-due"><b><?= pad2($m['n']) ?></b><small><?= e(t('Bientôt')) ?></small></span>
    <?php else: ?>
      <span class="mom is-locked"><b><?= pad2($m['n']) ?></b><small>🔒 <?= e(date_short($m['date'])) ?></small></span>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
