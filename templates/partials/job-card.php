<?php
/**
 * Carte d'offre (grille). @var array $job ligne d'index
 */
use App\Services\I18n;

$color = tile_color((string) ($job['company'] ?: $job['title']));
?>
<a class="card card-link job-card" href="<?= e(I18n::url('/offre/' . $job['slug'])) ?>" data-reveal>
  <div class="job-top">
    <span class="tile tile-40" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
      <?= e(initials((string) ($job['company'] ?: $job['title']), 1)) ?>
    </span>
    <span>
      <span class="company"><?= e($job['company'] ?: '—') ?></span><br>
      <span class="age"><?= e(time_ago((string) $job['published_at'])) ?></span>
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
