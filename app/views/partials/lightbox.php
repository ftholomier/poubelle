<?php
/** Visionneuse plein écran de l'album photo : flèches, clavier, fermeture. */

use App\I18n;
use App\Text;
?>
<div class="lightbox" data-lightbox hidden role="dialog" aria-modal="true" aria-label="<?= Text::e(I18n::t('album.title', ['name' => ''])) ?>">
  <div class="lightbox__bar">
    <span class="lightbox__counter" data-lightbox-counter></span>
    <button class="lightbox__close" type="button" data-lightbox-close aria-label="<?= Text::e(I18n::t('album.close')) ?>">×</button>
  </div>
  <div class="lightbox__stage">
    <button class="lightbox__nav lightbox__nav--prev" type="button" data-lightbox-prev aria-label="<?= Text::e(I18n::t('album.prev')) ?>">‹</button>
    <img class="lightbox__img" data-lightbox-img src="" alt="">
    <button class="lightbox__nav lightbox__nav--next" type="button" data-lightbox-next aria-label="<?= Text::e(I18n::t('album.next')) ?>">›</button>
  </div>
  <div class="lightbox__caption" data-lightbox-caption></div>
</div>
