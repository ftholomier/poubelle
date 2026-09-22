<?php
/** Annuaire de CV. @var array $results @var array $facets @var array $criteria @var array $query */
use App\Core\Config;
use App\Core\View;
use App\Services\I18n;
use App\Support\Icon;

$infeed = max(2, (int) Config::get('search.infeed_every', 6));
?>
<div class="container">
  <div class="page-head" data-reveal>
    <h1 class="h1-sub"><?= e(I18n::t('cv.title', number_format((int) ($facets['total'] ?? 0), 0, ',', ' '))) ?></h1>
    <p><?= e(I18n::t('home.profiles_note')) ?></p>
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
    <button type="submit" class="btn btn-coral"><?= e(I18n::t('search.filter')) ?></button>
  </form>

  <?php if (!empty($facets['skills'])): ?>
    <div class="tag-row" style="margin-top:18px" data-reveal>
      <?php foreach (array_slice((array) $facets['skills'], 0, 14, true) as $skill => $count): ?>
        <a class="chip" href="?skill[]=<?= e(urlencode((string) $skill)) ?>"><?= e(str_excerpt((string) $skill, 28)) ?></a>
      <?php endforeach; ?>
      <?php if ($criteria['skill'] !== []): ?>
        <a class="chip" style="border-color:var(--coral);color:var(--coral)" href="<?= e(I18n::url('/cv')) ?>">
          <?= e(I18n::t('search.reset')) ?>
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="result-head" style="margin-top:26px">
    <span class="result-count"><?= e(I18n::t('search.results', number_format((int) $results['total'], 0, ',', ' '))) ?></span>
  </div>

  <?php if ($results['items'] === []): ?>
    <div class="card empty">
      <h2 class="filter-title"><?= e(I18n::t('search.none')) ?></h2>
      <p><?= e(I18n::t('search.none_note')) ?></p>
      <a class="btn btn-coral" href="<?= e(I18n::url('/cv')) ?>"><?= e(I18n::t('search.reset')) ?></a>
    </div>
  <?php else: ?>
    <div class="grid-cv">
      <?php foreach ($results['items'] as $i => $cv): ?>
        <?= View::partial('partials/cv-card', ['cv' => $cv]) ?>
        <?php // Jamais en dernière position : une annonce qui ferme la grille
              // se confond avec une fiche, ce que Google interdit. ?>
        <?php if (($i + 1) % $infeed === 0 && $i + 1 < count($results['items'])): ?>
          <?= View::partial('partials/ad', ['slot' => 'cv_infeed']) ?>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?= View::partial('partials/ad', ['slot' => 'cv_bottom']) ?>

  <?= View::partial('partials/pagination', ['results' => $results, 'query' => $query]) ?>

  <div class="cta-banner" data-reveal style="margin-top:56px">
    <h2><?= e(I18n::t('cv.you_too')) ?></h2>
    <p><?= e(I18n::t('cv.you_too_note')) ?></p>
    <div class="row"><a class="btn btn-coral btn-lg" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('cta.post_cv')) ?></a></div>
  </div>
</div>
