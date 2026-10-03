<?php
/** Masque « match » : infos, composition et événements. Variables : $doc, $lineup */
use App\Admin\FicheForm;
use App\Admin\Form;

$m = $doc['match'];
$home = (bool) ($m['sochaux_home'] ?? true);
$opp = $home ? ($m['away'] ?? []) : ($m['home'] ?? []);
$us = $home ? ($m['home'] ?? []) : ($m['away'] ?? []);
$s = $m['score'] ?? null;
$comps = array_combine(FicheForm::COMPETITIONS, FicheForm::COMPETITIONS);
?>
<div class="fpanel" data-panel="infos">
  <div class="card card--pad">
    <div class="fgrid">
      <?= Form::text('match.date', 'Date', $m['date'] ?? '', ['type' => 'date', 'hint' => 'saison calculée']) ?>
      <?= Form::select('match.competition', 'Compétition', $m['competition'] ?? 'Championnat', $comps) ?>
      <?= Form::text('match.competition_label', 'Libellé', $m['competition_label'] ?? '', ['hint' => 'ex. Division 1, Ligue 2', 'list' => 'dl-comp-labels']) ?>
      <?= Form::text('match.round', 'Journée / tour', $m['round'] ?? '', ['placeholder' => 'J15, 8e de finale…']) ?>
      <?= Form::seg('match.venue', 'Domicile / extérieur', $home ? 'domicile' : 'exterieur', ['domicile' => 'Domicile', 'exterieur' => 'Extérieur']) ?>
      <?= Form::text('match.opponent', 'Adversaire', $opp['name'] ?? '', ['ac' => 'clubs', 'hint' => '→ face-à-face', 'required' => true]) ?>
      <?= Form::text('match.opponent_level', 'Niveau adversaire', $opp['level'] ?? '', ['placeholder' => 'D1, L2 Sui…']) ?>
      <?= Form::text('match.sochaux_level', 'Niveau Sochaux', $us['level'] ?? '', ['placeholder' => 'D1, L2…']) ?>
      <?= Form::text('match.stadium', 'Stade', $m['stadium'] ?? '', ['ac' => 'stades', 'hint' => '→ carto', 'class' => 'f--2']) ?>
      <?= Form::number('match.spectators', 'Spectateurs', $m['spectators'] ?? null) ?>
      <?= Form::text('match.referee', 'Arbitre', $m['referee'] ?? '', ['missing' => empty($m['referee'])]) ?>
    </div>
  </div>
  <div class="card card--pad">
    <h2 class="card__t">Score</h2>
    <div class="fgrid">
      <?= Form::number('match.score_home', 'Buts équipe à domicile', $s['home'] ?? null, ['hint' => $home ? 'Sochaux' : 'adversaire']) ?>
      <?= Form::number('match.score_away', 'Buts équipe à l’extérieur', $s['away'] ?? null, ['hint' => $home ? 'adversaire' : 'Sochaux']) ?>
      <?= Form::select('match.extra', 'Prolongation', $s['extra'] ?? '', ['' => 'Non', 'ap' => 'Après prolongation', 'tab' => 'Tirs au but'], ['strict' => true]) ?>
      <?= Form::number('match.pens_home', 'TAB domicile', $s['pens']['home'] ?? null, ['show_if' => 'match.extra', 'show_value' => 'tab']) ?>
      <?= Form::number('match.pens_away', 'TAB extérieur', $s['pens']['away'] ?? null, ['show_if' => 'match.extra', 'show_value' => 'tab']) ?>
    </div>
    <?= Form::text('match.goals_text', 'Buteurs (texte de l’en-tête)', $m['goals_text'] ?? '', ['class' => 'f--full', 'placeholder' => "Prat 33' pour Sochaux ; Robert 57' pour Nantes"]) ?>
    <?= Form::repeater('match.goals', 'Buts par équipe', $m['goals'] ?? [], fn ($g) => '<div class="fgrid">' . Form::text('@team', 'Équipe', $g['team'] ?? '') . Form::text('@scorers', 'Buteurs', $g['scorers'] ?? '', ['class' => 'f--2', 'placeholder' => "Prat 33', Thomas 78'"]) . '</div>', ['compact' => true, 'add' => 'Ajouter une équipe']) ?>
    <?= Form::lines('match.header_extra', 'Lignes complémentaires de l’en-tête', $m['header_extra'] ?? [], ['help' => 'Une information par ligne (ex. « Ruiz 38’, Alphonse 83’ pour Sochaux. »).']) ?>
    <?= Form::text('match.event', 'Événement (match particulier)', $m['event'] ?? '', ['class' => 'f--full', 'placeholder' => 'ex. Inauguration du nouveau stade Bonal']) ?>
  </div>
</div>

<div class="fpanel" data-panel="compo">
  <div class="card">
    <div class="card__head">
      <h2 class="card__t">Composition Sochaux</h2>
      <span class="card__note">Chaque nom est relié automatiquement à sa fiche joueur</span>
      <span class="row"><?= Form::text('match.formation', '', $m['formation'] ?? '', ['placeholder' => 'Formation 4-4-2', 'class' => 'f--inline']) ?><button type="button" class="btn btn--sm" data-lineup-paste>Importer depuis un tableau</button></span>
    </div>
    <div class="card__body">
      <div class="lhead" aria-hidden="true"><span></span><span>Poste</span><span>Joueur</span><span>Cap.</span><span>Buts</span><span>Remplacement</span><span>Cartons</span><span>Fiche</span></div>
      <?= Form::repeater('match.lineup', '', $lineup ?: ($m['lineup']['rows'] ?? []), function ($r) {
          $pid = $r['pid'] ?? ($r['person_id'] ?? null);
          $state = $pid ? '<a class="link-state link-state--ok" href="/admin/fiche/' . (int) $pid . '" target="_blank">Reliée</a>' : (!empty($r['name']) ? '<a class="link-state link-state--todo" href="/admin/fiche/nouvelle/personne?nom=' . e(rawurlencode(\App\Data\Names::display((string) $r['name']))) . '" target="_blank" title="Créer la fiche de ce joueur">À créer</a>' : '<span class="link-state">—</span>');
          return '<div class="lrow">'
              . '<select data-field="position" aria-label="Poste" class="in in--sm">' . implode('', array_map(fn ($k, $l) => '<option value="' . e($k) . '"' . ((string) ($r['position'] ?? '') === (string) $k ? ' selected' : '') . '>' . e($k !== '' ? $k . ' · ' . $l : $l) . '</option>', array_keys(FicheForm::POSITIONS), FicheForm::POSITIONS)) . '</select>'
              . '<input class="in in--sm" data-field="name" value="' . e($r['name'] ?? '') . '" placeholder="NOM Prénom" aria-label="Joueur" data-ac="personnes" data-ac-id="person_id">'
              . '<input type="hidden" data-field="person_id" value="' . e((string) ($r['person_id'] ?? '')) . '" data-type="int">'
              . '<label class="toggle" title="Capitaine"><input type="checkbox" data-field="captain"' . (!empty($r['captain']) ? ' checked' : '') . '><span class="toggle__box"></span></label>'
              . '<input class="in in--sm" data-field="goals_text" value="' . e($r['goals_text'] ?? '') . '" placeholder="33\', 78\'" aria-label="Buts">'
              . '<input class="in in--sm" data-field="sub_text" value="' . e($r['sub_text'] ?? '') . '" placeholder="Sortie 75\'" aria-label="Remplacement">'
              . '<input class="in in--sm" data-field="cards_text" value="' . e($r['cards_text'] ?? '') . '" placeholder="J 50\'" aria-label="Cartons">'
              . $state . '</div>';
      }, ['compact' => true, 'add' => 'Ajouter un joueur', 'blank' => ['position' => 'R']]) ?>
      <p class="xs muted" style="margin:0">Postes : G gardien, D défenseur, M milieu, A attaquant (titulaires), R remplaçant, E entraîneur. Buts : minutes séparées par des virgules. Remplacement : « Entrée 75’ » ou « Sortie 81’ ». Cartons : « J 50’ » (jaune), « R 80’ » (rouge).</p>
    </div>
  </div>

  <div class="card">
    <div class="card__head"><h2 class="card__t">Temps forts (minute par minute)</h2><span class="card__note">Alimente la frise du résumé sur la fiche</span></div>
    <div class="card__body">
      <?= Form::repeater('match.highlights', '', $m['highlights'] ?? [], fn ($h) => '<div class="hrow">' . Form::text('@minute', 'Min.', $h['minute'] ?? '', ['placeholder' => "33"]) . Form::text('@text', 'Action', $h['text'] ?? '', ['class' => 'f--2']) . Form::toggle('@goal', 'But', !empty($h['goal'])) . Form::text('@score', 'Score', $h['score'] ?? '', ['placeholder' => '1-0']) . '</div>', ['compact' => true, 'add' => 'Ajouter une action']) ?>
    </div>
  </div>
  <div class="cols">
    <div class="card">
      <div class="card__head"><h2 class="card__t">Réactions</h2></div>
      <div class="card__body">
        <?= Form::repeater('match.reactions', '', $m['reactions'] ?? [], fn ($r) => Form::text('@who', 'Qui', $r['who'] ?? '', ['placeholder' => 'Silvester Takac']) . Form::textarea('@text', 'Citation', $r['text'] ?? '', ['rows' => 3]), ['compact' => true, 'add' => 'Ajouter une réaction']) ?>
      </div>
    </div>
    <div class="card">
      <div class="card__head"><h2 class="card__t">Brèves</h2></div>
      <div class="card__body">
        <?= Form::repeater('match.breves', '', $m['breves'] ?? [], fn ($b) => Form::textarea('@_', 'Brève', is_string($b) ? $b : ($b['text'] ?? ''), ['rows' => 2]), ['compact' => true, 'scalar' => true, 'add' => 'Ajouter une brève', 'numbered' => true]) ?>
      </div>
    </div>
  </div>
</div>
<datalist id="dl-comp-labels"><option value="Division 1"><option value="Division 2"><option value="Ligue 1"><option value="Ligue 2"><option value="National"><option value="Coupe UEFA"><option value="Coupe Intertoto"><option value="Ligue Europa"><option value="Coupe des villes de foires"><option value="Coupe Mitropa"></datalist>
