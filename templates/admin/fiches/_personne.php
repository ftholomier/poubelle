<?php
/** Masque « personne » : identité, carrière, statistiques. Variables : $doc */
use App\Admin\FicheForm;
use App\Admin\Form;
use App\Data\Derived;
use App\Data\Index;

$p = $doc['personne'];
$birth = $p['birth'] ?? [];
$place = $birth['place'] ?? [];
$death = $p['death'] ?? [];
$dt = fn ($d) => FicheForm::dateText($d);
$geo = \App\Data\Collections::get('geo', []);
$pid = (int) $doc['id'];
$tot = Derived::part('person_totals')[$pid] ?? null;
$matches = $pid ? Derived::personMatches($pid) : [];
?>
<div class="fpanel" data-panel="identite">
  <div class="card card--pad">
    <h2 class="card__t">Identité</h2>
    <div class="fgrid">
      <?= Form::text('personne.first_name', 'Prénom', $p['first_name'] ?? '') ?>
      <?= Form::text('personne.last_name', 'Nom', $p['last_name'] ?? '', ['required' => true]) ?>
      <?= Form::text('personne.display_name', 'Nom affiché', $p['display_name'] ?? '', ['hint' => 'auto si vide']) ?>
      <?= Form::text('personne.nickname', 'Surnom', $p['nickname'] ?? '') ?>
    </div>
    <?= Form::lines('personne.aliases', 'Autres graphies dans les compositions', (array) ($p['aliases'] ?? []), ['placeholder' => 'ex. CAMARA Razza', 'add' => 'Ajouter une graphie', 'help' => 'Les compositions qui écrivent le nom ainsi seront reliées à cette fiche (« Joueurs sans fiche » dans Qualité).']) ?>
    <?= Form::checks('personne.roles', 'Rubriques', $p['roles'] ?? ['joueur'], FicheForm::ROLES, ['help' => 'La première rubrique cochée détermine l’adresse de la fiche (/joueurs/…, /entraineurs/…).']) ?>
    <div class="fgrid">
      <?= Form::text('personne.position', 'Poste (texte)', $p['position'] ?? '', ['placeholder' => 'défenseur latéral droit', 'class' => 'f--2', 'proof' => true]) ?>
      <?= Form::select('personne.line', 'Ligne (filtres, terrain)', $p['line'] ?? '', FicheForm::LINES) ?>
      <?= Form::text('personne.nationality', 'Nationalité', $p['nationality'] ?? '') ?>
      <?= Form::text('personne.foot', 'Pied', $p['foot'] ?? '', ['placeholder' => 'droitier']) ?>
      <?= Form::text('personne.height', 'Taille', $p['height'] ?? '', ['placeholder' => '1m81']) ?>
      <?= Form::text('personne.weight', 'Poids', $p['weight'] ?? '', ['placeholder' => '72 kg']) ?>
      <?= Form::text('personne.shirt_numbers', 'Numéros portés', $p['shirt_numbers'] ?? '') ?>
    </div>
    <?= Form::text('personne.subtitle', 'Sous-titre (accroche sous le nom)', $p['subtitle'] ?? '', ['class' => 'f--full', 'maxlength' => 300, 'proof' => true]) ?>
  </div>
  <div class="cols">
    <div class="card card--pad">
      <h2 class="card__t">Naissance</h2>
      <div class="fgrid">
        <?= Form::text('personne.birth_date', 'Date', $dt($birth['date'] ?? null), ['placeholder' => '8 décembre 1964 ou 12/1964', 'missing' => empty($birth['date'])]) ?>
        <?= Form::text('personne.birth_city', 'Ville', $place['city'] ?? '', ['missing' => empty($place['city'])]) ?>
        <?= Form::text('personne.birth_department', 'Département', $place['department'] ?? '', ['placeholder' => '25']) ?>
        <?= Form::text('personne.birth_country', 'Pays', $place['country'] ?? '', ['placeholder' => 'France']) ?>
        <?= Form::number('personne.birth_lat', 'Latitude', $place['lat'] ?? null, ['decimal' => true, 'hint' => 'auto']) ?>
        <?= Form::number('personne.birth_lng', 'Longitude', $place['lng'] ?? null, ['decimal' => true, 'hint' => 'auto']) ?>
      </div>
      <span class="f__help">La ville est géolocalisée automatiquement pour la carto des origines. Corrigez la latitude et la longitude seulement en cas d’erreur.</span>
    </div>
    <div class="card card--pad">
      <h2 class="card__t">Décès</h2>
      <div class="fgrid">
        <?= Form::text('personne.death_date', 'Date', $dt($death['date'] ?? null)) ?>
        <?= Form::text('personne.death_place', 'Lieu', $death['place']['text'] ?? '') ?>
      </div>
    </div>
  </div>
  <div class="card card--pad">
    <h2 class="card__t">Statuts & carto</h2>
    <div class="row" style="gap:18px">
      <?= Form::toggle('personne.formed_at_club', 'Formé au club', !empty($p['formed_at_club'])) ?>
      <?= Form::toggle('personne.international_flag', 'International', !empty($p['international_flag'])) ?>
      <?= Form::toggle('personne.legend', 'Légende (mise en avant)', !empty($p['legend'])) ?>
      <?= Form::toggle('personne.is_trial', 'À l’essai', !empty($p['is_trial'])) ?>
      <?= Form::toggle('personne.on_map', 'Visible sur la carto', ($p['on_map'] ?? true) !== false) ?>
    </div>
    <?= Form::lines('personne.international', 'Sélections internationales', $p['international'] ?? [], ['help' => 'Une ligne par sélection (ex. « France A : 3 sélections »).']) ?>
  </div>
  <div class="card card--pad">
    <h2 class="card__t">Carte de l’album du centenaire</h2>
    <div class="fgrid">
      <?= Form::toggle('personne.album.in', 'Dans l’album', !empty($p['album']['in'])) ?>
      <?= Form::seg('personne.album.rarity', 'Rareté', $p['album']['rarity'] ?? '', ['' => 'Auto'] + FicheForm::RARITIES, ['help' => 'Auto : légende, actuel ou classique selon la carrière.']) ?>
      <?= Form::number('personne.album.number', 'Numéro de la carte', $p['album']['number'] ?? null, ['hint' => '1 à 120']) ?>
    </div>
    <span class="f__help">La carte se débloque en visitant la fiche (ou via le quiz pour les cartes rares).</span>
  </div>
</div>

<div class="fpanel" data-panel="carriere">
  <div class="card card--pad">
    <h2 class="card__t">Au club</h2>
    <div class="fgrid">
      <?= Form::text('personne.arrival', 'Arrivée (joueur)', $dt($p['arrival'] ?? null), ['placeholder' => 'juillet 1980']) ?>
      <?= Form::text('personne.departure', 'Départ (joueur)', $dt($p['departure'] ?? null), ['placeholder' => 'juin 1992']) ?>
      <?= Form::text('personne.arrival_coach', 'Arrivée (entraîneur)', $dt($p['arrival_coach'] ?? null)) ?>
      <?= Form::text('personne.departure_coach', 'Départ (entraîneur)', $dt($p['departure_coach'] ?? null)) ?>
      <?= Form::text('personne.trial', 'Période d’essai', $dt($p['trial'] ?? null)) ?>
    </div>
    <span class="f__help">Dates partielles acceptées : « 1980 », « 07/1980 », « juillet 1980 », « 12 juillet 1980 ». Elles alimentent les filtres par décennie, la carto et les records.</span>
    <div class="fgrid fgrid--2">
      <?= Form::text('personne.first_match', 'Premier match', $p['first_match'] ?? '') ?>
      <?= Form::text('personne.last_match', 'Dernier match', $p['last_match'] ?? '') ?>
      <?= Form::text('personne.first_goal', 'Premier but', $p['first_goal'] ?? '') ?>
      <?= Form::text('personne.first_match_coached', 'Premier match comme entraîneur', $p['first_match_coached'] ?? '') ?>
      <?= Form::text('personne.last_match_coached', 'Dernier match comme entraîneur', $p['last_match_coached'] ?? '') ?>
    </div>
  </div>
  <div class="cols">
    <div class="card card--pad">
      <h2 class="card__t">Palmarès</h2>
      <?= Form::repeater('personne.honours', '', $p['honours'] ?? [], fn ($h) => Form::text('@_', 'Titre', is_string($h) ? $h : '', ['placeholder' => 'Vainqueur de la Coupe Gambardella en 1983', 'proof' => true]), ['compact' => true, 'scalar' => true, 'add' => 'Ajouter un titre']) ?>
    </div>
    <div class="card card--pad">
      <h2 class="card__t">Après Sochaux</h2>
      <?= Form::repeater('personne.then', '', $p['then'] ?? [], fn ($h) => Form::text('@_', 'Étape', is_string($h) ? $h : '', ['proof' => true]), ['compact' => true, 'scalar' => true, 'add' => 'Ajouter une étape']) ?>
    </div>
  </div>
  <div class="card card--pad">
    <h2 class="card__t">Fiche d’identité (tableau d’origine)</h2>
    <p class="small muted" style="margin:0">Lignes affichées telles quelles dans l’encadré « Fiche d’identité ». Laissez le libellé vide pour une ligne de texte libre.</p>
    <?= Form::repeater('personne.fiche', '', $p['fiche'] ?? [], fn ($r) => '<div class="fgrid">' . Form::text('@label', 'Libellé', $r['label'] ?? '', ['placeholder' => 'Né le', 'proof' => true]) . Form::text('@value', 'Valeur', $r['value'] ?? '', ['class' => 'f--2', 'proof' => true]) . '</div>', ['compact' => true, 'add' => 'Ajouter une ligne']) ?>
  </div>
  <div class="card card--pad">
    <h2 class="card__t">Matchs marquants</h2>
    <?= Form::repeater('personne.highlight_matches', '', array_map(fn ($mid) => ['id' => $mid, 'title' => Index::get((int) $mid)['title'] ?? ('Fiche ' . $mid)], $p['highlight_matches'] ?? []), fn ($r) => '<div class="fgrid">' . Form::text('@title', 'Match', $r['title'] ?? '', ['ac' => 'matchs', 'ac_id' => 'id', 'class' => 'f--2', 'placeholder' => 'Tapez une équipe ou une date…']) . '<input type="hidden" data-field="id" data-type="int" value="' . e((string) ($r['id'] ?? '')) . '"></div>', ['compact' => true, 'add' => 'Ajouter un match marquant']) ?>
  </div>
</div>

<div class="fpanel" data-panel="stats">
  <div class="card card--pad">
    <h2 class="card__t">Statistiques (tableau saison par saison)</h2>
    <input type="hidden" name="personne.stats" data-type="json" data-table-editor data-single value="<?= e(json_encode($p['stats'] ?? null, JSON_UNESCAPED_UNICODE)) ?>">
  </div>
  <div class="card">
    <div class="card__head"><h2 class="card__t">Matchs reliés automatiquement</h2><span class="card__note">depuis les compositions<?= $tot ? ' · ' . (int) $tot['matches'] . ' matchs, ' . (int) $tot['goals'] . ' buts' : '' ?></span></div>
    <?php foreach (array_slice(array_reverse($matches), 0, 60) as $x): ?>
      <a class="card__row" style="grid-template-columns:100px minmax(0,1fr) 60px 70px" href="/admin/fiche/<?= (int) $x['id'] ?>"><span class="d" style="font-weight:800"><?= e(date_num($x['date'])) ?></span><span><?= e(\App\Front\Site::matchLabel($x)) ?><?= $x['role'] === 'coach' ? ' <span class="pill">entraîneur</span>' : '' ?></span><span><?= $x['role'] === 'coach' ? '' : (int) $x['minutes'] . "'" ?></span><span><?= (int) $x['goals'] ? (int) $x['goals'] . ' but' . ((int) $x['goals'] > 1 ? 's' : '') : '—' ?></span></a>
    <?php endforeach; ?>
    <?php if (!$matches): ?><div class="card__body muted">Aucun match relié pour l’instant : les compositions citant ce nom seront reliées automatiquement.</div><?php endif; ?>
    <?php if (count($matches) > 60): ?><div class="card__body xs muted">… et <?= count($matches) - 60 ?> autres matchs.</div><?php endif; ?>
  </div>
</div>
