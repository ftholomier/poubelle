<?php
/**
 * Accueil & bandeau. Variables : $slider, $manual, $pool, $poolSmall, $poolNoImage, $slideMin, $ticker, $home, $schema,
 * $palmares, $eras, $reserves, $teasers, $legends, $figure (chiffre du jour)
 */
use App\Admin\Form;

$tr = fn (string $fields) => '<button type="button" class="btn btn--sm btn--ghost" data-tr="' . e($fields) . '">Traduire en anglais</button>';
?>
<form class="stack" data-json-form data-tabs-scope data-url="/admin/accueil" data-lock="ecran:accueil" data-lock-what="l’accueil" novalidate>
  <div class="row" style="justify-content:space-between">
    <div class="ftabs" data-ftabs role="tablist">
      <?php foreach (['slider' => 'Grand slider', 'bandeau' => 'Bandeau défilant', 'textes' => 'Textes & compteurs', 'palmares' => 'Palmarès', 'epoques' => 'Grandes époques', 'reserves' => 'Réserves', 'vignettes' => 'Vignettes'] as $k => $l): ?>
        <button type="button" data-tab="<?= $k ?>" role="tab"><?= e($l) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="row"><span class="small muted" data-saved></span><button type="button" class="btn" data-proofread>Vérifier l’orthographe</button><a class="btn" href="/" target="_blank" rel="noopener">Voir l’accueil ↗</a><button type="submit" class="btn btn--navy" data-save>Enregistrer</button></div>
  </div>

  <div class="fpanel" data-panel="slider">
    <div class="card card--pad">
      <h2 class="card__t">Grand slider de l’accueil</h2>
      <?= Form::seg('slider.mode', 'Contenu du slider', $slider['mode'] ?? 'random', ['random' => 'Tirage au hasard', 'manual' => 'Sélection manuelle']) ?>
      <div data-show-if="slider.mode" data-show-value="random">
        <?php $minTxt = number_format($slideMin[0], 0, ',', ' ') . ' × ' . number_format($slideMin[1], 0, ',', ' ') . ' pixels'; ?>
        <p class="alert alert--info" style="margin:0">À chaque visite, <b><?= (int) ($home['slider_count'] ?? 5) ?></b> fiches sont tirées au hasard parmi les <b><?= number_format((int) $pool, 0, ',', ' ') ?></b> fiches publiées (matchs, joueurs, récits, articles…) dont la photo à la une est assez grande pour rester nette en plein écran (au moins <?= e($minTxt) ?>).<?= $poolSmall ? ' Écartées : ' . number_format((int) $poolSmall, 0, ',', ' ') . ' fiches à la photo trop petite' . ($poolNoImage ? ' et ' . (int) $poolNoImage . ' sans vraie photo (pas d’image ou silhouette « ? »)' : '') . '.' : ($poolNoImage ? ' Écartées : ' . (int) $poolNoImage . ' fiches sans vraie photo (pas d’image ou silhouette « ? »).' : '') ?> Cochez « À la une » dans l’onglet « Classement & SEO » d’une fiche pour l’ajouter ; un plus grand scan de sa photo (Médiathèque › « Remplacer le fichier… ») la fait entrer dans le tirage.</p>
      </div>
      <div data-show-if="slider.mode" data-show-value="manual">
        <?= Form::repeater('slider.ids', 'Fiches du slider, dans l’ordre', $manual, fn ($it) => '<div class="row" style="gap:10px;flex-wrap:nowrap">'
            . (!empty($it['image']) ? '<img src="' . e(img($it['image'], 160)) . '" alt="" style="width:72px;height:48px;object-fit:cover;border:1px solid var(--navy)">' : '')
            . '<input type="text" class="in in--sm" data-ac="fiches" data-ac-id="id" value="' . e($it['title'] ?? '') . '" placeholder="Tapez le titre d’une fiche…" aria-label="Fiche" style="flex:1">'
            . '<input type="hidden" data-field="id" data-type="int" value="' . (int) ($it['id'] ?? 0) . '">'
            . (!empty($it['small']) ? '<span class="pill pill--warn" title="' . e($it['small']) . '">photo trop petite</span>' : '') . '</div>', ['compact' => true, 'numbered' => true, 'add' => 'Ajouter une fiche']) ?>
        <span class="f__help">Les fiches sans image à la une ou non publiées sont ignorées sur le site. « Photo trop petite » : moins de <?= e($minTxt) ?>, elle paraîtra floue en plein écran.</span>
      </div>
    </div>
  </div>

  <div class="fpanel" data-panel="bandeau" id="bandeau">
    <div class="card card--pad">
      <h2 class="card__t">Bandeau « En direct du musée »</h2>
      <p class="small muted" style="margin:0">Messages automatiques, mis à jour chaque jour :</p>
      <div class="row">
        <?= Form::toggle('ticker.auto.jour', 'Ce jour-là (un match joué à cette date)', !empty($ticker['auto']['jour'])) ?>
        <?= Form::toggle('ticker.auto.centenaire', 'Compte à rebours du centenaire', !empty($ticker['auto']['centenaire'])) ?>
        <?= Form::toggle('ticker.auto.dernier', 'Dernier match fiché', !empty($ticker['auto']['dernier'])) ?>
        <?= Form::toggle('ticker.auto.retro', 'Rétro-Direct (en cours ou dans les 7 jours)', $ticker['auto']['retro'] ?? true) ?>
      </div>
      <?= Form::repeater('ticker.messages', 'Messages de l’équipe', $ticker['messages'] ?? [], fn ($m) => '<div class="fgrid">'
          . Form::toggle('@on', 'Affiché', !empty($m['on']))
          . Form::text('@k', 'Étiquette', $m['k'] ?? '', ['placeholder' => 'Nouveau', 'maxlength' => 40])
          . Form::text('@v', 'Message', $m['v'] ?? '', ['placeholder' => 'La carto des stades et des origines', 'maxlength' => 160, 'class' => 'f--2', 'proof' => true])
          . Form::text('@href', 'Lien', $m['href'] ?? '', ['placeholder' => '/interactif/carto/'])
          . Form::text('@k_en', 'Étiquette (EN)', $m['k_en'] ?? '', ['maxlength' => 40])
          . Form::text('@v_en', 'Message (EN)', $m['v_en'] ?? '', ['maxlength' => 160, 'class' => 'f--2', 'proof' => true])
          . '<div class="f" style="justify-content:flex-end">' . $tr('k,v') . '</div></div>', ['compact' => true, 'add' => 'Ajouter un message', 'blank' => ['on' => true]]) ?>
    </div>
  </div>

  <div class="fpanel" data-panel="textes">
    <div class="card card--pad">
      <h2 class="card__t">Référencement de l’accueil</h2>
      <?= Form::text('home.intro_title', $schema['intro_title']['label'], $home['intro_title'] ?? '', ['class' => 'f--full', 'maxlength' => 200, 'proof' => 'title']) ?>
      <?= Form::html('home.intro_text', $schema['intro_text']['label'], $home['intro_text'] ?? '') ?>
    </div>
    <div class="card card--pad">
      <h2 class="card__t">Compteurs et centenaire</h2>
      <div class="fgrid">
        <?= Form::number('home.slider_count', $schema['slider_count']['label'], $home['slider_count'] ?? 5, ['min' => 1, 'max' => 12]) ?>
        <?= Form::text('home.counter_community', $schema['counter_community']['label'], $home['counter_community'] ?? '') ?>
        <?= Form::text('home.counter_videos', $schema['counter_videos']['label'], $home['counter_videos'] ?? '') ?>
        <?= Form::text('home.centenary_date', $schema['centenary_date']['label'], $home['centenary_date'] ?? '2028-05-20', ['type' => 'date']) ?>
      </div>
      <p class="small muted" style="margin:0">Les compteurs de matchs et de joueurs sont calculés automatiquement à partir des fiches.</p>
    </div>
    <div class="card card--pad">
      <h2 class="card__t">Le teaser vidéo</h2>
      <?= Form::toggle('home.teaser', $schema['teaser']['label'], (bool) ($home['teaser'] ?? true)) ?>
      <p class="small" style="margin:0">La vidéo de présentation (1 min 55), sous les compteurs de l’accueil. Tant que la page d’attente est active, le public ne la voit pas ; l’équipe connectée, si. Pour la montrer aussi sur la page d’attente : Éditorial › Page d’attente.</p>
      <a class="btn btn--sm" href="/#teaser" target="_blank" rel="noopener" style="align-self:flex-start">Voir sur l’accueil ↗</a>
    </div>
    <div class="card card--pad">
      <h2 class="card__t">Le chiffre du jour</h2>
      <?= Form::toggle('home.daily_figure', $schema['daily_figure']['label'], (bool) ($home['daily_figure'] ?? true)) ?>
      <?php if ($figure): $fs = $figure['stat']; ?>
        <p class="small" style="margin:0">Aujourd’hui : n° <?= (int) $fs['n'] ?>, <b><?= e($fs['label']) ?></b> : <?= e($fs['value'] . ($fs['unit'] !== '' ? ' ' . $fs['unit'] : '')) ?>. Un nouveau chiffre chaque jour, tiré parmi les <?= (int) $figure['count'] ?> de la page « Les chiffres du FCSM », sans répétition avant de les avoir tous montrés. Rien à saisir : tout est calculé depuis les fiches.</p>
      <?php else: ?>
        <p class="small muted" style="margin:0">Les chiffres sont en cours de calcul : le bloc apparaîtra sur l’accueil dans quelques instants.</p>
      <?php endif; ?>
      <a class="btn btn--sm" href="/chiffres/" target="_blank" rel="noopener" style="align-self:flex-start">Voir les 100 chiffres ↗</a>
    </div>
    <div class="card card--pad">
      <h2 class="card__t">« Ils ont porté le lion »</h2>
      <p style="margin:0"><?= (int) $legends ?> personne<?= $legends > 1 ? 's' : '' ?> marquée<?= $legends > 1 ? 's' : '' ?> « Légende ». Cochez « Légende » dans la fiche d’un joueur pour l’ajouter à cette rubrique de l’accueil (4 tirées au hasard ; à défaut, les joueurs les plus capés).</p>
      <a class="btn btn--sm" href="/admin/personnes?filtre=legendes" style="align-self:flex-start">Voir les légendes</a>
    </div>
  </div>

  <div class="fpanel" data-panel="palmares">
    <div class="card card--pad">
      <h2 class="card__t">Palmarès (bandeau jaune)</h2>
      <?= Form::repeater('palmares', '', $palmares, fn ($p) => '<div class="fgrid">'
          . Form::text('@years', 'Chiffre', $p['years'] ?? '', ['placeholder' => '2×', 'maxlength' => 20])
          . Form::text('@title', 'Titre', $p['title'] ?? '', ['placeholder' => 'Champion de France', 'maxlength' => 80, 'class' => 'f--2', 'proof' => 'title'])
          . Form::text('@title_en', 'Titre (EN)', $p['title_en'] ?? '', ['maxlength' => 80, 'class' => 'f--2', 'proof' => 'title'])
          . '<div class="f" style="justify-content:flex-end">' . $tr('title') . '</div></div>', ['compact' => true, 'add' => 'Ajouter un titre']) ?>
    </div>
  </div>

  <div class="fpanel" data-panel="epoques">
    <div class="card card--pad">
      <h2 class="card__t">Les grandes époques</h2>
      <?= Form::repeater('eras', '', $eras, fn ($e) => '<div class="fgrid">'
          . Form::text('@range', 'Période', $e['range'] ?? '', ['placeholder' => '1928–1945', 'maxlength' => 20])
          . Form::text('@name', 'Nom', $e['name'] ?? '', ['placeholder' => 'Les pionniers', 'maxlength' => 80, 'proof' => 'title'])
          . Form::text('@name_en', 'Nom (EN)', $e['name_en'] ?? '', ['maxlength' => 80, 'proof' => 'title'])
          . Form::text('@href', 'Lien « Visiter cette époque »', $e['href'] ?? '', ['placeholder' => '/interactif/frise/#1928'])
          . Form::textarea('@text', 'Texte', $e['text'] ?? '', ['class' => 'f--full', 'rows' => 3])
          . Form::textarea('@text_en', 'Texte (EN)', $e['text_en'] ?? '', ['class' => 'f--full', 'rows' => 3])
          . Form::image('@image', 'Image', $e['image'] ?? null)
          . '<div class="f" style="justify-content:flex-end">' . $tr('name,text') . '</div></div>'
          . Form::repeater('facts', 'Dates clés', $e['facts'] ?? [], fn ($f) => '<div class="fgrid">' . Form::text('@y', 'Année', $f['y'] ?? '', ['maxlength' => 12]) . Form::text('@t', 'Fait', $f['t'] ?? '', ['maxlength' => 120, 'class' => 'f--2', 'proof' => true]) . Form::text('@t_en', 'Fait (EN)', $f['t_en'] ?? '', ['maxlength' => 120, 'class' => 'f--2', 'proof' => true]) . '<div class="f" style="justify-content:flex-end">' . $tr('t') . '</div></div>', ['compact' => true, 'add' => 'Ajouter une date']),
          ['add' => 'Ajouter une époque', 'numbered' => true]) ?>
    </div>
  </div>

  <div class="fpanel" data-panel="reserves">
    <div class="card card--pad">
      <h2 class="card__t">Les réserves du musée</h2>
      <p class="small muted" style="margin:0">Chaque tuile ouvre la collection d’objets correspondante (champ « Collection » des fiches objets). Sans image, la première photo d’un objet de la collection est utilisée.</p>
      <?= Form::repeater('reserves', '', $reserves, fn ($r) => '<div class="fgrid">'
          . Form::text('@slug', 'Identifiant (adresse)', $r['slug'] ?? '', ['placeholder' => 'affiches', 'maxlength' => 40, 'help' => '/reserves/<b>identifiant</b>/'])
          . Form::text('@name', 'Nom', $r['name'] ?? '', ['maxlength' => 40])
          . Form::text('@desc', 'Sous-titre', $r['desc'] ?? '', ['maxlength' => 80, 'proof' => true])
          . Form::text('@name_en', 'Nom (EN)', $r['name_en'] ?? '', ['maxlength' => 40])
          . Form::text('@desc_en', 'Sous-titre (EN)', $r['desc_en'] ?? '', ['maxlength' => 80, 'proof' => true])
          . Form::image('@image', 'Image', $r['image'] ?? null)
          . '<div class="f" style="justify-content:flex-end">' . $tr('name,desc') . '</div></div>', ['compact' => true, 'add' => 'Ajouter une collection']) ?>
    </div>
  </div>

  <div class="fpanel" data-panel="vignettes">
    <div class="card card--pad">
      <h2 class="card__t">Images des encarts</h2>
      <div class="fgrid fgrid--2">
        <?= Form::image('teasers.quiz', 'Encart Quiz', $teasers['quiz'] ?? null) ?>
        <?= Form::image('teasers.maillots', 'Encart Maillots', $teasers['maillots'] ?? null) ?>
        <?= Form::image('teasers.frise', 'Encart Frise', $teasers['frise'] ?? null) ?>
        <?= Form::image('teasers.contribuer', 'Encart Contribuer', $teasers['contribuer'] ?? null) ?>
      </div>
    </div>
  </div>
</form>
