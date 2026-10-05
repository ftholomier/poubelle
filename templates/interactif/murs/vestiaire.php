<?php
/**
 * Le mur du vestiaire : tirages façon Polaroid punaisés ou scotchés sur le carrelage, crédit écrit
 * à la main ; on les déplace à la souris. Variables : $prints
 */
use App\Front\Walls;
?>
<div class="vm">
  <div class="vm__wall">
    <span class="vm__stencil" aria-hidden="true">Sochaux</span>
    <?php if (!$prints): ?><p class="wempty"><?= e(t('Aucune photo pour ce choix : essayez une autre décennie ou un autre photographe.')) ?></p><?php endif; ?>
    <?php foreach ($prints as $i => $p): ?>
      <figure class="vm__print vm__print--<?= e($p['hold']) ?>" style="--r:<?= $p['rot'] ?>deg;--x:<?= (int) $p['x'] ?>px;--y:<?= (int) $p['y'] ?>px;--z:<?= (int) $p['z'] ?>;--t:<?= (int) $p['tape'] ?>deg;--i:<?= $i ?>" data-print>
        <span class="vm__hold" aria-hidden="true"></span>
        <button type="button" class="vm__shot" <?= Walls::attrs($p, $i) ?>><img src="<?= e($p['src480']) ?>" alt="<?= e($p['alt']) ?>" width="480" height="480" loading="lazy" decoding="async" draggable="false"></button>
        <figcaption class="vm__hand"><span>© <?= e($p['credit']) ?></span><?php if ($p['year']): ?><small><?= (int) $p['year'] ?></small><?php endif; ?></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</div>
