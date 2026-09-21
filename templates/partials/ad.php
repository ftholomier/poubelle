<?php
/**
 * Emplacement publicitaire.
 * Tant que le consentement n'est pas donné (ou qu'aucun identifiant n'est
 * configuré), on affiche le cadre en pointillés de la maquette.
 *
 * @var string $slot nom de l'emplacement
 */
use App\Services\Ads;
use App\Services\I18n;

if (!Ads::isEnabled($slot)) {
    return;
}
$config = Ads::slots()[$slot];
$live = Ads::isLive($slot);
?>
<div class="ad-slot" data-slot="<?= e($slot) ?>"<?= $live ? ' data-client="' . e(Ads::client()) . '"' : '' ?>>
  <div class="ad-frame" aria-hidden="true">
    <span><?= e(I18n::t('ads.placeholder')) ?> · <?= e($slot) ?></span>
    <span class="fmt"><?= e($config['format']) ?></span>
  </div>
  <?php if ($live): ?>
    <ins class="adsbygoogle" style="display:block"
         data-ad-client="<?= e(Ads::client()) ?>"
         data-ad-slot="<?= e($config['slot']) ?>"
         data-ad-format="<?= e($config['format'] === 'in-feed' ? 'fluid' : 'auto') ?>"
         data-full-width-responsive="true"></ins>
  <?php endif; ?>
</div>
