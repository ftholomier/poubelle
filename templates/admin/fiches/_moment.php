<?php
/**
 * Masque « moment du centenaire » : titre, année, date de l'événement (date anniversaire
 * proposée pour la parution), fiches liées ; numéro (ordre des dates) ; premier jet de l'IA à
 * vérifier, avec ses sources et ses points de vigilance (rien de tout cela n'est montré au
 * public). Variables : $doc
 */
use App\Admin\Form;
use App\Data\Fiches;
use App\Data\Index;
use App\Services\Moments;

$mo = $doc['moment'] ?? [];
$ai = is_array($mo['ai'] ?? null) ? $mo['ai'] : null;
$validated = is_array($mo['validated'] ?? null) ? $mo['validated'] : null;
$number = (int) ($mo['number'] ?? 0);
$online = Fiches::isVisible($doc);
$dated = Moments::dateOf($doc) !== null;
$taken = array_values(array_filter(array_map(fn ($r) => $r['date'] && $r['id'] !== (int) ($doc['id'] ?? 0) ? substr($r['date'], 0, 10) : null, Moments::all())));
$suggest = !$online ? Moments::anniversary(Moments::eventDate($doc), $taken) : null;
$link = fn (int $id): string => ($s = Index::get($id)) ? '<a href="/admin/fiche/' . $id . '" target="_blank" rel="noopener">' . e($s['title']) . '</a>' : 'fiche n° ' . $id;
?>
<div class="fpanel" data-panel="moment">
  <?php if ($ai && !$validated): ?>
    <div class="alert alert--info" role="note">
      <b>Premier jet rédigé par l’IA</b> le <?= e(date_fr((string) ($ai['at'] ?? ''))) ?>, à partir de <?= count($ai['sources'] ?? []) ?> fiche<?= count($ai['sources'] ?? []) > 1 ? 's' : '' ?> du musée : à relire et vérifier avant de valider. Rien n’est en ligne tant que vous ne l’avez pas daté (Planifié) ou publié.
      <?php if (!empty($ai['sources'])): ?><br><span class="small">Sources : <?= implode(', ', array_map(fn ($id) => $link((int) $id), $ai['sources'])) ?>.</span><?php endif; ?>
      <?php if (!empty($ai['checks'])): ?>
        <span class="small" style="display:block;margin-top:6px"><b>Points à vérifier :</b></span>
        <ul class="small" style="margin:4px 0 0;padding-left:20px"><?php foreach ($ai['checks'] as $c): ?><li><?= e((string) $c) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if (!empty($ai['caption'])): ?><span class="small" style="display:block;margin-top:6px">Légende proposée pour la photo : « <?= e((string) $ai['caption']) ?> »</span><?php endif; ?>
    </div>
  <?php elseif ($ai && $validated): ?>
    <p class="xs muted" style="margin:0">Premier jet de l’IA (<?= e(date_num(substr((string) ($ai['at'] ?? ''), 0, 10))) ?>), relu et validé par <?= e((string) $validated['by']) ?> le <?= e(date_num(substr((string) $validated['at'], 0, 10))) ?>. Rien n’en est indiqué sur le site.</p>
  <?php endif; ?>
  <div class="card card--pad">
    <?= Form::text('title', 'Titre du moment', $doc['title'] ?? '', ['required' => true, 'class' => 'f--full', 'proof' => 'title']) ?>
    <div class="fgrid">
      <?= Form::number('moment.year', 'Année du moment', $mo['year'] ?? null) ?>
      <?= Form::text('moment.event_date', 'Date de l’événement', $mo['event_date'] ?? '', ['type' => 'date', 'hint' => 'facultatif : sert à proposer la date anniversaire']) ?>
    </div>
    <div class="f">
      <span class="f__k">Numéro et parution</span>
      <span class="small"><?php
        if ($online && $number) {
            echo '<b>N° ' . $number . ' / 100</b>, en ligne : ce numéro ne change plus.';
        } elseif ($dated && $number) {
            echo '<b>N° ' . $number . ' / 100</b> (provisoire) : il suit l’ordre des dates de parution et peut changer si un autre moment est daté avant celui-ci.';
        } elseif ($dated) {
            echo '<span class="ko">Au-delà des 100 moments datés : pas de numéro.</span>';
        } else {
            echo 'Pas encore de numéro : il est donné à la validation, selon la date de parution choisie.';
        }
      ?></span>
      <?php if (!$online): $planned = $dated ? substr((string) Moments::dateOf($doc), 0, 10) : null; ?>
        <?php if ($planned): ?>
          <span class="f__help">Parution prévue le <b><?= e(date_fr($planned)) ?></b> : le moment est validé. La date se change dans le panneau Publication, ou ci-dessous.</span>
        <?php else: ?>
          <span class="f__help">Pour valider : statut <b>Planifié</b> et date de parution (panneau Publication), puis Enregistrer. Une seule validation suffit.</span>
        <?php endif; ?>
        <div class="row" style="gap:8px;margin-top:6px">
          <?php if ($suggest && $suggest['date'] !== $planned): ?><button type="button" class="btn btn--sm btn--navy" data-plan-at="<?= e($suggest['date'] . 'T' . Moments::HOUR) ?>"><?= $planned ? 'Paraître plutôt à la date anniversaire' : 'Planifier à la date anniversaire' ?> : <?= e(date_fr($suggest['date'])) ?> (<?= (int) $suggest['years'] ?> ans<?= $suggest['taken'] ? ', jour déjà pris' : '' ?>)</button><?php endif; ?>
          <button type="button" class="btn btn--sm" data-plan-at=""><?= $planned ? 'Changer la date…' : 'Choisir une autre date…' ?></button>
        </div>
      <?php endif; ?>
    </div>
    <?= Form::repeater('moment.linked', 'Fiches liées', array_map(fn ($id) => ['id' => $id, 'title' => Index::get((int) $id)['title'] ?? ('Fiche ' . $id)], $mo['linked'] ?? []), fn ($r) => Form::text('@title', 'Fiche', $r['title'] ?? '', ['ac' => 'fiches', 'ac_id' => 'id']) . '<input type="hidden" data-field="id" data-type="int" value="' . e((string) ($r['id'] ?? '')) . '">', ['compact' => true, 'add' => 'Lier une fiche']) ?>
  </div>
</div>
