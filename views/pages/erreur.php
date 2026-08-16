<?php /** @var int $code @var string $titre */ ?>
<section class="erreur">
  <p class="erreur__code"><?= e($code) ?></p>
  <h1><?= e($titre) ?></h1>
  <p><a href="<?= url('/') ?>">Retour à l’accueil</a></p>
</section>
