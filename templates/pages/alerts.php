<?php
/** Résultat d'un lien d'alerte. @var string $message @var bool $ok */
use App\Services\I18n;
?>
<div class="container" style="max-width:560px">
  <div class="card card-lg" style="margin:40px 0">
    <h1 class="h1-sub" style="margin-bottom:12px"><?= e(I18n::t('alerts.title')) ?></h1>
    <p class="lede"><?= e($message) ?></p>
    <a class="btn btn-coral" style="margin-top:18px" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('nav.jobs')) ?></a>
  </div>
</div>
