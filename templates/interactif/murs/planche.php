<?php
/**
 * Planche-contact : bandes de film noir, perforations, numéro et crédit imprimés dans la marge,
 * images entourées au crayon gras rouge, avec un mot griffonné (« la bonne ! »). Variables :
 * $photos, $sheet, $marks (rang => tracé et mot)
 */
use App\Front\Walls;

$strips = array_chunk($photos, 6, true);
?>
<div class="pc">
  <?php if ($marks): ?>
    <svg class="pc__defs" width="0" height="0" aria-hidden="true" focusable="false"><filter id="pc-wax" x="-5%" y="-5%" width="110%" height="110%"><feTurbulence type="fractalNoise" baseFrequency=".9" numOctaves="2" seed="4" result="n"/><feDisplacementMap in="SourceGraphic" in2="n" scale="1.8" xChannelSelector="R" yChannelSelector="G" result="d"/><feTurbulence type="fractalNoise" baseFrequency="2.2" numOctaves="1" seed="9" result="g"/><feColorMatrix in="g" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 -1.1 1.55" result="a"/><feComposite in="d" in2="a" operator="in"/></filter></svg>
  <?php endif; ?>
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
                <?php if (isset($marks[$i])): ?>
                  <svg class="pc__mark" viewBox="0 0 120 90" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path class="pc__ink" d="<?= e($marks[$i]['d']) ?>" pathLength="1"/><path class="pc__ink pc__ink--2" d="<?= e($marks[$i]['d']) ?>" pathLength="1"/></svg>
                  <span class="pc__note" aria-hidden="true" style="--r:<?= random_int(-12, -3) ?>deg"><?= e(t($marks[$i]['note'])) ?></span>
                <?php endif; ?>
              </div>
              <figcaption class="pc__edge"><b><?= $i + 1 ?></b><span>© <?= e($p['credit']) ?></span></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
