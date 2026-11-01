<?php
/**
 * Galerie en mosaïque (maquette « Galerie du match ») + agrandissement.
 * Variables : $items (image, caption, credit), $title, $anchor (id), $context (complément de légende), $light (fond clair)
 */
$items = array_values(array_filter($items ?? [], fn ($g) => !empty($g['image'])));
if (!$items) {
    return;
}
$credit = gallery_credit($items);
// Motif de la maquette : [lignes, colonnes] répété.
$pattern = [[2, 2], [1, 1], [1, 1], [2, 1], [1, 2], [1, 1], [1, 1], [1, 2], [2, 1]];
$n = count($items);
?>
<section class="gallery<?= !empty($light) ? ' gallery--light' : '' ?>" id="<?= e($anchor ?? 'galerie') ?>">
  <div class="wrap gallery__inner">
    <div class="gallery__head">
      <h2 class="h-section"><?= e($title ?? t('Galerie')) ?></h2>
      <?php if ($credit): ?><span class="gallery__credit"><?= e(t('Photos')) ?> : <?= e($credit) ?></span><?php endif; ?>
    </div>
    <div class="gallery__grid gallery__grid--<?= $n < 4 ? $n : 'n' ?>" data-gallery>
      <?php foreach ($items as $i => $g):
          [$r, $c] = $n >= 4 ? $pattern[$i % count($pattern)] : [1, 1];
          // Photo en basse définition : jamais une grande case de la mosaïque, et montrée à sa taille
          // réelle (nette, sur un fond flou) si même sa case la pixelliserait. Tailles des cases sur
          // grand écran (colonnes d'environ 300 px, lignes de 200 px) et sur téléphone (358 × 240).
          if (($r > 1 || $c > 1) && img_lowres($g['image'], $c > 1 ? 606 : 300, $r > 1 ? 412 : 200)) {
              [$r, $c] = [1, 1];
          }
          $low = $n === 1 ? img_lowres($g['image'], 860)
              : img_lowres($g['image'], max(358, $c > 1 ? 606 : 300), max($n < 4 ? 320 : 240, $r > 1 ? 412 : 200));
          $cap = trim((string) ($g['caption'] ?? ''));
          $full = trim($cap . (!$credit && !empty($g['credit']) ? ' – ' . $g['credit'] : ''));
          $lbCap = trim($full . (!empty($context) ? ' · ' . $context : ''), ' ·');
          $w = ($r > 1 || $c > 1) ? 800 : 480;
      ?>
      <a class="gallery__item<?= $low ? ' is-lowres' : '' ?>" style="grid-row:span <?= $r ?>;grid-column:span <?= $c ?><?= $low ? ';' . $low['style'] : '' ?>" href="<?= e(img($g['image'], 1600)) ?>" data-lb data-caption="<?= e($lbCap) ?>">
        <?= $low ? $low['blur'] : '' ?><img src="<?= e(img($g['image'], $w)) ?>" srcset="<?= e(srcset($g['image'], [320, 480, 800, 1200])) ?>" sizes="(max-width: 700px) 100vw, <?= $c > 1 ? '50vw' : '25vw' ?>" alt="<?= e($cap ?: ($context ?? '')) ?>" loading="lazy" decoding="async">
        <span class="gallery__zoom"><?= e(t('Agrandir')) ?></span>
        <?php if ($full !== ''): ?><span class="gallery__cap"><?= e($full) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
