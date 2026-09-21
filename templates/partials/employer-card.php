<?php
/**
 * Carte employeur. @var array $employer ligne d'index
 */
use App\Core\View;
use App\Services\I18n;
?>
<a class="card card-link employer-card" href="<?= e(I18n::url('/employeur/' . $employer['slug'])) ?>" data-reveal>
  <span class="employer-top">
    <span class="<?= !empty($employer['has_logo']) ? 'tile-logo' : '' ?>">
      <?= View::partial('partials/avatar', [
            'name' => (string) $employer['name'], 'size' => 'tile-50', 'kind' => 'logo',
            'id' => !empty($employer['has_logo']) ? (string) $employer['id'] : '', 'chars' => 2,
          ]) ?>
    </span>
    <span class="employer-id">
      <span class="name"><?= e(str_excerpt((string) $employer['name'], 44)) ?></span>
      <?php
      // Pas de tiret de remplissage : une ligne vide vaut mieux qu'un « — ».
      $sub = array_filter([
          str_excerpt((string) ($employer['tagline'] ?: $employer['kind']), 70),
          (string) $employer['city'],
      ], 'strlen');
      ?>
      <?php if ($sub !== []): ?>
        <span class="sub"><?= e(implode(' · ', $sub)) ?></span>
      <?php endif; ?>
    </span>
  </span>

  <span class="employer-foot">
    <span class="tag <?= (int) $employer['job_count'] > 0 ? 'tag-ink' : 'tag-soft' ?>">
      <?= (int) $employer['job_count'] > 0
          ? e(I18n::t('employers.jobs', (int) $employer['job_count']))
          : e(I18n::t('employers.none')) ?>
    </span>
  </span>
</a>
