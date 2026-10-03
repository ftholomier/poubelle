<?php
/** Masque « objet des réserves ». Variables : $doc */
use App\Admin\Form;
use App\Data\Index;

$o = $doc['objet'] ?? [];
$cols = [];
foreach (\App\Front\Interactive::reserves() as $c) {
    $cols[$c['slug']] = $c['name'];
}
?>
<div class="fpanel" data-panel="objet">
  <div class="card card--pad">
    <?= Form::text('title', 'Nom de l’objet', $doc['title'] ?? '', ['required' => true, 'class' => 'f--full', 'placeholder' => 'Maillot porté en finale de 1988']) ?>
    <div class="fgrid">
      <?= Form::select('objet.collection', 'Collection', $o['collection'] ?? 'photos', $cols ?: ['photos' => 'Photos']) ?>
      <?= Form::number('objet.year', 'Année', $o['year'] ?? null) ?>
      <?= Form::text('objet.date_text', 'Date (texte)', $o['date_text'] ?? '', ['placeholder' => 'saison 1987-1988']) ?>
      <?= Form::text('objet.credit', 'Crédit / propriétaire', $o['credit'] ?? '', ['placeholder' => 'Collection famille Martin']) ?>
      <?= Form::text('objet.origin', 'Provenance', $o['origin'] ?? '', ['class' => 'f--2']) ?>
    </div>
    <?= Form::repeater('objet.linked', 'Fiches liées', array_map(fn ($id) => ['id' => $id, 'title' => Index::get((int) $id)['title'] ?? ('Fiche ' . $id)], $o['linked'] ?? []), fn ($r) => Form::text('@title', 'Fiche', $r['title'] ?? '', ['ac' => 'fiches', 'ac_id' => 'id']) . '<input type="hidden" data-field="id" data-type="int" value="' . e((string) ($r['id'] ?? '')) . '">', ['compact' => true, 'add' => 'Lier une fiche (match, joueur…)']) ?>
    <?php if (!empty($o['contribution'])): ?><p class="small muted" style="margin:0">Issu de la contribution <a href="/admin/contributions/<?= e($o['contribution']) ?>"><?= e($o['contribution']) ?></a>.</p><?php endif; ?>
  </div>
</div>
