<?php
/**
 * Carte de profil. @var array $cv ligne d'index
 */
use App\Services\I18n;

$color = tile_color((string) $cv['name']);
?>
<a class="card card-link cv-card" href="<?= e(I18n::url('/cv/' . $cv['slug'])) ?>" data-reveal>
  <span class="tile tile-64" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
    <?= e(initials((string) $cv['name'])) ?>
  </span>
  <span class="cv-name"><?= e(str_excerpt((string) $cv['name'], 32)) ?></span>
  <span class="cv-role"><?= e(str_excerpt((string) ($cv['title'] ?: '—'), 46)) ?></span>
  <span class="cv-meta">
    <?= e($cv['city'] ?: ($cv['region'] ?: '—')) ?>
    <?php if ((int) $cv['years'] > 0): ?> · <?= e(I18n::t('cv.years', (int) $cv['years'])) ?><?php endif; ?>
  </span>
  <?php if (!empty($cv['skills'])): ?>
    <span class="tag-row">
      <?php foreach (array_slice((array) $cv['skills'], 0, 3) as $skill): ?>
        <span class="tag tag-soft"><?= e(str_excerpt((string) $skill, 24)) ?></span>
      <?php endforeach; ?>
    </span>
  <?php endif; ?>
</a>
