<?php
/** @var int $page @var int $pages @var callable $link (int $page): string */
if ($pages <= 1) {
    return;
}
$show = [];
for ($i = 1; $i <= $pages; $i++) {
    if ($i === 1 || $i === $pages || abs($i - $page) <= 2) {
        $show[] = $i;
    }
}
?>
<nav class="pagination" aria-label="Pagination">
  <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>" rel="prev" aria-label="Page précédente">←</a><?php endif; ?>
  <?php $prev = 0; foreach ($show as $i): ?>
    <?php if ($i - $prev > 1): ?><span class="gap">…</span><?php endif; ?>
    <?php if ($i === $page): ?><span class="on" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e($link($i)) ?>"><?= $i ?></a><?php endif; ?>
    <?php $prev = $i; endforeach; ?>
  <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>" rel="next" aria-label="Page suivante">→</a><?php endif; ?>
</nav>
