<?php
/**
 * Dépôt de CV. Accent corail.
 * @var array $old  @var array $errors  @var string $done  @var array $suggested
 */
use App\Core\Csrf;
use App\Services\SpamGuard;
use App\Services\I18n;
use App\Support\Icon;

$val = static fn(string $k, string $d = ''): string => e((string) ($old[$k] ?? $d));
$err = static fn(string $k): string => (string) ($errors[$k] ?? '');
$steps = ['post_cv.step1' => 'bloc-profil', 'post_cv.step2' => 'bloc-competences', 'post_cv.step3' => 'bloc-cv'];
?>
<div class="container" style="max-width:900px">
  <?php if ($done !== ''): ?>
    <div class="card card-lg" style="margin-top:34px" data-reveal>
      <span class="tag tag-teal"><span class="dot"></span><?= e(I18n::t('post_cv.done')) ?></span>
      <h1 class="h1-sub" style="margin-top:16px"><?= e(I18n::t('post_cv.done')) ?></h1>
      <p class="lede" style="margin:12px 0 22px">
        <?= e($done === 'pending' ? I18n::t('post_cv.pending_note') : I18n::t('post_cv.done_note')) ?>
      </p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php if ($done !== 'draft' && $done !== 'pending'): ?>
          <a class="btn btn-coral" href="<?= e(I18n::url('/cv/' . $done)) ?>"><?= e(I18n::t('cv.summary')) ?></a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('nav.jobs')) ?></a>
      </div>
    </div>
  <?php else: ?>

  <div style="text-align:center;margin-top:40px" data-reveal>
    <span class="pill-status"><span class="dot"></span><?= e(I18n::t('post_cv.badge')) ?></span>
    <h1 class="h1-sub" style="margin:20px 0 12px"><?= e(I18n::t('post_cv.title')) ?></h1>
    <p class="lede" style="max-width:560px;margin:0 auto"><?= e(I18n::t('post_cv.lede')) ?></p>
    <p style="margin-top:12px;font-size:14px"><?= e(I18n::t('post_cv.have_account')) ?>
      <a href="/admin"><?= e(I18n::t('nav.login')) ?></a></p>
  </div>

  <div class="steps" style="margin-top:32px;justify-content:center" data-steps data-reveal>
    <?php $i = 0; foreach ($steps as $labelKey => $anchor): $i++; ?>
      <button type="button" class="step<?= $i === 1 ? ' is-current' : '' ?>" data-step="<?= e($anchor) ?>">
        <span class="step-num"><?= $i ?></span>
        <span class="step-label"><?= e(I18n::t($labelKey)) ?></span>
      </button>
      <?php if ($i < count($steps)): ?><span class="step-bar"></span><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php // Le résumé d'erreurs prend le focus : sans cela, un visiteur au
        // clavier ou au lecteur d'écran ne sait pas pourquoi rien ne s'est passé. ?>
  <?php if ($err('_form') !== ''): ?>
    <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus><?= e($err('_form')) ?></div>
  <?php elseif ($errors !== []): ?>
    <div class="notice notice-err" role="alert" tabindex="-1" data-error-focus><?= e(I18n::t('form.error')) ?></div>
  <?php endif; ?>

  <form class="card card-lg" method="post" enctype="multipart/form-data" novalidate data-reveal>
    <?= Csrf::field('post-cv') ?>
    <?= SpamGuard::fields() ?>

    <section id="bloc-profil">
      <h2><?= e(I18n::t('post_cv.step1')) ?></h2>
      <div class="grid-fields" style="margin-top:18px">
        <label class="field">
          <span class="label"><?= e(I18n::t('post_cv.name')) ?> *</span>
          <input class="input" type="text" name="name" value="<?= $val('name') ?>" required
                 autocomplete="name" <?= $err('name') !== '' ? 'aria-invalid="true"' : '' ?>>
          <?php if ($err('name') !== ''): ?><span class="field-error"><?= e($err('name')) ?></span><?php endif; ?>
        </label>

        <label class="field">
          <span class="label"><?= e(I18n::t('post_cv.job')) ?> *</span>
          <input class="input" type="text" name="title" value="<?= $val('title') ?>" required
                 placeholder="Régisseur son, costumière, cadreur…" <?= $err('title') !== '' ? 'aria-invalid="true"' : '' ?>>
          <?php if ($err('title') !== ''): ?><span class="field-error"><?= e($err('title')) ?></span><?php endif; ?>
        </label>

        <label class="field">
          <span class="label"><?= e(I18n::t('post_cv.city')) ?> *</span>
          <input class="input" type="text" name="city" value="<?= $val('city') ?>" required
                 placeholder="Paris, Lyon, Bordeaux…" <?= $err('city') !== '' ? 'aria-invalid="true"' : '' ?>>
          <?php if ($err('city') !== ''): ?><span class="field-error"><?= e($err('city')) ?></span><?php endif; ?>
        </label>

        <label class="field">
          <span class="label"><?= e(I18n::t('post_cv.experience')) ?> <span class="opt">(<?= e(I18n::t('form.optional')) ?>)</span></span>
          <input class="input" type="number" name="experience" min="0" max="60" value="<?= $val('experience') ?>">
        </label>

        <label class="field">
          <span class="label"><?= e(I18n::t('post_cv.mobility')) ?> <span class="opt">(<?= e(I18n::t('form.optional')) ?>)</span></span>
          <input class="input" type="text" name="mobility" value="<?= $val('mobility') ?>"
                 placeholder="France entière, Île-de-France, tournées…">
        </label>

        <label class="field">
          <span class="label"><?= e(I18n::t('post_cv.email')) ?> *</span>
          <input class="input" type="email" name="email" value="<?= $val('email') ?>" required
                 autocomplete="email" <?= $err('email') !== '' ? 'aria-invalid="true"' : '' ?>>
          <?php if ($err('email') !== ''): ?><span class="field-error"><?= e($err('email')) ?></span><?php endif; ?>
        </label>
      </div>

      <label class="field" style="margin-top:18px">
        <span class="label"><?= e(I18n::t('post_cv.summary')) ?></span>
        <textarea class="textarea" name="summary" rows="5"
                  placeholder="<?= e(I18n::t('post_cv.summary_ph')) ?>"><?= $val('summary') ?></textarea>
      </label>
    </section>

    <hr>

    <section id="bloc-competences">
      <h2><?= e(I18n::t('post_cv.skills')) ?></h2>
      <p class="meta" style="margin:6px 0 14px"><?= e(I18n::t('post_cv.skills_note')) ?></p>

      <div class="tag-row" data-skills>
        <input type="hidden" name="skills" value="<?= $val('skills') ?>">
        <?php foreach ($suggested as $skill): ?>
          <button type="button" class="chip" data-skill="<?= e((string) $skill) ?>" aria-pressed="false">
            <?= e(str_excerpt((string) $skill, 28)) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <label class="field" style="margin-top:16px">
        <span class="label"><?= e(I18n::t('post_cv.skills_free')) ?></span>
        <input class="input" type="text" name="skills_free" value="<?= $val('skills_free') ?>"
               placeholder="Protools, accroche-levage, anglais…">
      </label>
    </section>

    <hr>

    <section id="bloc-cv">
      <h2><?= e(I18n::t('post_cv.file')) ?></h2>
      <p class="meta" style="margin:6px 0 14px"><?= e(I18n::t('post_cv.file_note')) ?></p>

      <label class="dropzone" data-dropzone>
        <input type="file" name="cv_file" accept=".pdf,.doc,.docx,application/pdf">
        <span style="display:flex;justify-content:center"><?= Icon::svg('upload', 26, '#17123A', 2) ?></span>
        <span style="display:block;margin-top:10px;font-weight:700"><?= e(I18n::t('post_cv.file_drop')) ?></span>
        <span class="dz-name" data-dz-name style="display:block"></span>
      </label>
      <?php if ($err('cv_file') !== ''): ?><span class="field-error"><?= e($err('cv_file')) ?></span><?php endif; ?>

      <div style="display:flex;flex-direction:column;gap:12px;margin-top:22px">
        <label class="check">
          <input type="checkbox" name="listed" value="1" <?= !empty($old['listed']) || $old === [] ? 'checked' : '' ?>>
          <span><strong><?= e(I18n::t('post_cv.listed')) ?></strong><br>
            <span class="meta"><?= e(I18n::t('post_cv.listed_note')) ?></span></span>
        </label>
        <label class="check">
          <input type="checkbox" name="contact_public" value="1" <?= !empty($old['contact_public']) ? 'checked' : '' ?>>
          <span><?= e(I18n::t('post_cv.mail_public')) ?><br>
            <span class="meta"><?= e(I18n::t('post_cv.mail_public_note')) ?></span></span>
        </label>
        <label class="check">
          <input type="checkbox" name="contact_closed" value="1" <?= !empty($old['contact_closed']) ? 'checked' : '' ?>>
          <span><?= e(I18n::t('post_cv.no_contact')) ?><br>
            <span class="meta"><?= e(I18n::t('post_cv.no_contact_note')) ?></span></span>
        </label>
        <label class="check">
          <input type="checkbox" name="gdpr" value="1" required <?= !empty($old['gdpr']) ? 'checked' : '' ?>>
          <span><?= e(I18n::t('form.gdpr')) ?>
            <a href="<?= e(I18n::url('/mentions-legales')) ?>"><?= e(I18n::t('form.gdpr_link')) ?></a></span>
        </label>
        <?php if ($err('gdpr') !== ''): ?><span class="field-error"><?= e($err('gdpr')) ?></span><?php endif; ?>
      </div>
    </section>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:26px">
      <button type="submit" name="action" value="publish" class="btn btn-coral btn-lg"><?= e(I18n::t('post_cv.submit')) ?></button>
      <button type="submit" name="action" value="draft" class="btn btn-ghost"><?= e(I18n::t('post_cv.draft')) ?></button>
    </div>
    <p class="meta" style="margin-top:12px">* <?= e(I18n::t('form.required')) ?></p>
  </form>
  <?php endif; ?>
</div>
