<?php
/** Message de confirmation (newsletter…). Variables : $title, $text, $action (bouton à valider : [libellé] ou null) */
?>
<section class="wrap cmessage">
  <img src="/assets/img/logo-sochaux-retro.png" alt="" width="106" height="120">
  <h1 class="h-2"><?= e($title) ?></h1>
  <p class="lead" style="margin:0 auto"><?= e($text) ?></p>
  <?php if (!empty($action)): ?>
    <form method="post">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--navy"><?= e($action) ?></button>
    </form>
  <?php endif; ?>
  <a class="btn btn--yellow" href="<?= e(url('/')) ?>"><?= e(t("Retour à l'accueil")) ?></a>
</section>
