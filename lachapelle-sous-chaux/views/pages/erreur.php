<?php
/**
 * Page d'erreur (404 / 500).
 *
 * @var int $code
 * @var string $titre
 */
?>
<div class="erreur-page">
  <div class="conteneur conteneur--etroit">
    <p class="erreur-page__code"><?= e($code) ?></p>
    <h1 class="erreur-page__titre"><?= e($titre) ?></h1>
    <p class="erreur-page__texte">
      <?= e(t('La page que vous cherchez a peut-être été déplacée, ou son adresse a changé.')) ?>
    </p>
    <div class="erreur-page__actions">
      <a class="btn btn--vert" href="<?= route('accueil') ?>"><?= e(t('Retour à l’accueil')) ?></a>
      <a class="btn btn--contour" href="<?= route('contact') ?>"><?= e(t('Nous contacter')) ?></a>
    </div>
  </div>
</div>
