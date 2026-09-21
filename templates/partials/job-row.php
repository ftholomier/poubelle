<?php
/**
 * Ligne d'offre (page liste). Gère les deux origines :
 * une annonce déposée ici pointe vers sa page, une offre externe vers le site
 * d'origine, en nouvel onglet et sans transmettre de référent exploitable.
 *
 * @var array $job ligne d'index ou offre externe normalisée
 */
use App\Core\View;
use App\Services\I18n;

$external = !empty($job['external']);
$color = tile_color((string) ($job['company'] ?: $job['title']));
$href = $external ? (string) $job['url'] : I18n::url('/offre/' . $job['slug']);
$attrs = $external ? ' target="_blank" rel="nofollow noopener noreferrer"' : '';
?>
<a class="card card-link job-row<?= $external ? ' is-external' : '' ?>"
   href="<?= e($href) ?>"<?= $attrs ?> data-reveal>
  <span class="<?= !empty($job['logo_id']) ? 'tile-logo' : '' ?>">
    <?= View::partial('partials/avatar', [
          'name' => (string) ($job['company'] ?: $job['title']), 'size' => 'tile-48',
          'kind' => 'logo', 'id' => (string) ($job['logo_id'] ?? ''), 'chars' => 1,
        ]) ?>
  </span>

  <span class="job-body">
    <span class="job-title"><?= e(str_excerpt((string) $job['title'], 90)) ?></span>
    <span class="job-sub">
      <?= e($job['company'] ?: '—') ?><?= $job['city'] !== '' ? ' · ' . e($job['city']) : '' ?>
      <?php if (!empty($job['remote'])): ?> · <?= e(I18n::t('jobs.remote')) ?><?php endif; ?>
    </span>
    <span class="tag-row">
      <?php if ($external): ?>
        <span class="tag tag-external">
          <?= e(I18n::t('jobs.via', (string) $job['source'])) ?>
        </span>
      <?php endif; ?>
      <?php foreach (array_slice((array) $job['contract'], 0, 2) as $contract): ?>
        <span class="tag tag-soft"><?= e($contract) ?></span>
      <?php endforeach; ?>
      <?php foreach (array_slice((array) $job['tags'], 0, $external ? 2 : 3) as $tag): ?>
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
