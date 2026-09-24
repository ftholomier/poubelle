<?php
/**
 * Mosaïque des métiers, rangée par famille.
 *
 * @var array $groups    famille => lignes d'index
 * @var array $families  catalogue des familles
 * @var array $counts    slug => annonces en ligne
 * @var int   $total     nombre de fiches publiées
 */
use App\Core\View;
use App\Services\I18n;
use App\Services\Trades;
use App\Support\Icon;
?>
<div class="container">
  <div class="page-head" data-reveal>
    <h1 class="h1-sub"><?= e(I18n::t('trades.title')) ?></h1>
    <p><?= e(I18n::t('trades.lede', $total)) ?></p>
  </div>

  <?php // Sommaire des familles : on saute à la sienne sans faire défiler
        // soixante pavés. ?>
  <nav class="trade-jump" aria-label="<?= e(I18n::t('trades.jump')) ?>">
    <?php foreach ($groups as $key => $rows): $family = $families[$key] ?? Trades::family((string) $key); ?>
      <a class="chip tone-<?= e($family['tone']) ?>" href="#famille-<?= e((string) $key) ?>">
        <?= Icon::svg($family['icon'], 15, 'currentColor', 2) ?>
        <?= e($family['name']) ?> <span class="n"><?= count($rows) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php $position = 0; ?>
  <?php foreach ($groups as $key => $rows): $family = $families[$key] ?? Trades::family((string) $key); $position++; ?>
    <section class="trade-family" id="famille-<?= e((string) $key) ?>"
             aria-labelledby="famille-<?= e((string) $key) ?>-titre">
      <div class="trade-family-head tone-<?= e($family['tone']) ?>">
        <span class="trade-icon trade-icon-lg"><?= Icon::svg($family['icon'], 28, 'currentColor', 2) ?></span>
        <div>
          <h2 id="famille-<?= e((string) $key) ?>-titre"><?= e($family['name']) ?></h2>
          <?php if ($family['intro'] !== ''): ?><p><?= e($family['intro']) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="grid-trades">
        <?php foreach ($rows as $row): ?>
          <?= View::partial('partials/trade-tile', [
                'row' => $row, 'family' => $family, 'count' => $counts[$row['slug']] ?? 0,
              ]) ?>
        <?php endforeach; ?>
      </div>
    </section>

    <?php // Entre deux familles, jamais au milieu d'une grille : l'annonce ne
          // doit pas se confondre avec un pavé de métier. ?>
    <?php if ($position === 3): ?>
      <?= View::partial('partials/ad', ['slot' => 'trades_infeed']) ?>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="cta-banner" data-reveal style="margin-top:56px">
    <h2><?= e(I18n::t('trades.cta_title')) ?></h2>
    <p><?= e(I18n::t('trades.cta_note')) ?></p>
    <div class="row">
      <a class="btn btn-coral btn-lg" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('cta.post_cv')) ?></a>
      <a class="btn btn-violet btn-lg" href="<?= e(I18n::url('/deposer-une-annonce')) ?>"><?= e(I18n::t('cta.post_job')) ?></a>
    </div>
  </div>
</div>
