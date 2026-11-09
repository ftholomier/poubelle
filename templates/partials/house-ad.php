<?php
/** Bannière maison, sous l'en-tête de toutes les pages publiques. */
use App\Services\HouseAd;

$ad = HouseAd::current();
if ($ad === null) {
    return;
}
[$w, $h] = HouseAd::dims($ad['desktop']);
?>
<aside class="house-ad" aria-label="Publicité">
  <a href="<?= e($ad['url']) ?>" target="_blank" rel="sponsored noopener">
    <picture>
      <?php if ($ad['mobile'] !== ''): ?>
        <source media="(max-width: 640px)" srcset="<?= e(HouseAd::src($ad['mobile'])) ?>">
      <?php endif; ?>
      <img src="<?= e(HouseAd::src($ad['desktop'])) ?>" alt="<?= e($ad['alt']) ?>"
           <?= $w > 0 ? 'width="' . $w . '" height="' . $h . '"' : '' ?> decoding="async">
    </picture>
  </a>
</aside>
