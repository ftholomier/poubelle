<?php
/** Pages d'album complétées dans « Mon album » (Album Sochini), sur le carnet du supporter. Variables : $cid */
$done = \App\Services\Vignettes::badges((string) $cid);
if (!$done) return;
$name = \App\Front\AlbumBook::name();
?>
<section class="cnbadges" aria-labelledby="cn-vig">
  <h2 class="h-section" id="cn-vig"><?= e($name) ?> <small><?= e(t('{n} page(s) complétée(s)', ['n' => count($done)])) ?></small></h2>
  <ul class="cnbadges__list">
    <?php foreach ($done as $b): ?><li class="cnbadge is-on"><span class="cnbadge__i" aria-hidden="true">▤</span><b><?= e($b['title']) ?></b><small><?= e(date_fr($b['date'])) ?> · <a href="<?= e('/api/album-vignettes/partage/' . $b['k'] . '.png') ?>" target="_blank" rel="noopener"><?= e(t('Image à partager')) ?></a></small></li><?php endforeach; ?>
  </ul>
</section>
