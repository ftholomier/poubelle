<?php
/**
 * Liste des pages du site, groupées comme le menu.
 *
 * @var array $pages
 */
?>
<p class="bo-intro">
  Chaque page est faite d’un bandeau et d’une suite de blocs. Ouvrez-en une pour
  modifier son texte, ses photos et ses liens. Les fiches de démarche et les
  actualités se modifient depuis leurs écrans dédiés, et les listes — élus,
  associations, numéros, documents — depuis les leurs.
</p>

<?php foreach ($pages as $groupe => $liste): ?>
  <section class="bo-zone">
    <div class="bo-zone__tete"><h2><?= e($groupe) ?></h2></div>
    <ul class="bo-liste">
      <?php foreach ($liste as $cle => $infos): ?>
        <li class="bo-ligne">
          <div class="bo-ligne__corps">
            <a class="bo-ligne__nom" href="<?= url('/admin/pages/' . $cle) ?>"><?= e($infos['nom']) ?></a>
            <p class="bo-ligne__note">
              <code><?= e($infos['chemin']) ?></code>
              <span aria-hidden="true"> · </span>
              <?= $infos['blocs'] ?> bloc<?= $infos['blocs'] > 1 ? 's' : '' ?>
            </p>
          </div>
          <div class="bo-ligne__actions">
            <a class="bo-btn bo-btn--petit bo-btn--contour" href="<?= url('/admin/pages/' . $cle) ?>">Modifier</a>
            <a class="bo-btn bo-btn--petit bo-btn--fantome" href="<?= url($infos['chemin']) ?>" target="_blank" rel="noopener">Voir ↗</a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
