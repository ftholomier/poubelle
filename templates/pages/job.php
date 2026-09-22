<?php
/**
 * Détail d'une offre.
 *
 * @var array      $job       fiche complète
 * @var array|null $employer  fiche employeur si connue
 * @var array      $siblings  autres offres du même employeur
 */
use App\Core\Csrf;
use App\Core\View;
use App\Services\I18n;
use App\Services\SpamGuard;
use App\Support\Icon;

$color = tile_color((string) ($job['company']['name'] ?: $job['title']));
$isGuso = (bool) array_filter((array) $job['contract'],
    static fn(string $c) => stripos($c, 'guso') !== false || stripos($c, 'usage') !== false);

// L'adresse de l'employeur ne figure plus dans la page : elle était moissonnée
// par les robots dès la mise en ligne. La candidature passe par le site, qui
// relaie le message et place le candidat en adresse de réponse.
$externalApply = (string) ($job['apply']['url'] ?? '');
$canApply = $canApply ?? false;
$showForm = $showForm ?? false;
$errors = $errors ?? [];
$sent = $sent ?? false;
$expired = $expired ?? false;
?>
<div class="container">
  <a class="back-link" href="<?= e(I18n::url('/offres')) ?>"><?= Icon::svg('arrow-l', 16) ?><?= e(I18n::t('job.back')) ?></a>

  <div class="layout-detail">
    <article class="detail-main">
      <div class="card card-lg" data-reveal>
        <?php if ($expired): ?>
          <div class="notice notice-wait">
            <strong><?= e(I18n::t('jobs.expired')) ?></strong> ·
            <?= e(I18n::t('jobs.expired_note')) ?>
            <a href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('job.back')) ?></a>
          </div>
        <?php endif; ?>

        <header class="detail-head">
          <span class="<?= ($employer['logo']['path'] ?? '') !== '' ? 'tile-logo' : '' ?>">
            <?= View::partial('partials/avatar', [
                  'name' => (string) ($job['company']['name'] ?: $job['title']), 'size' => 'tile-60',
                  'kind' => 'logo',
                  'id' => ($employer['logo']['path'] ?? '') !== '' ? (string) $employer['id'] : '',
                  'chars' => 1,
                ]) ?>
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
              <?= e(I18n::t('job.starts')) ?> <?= e(format_date((string) $job['starts_at'])) ?></span>
          <?php endif; ?>
          <span class="tag tag-soft"><?= Icon::svg('clock', 14, '#4A4470', 2) ?>
            <?= e(I18n::t('job.published', time_ago((string) ($job['published_at'] ?: $job['created_at'])))) ?></span>
          <?php if (($job['expires_at'] ?? '') !== ''): ?>
            <span class="tag tag-soft"><?= Icon::svg('calendar', 14, '#4A4470', 2) ?>
              <?= e(I18n::t('job.expires', format_date((string) $job['expires_at']))) ?></span>
          <?php endif; ?>
        </div>

        <div class="prose">
          <h2><?= e(I18n::t('job.post')) ?></h2>
          <?php foreach (preg_split('/\n\s*\n/', (string) $job['description']) ?: [] as $paragraph): ?>
            <?php if (trim($paragraph) !== ''): ?><p><?= nl2br(e(trim($paragraph))) ?></p><?php endif; ?>
          <?php endforeach; ?>

          <?php // Seulement s'il reste du texte après : sur une annonce courte,
                // ce bloc se retrouverait collé à celui du dessous, et deux
                // annonces à la suite pour trois lignes d'offre, c'est ce que
                // Google appelle un contenu dominé par la publicité. ?>
          <?php if (!empty($job['requirements']) || trim((string) $job['conditions']) !== ''): ?>
            <?= View::partial('partials/ad', ['slot' => 'job_inline']) ?>
          <?php endif; ?>

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
      <div class="card card-dark" id="candidater" data-reveal>
        <h2 class="card-title"><?= e(I18n::t('job.apply_title')) ?></h2>

        <?php if ($sent): ?>
          <p class="notice notice-ok" style="margin:12px 0 0"><?= e(I18n::t('apply.done')) ?></p>
        <?php elseif ($externalApply !== ''): ?>
          <p class="aside-note"><?= e(I18n::t('job.apply_external')) ?></p>
          <a class="btn btn-coral btn-block" href="<?= e($externalApply) ?>"
             rel="noopener noreferrer" target="_blank">
            <?= e(I18n::t('job.apply')) ?> <?= Icon::svg('arrow-r', 14, '#fff', 2) ?>
          </a>
        <?php elseif ($canApply): ?>
          <p class="aside-note"><?= e(I18n::t('job.apply_direct')) ?></p>

          <?php if (!empty($errors['_form'])): ?>
            <p class="notice notice-err" role="alert" tabindex="-1" data-error-focus>
              <?= e((string) $errors['_form']) ?>
            </p>
          <?php endif; ?>

          <?php if (!$showForm): ?>
            <?php // Sans jeton dans la page, la fiche reste cachable. Le lien
                  // le réclame : par JavaScript, ou par cet aller-retour. ?>
            <a class="btn btn-coral btn-block" data-form-reveal="apply"
               aria-controls="apply-form" aria-expanded="false"
               href="<?= e(I18n::url('/offre/' . $job['slug'])) ?>?candidater=1#candidater">
              <?= e(I18n::t('job.apply')) ?>
            </a>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" class="apply-form" id="apply-form"
                data-token-form="apply" <?= $showForm ? '' : 'hidden' ?>
                action="<?= e(I18n::url('/offre/' . $job['slug'])) ?>?candidater=1#candidater">
            <?php if ($showForm): ?>
              <?= Csrf::field('apply') ?>
            <?php else: ?>
              <input type="hidden" name="_csrf" value="">
              <input type="hidden" name="_form" value="apply">
            <?php endif; ?>
            <?= SpamGuard::fields() ?>

            <label class="field">
              <span class="label"><?= e(I18n::t('apply.name')) ?> *</span>
              <input class="input" type="text" name="name" id="apply-name" required
                     autocomplete="name" maxlength="120">
            </label>
            <label class="field">
              <span class="label"><?= e(I18n::t('apply.email')) ?> *</span>
              <input class="input" type="email" name="email" id="apply-email" required
                     autocomplete="email" maxlength="180">
            </label>
            <label class="field">
              <span class="label"><?= e(I18n::t('apply.phone')) ?> <span class="opt"><?= e(I18n::t('form.optional')) ?></span></span>
              <input class="input" type="tel" name="phone" id="apply-phone"
                     autocomplete="tel" maxlength="40">
            </label>
            <label class="field">
              <span class="label"><?= e(I18n::t('apply.message')) ?> *</span>
              <textarea class="input" name="message" id="apply-message" rows="5" required
                        maxlength="4000" aria-describedby="apply-message-help"></textarea>
              <span class="opt" id="apply-message-help"><?= e(I18n::t('apply.message_help')) ?></span>
            </label>
            <label class="field">
              <span class="label"><?= e(I18n::t('apply.file')) ?> <span class="opt"><?= e(I18n::t('form.optional')) ?></span></span>
              <input class="input" type="file" name="cv_file" id="apply-file"
                     accept=".pdf,.doc,.docx" aria-describedby="apply-file-help">
              <span class="opt" id="apply-file-help"><?= e(I18n::t('apply.file_help')) ?></span>
            </label>

            <button type="submit" class="btn btn-coral btn-block"><?= e(I18n::t('job.apply')) ?></button>
            <p class="aside-note" style="margin-top:10px"><?= e(I18n::t('apply.privacy')) ?></p>
          </form>
        <?php else: ?>
          <p class="aside-note"><?= e($expired ? I18n::t('apply.err_closed') : I18n::t('job.apply_none')) ?></p>
        <?php endif; ?>

        <button type="button" class="btn btn-ghost-light btn-block" style="margin-top:9px"
                data-bookmark="<?= e($job['id']) ?>" aria-pressed="false"
                data-bookmark-title="<?= e($job['title']) ?>"
                data-bookmark-url="<?= e(I18n::url('/offre/' . $job['slug'])) ?>"
                data-bookmark-on="<?= e(I18n::t('job.saved')) ?>"><?= e(I18n::t('job.save')) ?></button>
      </div>

      <?php if ($employer !== null): ?>
        <div class="card" data-reveal>
          <h2 class="card-title"><?= e(I18n::t('job.employer')) ?></h2>
          <div class="employer-card" style="margin-top:14px">
            <span class="<?= ($employer['logo']['path'] ?? '') !== '' ? 'tile-logo' : '' ?>">
              <?= View::partial('partials/avatar', [
                    'name' => (string) $employer['name'], 'size' => 'tile-48', 'kind' => 'logo',
                    'id' => ($employer['logo']['path'] ?? '') !== '' ? (string) $employer['id'] : '',
                    'chars' => 2,
                  ]) ?>
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
        <h2 class="card-title"><?= Icon::svg('robot', 19, '#17123A', 2) ?> <?= e(I18n::t('job.ask_regie')) ?></h2>
        <p style="font-size:14px;color:#17123A;margin:10px 0 0"><?= e(I18n::t('job.ask_regie_note')) ?></p>
      </div>

      <?php // En bas de colonne, à distance du bouton « Postuler » : une
            // annonce collée à un bouton récolte des clics par erreur, que
            // Google finit par facturer au site en le suspendant. ?>
      <?= View::partial('partials/ad', ['slot' => 'job_side']) ?>
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
