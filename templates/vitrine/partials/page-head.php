<?php
/** En-tête de page : fil d'Ariane, sur-titre, titre, accroche, image facultative. Variables : $title, $lead, $eyebrow, $image, $crumbs [[libellé, lien]] */
use App\Vitrine\Host;

$crumbs = $crumbs ?? [];
?>
<section class="vpage-head<?= !empty($image) ? ' vpage-head--img' : '' ?>">
  <?php if (!empty($image)): ?>
    <div class="vpage-head__bg" aria-hidden="true"><img src="<?= e(img($image, 1600)) ?>" srcset="<?= e(srcset($image, [800, 1200, 1600])) ?>" sizes="100vw" alt="" fetchpriority="high"></div>
  <?php endif; ?>
  <div class="wrap vpage-head__in">
    <nav class="crumbs vcrumbs" aria-label="Fil d’Ariane"><a href="<?= e(Host::url('/')) ?>">Accueil</a><?php foreach ($crumbs as [$l, $h]): ?><span aria-hidden="true">›</span><a href="<?= e($h) ?>"><?= e($l) ?></a><?php endforeach; ?></nav>
    <?php if (!empty($eyebrow)): ?><span class="eyebrow eyebrow--lg"><?= e($eyebrow) ?></span><?php endif; ?>
    <h1 class="vpage-head__t"><?= e($title) ?></h1>
    <?php if (!empty($lead)): ?><p class="vpage-head__lead"><?= e($lead) ?></p><?php endif; ?>
  </div>
</section>
