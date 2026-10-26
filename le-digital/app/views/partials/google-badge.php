<?php
/** @var array $badge ['rating','count','url','write']  @var bool $write  afficher « Laisser un avis » */
$write ??= false;
$note = number_format($badge['rating'], 1, ',', ' ');
$full = (int) round($badge['rating']);
?>
<div class="gbadge">
  <?php $tag = $badge['url'] !== '' ? 'a' : 'span'; ?>
  <<?= $tag ?> class="gbadge__score"<?= $tag === 'a' ? ' href="' . e($badge['url']) . '" target="_blank" rel="noopener"' : '' ?> aria-label="Note Google : <?= e($note) ?> sur 5, <?= (int) $badge['count'] ?> avis">
    <?= google_g(22) ?>
    <b><?= e($note) ?></b>
    <span class="gbadge__stars" aria-hidden="true"><?= str_repeat('★', $full) ?><span class="rv__off"><?= str_repeat('★', 5 - $full) ?></span></span>
    <span class="gbadge__count"><?= (int) $badge['count'] ?>&nbsp;avis Google</span>
  </<?= $tag ?>>
  <?php if ($write && $badge['write'] !== ''): ?>
    <a class="gbadge__write" href="<?= e($badge['write']) ?>" target="_blank" rel="noopener">Laisser un avis Google ↗</a>
  <?php endif; ?>
</div>
