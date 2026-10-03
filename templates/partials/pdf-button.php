<?php
/** Bouton « Télécharger en PDF » (vrai document mis en page). Variables : $href, $light (sur fond sombre) */
?>
<a class="btn btn--sm <?= !empty($light) ? 'btn--yellow' : 'btn--navy' ?> pdfbtn" href="<?= e($href) ?>" rel="nofollow" download>
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" aria-hidden="true"><path d="M12 3v12M6.5 10 12 15.5 17.5 10M4 20h16"/></svg>
  <span><?= e(t('Télécharger en PDF')) ?></span>
</a>
