<?php
/**
 * Détail d'une offre.
 *
 * @var array      $job       fiche complète
 * @var array|null $employer  fiche employeur si connue
 * @var array      $siblings  autres offres du même employeur
 */
use App\Core\View;
use App\Services\I18n;
use App\Support\Icon;

$color = tile_color((string) ($job['company']['name'] ?: $job['title']));
$isGuso = (bool) array_filter((array) $job['contract'],
    static fn(string $c) => stripos($c, 'guso') !== false || stripos($c, 'usage') !== false);
$applyHref = $job['apply']['url'] !== ''
    ? $job['apply']['url']
    : ($job['apply']['email'] !== ''
        ? 'mailto:' . $job['apply']['email'] . '?subject=' . rawurlencode('Candidature — ' . $job['title'])
        : '');
?>
<div class="container">
  <a class="back-link" href="<?= e(I18n::url('/offres')) ?>"><?= Icon::svg('arrow-l', 16) ?><?= e(I18n::t('job.back')) ?></a>

  <div class="layout-detail">
    <article class="detail-main">
      <div class="card card-lg" data-reveal>
        <?php if ($job['status'] !== 'publish'): ?>
          <div class="notice notice-wait"><?= e(I18n::t('jobs.expired')) ?></div>
        <?php endif; ?>

        <header class="detail-head">
          <span class="tile tile-60" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
            <?= e(initials((string) ($job['company']['name'] ?: $job['title']), 1)) ?>
          </span>
          <div style="min-width:0">
            <div class="company"><?= e($job['company']['name'] ?: '—') ?></div>
            <h1 class="h1-sub"><?= e($job['title']) ?></h1>
          </div>
        </header>

        <div class="meta-row">
          <span class="tag tag-ink"><?= Icon::svg('euro', 14, '#fff', 2) ?><?= e($job['salary'] ?: I18n::t('job.no_salary')) ?></span>
          <?php if (($job['location']['city'] ?? '') !== '' || ($job['location']['region'] ?? '') !== ''): ?>
            <span class="tag tag-soft"><?= Icon::svg('pin', 14, '#4A4470', 2) ?>
              <?= e($job['location']['city'] ?: $job['location']['region']) ?></span>
          <?php endif; ?>
          <?php foreach ((array) $job['contract'] as $contract): ?>
            <span class="tag tag-soft"><?= e($contract) ?></span>
          <?php endforeach; ?>
          <?php if ($isGuso): ?><span class="tag tag-teal">GUSO</span><?php endif; ?>
          <?php if (!empty($job['location']['remote'])): ?>
            <span class="tag tag-soft"><?= e(I18n::t('jobs.remote')) ?></span>
          <?php endif; ?>
          <?php if (($job['starts_at'] ?? '') !== ''): ?>
            <span class="tag tag-soft"><?= Icon::svg('calendar', 14, '#4A4470', 2) ?>
              <?= e(I18n::t('job.starts')) ?> <?= e(date('d/m/Y', (int) strtotime((string) $job['starts_at']))) ?></span>
          <?php endif; ?>
          <span class="tag tag-soft"><?= Icon::svg('clock', 14, '#4A4470', 2) ?>
            <?= e(I18n::t('job.published', time_ago((string) ($job['published_at'] ?: $job['created_at'])))) ?></span>
        </div>

        <div class="prose">
          <h2><?= e(I18n::t('job.post')) ?></h2>
          <?php foreach (preg_split('/\n\s*\n/', (string) $job['description']) ?: [] as $paragraph): ?>
            <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
          <?php endforeach; ?>

          <?php if (!empty($job['requirements'])): ?>
            <h2><?= e(I18n::t('job.profile')) ?></h2>
            <ul>
              <?php foreach ((array) $job['requirements'] as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (trim((string) $job['conditions']) !== ''): ?>
            <h2><?= e(I18n::t('job.conditions')) ?></h2>
            <?php foreach (preg_split('/\n\s*\n/', (string) $job['conditions']) ?: [] as $paragraph): ?>
              <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php if (!empty($job['tags'])): ?>
            <div class="tag-row" style="margin-top:22px">
              <?php foreach ((array) $job['tags'] as $tag): ?><span class="tag"><?= e($tag) ?></span><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?= View::partial('partials/ad', ['slot' => 'job_below']) ?>
    </article>

    <aside class="detail-aside">
      <div class="card card-dark" data-reveal>
        <h3><?= e(I18n::t('job.apply_title')) ?></h3>
        <p style="color:rgba(255,255,255,.72);font-size:14px;margin:10px 0 18px">
          <?= e($applyHref !== '' ? I18n::t('job.apply_direct') : I18n::t('job.apply_none')) ?>
        </p>
        <?php if ($applyHref !== ''): ?>
          <a class="btn btn-coral btn-block" href="<?= e($applyHref) ?>"
             <?= str_starts_with($applyHref, 'http') ? 'rel="noopener noreferrer" target="_blank"' : '' ?>>
            <?= e(I18n::t('job.apply')) ?>
          </a>
        <?php endif; ?>
        <button type="button" class="btn btn-ghost-light btn-block" style="margin-top:9px"
                data-bookmark="<?= e($job['id']) ?>"><?= e(I18n::t('job.save')) ?></button>
      </div>

      <?php if ($employer !== null): ?>
        <div class="card" data-reveal>
          <h3><?= e(I18n::t('job.employer')) ?></h3>
          <div class="employer-card" style="margin-top:14px">
            <span class="tile tile-48" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
              <?= e(initials((string) $employer['name'], 2)) ?>
            </span>
            <span style="min-width:0">
              <span class="name"><?= e($employer['name']) ?></span><br>
              <span class="sub"><?= e($employer['location']['city'] ?: '—') ?></span>
            </span>
          </div>
          <?php if (trim((string) $employer['description']) !== ''): ?>
            <p style="font-size:14px;color:var(--text);margin:14px 0 0"><?= e(str_excerpt((string) $employer['description'], 180)) ?></p>
          <?php endif; ?>
          <a class="btn btn-ghost btn-sm btn-block" style="margin-top:16px"
             href="<?= e(I18n::url('/employeur/' . $employer['slug'])) ?>">
            <?= e(I18n::t('job.employer_jobs')) ?><?= count($siblings) > 0 ? ' (' . count($siblings) . ')' : '' ?>
          </a>
        </div>
      <?php endif; ?>

      <div class="card card-yellow" data-reveal>
        <h3><?= Icon::svg('robot', 19, '#17123A', 2) ?> <?= e(I18n::t('job.ask_regie')) ?></h3>
        <p style="font-size:14px;color:#17123A;margin:10px 0 0"><?= e(I18n::t('job.ask_regie_note')) ?></p>
      </div>
    </aside>
  </div>

  <?php if ($siblings !== []): ?>
    <section class="section">
      <div class="section-head"><h2><?= e(I18n::t('job.employer_jobs')) ?></h2></div>
      <div class="grid-jobs">
        <?php foreach ($siblings as $sibling): ?><?= View::partial('partials/job-card', ['job' => $sibling]) ?><?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
