<?php
/** Profil public. @var array $cv @var array $related */
use App\Core\Csrf;
use App\Core\View;
use App\Services\I18n;
use App\Services\SpamGuard;
use App\Support\Icon;

$hasFile = ($cv['file']['path'] ?? '') !== '';
$hasPhoto = ($cv['photo']['path'] ?? '') !== '';
$reachable = $reachable ?? false;
$showForm = $showForm ?? false;
$errors = $errors ?? [];
$sent = $sent ?? false;
?>
<div class="container">
  <a class="back-link" href="<?= e(I18n::url('/cv')) ?>"><?= Icon::svg('arrow-l', 16) ?><?= e(I18n::t('cv.back')) ?></a>

  <header class="profile-head" data-reveal>
    <?= View::partial('partials/avatar', [
          'name' => (string) $cv['name'], 'size' => 'tile-92', 'kind' => 'photo',
          'id' => $hasPhoto ? (string) $cv['id'] : '', 'chars' => 2,
        ]) ?>
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
      <?php if ($reachable): ?>
        <a class="btn btn-coral" href="#contacter"><?= e(I18n::t('cv.contact')) ?></a>
      <?php endif; ?>
      <?php if ($hasFile): ?>
        <?php // Un téléchargement s'ouvre à côté : la fiche reste sous les yeux. ?>
        <a class="btn btn-ghost-light" href="/media/cv/<?= e($cv['id']) ?>"
           rel="nofollow noopener" target="_blank">
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
          <h2 class="card-title"><?= e(I18n::t('cv.skills')) ?></h2>
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
          <h2 class="card-title"><?= e(I18n::t('cv.links')) ?></h2>
          <ul style="list-style:none;margin:12px 0 0;padding:0;display:flex;flex-direction:column;gap:8px">
            <?php foreach ((array) $cv['links'] as $link): ?>
              <li><a href="<?= e($link['url']) ?>" rel="noopener noreferrer nofollow" target="_blank"><?= e($link['name']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!$hasFile): ?>
        <div class="card" data-reveal>
          <p class="notice notice-wait" style="margin:0"><?= e(I18n::t('cv.no_file')) ?></p>
        </div>
      <?php endif; ?>

      <div class="card" id="contacter" data-reveal>
        <h2 class="card-title"><?= e(I18n::t('cv.contact_title')) ?></h2>

        <?php if ($sent): ?>
          <p class="notice notice-ok" style="margin:12px 0 0"><?= e(I18n::t('cv.contact_done')) ?></p>
        <?php elseif (!$reachable): ?>
          <p class="secret-help" style="margin:12px 0 0"><?= e(I18n::t('cv.closed_note')) ?></p>
        <?php else: ?>
          <p class="secret-help" style="margin:12px 0 14px"><?= e(I18n::t('cv.contact_intro')) ?></p>

          <?php if (!empty($errors['_form'])): ?>
            <p class="notice notice-err" role="alert" tabindex="-1" data-error-focus>
              <?= e((string) $errors['_form']) ?>
            </p>
          <?php endif; ?>

          <?php if (!$showForm): ?>
            <a class="btn btn-coral btn-block" data-form-reveal="contact"
               aria-controls="contact-form" aria-expanded="false"
               href="<?= e(I18n::url('/cv/' . $cv['slug'])) ?>?contacter=1#contacter">
              <?= e(I18n::t('cv.contact')) ?>
            </a>
          <?php endif; ?>

          <form method="post" class="apply-form" id="contact-form"
                data-token-form="contact" <?= $showForm ? '' : 'hidden' ?>
                action="<?= e(I18n::url('/cv/' . $cv['slug'])) ?>?contacter=1#contacter">
            <?php if ($showForm): ?>
              <?= Csrf::field('contact') ?>
            <?php else: ?>
              <input type="hidden" name="_csrf" value="">
              <input type="hidden" name="_form" value="contact">
            <?php endif; ?>
            <?= SpamGuard::fields() ?>

            <label class="field">
              <span class="label"><?= e(I18n::t('apply.name')) ?> *</span>
              <input class="input" type="text" name="name" id="contact-name" required
                     autocomplete="name" maxlength="120">
            </label>
            <label class="field">
              <span class="label"><?= e(I18n::t('cv.contact_company')) ?></span>
              <input class="input" type="text" name="company" id="contact-company"
                     autocomplete="organization" maxlength="120">
            </label>
            <label class="field">
              <span class="label"><?= e(I18n::t('apply.email')) ?> *</span>
              <input class="input" type="email" name="email" id="contact-email" required
                     autocomplete="email" maxlength="180">
            </label>
            <label class="field">
              <span class="label"><?= e(I18n::t('apply.message')) ?> *</span>
              <textarea class="input" name="message" id="contact-message" rows="5" required
                        maxlength="4000"></textarea>
            </label>

            <button type="submit" class="btn btn-coral btn-block"><?= e(I18n::t('cv.contact_send')) ?></button>
          </form>
        <?php endif; ?>
      </div>

      <div class="card card-yellow" data-reveal>
        <h2 class="card-title"><?= e(I18n::t('cv.you_too')) ?></h2>
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
