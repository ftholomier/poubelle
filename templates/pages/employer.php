<?php
/** Fiche employeur. @var array $employer @var array $jobs */
use App\Core\View;
use App\Services\I18n;
use App\Support\Icon;

$color = tile_color((string) $employer['name']);
$live = array_values(array_filter($jobs, static fn(array $j) => $j['status'] === 'publish'));
$past = array_values(array_filter($jobs, static fn(array $j) => $j['status'] !== 'publish'));
?>
<div class="container">
  <a class="back-link" href="<?= e(I18n::url('/employeurs')) ?>"><?= Icon::svg('arrow-l', 16) ?><?= e(I18n::t('nav.employers')) ?></a>

  <header class="profile-head" data-reveal>
    <span class="tile tile-92" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
      <?= e(initials((string) $employer['name'], 2)) ?>
    </span>
    <div style="min-width:0">
      <span class="tag <?= count($live) > 0 ? 'tag-teal' : 'tag-soft' ?>">
        <?= count($live) > 0 ? e(I18n::t('employers.jobs', count($live))) : e(I18n::t('employers.none')) ?>
      </span>
      <h1 style="margin-top:12px"><?= e($employer['name']) ?></h1>
      <p class="role">
        <?= e($employer['kind'] ?: '—') ?>
        <?php if (($employer['location']['city'] ?? '') !== ''): ?> · <?= e($employer['location']['city']) ?><?php endif; ?>
      </p>
    </div>
    <?php if (($employer['website'] ?? '') !== ''): ?>
      <div class="profile-actions">
        <a class="btn btn-ghost-light" href="<?= e($employer['website']) ?>" rel="noopener noreferrer nofollow" target="_blank">
          <?= e(I18n::t('employers.visit')) ?>
        </a>
      </div>
    <?php endif; ?>
  </header>

  <?php if (trim((string) $employer['description']) !== ''): ?>
    <div class="card card-lg" style="margin-top:22px" data-reveal>
      <div class="prose">
        <?php foreach (preg_split('/\n\s*\n/', (string) $employer['description']) ?: [] as $paragraph): ?>
          <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($live !== []): ?>
    <section class="section">
      <div class="section-head"><h2><?= e(I18n::t('nav.jobs')) ?></h2></div>
      <div class="result-list">
        <?php foreach ($live as $job): ?><?= View::partial('partials/job-row', ['job' => $job]) ?><?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($past !== []): ?>
    <section class="section">
      <div class="section-head"><h2><?= e(I18n::t('jobs.expired')) ?></h2></div>
      <div class="grid-jobs">
        <?php foreach (array_slice($past, 0, 6) as $job): ?>
          <?= View::partial('partials/job-card', ['job' => $job]) ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?= View::partial('partials/ad', ['slot' => 'dir_bottom']) ?>
</div>
