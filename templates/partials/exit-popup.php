<?php
/** Pop-up de sortie. Déclenchée une seule fois par session, côté client. */
use App\Services\I18n;
use App\Services\Search;
use App\Storage\Index;

$facets = Index::meta('jobs');
$teasers = Search::latestJobs(2);
?>
<?php // « aria-modal » sans piège de focus laisse le lecteur d'écran sortir de
      // la fenêtre sans la fermer : le JS installe le piège et la touche Échap. ?>
<div class="exit-overlay" data-exit-popup role="dialog" aria-modal="true"
     aria-label="<?= e(I18n::t('exit.title')) ?>" hidden>
  <div class="exit-card">
    <div class="exit-left">
      <span class="tag tag-coral"><?= e(I18n::t('exit.badge')) ?></span>
      <h2><?= e(I18n::t('exit.title')) ?></h2>
      <p><?= e(I18n::t('exit.argument',
            number_format((int) ($facets['total'] ?? 0), 0, ',', ' '),
            number_format(count(Index::load('employers')), 0, ',', ' '))) ?></p>
      <div class="exit-actions">
        <a class="btn btn-coral btn-sm" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('exit.cv')) ?></a>
        <a class="btn btn-violet btn-sm" href="<?= e(I18n::url('/deposer-une-annonce')) ?>"><?= e(I18n::t('exit.job')) ?></a>
      </div>
      <button type="button" class="exit-decline" data-exit-close><?= e(I18n::t('exit.decline')) ?></button>
    </div>
    <div class="exit-right">
      <?php foreach ($teasers as $job): ?>
        <div class="mini-card">
          <div class="t"><?= e(str_excerpt((string) $job['title'], 52)) ?></div>
          <div class="s"><?= e($job['company'] ?: '—') ?><?= $job['city'] !== '' ? ' · ' . e($job['city']) : '' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
