<?php
/**
 * Liste d'offres : en-tête sombre, barre de filtres, deux colonnes.
 * Les filtres passent par GET et fonctionnent sans JavaScript.
 *
 * @var array $results  @var array $facets  @var array $criteria  @var array $query
 * @var string $newest  @var array $partners
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
    <?php foreach ($criteria['source'] as $value): ?>
      <input type="hidden" name="source[]" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <?php foreach ($criteria['contract'] as $value): ?>
      <input type="hidden" name="contract[]" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <button type="submit" class="btn btn-coral"><?= e(I18n::t('search.filter')) ?></button>
  </form>

  <div class="layout-list">
    <aside class="filters">
      <form class="card filter-card" method="get" data-autosubmit-form>
        <?php if ($criteria['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($criteria['q']) ?>"><?php endif; ?>
        <?php if ($criteria['city'] !== ''): ?><input type="hidden" name="city" value="<?= e($criteria['city']) ?>"><?php endif; ?>

        <?php if ($partners !== []): ?>
          <h2 class="filter-title"><?= e(I18n::t('search.origin')) ?></h2>
          <div class="filter-list">
            <label class="check">
              <input type="checkbox" name="source[]" value="site"
                     <?= $checked($criteria['source'], 'site') ? 'checked' : '' ?>>
              <span><?= e(I18n::t('search.origin_site')) ?></span>
              <span class="n"><?= (int) ($facets['total'] ?? 0) ?></span>
            </label>
            <?php foreach ($partners as $key => $nom): ?>
              <label class="check">
                <input type="checkbox" name="source[]" value="<?= e((string) $key) ?>"
                       <?= $checked($criteria['source'], (string) $key) ? 'checked' : '' ?>>
                <span><?= e($nom) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <h2 class="filter-title"<?= $partners !== [] ? ' style="margin-top:20px"' : '' ?>><?= e(I18n::t('search.family')) ?></h2>
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

        <h2 class="filter-title" style="margin-top:20px"><?= e(I18n::t('search.contract')) ?></h2>
        <div class="tag-row">
          <?php foreach ((array) ($facets['contracts'] ?? []) as $name => $count): ?>
            <label class="chip" style="cursor:pointer">
              <input type="checkbox" class="visually-hidden" name="contract[]" value="<?= e((string) $name) ?>"
                     <?= $checked($criteria['contract'], (string) $name) ? 'checked' : '' ?>>
              <?= e((string) $name) ?> <span class="n"><?= (int) $count ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <h2 class="filter-title" style="margin-top:20px"><?= e(I18n::t('search.region')) ?></h2>
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
      <?php // Restitution des offres mises de côté : le bouton existait, la
            // page qui les montre manquait. Tout est côté navigateur. ?>
      <section class="card saved-box" data-saved-list hidden
               data-remove-label="<?= e(I18n::t('saved.remove')) ?>">
        <h2 class="h3"><?= e(I18n::t('saved.title')) ?></h2>
        <ul class="saved-items" data-saved-items></ul>
      </section>

      <div class="result-head">
        <span class="result-count">
          <?= e(I18n::t('search.results', number_format((int) $results['total'], 0, ',', ' '))) ?>
          <?php if (($external ?? 0) > 0): ?>
            <span class="s"><?= e(I18n::t('search.breakdown',
              number_format((int) ($results['local_total'] ?? $results['total']), 0, ',', ' '),
              number_format((int) $external, 0, ',', ' '))) ?></span>
          <?php endif; ?>
        </span>
        <form method="get">
          <?php foreach ($query as $key => $value): if ($key === 'sort') continue; ?>
            <?php foreach ((array) $value as $one): ?>
              <input type="hidden" name="<?= e($key) ?><?= is_array($value) ? '[]' : '' ?>" value="<?= e((string) $one) ?>">
            <?php endforeach; ?>
          <?php endforeach; ?>
          <label class="visually-hidden" for="sort"><?= e(I18n::t('search.sort')) ?></label>
          <?php // Le tri s'envoie par son bouton ; le script le déclenche au
                // changement et masque alors le bouton. Un gestionnaire en
                // attribut serait refusé par la politique de sécurité. ?>
          <select id="sort" name="sort" class="select" data-autosubmit-field
                  style="width:auto;padding:9px 13px;border-width:1.5px;border-radius:999px;font-size:13.5px">
            <option value="relevance"<?= ($criteria['sort'] ?? '') === 'relevance' ? ' selected' : '' ?>><?= e(I18n::t('search.sort_relevance')) ?></option>
            <option value="recent" <?= $criteria['sort'] === 'recent' ? 'selected' : '' ?>><?= e(I18n::t('search.sort_recent')) ?></option>
            <option value="oldest" <?= $criteria['sort'] === 'oldest' ? 'selected' : '' ?>><?= e(I18n::t('search.sort_oldest')) ?></option>
            <option value="title"  <?= $criteria['sort'] === 'title'  ? 'selected' : '' ?>><?= e(I18n::t('search.sort_title')) ?></option>
          </select>
          <button type="submit" class="btn btn-ghost btn-sm" data-autosubmit-go>
            <?= e(I18n::t('search.sort')) ?>
          </button>
        </form>
      </div>

      <?php if ($results['items'] === []): ?>
        <div class="card empty">
          <h2 class="filter-title"><?= e(I18n::t('search.none')) ?></h2>
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
