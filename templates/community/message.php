<?php
/** Message de confirmation (newsletter…). Variables : $title, $text */
?>
<section class="wrap cmessage">
  <img src="/assets/img/logo-sochaux-retro.png" alt="" width="106" height="120">
  <h1 class="h-2"><?= e($title) ?></h1>
  <p class="lead" style="margin:0 auto"><?= e($text) ?></p>
  <a class="btn btn--yellow" href="<?= e(url('/')) ?>"><?= e(t("Retour à l'accueil")) ?></a>
</section>
