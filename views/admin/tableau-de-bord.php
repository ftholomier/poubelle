<?php
/**
 * Tableau de bord.
 * @var array $stats
 */
?>
<div class="bo-stats">
  <?php foreach ($stats as $s): ?>
    <a class="bo-stat" href="<?= url($s['url']) ?>">
      <strong><?= e($s['valeur']) ?></strong>
      <span><?= e($s['libelle']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="bo-raccourcis">
  <a href="<?= url('/admin/site') ?>">→ Modifier le téléphone, l'adresse ou les liens de réservation</a>
  <a href="<?= url('/admin/accueil') ?>">→ Modifier les textes de la page d'accueil</a>
  <a href="<?= url('/admin/galerie') ?>">→ Ajouter des photos à la galerie</a>
  <a href="<?= url('/admin/avance') ?>">→ Éditeur avancé (tous les contenus, sauvegardes et restauration)</a>
</div>
