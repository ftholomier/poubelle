<?php
/**
 * Carte employeur. @var array $employer ligne d'index
 */
use App\Services\I18n;

$color = tile_color((string) $employer['name']);
?>
<a class="card card-link employer-card" href="<?= e(I18n::url('/employeur/' . $employer['slug'])) ?>" data-reveal>
  <span class="tile tile-50" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
    <?= e(initials((string) $employer['name'], 2)) ?>
  </span>
  <span style="flex:1;min-width:0">
    <span class="name"><?= e(str_excerpt((string) $employer['name'], 40)) ?></span><br>
    <span class="sub"><?= e($employer['kind'] ?: '—') ?><?= $employer['city'] !== '' ? ' · ' . e($employer['city']) : '' ?></span>
  </span>
  <span class="tag <?= (int) $employer['job_count'] > 0 ? 'tag-ink' : 'tag-soft' ?>">
    <?= (int) $employer['job_count'] > 0
        ? e(I18n::t('employers.jobs', (int) $employer['job_count']))
        : e(I18n::t('employers.none')) ?>
  </span>
</a>
