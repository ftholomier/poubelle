<?php
/**
 * Pagination. @var array $results  @var array $query
 */
use App\Services\I18n;
use App\Services\Search;

if ((int) $results['pages'] <= 1) {
    return;
}
$page = (int) $results['page'];
$pages = (int) $results['pages'];

// Fenêtre glissante autour de la page courante.
$from = max(1, $page - 2);
$to = min($pages, $from + 4);
$from = max(1, $to - 4);
?>
<nav class="pagination" aria-label="<?= e(I18n::t('search.page', $page, $pages)) ?>">
  <a class="<?= $page <= 1 ? 'is-off' : '' ?>" href="<?= e(Search::urlWith($query, 'page', $page - 1)) ?>"
     rel="prev"<?= $page <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= e(I18n::t('search.previous')) ?></a>

  <?php if ($from > 1): ?>
    <a href="<?= e(Search::urlWith($query, 'page', null)) ?>">1</a>
    <?php if ($from > 2): ?><span class="is-off">…</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $from; $i <= $to; $i++): ?>
    <?php if ($i === $page): ?>
      <span aria-current="page"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= e(Search::urlWith($query, 'page', $i === 1 ? null : $i)) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($to < $pages): ?>
    <?php if ($to < $pages - 1): ?><span class="is-off">…</span><?php endif; ?>
    <a href="<?= e(Search::urlWith($query, 'page', $pages)) ?>"><?= $pages ?></a>
  <?php endif; ?>

  <a class="<?= $page >= $pages ? 'is-off' : '' ?>" href="<?= e(Search::urlWith($query, 'page', $page + 1)) ?>"
     rel="next"<?= $page >= $pages ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= e(I18n::t('search.next')) ?></a>
</nav>
