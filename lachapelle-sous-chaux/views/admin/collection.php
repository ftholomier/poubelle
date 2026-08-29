<?php
/**
 * Liste des fiches d'une collection : démarches, actualités.
 *
 * @var string $collection
 * @var array  $reglages
 * @var array  $donnees
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$items = $donnees['items'] ?? [];
$cleTitre = $reglages['titre'];
?>
<p class="bo-intro">
  <?= $collection === 'demarches'
      ? 'Chaque fiche décrit une démarche : les pièces à fournir, le guichet, le délai, le coût et les téléservices. La famille décide de la rubrique dans laquelle elle apparaît.'
      : 'Chaque actualité a sa page. La plus récente s’affiche en grand sur la page « Actualités » et sur l’accueil.' ?>
</p>

<section class="bo-zone">
  <div class="bo-zone__tete"><h2>Introduction de la page</h2></div>
  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/' . $collection . '/intro') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ bo-champ--large">
      <label for="c-intro">Texte d’introduction</label>
      <textarea id="c-intro" name="intro" rows="3"><?= e($donnees['intro'] ?? '') ?></textarea>
    </div>
    <button class="bo-btn bo-btn--petit" type="submit">Enregistrer</button>
  </form>
</section>

<section class="bo-zone">
  <div class="bo-zone__tete">
    <h2><?= count($items) ?> fiche<?= count($items) > 1 ? 's' : '' ?></h2>
  </div>

  <ul class="bo-liste">
    <?php foreach ($items as $rang => $item): ?>
      <?php $enLigne = (bool) ($item['actif'] ?? true); ?>
      <li class="bo-ligne<?= $enLigne ? '' : ' bo-ligne--hors-ligne' ?>">
        <div class="bo-ligne__corps">
          <a class="bo-ligne__nom" href="<?= url('/admin/' . $collection . '/' . $item['slug']) ?>">
            <?= e((string) ($item[$cleTitre] ?? $item['slug'])) ?>
          </a>
          <p class="bo-ligne__note">
            <code>/<?= e($collection) ?>/<?= e($item['slug']) ?></code>
            <?php if (($item['famille'] ?? '') !== ''): ?>
              <span aria-hidden="true"> · </span><?= e($item['famille']) ?>
            <?php endif; ?>
            <?php if (($item['date'] ?? '') !== ''): ?>
              <span aria-hidden="true"> · </span><?= e(date_texte((string) $item['date'])) ?>
            <?php endif; ?>
            <?php if (!$enLigne): ?><span class="bo-etiquette bo-etiquette--alerte">Hors ligne</span><?php endif; ?>
          </p>
        </div>

        <div class="bo-ligne__actions">
          <form method="post" action="<?= url('/admin/' . $collection . '/' . $item['slug'] . '/ordre') ?>">
            <?= Csrf::champ() ?>
            <button class="bo-btn bo-btn--mince" name="sens" value="haut" title="Monter"<?= $rang === 0 ? ' disabled' : '' ?>>↑</button>
            <button class="bo-btn bo-btn--mince" name="sens" value="bas" title="Descendre"<?= $rang === count($items) - 1 ? ' disabled' : '' ?>>↓</button>
          </form>
          <form method="post" action="<?= url('/admin/' . $collection . '/' . $item['slug'] . '/publication') ?>">
            <?= Csrf::champ() ?>
            <button class="bo-btn bo-btn--petit bo-btn--contour" type="submit"><?= $enLigne ? 'Retirer' : 'Publier' ?></button>
          </form>
          <a class="bo-btn bo-btn--petit bo-btn--contour" href="<?= url('/admin/' . $collection . '/' . $item['slug']) ?>">Modifier</a>
          <form method="post" action="<?= url('/admin/' . $collection . '/' . $item['slug'] . '/supprimer') ?>"
                onsubmit="return confirm('Supprimer cette fiche ? La version précédente reste restaurable depuis l’Éditeur avancé.')">
            <?= Csrf::champ() ?>
            <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Supprimer</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="bo-zone">
  <div class="bo-zone__tete"><h2>Créer une <?= e($reglages['singulier']) ?></h2></div>
  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/' . $collection . '/creer') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="c-nom">Titre</label>
      <input id="c-nom" type="text" name="nom" required>
    </div>
    <button class="bo-btn" type="submit">Créer</button>
    <p class="bo-aide">La fiche est créée hors ligne : complétez-la, puis publiez-la.</p>
  </form>
</section>
