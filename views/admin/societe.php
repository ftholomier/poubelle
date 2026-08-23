<?php
/**
 * Page « La société ».
 *
 * @var array $societe
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;
?>
<form class="bo-form" method="post" action="<?= url('/admin/societe') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="c-hsur">Sur-titre</label>
        <input id="c-hsur" type="text" name="hero_surtitre" value="<?= e($societe['hero']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="c-htit">Titre principal (H1)</label>
        <input id="c-htit" type="text" name="hero_titre" value="<?= e($societe['hero']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="c-htex">Texte d’introduction</label>
      <textarea id="c-htex" name="hero_texte" rows="3"><?= e($societe['hero']['texte']) ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'chero',
          'choisie' => $societe['hero']['image'], 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Notre histoire</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="c-isur">Sur-titre</label>
        <input id="c-isur" type="text" name="histoire_surtitre" value="<?= e($societe['histoire']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="c-itit">Titre</label>
        <input id="c-itit" type="text" name="histoire_titre" value="<?= e($societe['histoire']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="c-itex">Texte</label>
      <textarea id="c-itex" name="histoire_texte" rows="6"><?= e(implode("\n\n", $societe['histoire']['paragraphes'])) ?></textarea>
      <p class="bo-aide">Une ligne vide sépare deux paragraphes.</p>
    </div>
  </fieldset>

  <?php foreach ($societe['piliers']['items'] as $i => $pilier): ?>
    <fieldset>
      <legend>Pilier <?= e($pilier['numero']) ?></legend>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="c-pl<?= $i ?>">Étiquette</label>
          <input id="c-pl<?= $i ?>" type="text" name="pilier_label_<?= $i ?>" value="<?= e($pilier['label']) ?>">
        </div>
        <div class="bo-champ">
          <label for="c-pt<?= $i ?>">Titre</label>
          <input id="c-pt<?= $i ?>" type="text" name="pilier_titre_<?= $i ?>" value="<?= e($pilier['titre']) ?>">
        </div>
      </div>
      <div class="bo-champ">
        <label for="c-px<?= $i ?>">Texte</label>
        <textarea id="c-px<?= $i ?>" name="pilier_texte_<?= $i ?>" rows="3"><?= e($pilier['texte']) ?></textarea>
      </div>
      <div class="bo-champ">
        <label>Photo</label>
        <?= $view->partial('admin/choix-photo', [
            'medias' => $medias, 'nom' => 'pilier_image_' . $i, 'id' => 'pil' . $i,
            'choisie' => $pilier['image'], 'vide' => '',
        ]) ?>
      </div>
    </fieldset>
  <?php endforeach; ?>

  <fieldset>
    <legend>Nos clients</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="c-clsur">Sur-titre</label>
        <input id="c-clsur" type="text" name="clients_surtitre" value="<?= e($societe['clients']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="c-cltit">Titre</label>
        <input id="c-cltit" type="text" name="clients_titre" value="<?= e($societe['clients']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="c-cltex">Texte</label>
      <textarea id="c-cltex" name="clients_texte" rows="2"><?= e($societe['clients']['texte']) ?></textarea>
    </div>

    <?php foreach ($societe['clients']['items'] as $i => $client): ?>
      <div class="bo-rangee">
        <div class="bo-champ">
          <label for="c-cn<?= $i ?>">Type de client <?= $i + 1 ?></label>
          <input id="c-cn<?= $i ?>" type="text" name="client_nom_<?= $i ?>" value="<?= e($client['nom']) ?>">
        </div>
        <div class="bo-champ bo-champ--large">
          <label for="c-cx<?= $i ?>">Description</label>
          <input id="c-cx<?= $i ?>" type="text" name="client_texte_<?= $i ?>" value="<?= e($client['texte']) ?>">
        </div>
      </div>
    <?php endforeach; ?>
    <p class="bo-aide">
      Pour ajouter ou retirer un type de client, passez par
      l’<a href="<?= url('/admin/avance?nom=pages/la-societe') ?>">Éditeur avancé</a>.
    </p>
  </fieldset>

  <fieldset>
    <legend>Notre engagement</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="c-esur">Sur-titre</label>
        <input id="c-esur" type="text" name="engagement_surtitre" value="<?= e($societe['engagement']['surtitre']) ?>">
      </div>
      <div class="bo-champ">
        <label for="c-etit">Titre</label>
        <input id="c-etit" type="text" name="engagement_titre" value="<?= e($societe['engagement']['titre']) ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="c-etex">Texte</label>
      <textarea id="c-etex" name="engagement_texte" rows="5"><?= e(implode("\n\n", $societe['engagement']['paragraphes'])) ?></textarea>
    </div>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="c-meta">Description affichée dans Google</label>
      <textarea id="c-meta" name="meta_description" rows="2"><?= e($societe['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
