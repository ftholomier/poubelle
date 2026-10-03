<?php
/** Récit commun : titre (match/personne), introduction, sections, chiffre clé. Variables : $doc */
use App\Admin\Form;

$k = $doc['key_figure'] ?? [];
?>
<div class="fpanel" data-panel="recit">
  <?php if (in_array($doc['type'], ['match', 'personne'], true)): ?>
    <div class="card card--pad">
      <?= Form::text('title', 'Titre de la fiche', $doc['title'] ?? '', ['class' => 'f--full', 'hint' => $doc['type'] === 'match' ? 'automatique si vide (journée – équipes – compétition – date – score)' : 'automatique si vide (nom affiché)']) ?>
    </div>
  <?php endif; ?>
  <div class="card card--pad">
    <?= Form::html('intro', 'Introduction', $doc['intro'] ?? '', ['placeholder' => 'Quelques lignes d’introduction (facultatif)…']) ?>
  </div>
  <?= Form::repeater('sections', '', $doc['sections'] ?? [], fn ($s) => Form::text('@title', 'Intertitre', $s['title'] ?? '', ['placeholder' => 'Avant-match et enjeux', 'class' => 'f--full']) . Form::html('@html', 'Texte', $s['html'] ?? ''), ['add' => 'Ajouter un bloc de texte (avant-match, résumé, réactions, carrière…)']) ?>
  <div class="card card--pad">
    <h2 class="card__t">Encart « chiffre clé »</h2>
    <div class="fgrid">
      <?= Form::text('key_figure.number', 'Chiffre', $k['number'] ?? '', ['placeholder' => '147']) ?>
      <?= Form::text('key_figure.text', 'Légende', $k['text'] ?? '', ['class' => 'f--2', 'placeholder' => 'buts marqués en une seule saison']) ?>
    </div>
  </div>
</div>
