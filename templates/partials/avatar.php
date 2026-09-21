<?php
/**
 * Vignette d'une fiche : la vraie image quand on l'a, la tuile à initiales
 * sinon. Les fichiers vivent hors racine web et passent par /media.
 *
 * @var string $name   nom affiché (sert aux initiales et à l'alternative)
 * @var string $size   classe de taille (tile-40, tile-48, tile-64, tile-92…)
 * @var string $kind   'photo' | 'logo'
 * @var string $id     identifiant de la fiche, '' si pas d'image
 * @var int    $chars  nombre d'initiales
 */
$color = tile_color($name);
$has = ($id ?? '') !== '';
?>
<?php if ($has): ?>
  <span class="tile <?= e($size) ?> tile-media" style="background:<?= e($color) ?>">
    <img src="/media/<?= e($kind) ?>/<?= e($id) ?>" alt="" loading="lazy" decoding="async">
  </span>
<?php else: ?>
  <span class="tile <?= e($size) ?>" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
    <?= e(initials($name, $chars ?? 2)) ?>
  </span>
<?php endif; ?>
