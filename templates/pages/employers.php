<?php
/** Annuaire des employeurs. @var array $results @var array $criteria @var array $query */
use App\Core\View;
use App\Services\I18n;
use App\Support\Icon;
?>
<div class="container">
  <div class="page-head" data-reveal>
    <h1 class="h1-sub"><?= e(I18n::t('employers.title')) ?></h1>
    <p><?= e(I18n::t('employers.lede')) ?></p>
  </div>

  <form class="filter-bar" method="get" role="search">
    <div class="fb-field">
      <?= Icon::svg('search', 18, '#6B6590') ?>
      <label class="visually-hidden" for="f-q"><?= e(I18n::t('search.keyword')) ?></label>
      <input id="f-q" type="search" name="q" value="<?= e($criteria['q']) ?>" placeholder="<?= e(I18n::t('search.keyword')) ?>">
    </div>
    <button type="submit" class="btn btn-coral"><?= e(I18n::t('search.filter')) ?></button>
  </form>

  <div class="result-head" style="margin-top:26px">
    <span class="result-count"><?= e(I18n::t('search.results', number_format((int) $results['total'], 0, ',', ' '))) ?></span>
    <a class="btn btn-ghost btn-sm" href="?hiring=<?= empty($criteria['hiring']) ? '1' : '0' ?>">
      <?= empty($criteria['hiring']) ? 'Avec offres en ligne' : e(I18n::t('search.reset')) ?>
    </a>
  </div>

  <?php if ($results['items'] === []): ?>
    <div class="card empty">
      <h3><?= e(I18n::t('search.none')) ?></h3>
      <p><?= e(I18n::t('search.none_note')) ?></p>
    </div>
  <?php else: ?>
    <div class="grid-employers">
      <?php foreach ($results['items'] as $employer): ?>
        <?= View::partial('partials/employer-card', ['employer' => $employer]) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?= View::partial('partials/pagination', ['results' => $results, 'query' => $query]) ?>
  <?= View::partial('partials/ad', ['slot' => 'dir_bottom']) ?>
</div>
