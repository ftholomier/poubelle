<?php
/**
 * Musée : « L'équipe de Sochaux Rétro » en vignettes à collectionner. Les membres se saisissent dans
 * le back-office (Association › Contenus › Équipe) et s'affichent aussi sur le site de l'association.
 * Variables : $team, $joinUrl
 */
?>
<section class="rhead tteam-head">
  <div class="wrap rhead__inner">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><span><?= e(t('Supporters')) ?></span><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('L’équipe de Sochaux Rétro')) ?></span></nav>
    <span class="eyebrow eyebrow--lg" style="color:var(--navy)"><?= e(t('La collection · {n} cartes', ['n' => count($team)])) ?></span>
    <h1 class="rhead__title"><?= e(t('L’équipe')) ?><br><?= e(t('de Sochaux Rétro')) ?></h1>
    <p class="chead__lead"><?= e(t('Les bénévoles qui font vivre le musée : chacun sa vignette, sa mission et son anecdote.')) ?></p>
  </div>
</section>
<section class="section tteam-body">
  <div class="wrap">
    <?php if ($team): ?>
      <p class="tteam-hint"><span class="h-hover"><?= e(t('Survolez une carte')) ?></span><span class="h-touch"><?= e(t('Touchez une carte')) ?></span> <?= e(t('pour la retourner.')) ?></p>
      <?= \App\Core\View::partial('partials/team-cards', ['team' => $team, 'theme' => 'musee']) ?>
    <?php else: ?>
      <p class="tteam-hint"><?= e(t('Les vignettes de l’équipe arrivent bientôt.')) ?></p>
    <?php endif; ?>
    <p class="tteam-join"><a class="btn btn--navy" href="<?= e($joinUrl) ?>"><?= e(t('Rejoindre l’équipe')) ?></a></p>
  </div>
</section>
