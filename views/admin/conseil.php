<?php
/**
 * Le conseil municipal : des groupes hiérarchisés, pas une liste plate.
 *
 * @var array $conseil
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$groupes = (array) ($conseil['groupes'] ?? []);
?>
<p class="bo-intro">
  Le trombinoscope est hiérarchisé : le maire, les adjoints, les conseillers
  délégués, les conseillers. C’est la délégation qui porte la carte — un
  administré qui cherche « qui s’occupe des travaux » ne connaît pas le nom.
</p>

<form class="bo-form" method="post" action="<?= url('/admin/conseil') ?>" data-form-page>
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Photo du conseil</legend>
    <div class="bo-champ">
      <label>Photo</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'photo', 'id' => 'cphoto',
          'choisie' => $conseil['photo'] ?? '', 'vide' => 'Aucune',
      ]) ?>
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="c-alt">Description de la photo</label>
      <input id="c-alt" type="text" name="photo_alt" value="<?= e($conseil['photo_alt'] ?? '') ?>">
    </div>
    <div class="bo-champ bo-champ--large">
      <label for="c-leg">Légende</label>
      <input id="c-leg" type="text" name="photo_legende" value="<?= e($conseil['photo_legende'] ?? '') ?>">
    </div>
  </fieldset>

  <?php foreach ($groupes as $rang => $groupe): ?>
    <?php $membres = (array) ($groupe['membres'] ?? []); ?>
    <section class="bo-zone">
      <div class="bo-zone__tete"><h2><?= e($groupe['titre'] ?? 'Groupe ' . ($rang + 1)) ?></h2></div>

      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="g<?= $rang ?>-sur">Sur-titre</label>
          <input id="g<?= $rang ?>-sur" type="text" name="groupe[<?= $rang ?>][surtitre]" value="<?= e($groupe['surtitre'] ?? '') ?>">
        </div>
        <div class="bo-champ">
          <label for="g<?= $rang ?>-tit">Titre du groupe</label>
          <input id="g<?= $rang ?>-tit" type="text" name="groupe[<?= $rang ?>][titre]" value="<?= e($groupe['titre'] ?? '') ?>">
        </div>
      </div>
      <div class="bo-champ bo-champ--large">
        <label for="g<?= $rang ?>-txt">Texte d’introduction</label>
        <textarea id="g<?= $rang ?>-txt" name="groupe[<?= $rang ?>][texte]" rows="2"><?= e($groupe['texte'] ?? '') ?></textarea>
      </div>
      <div class="bo-champ bo-champ--case">
        <input id="g<?= $rang ?>-fond" type="checkbox" name="groupe[<?= $rang ?>][fond]" value="1"<?= ($groupe['fond'] ?? '') === 'sombre' ? ' checked' : '' ?>>
        <label for="g<?= $rang ?>-fond">Poser ce groupe sur fond sombre</label>
      </div>

      <?php for ($j = 0; $j < count($membres) + 2; $j++): ?>
        <?php $membre = $membres[$j] ?? []; ?>
        <div class="bo-entree">
          <span class="bo-entree__rang"><?= $j + 1 ?></span>
          <div class="bo-entree__champs">
            <div class="bo-rangee">
              <div class="bo-champ">
                <label for="g<?= $rang ?>m<?= $j ?>-fct">Fonction</label>
                <input id="g<?= $rang ?>m<?= $j ?>-fct" type="text" name="groupe[<?= $rang ?>][membres][<?= $j ?>][fonction]" value="<?= e($membre['fonction'] ?? '') ?>">
              </div>
              <div class="bo-champ">
                <label for="g<?= $rang ?>m<?= $j ?>-nom">Nom</label>
                <input id="g<?= $rang ?>m<?= $j ?>-nom" type="text" name="groupe[<?= $rang ?>][membres][<?= $j ?>][nom]" value="<?= e($membre['nom'] ?? '') ?>">
              </div>
            </div>
            <div class="bo-champ bo-champ--large">
              <label for="g<?= $rang ?>m<?= $j ?>-del">Délégation</label>
              <textarea id="g<?= $rang ?>m<?= $j ?>-del" name="groupe[<?= $rang ?>][membres][<?= $j ?>][delegation]" rows="2"><?= e($membre['delegation'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      <?php endfor; ?>
      <p class="bo-aide">Un membre sans nom n’est pas enregistré : c’est ainsi qu’on en retire un.</p>
    </section>
  <?php endforeach; ?>

  <div class="bo-barre-actions">
    <button class="bo-btn" type="submit">Enregistrer le conseil</button>
    <a class="bo-btn bo-btn--fantome" href="<?= url('/admin/avance?nom=conseil') ?>">Éditeur avancé</a>
  </div>
</form>
