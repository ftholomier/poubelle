<?php
/**
 * Écran « Questions fréquentes ».
 *
 * @var array $faq
 * @var string[] $medias
 * @var App\Core\View $view
 */
use App\Core\Csrf;

$questions = implode("\n\n", array_map(
    static fn(array $q): string => ($q['question'] ?? '') . ' || ' . ($q['reponse'] ?? ''),
    $faq['faq']['items'] ?? []
));
?>
<p class="bo-aide">
  Les questions fréquentes ont leur propre page et leur entrée de menu.
  C’est souvent la page la plus lue avant une demande de devis : chaque
  question y est aussi lisible par les moteurs de recherche.
</p>

<form class="bo-form" method="post" action="<?= url('/admin/faq') ?>" enctype="multipart/form-data">
  <?= Csrf::champ() ?>

  <fieldset>
    <legend>Bandeau</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="q-hsur">Sur-titre</label>
        <input id="q-hsur" type="text" name="hero_surtitre" value="<?= e($faq['hero']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="q-htit">Titre principal (H1)</label>
        <input id="q-htit" type="text" name="hero_titre" value="<?= e($faq['hero']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="q-htex">Texte d’introduction</label>
      <textarea id="q-htex" name="hero_texte" rows="3"><?= e($faq['hero']['texte'] ?? '') ?></textarea>
    </div>
    <div class="bo-champ">
      <label>Photo du bandeau</label>
      <?= $view->partial('admin/choix-photo', [
          'medias' => $medias, 'nom' => 'hero_image', 'id' => 'faqhero',
          'choisie' => $faq['hero']['image'] ?? '', 'vide' => '',
      ]) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Questions</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="q-sur">Sur-titre de la section</label>
        <input id="q-sur" type="text" name="faq_surtitre" value="<?= e($faq['faq']['surtitre'] ?? '') ?>">
      </div>
      <div class="bo-champ">
        <label for="q-tit">Titre de la section</label>
        <input id="q-tit" type="text" name="faq_titre" value="<?= e($faq['faq']['titre'] ?? '') ?>">
      </div>
    </div>
    <div class="bo-champ">
      <label for="q-items">Questions et réponses</label>
      <textarea id="q-items" name="faq_items" rows="18"><?= e($questions) ?></textarea>
      <p class="bo-aide">
        Une question par bloc, sous la forme <code>Question || Réponse</code>
        (deux barres verticales). Séparez les blocs par une ligne vide.
        L’ordre de saisie est l’ordre d’affichage.
      </p>
    </div>
  </fieldset>

  <fieldset>
    <legend>Encadré de relance</legend>
    <div class="bo-rangee">
      <div class="bo-champ">
        <label for="q-rtit">Titre</label>
        <input id="q-rtit" type="text" name="faq_relance_titre" value="<?= e($faq['faq']['relance']['titre'] ?? '') ?>">
      </div>
      <div class="bo-champ bo-champ--large">
        <label for="q-rtex">Texte</label>
        <input id="q-rtex" type="text" name="faq_relance_texte" value="<?= e($faq['faq']['relance']['texte'] ?? '') ?>">
      </div>
    </div>
    <p class="bo-aide">Affiché sous les questions, avec le bouton d’appel. Videz le titre pour le retirer.</p>
  </fieldset>

  <fieldset>
    <legend>Référencement</legend>
    <div class="bo-champ">
      <label for="q-meta">Description affichée dans Google</label>
      <textarea id="q-meta" name="meta_description" rows="2"><?= e($faq['meta']['description'] ?? '') ?></textarea>
    </div>
  </fieldset>

  <button class="bo-btn" type="submit">Enregistrer</button>
</form>
