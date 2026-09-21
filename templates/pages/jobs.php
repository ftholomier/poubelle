<?php
/**
 * Liste d'offres : en-tête sombre, barre de filtres, deux colonnes.
 * Les filtres passent par GET et fonctionnent sans JavaScript.
 *
 * @var array $results  @var array $facets  @var array $criteria  @var array $query  @var string $newest
 */
use App\Core\Config;
use App\Core\View;
use App\Services\I18n;
use App\Support\Icon;

$infeed = (int) Config::get('search.infeed_every', 6);
$checked = static fn(array $list, string $value): bool => in_array($value, $list, true);
?>
<div class="container">
  <div class="page-head" data-reveal>
    <h1 class="h1-sub"><?= e(I18n::t('jobs.title', number_format((int) ($facets['total'] ?? 0), 0, ',', ' '))) ?></h1>
    <p><?= e(I18n::t('jobs.freshness', time_ago($newest))) ?></p>
  </div>

  <form class="filter-bar" method="get" role="search">
    <div class="fb-field">
      <?= Icon::svg('search', 18, '#6B6590') ?>
      <label class="visually-hidden" for="f-q"><?= e(I18n::t('search.keyword')) ?></label>
      <input id="f-q" type="search" name="q" value="<?= e($criteria['q']) ?>" placeholder="<?= e(I18n::t('search.keyword')) ?>">
    </div>
    <div class="fb-field">
      <?= Icon::svg('pin', 18, '#6B6590') ?>
      <label class="visually-hidden" for="f-city"><?= e(I18n::t('search.city')) ?></label>
      <input id="f-city" type="search" name="city" data-places value="<?= e($criteria['city']) ?>" placeholder="<?= e(I18n::t('search.city')) ?>">
    </div>
    <?php foreach ($criteria['category'] as $value): ?>
      <input type="hidden" name="category[]" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <?php foreach ($criteria['contract'] as $value): ?>
      <input type="hidden" name="contract[]" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <button type="submit" class="btn btn-coral"><?= e(I18n::t('search.filter')) ?></button>
  </form>

  <div class="layout-list">
    <aside class="filters">
      <form class="card filter-card" method="get" data-autosubmit>
        <?php if ($criteria['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($criteria['q']) ?>"><?php endif; ?>
        <?php if ($criteria['city'] !== ''): ?><input type="hidden" name="city" value="<?= e($criteria['city']) ?>"><?php endif; ?>

        <h3><?= e(I18n::t('search.family')) ?></h3>
        <div class="filter-list">
          <?php foreach (array_slice((array) ($facets['categories'] ?? []), 0, 8, true) as $name => $count): ?>
            <label class="check">
              <input type="checkbox" name="category[]" value="<?= e((string) $name) ?>"
                     <?= $checked($criteria['category'], (string) $name) ? 'checked' : '' ?>>
              <span><?= e((string) $name) ?></span>
              <span class="n"><?= (int) $count ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <h3 style="margin-top:20px"><?= e(I18n::t('search.contract')) ?></h3>
        <div class="tag-row">
          <?php foreach ((array) ($facets['contracts'] ?? []) as $name => $count): ?>
            <label class="chip" style="cursor:pointer">
              <input type="checkbox" class="visually-hidden" name="contract[]" value="<?= e((string) $name) ?>"
                     <?= $checked($criteria['contract'], (string) $name) ? 'checked' : '' ?>>
              <?= e((string) $name) ?> <span class="n"><?= (int) $count ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <h3 style="margin-top:20px"><?= e(I18n::t('search.region')) ?></h3>
        <div class="filter-list">
          <?php foreach (array_slice((array) ($facets['regions'] ?? []), 0, 6, true) as $name => $count): ?>
            <label class="check">
              <input type="checkbox" name="region[]" value="<?= e((string) $name) ?>"
                     <?= $checked($criteria['region'], (string) $name) ? 'checked' : '' ?>>
              <span><?= e((string) $name) ?></span>
              <span class="n"><?= (int) $count ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap">
          <button type="submit" class="btn btn-ink btn-sm" data-filter-submit><?= e(I18n::t('search.filter')) ?></button>
          <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('search.reset')) ?></a>
        </div>
      </form>

      <?= View::partial('partials/ad', ['slot' => 'list_side']) ?>
    </aside>

    <div>
      <div class="result-head">
        <span class="result-count"><?= e(I18n::t('search.results', number_format((int) $results['total'], 0, ',', ' '))) ?></span>
        <form method="get">
          <?php foreach ($query as $key => $value): if ($key === 'sort') continue; ?>
            <?php foreach ((array) $value as $one): ?>
              <input type="hidden" name="<?= e($key) ?><?= is_array($value) ? '[]' : '' ?>" value="<?= e((string) $one) ?>">
            <?php endforeach; ?>
          <?php endforeach; ?>
          <label class="visually-hidden" for="sort"><?= e(I18n::t('search.sort')) ?></label>
          <select id="sort" name="sort" class="select" style="width:auto;padding:9px 13px;border-width:1.5px;border-radius:999px;font-size:13.5px"
                  onchange="this.form.submit()">
            <option value="recent" <?= $criteria['sort'] === 'recent' ? 'selected' : '' ?>><?= e(I18n::t('search.sort_recent')) ?></option>
            <option value="oldest" <?= $criteria['sort'] === 'oldest' ? 'selected' : '' ?>><?= e(I18n::t('search.sort_oldest')) ?></option>
            <option value="title"  <?= $criteria['sort'] === 'title'  ? 'selected' : '' ?>><?= e(I18n::t('search.sort_title')) ?></option>
          </select>
        </form>
      </div>

      <?php if ($results['items'] === []): ?>
        <div class="card empty">
          <h3><?= e(I18n::t('search.none')) ?></h3>
          <p><?= e(I18n::t('search.none_note')) ?></p>
          <a class="btn btn-coral" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('search.reset')) ?></a>
        </div>
      <?php else: ?>
        <?php if (($external ?? 0) > 0): ?>
          <p class="external-note"><?= Icon::svg('globe', 15, '#6B6590', 2) ?>
            <?= e(I18n::t('jobs.external_note', (int) $external)) ?></p>
        <?php endif; ?>
        <div class="result-list">
          <?php foreach ($results['items'] as $i => $job): ?>
            <?= View::partial('partials/job-row', ['job' => $job]) ?>
            <?php if (($i + 1) % $infeed === 0 && $i + 1 < count($results['items'])): ?>
              <?= View::partial('partials/ad', ['slot' => 'list_infeed']) ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php if (count($results['items']) >= $infeed): ?>
          <?= View::partial('partials/ad', ['slot' => 'list_infeed']) ?>
        <?php endif; ?>
      <?php endif; ?>

      <?= View::partial('partials/pagination', ['results' => $results, 'query' => $query]) ?>
    </div>
  </div>
</div>
