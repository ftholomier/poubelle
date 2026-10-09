<?php
/**
 * Masque de saisie d'une fiche. Variables : $doc, $isNew, $versions, $checks, $auto, $enStatus, $lineup, $list, $proof,
 * $lock (verrou de modification : key, tab, holder = personne qui modifie déjà la fiche),
 * $web (recherche sur le web disponible : dernier résultat gardé, ou null)
 */
use App\Admin\Base;
use App\Admin\Form;
use App\Core\Auth;
use App\Data\Fiches;

$type = $doc['type'];
$id = (int) $doc['id'];
$tabs = match ($type) {
    'match' => ['infos' => 'Infos', 'compo' => 'Compo & événements', 'recit' => 'Récit', 'medias' => 'Médias', 'tableaux' => 'Tableaux', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    'personne' => ['identite' => 'Identité', 'carriere' => 'Carrière', 'recit' => 'Récit', 'stats' => 'Statistiques', 'medias' => 'Médias', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    'objet' => ['objet' => 'Objet', 'recit' => 'Description', 'medias' => 'Médias', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    'moment' => ['moment' => 'Moment', 'recit' => 'Récit', 'medias' => 'Médias', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
    default => ['contenu' => 'Contenu', 'recit' => 'Texte', 'medias' => 'Médias', 'tableaux' => 'Tableaux', 'seo' => 'Classement & SEO', 'en' => 'Version EN', 'historique' => 'Historique'],
};
if ($isNew) {
    unset($tabs['historique'], $tabs['en']);
}
$counts = [
    'medias' => count($doc['gallery'] ?? []) + count($doc['videos'] ?? []),
    'historique' => count($versions),
    'tableaux' => count($doc['tables'] ?? []),
    'compo' => count($doc['match']['lineup']['rows'] ?? []),
];
$visible = Fiches::isVisible($doc);
$last = $versions[0] ?? null;
$enLabel = ['none' => 'Non traduite', 'auto' => 'Traduite (Gemini)', 'manual' => 'Relue ✓', 'stale' => 'À revoir'][$enStatus] ?? '';
?>
<form class="editor<?= $isNew ? ' is-new' : '' ?>" data-json-form data-tabs-scope data-url="/admin/fiche/<?= $id ?: 0 ?>/enregistrer" data-modified="<?= e((string) ($doc['modified'] ?? '')) ?>" data-draft="fiche-<?= $id ?: 'nouvelle-' . e($type) ?>"<?php if (!empty($lock)): ?> data-lock="<?= e($lock['key']) ?>" data-lock-tab="<?= e($lock['tab']) ?>" data-lock-state="<?= $lock['holder'] ? 'other' : 'mine' ?>"<?php endif; ?> novalidate>
  <?php if (!empty($lock)): $h = $lock['holder']; ?>
    <div class="lockbar"<?= $h ? '' : ' hidden' ?> data-lockbar role="status"><?php if ($h): ?><span class="lockbar__t">🔒 <b><?= e($h['name']) ?></b> modifie cette fiche depuis <?= e($h['since']) ?><?= $h['idle'] >= 5 ? ' (sans activité depuis ' . (int) $h['idle'] . ' min)' : '' ?>. Vous êtes en lecture seule.</span><button type="button" class="btn btn--sm btn--navy" data-lock-take>Prendre la main</button><?php endif; ?></div>
  <?php endif; ?>
  <?php if ($isNew): ?><input type="hidden" name="_type" value="<?= e($type) ?>"><?php endif; ?>
  <?php if ($isNew && !empty($doc['_idee'])): ?><input type="hidden" name="_idee" value="<?= e((string) $doc['_idee']) ?>"><?php endif; ?>
  <div class="stack">
    <?php if ($doc['status'] === 'corbeille'): ?><p class="alert alert--error">Cette fiche est à la corbeille : elle n’est pas visible sur le site.</p><?php endif; ?>
    <?php if ($type === 'match' && !$isNew && ($why = \App\Services\MatchText::otherMatch($doc))): ?><p class="alert alert--error" role="alert">⚠ <?= e($why) ?></p><?php endif; ?>
    <div class="ftabs" data-ftabs role="tablist">
      <?php foreach ($tabs as $k => $l): ?><button type="button" data-tab="<?= e($k) ?>" role="tab"><?= e($l) ?><?php if (!empty($counts[$k])): ?><em><?= (int) $counts[$k] ?></em><?php endif; ?></button><?php endforeach; ?>
    </div>

    <?php if ($type === 'match'): ?>
      <?= \App\Core\View::partial('admin/fiches/_match', ['doc' => $doc, 'lineup' => $lineup]) ?>
    <?php elseif ($type === 'personne'): ?>
      <?= \App\Core\View::partial('admin/fiches/_personne', ['doc' => $doc]) ?>
    <?php elseif ($type === 'objet'): ?>
      <?= \App\Core\View::partial('admin/fiches/_objet', ['doc' => $doc]) ?>
    <?php elseif ($type === 'moment'): ?>
      <?= \App\Core\View::partial('admin/fiches/_moment', ['doc' => $doc]) ?>
    <?php else: ?>
      <?= \App\Core\View::partial('admin/fiches/_article', ['doc' => $doc]) ?>
    <?php endif; ?>

    <?= \App\Core\View::partial('admin/fiches/_recit', ['doc' => $doc]) ?>
    <?= \App\Core\View::partial('admin/fiches/_medias', ['doc' => $doc]) ?>
    <?php if (isset($tabs['tableaux'])): ?>
      <div class="fpanel" data-panel="tableaux">
        <div class="card card--pad">
          <h2 class="card__t">Tableaux</h2>
          <p class="small muted" style="margin:0">Statistiques, compositions d’origine, classements… Collez un tableau depuis Excel ou une page web avec « Coller un tableau ».</p>
          <input type="hidden" name="tables" data-type="json" data-table-editor value="<?= e(json_encode($doc['tables'] ?? [], JSON_UNESCAPED_UNICODE)) ?>">
        </div>
      </div>
    <?php endif; ?>
    <?= \App\Core\View::partial('admin/fiches/_seo', ['doc' => $doc]) ?>
    <?php if (!$isNew): ?>
      <?= \App\Core\View::partial('admin/fiches/_en', ['doc' => $doc, 'enStatus' => $enStatus]) ?>
      <?= \App\Core\View::partial('admin/fiches/_historique', ['doc' => $doc, 'versions' => $versions]) ?>
    <?php endif; ?>
  </div>

  <aside class="editor__side">
    <div class="card card--pad pub">
      <h2 class="card__t card__t--sm">Publication</h2>
      <div class="seg" style="--n:2">
        <?php foreach (['brouillon' => 'Brouillon', 'relire' => 'À relire', 'planifie' => 'Planifié', 'publie' => 'Publié'] as $k => $l): ?>
          <label><input type="radio" name="status" value="<?= $k ?>"<?= $doc['status'] === $k || ($doc['status'] === 'corbeille' && $k === ($doc['status_before_trash'] ?? 'brouillon')) ? ' checked' : '' ?>><span><?= $l ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="f" data-show-if="status" data-show-value="planifie">
        <span class="f__k">Publication programmée le</span>
        <input type="datetime-local" name="publish_at" value="<?= e(!empty($doc['publish_at']) ? date('Y-m-d\TH:i', strtotime($doc['publish_at'])) : '') ?>">
      </div>
      <span class="saved" data-saved><?= $isNew ? 'Nouvelle fiche, pas encore enregistrée' : ($last ? 'Enregistré · v' . (int) $last['n'] . ' · ' . e($last['by']) . ' · ' . e(Base::ago($last['at'])) : 'Enregistré') ?></span>
      <div class="row">
        <button type="submit" class="btn btn--navy grow" data-save>Enregistrer</button>
        <?php if (!$isNew): ?><button type="button" class="btn" data-preview="/admin/fiche/<?= $id ?>/apercu" title="Aperçu comme sur le site (avec vos modifications en cours)">Aperçu</button><?php endif; ?>
      </div>
      <?php if (!$isNew && $visible && $doc['path']): ?><a class="linkbtn" href="<?= e($doc['path']) ?>" target="_blank" rel="noopener">Voir sur le site ↗</a><?php endif; ?>
      <?php if (!$isNew): ?><a class="linkbtn" href="/pdf/fiche/<?= $id ?>.pdf" title="Le document PDF tel que les visiteurs le téléchargent (version enregistrée)" download>Télécharger le PDF ↓</a><?php endif; ?>
      <label class="f"><span class="f__k">Note de version <i>facultatif</i></span><input name="_message" maxlength="160" placeholder="ex. score corrigé d’après L’Est républicain"></label>
      <span class="xs muted">Ctrl+S pour enregistrer · brouillon gardé automatiquement sur cet ordinateur</span>
    </div>

    <div class="card card--pad proofcard">
      <h2 class="card__t card__t--sm">Orthographe</h2>
      <span class="xs muted" data-proof-state><?php
        if (!empty($proof) && $proof['n'] > 0) {
            echo 'La vérification automatique propose <b>' . (int) $proof['n'] . ' correction' . ($proof['n'] > 1 ? 's' : '') . '</b>' . ($proof['hi'] ? ', dont ' . (int) $proof['hi'] . ' faute' . ($proof['hi'] > 1 ? 's' : '') . ' de langue' : ' (ponctuation, typographie)') . '.';
        } elseif (!empty($proof)) {
            echo 'Vérifiée automatiquement' . (($proof['e'] ?? '') === 'gemini' ? '' : ' (règles de base)') . ' : aucune faute trouvée.';
        } else {
            echo 'Orthographe, accords et syntaxe de tous les textes de la fiche. Rien n’est modifié sans votre accord.';
        }
      ?></span>
      <button type="button" class="btn btn--sm" data-proofread data-proof-scope="<?= $isNew ? '' : 'fiche:' . $id ?>">Vérifier l’orthographe<?= !empty($proof['n']) ? ' <em class="proofcount">' . (int) $proof['n'] . '</em>' : '' ?></button>
    </div>

    <?php if (!empty($web)): $wlast = $web['last']; $nb = $wlast ? count($wlast['items']) : 0; ?>
      <div class="card card--pad webcard" data-webcheck data-id="<?= $id ?>" data-nocollect<?= $wlast ? ' data-last="' . e(json_encode($wlast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"' : '' ?>>
        <h2 class="card__t card__t--sm">Recherche sur le web</h2>
        <span class="xs muted" data-web-state><?php if ($wlast): ?>Dernière recherche le <?= e(date('d/m/Y', (int) strtotime((string) $wlast['at']))) ?><?= ($wlast['by'] ?? '') !== '' ? ' par ' . e($wlast['by']) : '' ?> : <b><?= $nb ? $nb . ' proposition' . ($nb > 1 ? 's' : '') : 'rien de nouveau' ?></b>.<?php else: ?>L’IA cherche sur Internet ce qui pourrait corriger ou compléter cette fiche, avec ses sources. Rien n’est modifié : à vous de vérifier et de reporter.<?php endif; ?></span>
        <div class="row">
          <button type="button" class="btn btn--sm" data-web-run title="Gemini cherche avec Google (10 à 60 secondes)<?= \App\Core\Auth::isAdmin() ? ' : environ 1 centime' : '' ?>"><?= $wlast ? 'Relancer' : 'Chercher sur le web' ?></button>
          <button type="button" class="btn btn--sm" data-web-show<?= $wlast ? '' : ' hidden' ?>>Voir les propositions</button>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$isNew && \App\Services\FicheAudio::enabled()): $audioState = \App\Admin\Audio::stateOf($doc); ?>
      <div class="card card--pad audiocard" data-audio-card data-id="<?= $id ?>" data-state="<?= e(json_encode($audioState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>" data-nocollect>
        <h2 class="card__t card__t--sm">Écouter la fiche</h2>
        <?php if (count($audioState) > 1): ?><div class="seg" style="--n:2" role="radiogroup" aria-label="Langue du résumé"><?php foreach ($audioState as $l => $x): ?><label><input type="radio" name="_audio_lang" value="<?= e($l) ?>"<?= $l === 'fr' ? ' checked' : '' ?> data-nocollect><span><?= $l === 'fr' ? 'Français' : 'Anglais' ?></span></label><?php endforeach; ?></div><?php endif; ?>
        <span class="xs muted" data-audio-info></span>
        <label class="f"><span class="f__k">Texte lu <i><?= \App\Services\FicheAudio::maxWords() ?> mots au plus (<?= e(rtrim(rtrim(number_format(\App\Services\FicheAudio::maxMinutes(), 1, ',', ''), '0'), ',')) ?> min)</i></span><textarea rows="14" data-audio-textarea spellcheck="true"></textarea></label>
        <div class="row">
          <button type="button" class="btn btn--sm" data-audio-play>▶ Écouter</button>
          <button type="button" class="btn btn--sm btn--navy" data-audio-save hidden>Garder ce texte</button>
        </div>
        <div class="row">
          <button type="button" class="btn btn--sm" data-audio-act="ia-texte" title="Gemini rédige le résumé à partir de toute la fiche<?= \App\Core\Auth::isAdmin() ? ' (environ 0,05 centime)' : '' ?>">Rédiger avec l’IA</button>
          <button type="button" class="btn btn--sm" data-audio-act="voix" title="Gemini lit le texte d’une voix naturelle, enregistrée pour les visiteurs<?= \App\Core\Auth::isAdmin() ? ' (environ 0,6 centime)' : '' ?>">Voix IA</button>
        </div>
        <div class="row" style="gap:12px">
          <button type="button" class="linkbtn xs" data-audio-act="automatique" hidden>Revenir au résumé automatique</button>
          <a class="linkbtn xs" data-audio-download hidden>Télécharger le MP3</a>
          <button type="button" class="linkbtn xs" data-audio-act="supprimer-voix" hidden>Supprimer la voix IA</button>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($auto): ?>
      <div class="card card--pad">
        <h2 class="card__t card__t--sm">Mis à jour automatiquement</h2>
        <div class="autolinks stack" style="gap:6px">
          <?php foreach ($auto as [$t, $href]): ?><?= $href ? '<a href="' . e($href) . '" target="_blank" rel="noopener">' . e($t) . '</a>' : '<a>' . e($t) . '</a>' ?><?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($checks): ?>
      <?php $ack = \App\Services\QualityAck::get($id); $alerts = array_filter($checks, fn ($c) => $c[0] !== 'ok'); ?>
      <div class="card card--pad" id="qualite">
        <h2 class="card__t card__t--sm">Contrôle qualité</h2>
        <div class="checks">
          <?php foreach (array_slice($checks, 0, 8) as [$lvl, $msg]): ?><span><b class="<?= $lvl ?>"><?= $lvl === 'ok' ? '✓' : '⚠' ?></b><?= e($msg) ?></span><?php endforeach; ?>
          <?php if (count($checks) > 8): ?><span class="xs muted">… et <?= count($checks) - 8 ?> autres alertes</span><?php endif; ?>
        </div>
        <?php if ($alerts): ?>
          <button type="submit" class="linkbtn xs" form="quality-reset-form" style="align-self:flex-start">Remettre à zéro ces alertes</button>
        <?php endif; ?>
        <?php if ($ack): ?>
          <span class="xs muted">Contrôle remis à zéro par <?= e((string) $ack['by']) ?> le <?= date('d/m/Y', strtotime((string) $ack['at'])) ?> : <?= count($ack['keys'] ?? []) + (!empty($ack['ortho']) ? 1 : 0) ?> raison<?= count($ack['keys'] ?? []) + (!empty($ack['ortho']) ? 1 : 0) > 1 ? 's' : '' ?> mise<?= count($ack['keys'] ?? []) + (!empty($ack['ortho']) ? 1 : 0) > 1 ? 's' : '' ?> de côté. <button type="submit" class="linkbtn xs" form="quality-reopen-form">Tout réafficher</button></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!$isNew): ?>
      <div class="card card--pad" style="flex-direction:row;justify-content:space-between;align-items:center">
        <h2 class="card__t card__t--sm">Version EN</h2>
        <span class="pill pill--<?= ['none' => 'brouillon', 'auto' => 'info', 'manual' => 'ok', 'stale' => 'warn'][$enStatus] ?? 'brouillon' ?>"><?= e($enLabel) ?></span>
      </div>
      <div class="card card--pad">
        <span class="xs muted">Fiche n° <?= $id ?> · type <?= e(mb_strtolower(Fiches::TYPES[$type])) ?><?= !empty($doc['legacy']['url']) ? ' · <a href="' . e($doc['legacy']['url']) . '" target="_blank" rel="noopener">page WordPress d’origine</a>' : '' ?></span>
        <?php if ($doc['status'] !== 'corbeille'): ?>
          <button type="submit" class="btn btn--sm btn--danger" form="trash-form">Mettre à la corbeille</button>
        <?php else: ?>
          <button type="submit" class="btn btn--sm" form="untrash-form">Sortir de la corbeille</button>
          <?php if (Auth::can('destroy')): ?><button type="submit" class="btn btn--sm btn--danger" form="destroy-form">Supprimer définitivement</button><?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </aside>
</form>
<?php if (!$isNew): ?>
  <?php /* Formulaires secondaires hors du formulaire principal (les formulaires imbriqués sont interdits en HTML). */ ?>
  <form id="translate-form" method="post" action="/admin/fiche/<?= $id ?>/traduire" data-confirm="Traduire avec Gemini ?|La version anglaise actuelle sera remplacée par une nouvelle traduction automatique.|Traduire"><?= csrf_field() ?></form>
  <form id="restore-form" method="post" action="/admin/fiche/<?= $id ?>" data-confirm="Restaurer cette version ?|Rien n’est perdu : la restauration crée une nouvelle version, elle-même réversible.|Restaurer"><?= csrf_field() ?></form>
  <form id="trash-form" method="post" action="/admin/fiche/<?= $id ?>/corbeille" data-confirm="Mettre à la corbeille ?|La fiche disparaît du site. Vous pourrez la récupérer depuis la corbeille.|Mettre à la corbeille|danger"><?= csrf_field() ?></form>
  <form id="untrash-form" method="post" action="/admin/fiche/<?= $id ?>/sortir-corbeille"><?= csrf_field() ?></form>
  <form id="quality-reset-form" method="post" action="/admin/fiche/<?= $id ?>/qualite-zero" data-confirm="Remettre à zéro le contrôle qualité ?|Les alertes affichées ne seront plus signalées pour cette fiche, ni ici, ni dans l’écran Qualité, ni lors d’un prochain contrôle. Une alerte pour une autre raison réapparaîtra.|Remettre à zéro"><?= csrf_field() ?></form>
  <?php if ($type === 'match'): ?><form id="compos-form" method="post" action="/admin/fiche/<?= $id ?>/controler-composition" data-confirm="Contrôler la composition ?|Transfermarkt, pari-et-gagne.com, footballdatabase.eu, worldfootball.net, FCSM Story et la presse d’époque sont consultés (une à deux minutes). Enregistrez d’abord vos modifications : les écarts sont comparés à la fiche enregistrée et envoyés dans Trouvailles.|Contrôler"><?= csrf_field() ?></form><?php endif; ?>
  <form id="quality-reopen-form" method="post" action="/admin/fiche/<?= $id ?>/qualite-reafficher"><?= csrf_field() ?></form>
  <form id="destroy-form" method="post" action="/admin/fiche/<?= $id ?>/supprimer" data-confirm="Supprimer définitivement ?|Cette action est irréversible (une copie reste dans l’historique des versions).|Supprimer|danger"><?= csrf_field() ?></form>
<?php endif; ?>
