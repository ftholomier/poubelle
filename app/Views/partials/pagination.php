<?php
/**
 * Barre de pagination du back-office.
 *
 * Les filtres en cours (recherche, étape, brouillons) sont conservés :
 * changer de page ne doit pas réinitialiser la vue.
 *
 * @var array{page:int,pages:int,total:int,par:int} $pager
 */
if (($pager['pages'] ?? 1) < 2) {
    return;
}
$params = $_GET;
$lien = static function (int $page) use ($params): string {
    $params['p'] = $page;
    return '?' . http_build_query($params);
};
$page = (int) $pager['page'];
$pages = (int) $pager['pages'];
$debut = ($page - 1) * (int) $pager['par'] + 1;
$fin = min((int) $pager['total'], $page * (int) $pager['par']);
?>
<nav class="pagination" aria-label="Pagination">
  <span class="pagination__etat">
    <?= e((string) $debut) ?>–<?= e((string) $fin) ?> sur <?= e((string) $pager['total']) ?>
  </span>
  <span class="pagination__pages">
    <?php if ($page > 1): ?>
      <a class="btn btn--sm btn--ghost" href="<?= e($lien($page - 1)) ?>" rel="prev">Précédent</a>
    <?php endif; ?>
    <span class="pagination__num">Page <?= e((string) $page) ?> / <?= e((string) $pages) ?></span>
    <?php if ($page < $pages): ?>
      <a class="btn btn--sm btn--ghost" href="<?= e($lien($page + 1)) ?>" rel="next">Suivant</a>
    <?php endif; ?>
  </span>
</nav>
