<?php
/**
 * Barre CTA fixe. Les deux halos pulsent en alternance.
 *
 * Elle disparaît des pages de dépôt : elle y renvoyait vers la page déjà
 * ouverte tout en masquant les premiers champs du formulaire sur mobile.
 */
use App\Services\I18n;

$path = $path ?? '/';
foreach (['/deposer-un-cv', '/deposer-une-annonce'] as $form) {
    if (str_starts_with($path, $form)) {
        return;
    }
}
?>
<div class="cta-bar" data-cta-bar>
  <span class="cta-note"><span class="dot"></span><?= e(I18n::t('cta.note')) ?></span>
  <div class="cta-buttons">
    <a class="btn btn-coral halo-cv" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('cta.post_cv')) ?></a>
    <a class="btn btn-violet halo-job" href="<?= e(I18n::url('/deposer-une-annonce')) ?>"><?= e(I18n::t('cta.post_job')) ?></a>
  </div>
</div>
