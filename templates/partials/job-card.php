<?php
/**
 * Carte d'offre (grille). @var array $job ligne d'index
 */
use App\Core\View;
use App\Services\I18n;

$color = tile_color((string) ($job['company'] ?: $job['title']));
?>
<a class="card card-link job-card" href="<?= e(I18n::url('/offre/' . $job['slug'])) ?>" data-reveal>
  <div class="job-top">
    <span class="<?= !empty($job['logo_id']) ? 'tile-logo' : '' ?>">
      <?= View::partial('partials/avatar', [
            'name' => (string) ($job['company'] ?: $job['title']), 'size' => 'tile-40',
            'kind' => 'logo', 'id' => (string) ($job['logo_id'] ?? ''), 'chars' => 1,
          ]) ?>
    </span>
    <span>
      <span class="company"><?= e($job['company'] ?: '—') ?></span>
      <span class="age">
        <?= ($job['company_tagline'] ?? '') !== ''
              ? e(str_excerpt((string) $job['company_tagline'], 44))
              : e(time_ago((string) $job['published_at'])) ?>
      </span>
    </span>
  </div>

  <span class="job-title"><?= e(str_excerpt((string) $job['title'], 78)) ?></span>

  <?php if (!empty($job['tags'])): ?>
    <span class="tag-row">
      <?php foreach (array_slice((array) $job['tags'], 0, 3) as $tag): ?>
        <span class="tag"><?= e($tag) ?></span>
      <?php endforeach; ?>
    </span>
  <?php endif; ?>

  <span class="job-foot">
    <span><?= e($job['city'] ?: ($job['region'] ?: '—')) ?></span>
    <span><?= e($job['salary'] ?: I18n::t('job.no_salary')) ?></span>
  </span>
</a>
