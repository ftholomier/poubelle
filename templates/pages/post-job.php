<?php
/**
 * Dépôt d'annonce. Accent violet.
 * @var array $old @var array $errors @var string $done @var array $review
 * @var array|null $preview @var array $categories @var array $contracts
 */
use App\Core\Csrf;
use App\Services\SpamGuard;
use App\Services\I18n;
use App\Support\Icon;

$val = static fn(string $k, string $d = ''): string => e((string) ($old[$k] ?? $d));
$err = static fn(string $k): string => (string) ($errors[$k] ?? '');
$picked = static function (string $field, string $value) use ($old): bool {
    $current = $old[$field] ?? [];
    $current = is_array($current) ? $current : explode(',', (string) $current);
    return in_array($value, array_map('trim', $current), true);
};
?>
<div class="container form-violet" style="max-width:900px">
  <?php if ($done !== ''): ?>
    <div class="card card-lg" style="margin-top:34px" data-reveal>
      <span class="tag tag-violet"><?= e(I18n::t('post_job.badge')) ?></span>
      <h1 class="h1-sub" style="margin-top:16px"><?= e(I18n::t('post_job.done')) ?></h1>
      <?php if ($done === 'pending'): ?>
        <p class="lede" style="margin:12px 0 0"><?= e(I18n::t('post_job.pending_note')) ?></p>
      <?php endif; ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
        <?php if ($done !== 'pending'): ?>
          <a class="btn btn-violet" href="<?= e(I18n::url('/offre/' . $done)) ?>"><?= e(I18n::t('job.post')) ?></a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(I18n::url('/cv')) ?>"><?= e(I18n::t('nav.cv')) ?></a>
      </div>
    </div>
  <?php else: ?>

  <div style="text-align:center;margin-top:40px" data-reveal>
    <span class="tag tag-violet" style="padding:9px 18px;font-size:13.5px"><?= e(I18n::t('post_job.badge')) ?></span>
    <h1 class="h1-sub" style="margin:20px 0 12px"><?= e(I18n::t('post_job.title')) ?></h1>
    <p class="lede" style="max-width:560px;margin:0 auto"><?= e(I18n::t('post_job.lede')) ?></p>
  </div>

  <?php if ($err('_form') !== ''): ?>
    <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus
         style="margin-top:24px"><?= e($err('_form')) ?></div>
  <?php elseif ($errors !== []): ?>
    <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus
         style="margin-top:24px"><?= e(I18n::t('form.error')) ?></div>
  <?php endif; ?>

  <?php if ($review !== []): ?>
    <div class="card card-lg" style="margin-top:24px" data-reveal>
      <h3><?= Icon::svg('robot', 19, '#17123A', 2) ?> <?= e(I18n::t('post_job.review')) ?></h3>
      <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px">
        <?php foreach ($review as $note): ?>
          <p class="notice notice-<?= e($note['level']) ?>" style="margin:0"><?= e($note['message']) ?></p>
        <?php endforeach; ?>
      </div>
      <?php if ($preview !== null): ?>
        <div style="margin-top:18px;padding-top:18px;border-top:1px solid var(--rule)">
          <span class="meta"><?= e(I18n::t('post_job.preview')) ?></span>
          <h3 style="margin-top:8px"><?= e($preview['title']) ?></h3>
          <p class="meta"><?= e($preview['company']['name']) ?>
            <?= $preview['location']['city'] !== '' ? ' · ' . e($preview['location']['city']) : '' ?></p>
          <p style="margin-top:10px;color:var(--text);font-size:14.5px"><?= e(str_excerpt((string) $preview['description'], 300)) ?></p>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form class="card card-lg" method="post" novalidate style="margin-top:24px" data-reveal>
    <?= Csrf::field('post-job') ?>
    <?= SpamGuard::fields() ?>

    <h2><?= e(I18n::t('post_job.title')) ?></h2>
    <div class="grid-fields" style="margin-top:18px">
      <label class="field" style="grid-column:1/-1">
        <span class="label"><?= e(I18n::t('post_job.job_title')) ?> *</span>
        <input class="input" type="text" name="title" value="<?= $val('title') ?>" required
               placeholder="Régisseur·se son — tournée automne 2026" <?= $err('title') !== '' ? 'aria-invalid="true"' : '' ?>>
        <?php if ($err('title') !== ''): ?><span class="field-error"><?= e($err('title')) ?></span><?php endif; ?>
      </label>

      <label class="field">
        <span class="label"><?= e(I18n::t('post_job.company')) ?> *</span>
        <input class="input" type="text" name="company" value="<?= $val('company') ?>" required
               <?= $err('company') !== '' ? 'aria-invalid="true"' : '' ?>>
        <?php if ($err('company') !== ''): ?><span class="field-error"><?= e($err('company')) ?></span><?php endif; ?>
      </label>

      <label class="field">
        <span class="label"><?= e(I18n::t('post_job.place')) ?> *</span>
        <input class="input" type="text" name="city" value="<?= $val('city') ?>" required
               placeholder="Paris, Marseille, tournée…" <?= $err('city') !== '' ? 'aria-invalid="true"' : '' ?>>
        <?php if ($err('city') !== ''): ?><span class="field-error"><?= e($err('city')) ?></span><?php endif; ?>
      </label>

      <label class="field">
        <span class="label"><?= e(I18n::t('post_job.salary')) ?> <span class="opt">(<?= e(I18n::t('form.optional')) ?>)</span></span>
        <input class="input" type="text" name="salary" value="<?= $val('salary') ?>" placeholder="180 € brut / cachet">
      </label>

      <label class="field">
        <span class="label"><?= e(I18n::t('post_job.starts')) ?> <span class="opt">(<?= e(I18n::t('form.optional')) ?>)</span></span>
        <input class="input" type="date" name="starts_at" value="<?= $val('starts_at') ?>">
      </label>

      <label class="field">
        <span class="label"><?= e(I18n::t('post_job.apply_email')) ?> *</span>
        <input class="input" type="email" name="apply_email" value="<?= $val('apply_email') ?>" required
               <?= $err('apply_email') !== '' ? 'aria-invalid="true"' : '' ?>>
        <?php if ($err('apply_email') !== ''): ?><span class="field-error"><?= e($err('apply_email')) ?></span><?php endif; ?>
      </label>

      <label class="field">
        <span class="label">Site de la structure <span class="opt">(<?= e(I18n::t('form.optional')) ?>)</span></span>
        <input class="input" type="url" name="company_website" value="<?= $val('company_website') ?>" placeholder="https://">
      </label>
    </div>

    <h3 style="margin-top:24px"><?= e(I18n::t('post_job.contract')) ?></h3>
    <div class="tag-row" style="margin-top:10px">
      <?php foreach ($contracts as $contract): ?>
        <label class="chip" style="cursor:pointer">
          <input type="checkbox" class="visually-hidden" name="contract[]" value="<?= e((string) $contract) ?>"
                 <?= $picked('contract', (string) $contract) ? 'checked' : '' ?>>
          <?= e((string) $contract) ?>
        </label>
      <?php endforeach; ?>
      <label class="check" style="margin-left:6px">
        <input type="checkbox" name="remote" value="1" <?= !empty($old['remote']) ? 'checked' : '' ?>>
        <span><?= e(I18n::t('jobs.remote')) ?></span>
      </label>
    </div>

    <h3 style="margin-top:22px"><?= e(I18n::t('post_job.category')) ?></h3>
    <div class="tag-row" style="margin-top:10px">
      <?php foreach ($categories as $category): ?>
        <label class="chip" style="cursor:pointer">
          <input type="checkbox" class="visually-hidden" name="category[]" value="<?= e((string) $category) ?>"
                 <?= $picked('category', (string) $category) ? 'checked' : '' ?>>
          <?= e((string) $category) ?>
        </label>
      <?php endforeach; ?>
    </div>

    <label class="field" style="margin-top:22px">
      <span class="label"><?= e(I18n::t('post_job.description')) ?> *</span>
      <textarea class="textarea" name="description" rows="5" required
                placeholder="<?= e(I18n::t('post_job.description_ph')) ?>"
                <?= $err('description') !== '' ? 'aria-invalid="true"' : '' ?>><?= $val('description') ?></textarea>
      <?php if ($err('description') !== ''): ?><span class="field-error"><?= e($err('description')) ?></span><?php endif; ?>
    </label>

    <label class="field" style="margin-top:14px">
      <span class="label">Mots-clés <span class="opt">(<?= e(I18n::t('form.optional')) ?>, séparés par des virgules)</span></span>
      <input class="input" type="text" name="tags" value="<?= $val('tags') ?>" placeholder="régie son, tournée, plateau">
    </label>

    <div class="card card-yellow" style="margin-top:22px;box-shadow:4px 5px 0 var(--ink)">
      <h4><?= Icon::svg('robot', 17, '#17123A', 2) ?> <?= e(I18n::t('post_job.review')) ?></h4>
      <p style="font-size:13.5px;margin:8px 0 0"><?= e(I18n::t('post_job.review_note')) ?></p>
    </div>

    <label class="check" style="margin-top:20px">
      <input type="checkbox" name="gdpr" value="1" required <?= !empty($old['gdpr']) ? 'checked' : '' ?>>
      <span><?= e(I18n::t('form.gdpr')) ?>
        <a href="<?= e(I18n::url('/mentions-legales')) ?>"><?= e(I18n::t('form.gdpr_link')) ?></a></span>
    </label>
    <?php if ($err('gdpr') !== ''): ?><span class="field-error"><?= e($err('gdpr')) ?></span><?php endif; ?>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:26px">
      <button type="submit" name="action" value="publish" class="btn btn-violet btn-lg"><?= e(I18n::t('post_job.submit')) ?></button>
      <button type="submit" name="action" value="preview" class="btn btn-ghost"><?= e(I18n::t('post_job.preview')) ?></button>
    </div>
    <p class="meta" style="margin-top:12px">* <?= e(I18n::t('form.required')) ?></p>
  </form>
  <?php endif; ?>
</div>
