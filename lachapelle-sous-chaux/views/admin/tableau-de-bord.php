<?php
/**
 * Tableau de bord.
 *
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
  <a href="<?= url('/admin/site') ?>">→ Modifier le téléphone, l’adresse, les horaires ou le menu</a>
  <a href="<?= url('/admin/accueil') ?>">→ Modifier les textes de la page d’accueil</a>
  <a href="<?= url('/admin/services') ?>">→ Ajouter ou modifier un service</a>
  <a href="<?= url('/admin/apparence') ?>">→ Changer la disposition du menu (burger ou barre horizontale)</a>
  <a href="<?= url('/admin/avis') ?>">→ Connecter les avis Google, ou les actualiser</a>
  <a href="<?= url('/admin/photos') ?>">→ Envoyer des photos</a>
  <a href="<?= url('/admin/referencement') ?>">→ Référencement : adresses des pages, titres et descriptions Google</a>
  <a href="<?= url('/admin/mises-a-jour') ?>">→ Mettre le site à jour depuis le dépôt git</a>
  <a href="<?= url('/admin/avance') ?>">→ Éditeur avancé (tous les contenus, sauvegardes et restauration)</a>
</div>
