<?php
/**
 * Planche-contact : bandes de film noir, perforations, numéro et crédit imprimés dans la marge.
 * Variables : $photos, $sheet
 */
use App\Front\Walls;

$strips = array_chunk($photos, 6, true);
?>
<div class="pc">
  <div class="pc__sheet">
    <p class="pc__label"><span><?= e(t('Planche n°')) ?> <?= sprintf('%04d', (int) $sheet) ?></span><span><?= e(date_fr(date('Y-m-d'))) ?></span></p>
    <?php if (!$photos): ?><p class="wempty"><?= e(t('Aucune photo pour ce choix : essayez une autre décennie ou un autre photographe.')) ?></p><?php endif; ?>
    <?php foreach ($strips as $s => $strip): ?>
      <div class="pc__strip" style="--r:<?= random_int(-5, 5) / 10 ?>deg">
        <span class="pc__brand" aria-hidden="true">◀ 400 · SOCHAUX RÉTRO · <?= sprintf('%02d', $s + 1) ?></span>
        <div class="pc__frames">
          <?php foreach ($strip as $i => $p): ?>
            <figure class="pc__frame">
              <div class="pc__pic">
                <button type="button" class="pc__shot" <?= Walls::attrs($p, $i) ?>><img src="<?= e($p['src480']) ?>" data-loupe="<?= e($p['src800']) ?>" alt="<?= e($p['alt']) ?>" width="480" height="320" loading="lazy" decoding="async"></button>
              </div>
              <figcaption class="pc__edge"><b><?= $i + 1 ?></b><span>© <?= e($p['credit']) ?></span></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
