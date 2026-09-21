<?php
/**
 * Ligne d'offre (page liste). @var array $job ligne d'index
 */
use App\Services\I18n;

$color = tile_color((string) ($job['company'] ?: $job['title']));
?>
<a class="card card-link job-row" href="<?= e(I18n::url('/offre/' . $job['slug'])) ?>" data-reveal>
  <span class="tile tile-48" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
    <?= e(initials((string) ($job['company'] ?: $job['title']), 1)) ?>
  </span>

  <span class="job-body">
    <span class="job-title"><?= e(str_excerpt((string) $job['title'], 90)) ?></span>
    <span class="job-sub">
      <?= e($job['company'] ?: '—') ?><?= $job['city'] !== '' ? ' · ' . e($job['city']) : '' ?>
      <?php if (!empty($job['remote'])): ?> · <?= e(I18n::t('jobs.remote')) ?><?php endif; ?>
    </span>
    <span class="tag-row">
      <?php foreach (array_slice((array) $job['contract'], 0, 2) as $contract): ?>
        <span class="tag tag-soft"><?= e($contract) ?></span>
      <?php endforeach; ?>
      <?php foreach (array_slice((array) $job['tags'], 0, 3) as $tag): ?>
        <span class="tag"><?= e($tag) ?></span>
      <?php endforeach; ?>
      <?php if (!empty($job['filled'])): ?><span class="tag tag-soft"><?= e(I18n::t('jobs.filled')) ?></span><?php endif; ?>
    </span>
  </span>

  <span class="job-aside">
    <span class="job-pay"><?= e($job['salary'] ?: I18n::t('job.no_salary')) ?></span>
    <span class="meta"><?= e(time_ago((string) $job['published_at'])) ?></span>
  </span>
</a>
