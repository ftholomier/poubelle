<div class="lightbox" data-lightbox role="dialog" aria-modal="true" aria-label="<?= e(t('Agrandissement de la photo')) ?>" hidden>
  <button type="button" class="lightbox__close" data-lb-close aria-label="<?= e(t('Fermer')) ?>">✕</button>
  <img src="" alt="" data-lb-img>
  <div class="lightbox__bar">
    <button type="button" data-lb-prev aria-label="<?= e(t('Photo précédente')) ?>">←</button>
    <span class="lightbox__text"><span data-lb-caption></span> <a class="lightbox__link" data-lb-link href="#" hidden><?= e(t('Voir la fiche')) ?> →</a></span>
    <button type="button" data-lb-next aria-label="<?= e(t('Photo suivante')) ?>">→</button>
  </div>
</div>
