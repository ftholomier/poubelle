<?php
/** Barre CTA fixe. Les deux halos pulsent en alternance (décalage d'une demi-période). */
use App\Services\I18n;
?>
<div class="cta-bar">
  <span class="cta-note"><span class="dot"></span><?= e(I18n::t('cta.note')) ?></span>
  <div class="cta-buttons">
    <a class="btn btn-coral halo-cv" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('cta.post_cv')) ?></a>
    <a class="btn btn-violet halo-job" href="<?= e(I18n::url('/deposer-une-annonce')) ?>"><?= e(I18n::t('cta.post_job')) ?></a>
  </div>
</div>
