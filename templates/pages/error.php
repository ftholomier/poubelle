<?php
/** Page d'erreur (404 / 500). */
use App\Services\I18n;
?>
<div class="container">
  <div class="page-head" data-reveal>
    <span class="tag tag-coral"><?= e((string) $code) ?></span>
    <h1 class="h1-sub" style="margin-top:14px"><?= e($title) ?></h1>
    <p><?= e($body) ?></p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:22px">
      <a class="btn btn-coral" href="<?= e(I18n::url('/')) ?>"><?= e(I18n::t('error.home')) ?></a>
      <a class="btn btn-ghost-light" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('nav.jobs')) ?></a>
    </div>
  </div>
</div>
