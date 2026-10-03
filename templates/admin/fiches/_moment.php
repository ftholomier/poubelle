<?php
/** Masque « moment du centenaire ». Variables : $doc */
use App\Admin\Form;
use App\Data\Index;

$mo = $doc['moment'] ?? [];
?>
<div class="fpanel" data-panel="moment">
  <div class="card card--pad">
    <?= Form::text('title', 'Titre du moment', $doc['title'] ?? '', ['required' => true, 'class' => 'f--full', 'proof' => 'title']) ?>
    <div class="fgrid">
      <?= Form::number('moment.number', 'Numéro (1 à 100)', $mo['number'] ?? null) ?>
      <?= Form::number('moment.year', 'Année du moment', $mo['year'] ?? null) ?>
    </div>
    <span class="f__help">La date de mise en ligne se règle avec le statut « Planifié » (panneau Publication). Un moment par semaine, jusqu’au 20 mai 2028 : voir le calendrier dans « 100 moments ».</span>
    <?= Form::repeater('moment.linked', 'Fiches liées', array_map(fn ($id) => ['id' => $id, 'title' => Index::get((int) $id)['title'] ?? ('Fiche ' . $id)], $mo['linked'] ?? []), fn ($r) => Form::text('@title', 'Fiche', $r['title'] ?? '', ['ac' => 'fiches', 'ac_id' => 'id']) . '<input type="hidden" data-field="id" data-type="int" value="' . e((string) ($r['id'] ?? '')) . '">', ['compact' => true, 'add' => 'Lier une fiche']) ?>
  </div>
</div>
