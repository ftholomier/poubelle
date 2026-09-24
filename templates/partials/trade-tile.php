<?php
/**
 * Pavé de la mosaïque des métiers.
 *
 * @var array $row     ligne d'index du métier
 * @var array $family  sa famille (couleur, pictogramme)
 * @var int   $count   annonces en ligne pour ce métier
 */
use App\Services\I18n;
use App\Support\Icon;

$count = (int) ($count ?? 0);
?>
<a class="card card-link trade-tile tone-<?= e($family['tone']) ?>"
   href="<?= e(I18n::url('/metiers/' . $row['slug'])) ?>" data-reveal>
  <span class="trade-icon"><?= Icon::svg($family['icon'], 22, 'currentColor', 2) ?></span>
  <h3 class="trade-name"><?= e($row['name']) ?></h3>
  <?php if (trim((string) $row['summary']) !== ''): ?>
    <span class="trade-sum"><?= e($row['summary']) ?></span>
  <?php endif; ?>
  <?php if ($count > 0): ?>
    <span class="tag tag-teal trade-count">
      <?= e($count === 1 ? I18n::t('trades.job_one') : I18n::t('trades.job_many', $count)) ?>
    </span>
  <?php endif; ?>
</a>
