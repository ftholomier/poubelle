<?php
/**
 * Valeurs : liste et édition sur un seul écran.
 *
 * Les textes sont courts et peu nombreux : une fiche par valeur ferait faire
 * six allers-retours pour une relecture d'ensemble.
 *
 * @var array $valeurs
 * @var array $pageIntro
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Content;
use App\Core\Csrf;

$items = $valeurs['items'] ?? [];
$dernier = count($items) - 1;
?>
<p class="bo-aide">
  Les valeurs publiées apparaissent sur la page « Nos valeurs » et, en aperçu,
  sur la page d’accueil. Le premier paragraphe sert de résumé sur l’accueil.
</p>

<form class="bo-form" method="post" action="<?= url('/admin/valeurs') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <?php if ($items === []): ?>
    <p class="bo-vide">Aucune valeur pour l’instant. Créez-en une ci-dessous.</p>
  <?php endif; ?>

  <?php foreach ($items as $rang => $valeur):
      $slug = (string) $valeur['slug'];
      $publie = Content::estPublie($valeur); ?>
    <fieldset<?= $publie ? '' : ' class="bo-ligne--masquee"' ?>>
      <legend>
        <?= e($valeur['nom']) ?><?= $publie ? '' : ' — hors ligne' ?>
      </legend>

      <div class="bo-champ">
        <label for="v-n<?= $rang ?>">Nom</label>
        <input id="v-n<?= $rang ?>" type="text" name="nom_<?= e($slug) ?>" value="<?= e($valeur['nom']) ?>">
      </div>
      <div class="bo-champ">
        <label for="v-r<?= $rang ?>">Résumé</label>
        <textarea id="v-r<?= $rang ?>" name="resume_<?= e($slug) ?>" rows="2"><?= e($valeur['resume'] ?? '') ?></textarea>
        <p class="bo-aide">Une phrase, affichée sur la page d’accueil. Trois lignes au plus.</p>
      </div>
      <div class="bo-champ">
        <label for="v-t<?= $rang ?>">Texte complet</label>
        <textarea id="v-t<?= $rang ?>" name="texte_<?= e($slug) ?>" rows="6"><?= e(implode("\n\n", $valeur['paragraphes'] ?? [])) ?></textarea>
        <p class="bo-aide">Une ligne vide sépare deux paragraphes.</p>
      </div>
      <div class="bo-champ">
        <label>Photo</label>
        <?= $view->partial('admin/choix-photo', [
            'medias' => $medias, 'nom' => 'image_' . $slug, 'id' => 'val' . $rang,
            'choisie' => $valeur['image'] ?? '', 'vide' => 'Aucune',
        ]) ?>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <?php if ($items !== []): ?>
    <button class="bo-btn" type="submit">Enregistrer les valeurs</button>
  <?php endif; ?>
</form>

<?php if ($items !== []): ?>
<div class="bo-bloc">
  <h2>Ordre et publication</h2>
  <ul class="bo-liste">
    <?php foreach ($items as $rang => $valeur):
        $publie = Content::estPublie($valeur); ?>
      <li class="bo-ligne<?= $publie ? '' : ' bo-ligne--masquee' ?>">
        <div class="bo-ligne__corps">
          <strong><?= e($valeur['nom']) ?></strong>
          <span class="bo-ligne__note"><?= $publie ? 'En ligne' : 'Hors ligne' ?></span>
        </div>
        <div class="bo-ligne__actions">
          <form method="post" action="<?= url('/admin/valeurs/' . $valeur['slug'] . '/publication') ?>">
            <?= Csrf::champ() ?>
            <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit">
              <?= $publie ? 'Retirer du site' : 'Publier' ?>
            </button>
          </form>
          <form method="post" action="<?= url('/admin/valeurs/' . $valeur['slug'] . '/ordre') ?>">
            <?= Csrf::champ() ?>
            <input type="hidden" name="sens" value="haut">
            <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit"
                    aria-label="Monter <?= e($valeur['nom']) ?>"<?= $rang === 0 ? ' disabled' : '' ?>>↑</button>
          </form>
          <form method="post" action="<?= url('/admin/valeurs/' . $valeur['slug'] . '/ordre') ?>">
            <?= Csrf::champ() ?>
            <input type="hidden" name="sens" value="bas">
            <button class="bo-btn bo-btn--petit bo-btn--fantome" type="submit"
                    aria-label="Descendre <?= e($valeur['nom']) ?>"<?= $rang === $dernier ? ' disabled' : '' ?>>↓</button>
          </form>
          <form method="post" action="<?= url('/admin/valeurs/' . $valeur['slug'] . '/supprimer') ?>"
                data-confirmer="Supprimer la valeur « <?= e($valeur['nom']) ?> » ?">
            <?= Csrf::champ() ?>
            <button class="bo-btn bo-btn--petit bo-btn--danger" type="submit">Supprimer</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="bo-bloc">
  <h2>Ajouter une valeur</h2>
  <form class="bo-form bo-form--inline" method="post" action="<?= url('/admin/valeurs/creer') ?>">
    <?= Csrf::champ() ?>
    <div class="bo-champ">
      <label for="v-nom" class="bo-visuellement-cache">Nom de la valeur</label>
      <input id="v-nom" type="text" name="nom" placeholder="Proximité" required>
    </div>
    <button class="bo-btn" type="submit">Créer</button>
  </form>
</div>

<form class="bo-form" method="post" action="<?= url('/admin/valeurs/entete') ?>">
  <?= Csrf::champ() ?>
  <fieldset>
    <legend>En-tête de la page « Nos valeurs »</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="pv-sur">Sur-titre</label>
        <input id="pv-sur" type="text" name="hero_surtitre" value="<?= e($pageIntro['hero']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="pv-tit">Titre principal (H1)</label>
        <input id="pv-tit" type="text" name="hero_titre" value="<?= e($pageIntro['hero']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="pv-tex">Texte d’introduction</label>
      <textarea id="pv-tex" name="hero_texte" rows="3"><?= e($pageIntro['hero']['texte']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label for="pv-meta">Description affichée dans Google</label>
      <textarea id="pv-meta" name="meta_description" rows="2"><?= e($pageIntro['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>
  <button class="bo-btn" type="submit">Enregistrer l’en-tête</button>
</form>
