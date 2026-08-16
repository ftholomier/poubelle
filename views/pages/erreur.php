<?php
/**
 * Page d'erreur (404 / 500).
 * @var int $code
 * @var string $titre
 */
?>
<div class="erreur-page">
  <p class="code"><?= e($code) ?></p>
  <h1><?= e($titre) ?></h1>
  <a class="btn btn--or" href="<?= url('/') ?>">Retour à l'accueil</a>
</div>
