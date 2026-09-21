<?php
/** Profil public. @var array $cv @var array $related */
use App\Core\View;
use App\Services\I18n;
use App\Support\Icon;

$color = tile_color((string) $cv['name']);
$hasFile = ($cv['file']['path'] ?? '') !== '';
?>
<div class="container">
  <a class="back-link" href="<?= e(I18n::url('/cv')) ?>"><?= Icon::svg('arrow-l', 16) ?><?= e(I18n::t('cv.back')) ?></a>

  <header class="profile-head" data-reveal>
    <span class="tile tile-92" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
      <?= e(initials((string) $cv['name'])) ?>
    </span>
    <div style="min-width:0">
      <span class="tag <?= !empty($cv['available']) ? 'tag-teal' : 'tag-soft' ?>">
        <span class="dot"></span><?= e(!empty($cv['available']) ? I18n::t('cv.available') : I18n::t('cv.unavailable')) ?>
      </span>
      <h1 style="margin-top:12px"><?= e($cv['name']) ?></h1>
      <p class="role">
        <?= e($cv['title'] ?: '—') ?>
        <?php if (($cv['location']['city'] ?? '') !== ''): ?> · <?= e($cv['location']['city']) ?><?php endif; ?>
        <?php if ((int) $cv['experience_years'] > 0): ?> · <?= e(I18n::t('cv.years', (int) $cv['experience_years'])) ?><?php endif; ?>
      </p>
    </div>
    <div class="profile-actions">
      <?php if (($cv['contact']['email'] ?? '') !== '' && !empty($cv['contact']['public'])): ?>
        <a class="btn btn-coral" href="mailto:<?= e($cv['contact']['email']) ?>"><?= e(I18n::t('cv.contact')) ?></a>
      <?php else: ?>
        <a class="btn btn-coral" href="<?= e(I18n::url('/deposer-une-annonce')) ?>"><?= e(I18n::t('cv.contact')) ?></a>
      <?php endif; ?>
      <?php if ($hasFile): ?>
        <a class="btn btn-ghost-light" href="/media/cv/<?= e($cv['id']) ?>" rel="nofollow">
          <?= Icon::svg('download', 16, '#fff', 2) ?><?= e(I18n::t('cv.download')) ?>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div class="layout-detail">
    <div class="detail-main">
      <?php if (trim((string) $cv['summary']) !== ''): ?>
        <div class="card card-lg" data-reveal>
          <h2><?= e(I18n::t('cv.summary')) ?></h2>
          <div class="prose" style="margin-top:12px">
            <?php foreach (preg_split('/\n\s*\n/', (string) $cv['summary']) ?: [] as $paragraph): ?>
              <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($cv['experiences'])): ?>
        <div class="card card-lg" style="margin-top:18px" data-reveal>
          <h2><?= e(I18n::t('cv.experiences')) ?></h2>
          <div class="timeline" style="margin-top:12px">
            <?php foreach ((array) $cv['experiences'] as $row): ?>
              <div class="timeline-row">
                <span class="period"><?= e($row['period'] ?: '—') ?></span>
                <span>
                  <span class="role"><?= e($row['role'] ?: '—') ?></span>
                  <?php if (($row['employer'] ?? '') !== ''): ?>
                    <span class="org"><?= e($row['employer']) ?></span>
                  <?php endif; ?>
                  <?php if (($row['notes'] ?? '') !== ''): ?>
                    <span class="notes"><?= nl2br(e((string) $row['notes'])) ?></span>
                  <?php endif; ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($cv['education'])): ?>
        <div class="card card-lg" style="margin-top:18px" data-reveal>
          <h2><?= e(I18n::t('cv.education')) ?></h2>
          <div class="timeline" style="margin-top:12px">
            <?php foreach ((array) $cv['education'] as $row): ?>
              <div class="timeline-row">
                <span class="period"><?= e($row['period'] ?: '—') ?></span>
                <span>
                  <span class="role"><?= e($row['degree'] ?: '—') ?></span>
                  <?php if (($row['school'] ?? '') !== ''): ?><span class="org"><?= e($row['school']) ?></span><?php endif; ?>
                  <?php if (($row['notes'] ?? '') !== ''): ?><span class="notes"><?= e((string) $row['notes']) ?></span><?php endif; ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <aside class="detail-aside">
      <?php if (!empty($cv['skills'])): ?>
        <div class="card" data-reveal>
          <h3><?= e(I18n::t('cv.skills')) ?></h3>
          <div class="tag-row" style="margin-top:12px">
            <?php foreach ((array) $cv['skills'] as $skill): ?>
              <a class="tag tag-soft" href="<?= e(I18n::url('/cv')) ?>?skill[]=<?= e(urlencode((string) $skill)) ?>">
                <?= e(str_excerpt((string) $skill, 30)) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($cv['links'])): ?>
        <div class="card" data-reveal>
          <h3><?= e(I18n::t('cv.links')) ?></h3>
          <ul style="list-style:none;margin:12px 0 0;padding:0;display:flex;flex-direction:column;gap:8px">
            <?php foreach ((array) $cv['links'] as $link): ?>
              <li><a href="<?= e($link['url']) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= e($link['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!$hasFile && ($cv['file']['legacy_url'] ?? '') !== ''): ?>
        <div class="card" data-reveal>
          <p class="notice notice-wait" style="margin:0"><?= e(I18n::t('cv.file_missing')) ?></p>
        </div>
      <?php endif; ?>

      <div class="card card-yellow" data-reveal>
        <h3><?= e(I18n::t('cv.you_too')) ?></h3>
        <p style="font-size:14px;margin:10px 0 16px"><?= e(I18n::t('cv.you_too_note')) ?></p>
        <a class="btn btn-ink btn-sm btn-block" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('cta.post_cv')) ?></a>
      </div>

      <?= View::partial('partials/ad', ['slot' => 'profile_side']) ?>
    </aside>
  </div>

  <?php if ($related !== []): ?>
    <section class="section">
      <div class="section-head"><h2><?= e(I18n::t('home.profiles')) ?></h2></div>
      <div class="grid-cv">
        <?php foreach ($related as $row): ?><?= View::partial('partials/cv-card', ['cv' => $row]) ?><?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
