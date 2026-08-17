<?php
/**
 * Apparence : choix de la disposition du menu.
 *
 * Chaque option est illustrée par un schéma en CSS pur plutôt que par une
 * capture d'écran : le client voit tout de suite la différence, et le schéma
 * ne se périme pas quand le site évolue.
 *
 * @var array $menus
 * @var string $courant
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/apparence') ?>">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Disposition du menu</legend>
    <p class="bo-aide">
      Le changement s’applique immédiatement sur tout le site. Vous pouvez
      revenir en arrière à tout moment : les deux dispositions affichent les
      mêmes rubriques, réglées dans
      <a href="<?= url('/admin/site') ?>">Coordonnées &amp; menu</a>.
    </p>

    <div class="bo-options">
      <?php foreach ($menus as $cle => $menu): ?>
        <label class="bo-option<?= $courant === $cle ? ' bo-option--active' : '' ?>">
          <input type="radio" name="menu" value="<?= e($cle) ?>"<?= $courant === $cle ? ' checked' : '' ?>>

          <span class="bo-apercu bo-apercu--<?= e($cle) ?>" aria-hidden="true">
            <?php if ($cle === 'lateral'): ?>
              <span class="bo-apercu__barre">
                <span class="bo-apercu__burger"><i></i><i></i><i></i></span>
                <span class="bo-apercu__logo"></span>
                <span class="bo-apercu__cta"></span>
              </span>
              <span class="bo-apercu__panneau">
                <i></i><i></i><i></i><i></i>
              </span>
            <?php else: ?>
              <span class="bo-apercu__barre">
                <span class="bo-apercu__logo bo-apercu__logo--gauche"></span>
                <span class="bo-apercu__liens"><i></i><i></i><i></i><i></i></span>
                <span class="bo-apercu__cta"></span>
              </span>
            <?php endif; ?>
          </span>

          <span class="bo-option__corps">
            <strong><?= e($menu['nom']) ?></strong>
            <span class="bo-option__resume"><?= e($menu['resume']) ?></span>
            <span class="bo-option__detail"><?= e($menu['detail']) ?></span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer la disposition</button>
</form>
