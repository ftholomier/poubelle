<?php
/** Page introuvable : on propose la recherche et les grandes rubriques. */
?>
<section class="errpage">
  <div class="wrap errpage__inner">
    <span class="errpage__code" aria-hidden="true">404</span>
    <div class="errpage__text">
      <span class="eyebrow eyebrow--lg"><?= e(t('Hors-jeu !')) ?></span>
      <h1 class="h-2"><?= e(t('Cette page est introuvable')) ?></h1>
      <p class="lead"><?= e(t("L'adresse a peut-être changé avec la nouvelle version du musée. Cherchez un match, un joueur ou une saison :")) ?></p>
      <form class="searchbar" action="<?= e(url('/recherche/')) ?>" method="get" role="search">
        <label class="sr-only" for="q404"><?= e(t('Rechercher')) ?></label>
        <input id="q404" type="search" name="q" placeholder="<?= e(t('Un match, un joueur, une saison…')) ?>">
        <button class="btn btn--yellow" type="submit"><?= e(t('Rechercher')) ?></button>
      </form>
      <div class="chips">
        <a class="chip" href="<?= e(url('/matchs/')) ?>"><?= e(t('Matchs')) ?></a>
        <a class="chip" href="<?= e(url('/nos-lions/')) ?>"><?= e(t('Nos Lions')) ?></a>
        <a class="chip" href="<?= e(url('/supporters/')) ?>"><?= e(t('Supporters')) ?></a>
        <a class="chip" href="<?= e(url('/infrastructures/')) ?>"><?= e(t('Infrastructures')) ?></a>
        <a class="chip" href="<?= e(url('/symboles/')) ?>"><?= e(t('Symboles')) ?></a>
        <a class="chip" href="<?= e(url('/')) ?>"><?= e(t("Retour à l'accueil")) ?></a>
      </div>
    </div>
  </div>
</section>
